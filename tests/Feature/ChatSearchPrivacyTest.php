<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Searching people to message. It returned the whole users row of anyone
 * matching one letter — email, phone, address, coordinates, push tokens —
 * admins included (full regression run 2026-10-30).
 */
class ChatSearchPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private const PRIVATE_FIELDS = ['email', 'phone_number', 'address', 'latitude', 'longitude', 'token', 'web_token', 'google_auth_id'];

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([1 => 'admin', 2 => 'vendor', 3 => 'user'] as $id => $type) {
            DB::table('roles')->insertOrIgnore(['id' => $id, 'role_type' => $type]);
        }
    }

    private function user(int $roleId, string $first, string $status = 'active'): User
    {
        return User::create([
            'role_id' => $roleId,
            'first_name' => $first,
            'last_name' => 'Walker',
            'email' => strtolower($first) . '-' . uniqid() . '@example.com',
            'phone_number' => '+12025550100',
            'address' => '12 Elm Street',
            'token' => 'push-token-' . $first,
            'password' => Hash::make('secret-password'),
            'status' => $status,
        ]);
    }

    private function search(User $as, string $input = 'Walker'): array
    {
        return $this->actingAs($as, 'sanctum')
            ->getJson('/api/search?input=' . $input)
            ->assertOk()
            ->json('records');
    }

    public function test_results_carry_only_contact_fields(): void
    {
        $this->user(2, 'Megan');

        $records = $this->search($this->user(3, 'Ethan'));

        $this->assertCount(1, $records);
        $this->assertSame('Megan', $records[0]['first_name']);
        foreach (self::PRIVATE_FIELDS as $field) {
            $this->assertArrayNotHasKey($field, $records[0]);
        }
    }

    public function test_a_customer_finds_sellers_only_and_never_admins(): void
    {
        $seller = $this->user(2, 'Megan');
        $this->user(3, 'Olivia');
        $this->user(1, 'Rachel');
        $this->user(2, 'Suspended', 'inactive');

        $ids = array_column($this->search($this->user(3, 'Ethan')), 'id');

        $this->assertSame([$seller->id], $ids);
    }

    public function test_a_seller_finds_customers_and_sellers_but_not_admins_or_blocked(): void
    {
        $vendor = $this->user(2, 'Megan');
        $customer = $this->user(3, 'Olivia');
        $otherSeller = $this->user(2, 'Lucas');
        $blocked = $this->user(3, 'Blocked');
        $this->user(1, 'Rachel');
        UserBlock::create(['blocker_id' => $vendor->id, 'blocked_id' => $blocked->id]);

        $ids = array_column($this->search($vendor), 'id');
        sort($ids);

        $this->assertSame([$customer->id, $otherSeller->id], $ids);
    }

    public function test_favourites_carry_only_contact_fields(): void
    {
        $customer = $this->user(3, 'Ethan');
        $seller = $this->user(2, 'Megan');
        DB::table('ch_favorites')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $customer->id,
            'favorite_id' => $seller->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $favorite = $this->actingAs($customer, 'sanctum')
            ->postJson('/api/favorites')
            ->assertOk()
            ->json('favorites.0.user');

        $this->assertSame('Megan', $favorite['first_name']);
        foreach (self::PRIVATE_FIELDS as $field) {
            $this->assertArrayNotHasKey($field, $favorite);
        }
    }
}
