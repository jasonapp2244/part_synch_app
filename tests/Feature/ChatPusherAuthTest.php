<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Every chat message is pushed to private-chatify.{recipientId}. Chatify signed
 * a subscription to any channel for any signed-in user, so one user could read
 * another's messages as they arrived. Only the caller's own channel is signed
 * now, and Chatify's package routes (which skip the app's overrides) are off.
 */
class ChatPusherAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([1 => 'admin', 2 => 'vendor', 3 => 'user'] as $id => $type) {
            DB::table('roles')->insertOrIgnore(['id' => $id, 'role_type' => $type]);
        }

        // socket_auth signs locally with the secret; no request reaches Pusher.
        config([
            'chatify.pusher.key' => 'test-key',
            'chatify.pusher.secret' => 'test-secret',
            'chatify.pusher.app_id' => '1',
        ]);
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

    private function auth(?User $as, string $channel)
    {
        $request = $as ? $this->actingAs($as, 'sanctum') : $this;

        return $request->postJson('/api/chat/auth', ['channel_name' => $channel, 'socket_id' => '1234.5678']);
    }

    public function test_a_user_can_subscribe_to_their_own_channel(): void
    {
        $customer = $this->user(3);

        $this->auth($customer, 'private-chatify.' . $customer->id)
            ->assertOk()
            ->assertJsonStructure(['auth']);
    }

    public function test_a_user_cannot_subscribe_to_someone_elses_channel(): void
    {
        $customer = $this->user(3);
        $vendor = $this->user(2);

        $this->auth($customer, 'private-chatify.' . $vendor->id)->assertForbidden();
    }

    public function test_a_channel_that_only_starts_with_the_users_id_is_refused(): void
    {
        $customer = $this->user(3);

        $this->auth($customer, 'private-chatify.' . $customer->id . '0')->assertForbidden();
        $this->auth($customer, 'presence-chatify.' . $customer->id)->assertForbidden();
    }

    public function test_channel_auth_needs_a_signed_in_user(): void
    {
        $this->auth(null, 'private-chatify.1')->assertUnauthorized();
    }

    public function test_chatify_package_routes_are_not_registered(): void
    {
        $customer = $this->user(3);

        $this->getJson('/chatify/api/getContacts')->assertNotFound();
        $this->postJson('/chatify/api/sendMessage', ['id' => 1, 'message' => 'x'])->assertNotFound();
        $this->postJson('/chatify/api/chat/auth', ['channel_name' => 'private-chatify.1', 'socket_id' => '1.2'])->assertNotFound();
        $this->actingAs($customer)->get('/chatify')->assertNotFound();

        // The app's own chat routes are unaffected.
        $this->actingAs($customer, 'sanctum')->getJson('/api/getContacts')->assertOk();
    }

    public function test_broadcasting_reads_the_laravel_12_env_key(): void
    {
        $config = file_get_contents(config_path('broadcasting.php'));

        $this->assertStringContainsString("env('BROADCAST_CONNECTION'", $config);
    }
}
