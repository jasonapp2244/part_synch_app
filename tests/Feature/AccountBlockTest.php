<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The admin's block switch (`users.status`). Before the 2026-10-20 device run
 * it did nothing to the app: existing tokens kept working, and signing in
 * wrote 'active' back, so a blocked user unblocked themselves by logging in.
 */
class AccountBlockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([1 => 'admin', 2 => 'vendor', 3 => 'user'] as $id => $type) {
            DB::table('roles')->insertOrIgnore(['id' => $id, 'role_type' => $type]);
        }
    }

    private function user(int $roleId): User
    {
        return User::create([
            'role_id' => $roleId,
            'first_name' => 'Sam',
            'last_name' => 'Ray',
            'email' => "role{$roleId}-" . uniqid() . '@example.com',
            'password' => Hash::make('secret-password'),
            'status' => 'active',
            'business_status' => 'active',
        ]);
    }

    public static function roles(): array
    {
        return ['customer' => [3, '/user'], 'vendor' => [2, '/vendor']];
    }

    /**
     * @dataProvider roles
     */
    public function test_blocking_ends_sessions_and_signin_stays_refused(int $roleId, string $prefix): void
    {
        $account = $this->user($roleId);
        $account->createToken('app');

        $this->actingAs($this->user(1))->post("{$prefix}/{$account->id}/toggle-status")->assertRedirect();

        $this->assertSame('inactive', $account->fresh()->status);
        $this->assertSame(0, $account->tokens()->count());

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/signin', ['email' => $account->email, 'password' => 'secret-password'])
            ->assertForbidden()
            ->assertJsonPath('error', 'Your account has been blocked. Please contact support.');

        $this->assertSame('inactive', $account->fresh()->status, 'signin must not unblock the account');
        $this->assertSame(0, $account->tokens()->count());
    }

    public function test_unblocked_account_signs_in_again(): void
    {
        $customer = $this->user(3);
        $customer->update(['status' => 'inactive']);

        $this->actingAs($this->user(1))->post("/user/{$customer->id}/toggle-status");
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/signin', ['email' => $customer->email, 'password' => 'secret-password'])
            ->assertOk()
            ->assertJsonPath('status', true);
    }
}
