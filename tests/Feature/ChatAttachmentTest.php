<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * BCR-10: chat photos showed "Unavailable". GET /api/download/{file} built its
 * URL from APP_URL (`http://localhost/` + `/storage/...`), so a phone got a
 * double-slashed URL on its own localhost. It now answers on the host the app
 * called, and only to the two people in the conversation.
 */
class ChatAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private const FILE = '3f660f18-1895-40b8-b9f7-94b0e494a876.jpg';

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([1 => 'admin', 2 => 'vendor', 3 => 'user'] as $id => $type) {
            DB::table('roles')->insertOrIgnore(['id' => $id, 'role_type' => $type]);
        }

        Storage::fake('public');
    }

    private function user(int $roleId): User
    {
        return User::create([
            'role_id' => $roleId,
            'first_name' => 'Test',
            'last_name' => 'Account',
            'email' => "role{$roleId}-" . uniqid() . '@example.com',
            'password' => Hash::make('secret-password'),
            'status' => 'active',
        ]);
    }

    private function attachmentMessage(User $from, User $to, string $file = self::FILE): void
    {
        DB::table('ch_messages')->insert([
            'id' => (string) Str::uuid(),
            'from_id' => $from->id,
            'to_id' => $to->id,
            'body' => '',
            'attachment' => json_encode(['new_name' => $file, 'old_name' => 'photo.jpg']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Storage::disk('public')->put('attachments/' . $file, 'jpeg-bytes');
    }

    public function test_participants_get_a_url_on_the_host_they_called(): void
    {
        $customer = $this->user(3);
        $vendor = $this->user(2);
        $this->attachmentMessage($customer, $vendor);

        foreach ([$customer, $vendor] as $participant) {
            $this->actingAs($participant, 'sanctum')
                ->getJson('http://192.168.1.20:8001/api/download/' . self::FILE)
                ->assertOk()
                ->assertExactJson([
                    'file_name' => self::FILE,
                    'download_path' => 'http://192.168.1.20:8001/storage/attachments/' . self::FILE,
                ]);
        }
    }

    public function test_someone_outside_the_conversation_gets_404(): void
    {
        $this->attachmentMessage($this->user(3), $this->user(2));

        $this->actingAs($this->user(3), 'sanctum')
            ->getJson('/api/download/' . self::FILE)
            ->assertNotFound();
    }

    public function test_a_deleted_file_is_404(): void
    {
        $customer = $this->user(3);
        $this->attachmentMessage($customer, $this->user(2));
        Storage::disk('public')->delete('attachments/' . self::FILE);

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/download/' . self::FILE)
            ->assertNotFound();
    }

    public function test_only_a_bare_file_name_is_accepted(): void
    {
        $customer = $this->user(3);
        $this->attachmentMessage($customer, $this->user(2), '..');

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/download/..')
            ->assertNotFound();
        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/download/' . rawurlencode('..\\.env'))
            ->assertNotFound();
    }

    public function test_download_needs_a_signed_in_user(): void
    {
        $this->getJson('/api/download/' . self::FILE)->assertUnauthorized();
    }

    public function test_the_query_string_lookup_answers_the_same_and_keeps_the_same_guards(): void
    {
        // Live, nginx answered /api/download/<name>.jpg itself with a 404, so
        // the app now asks /api/chat/attachment?file=<name>.
        $customer = $this->user(3);
        $vendor = $this->user(2);
        $this->attachmentMessage($customer, $vendor);

        $this->actingAs($customer, 'sanctum')
            ->getJson('http://192.168.1.20:8001/api/chat/attachment?file=' . self::FILE)
            ->assertOk()
            ->assertExactJson([
                'file_name' => self::FILE,
                'download_path' => 'http://192.168.1.20:8001/storage/attachments/' . self::FILE,
            ]);

        $this->actingAs($this->user(3), 'sanctum')
            ->getJson('/api/chat/attachment?file=' . self::FILE)
            ->assertNotFound();
        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/chat/attachment?file=' . rawurlencode('../.env'))
            ->assertNotFound();
        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/chat/attachment')
            ->assertNotFound();
    }
}
