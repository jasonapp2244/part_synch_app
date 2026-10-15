<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The admin's own account: profile, change password, and the sidebar links
 * and logout that reach them.
 */
class AdminProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([1 => 'admin', 2 => 'vendor', 3 => 'user'] as $id => $type) {
            DB::table('roles')->insertOrIgnore(['id' => $id, 'role_type' => $type]);
        }
    }

    private function user(int $roleId, ?string $email = null): User
    {
        return User::create([
            'role_id' => $roleId,
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => $email ?? "role{$roleId}-" . uniqid() . '@example.com',
            'password' => Hash::make('old-password-1'),
            'status' => 'active',
        ]);
    }

    public function test_profile_page_requires_an_admin(): void
    {
        $this->get('/profile')->assertRedirect(route('admin.login'));
        $this->actingAs($this->user(3))->get('/profile')->assertRedirect();
    }

    public function test_sidebar_shows_profile_change_password_and_logout(): void
    {
        $this->actingAs($this->user(1))->get('/profile')->assertOk()
            ->assertSee('My Profile')
            ->assertSee('Change Password')
            ->assertSee('sidebar-logout-form', false)
            ->assertSee(route('admin.profile', ['tab' => 'password']), false);
    }

    public function test_admin_updates_profile(): void
    {
        $admin = $this->user(1);

        $this->actingAs($admin)->post('/profile', [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane.admin@example.com',
            'phone_number' => '+1 555 0100',
        ])->assertRedirect(route('admin.profile'))->assertSessionHas('success');

        $admin->refresh();
        $this->assertSame('Jane', $admin->first_name);
        $this->assertSame('jane.admin@example.com', $admin->email);
        $this->assertSame('+1 555 0100', $admin->phone_number);
        $this->assertSame(1, (int) $admin->role_id);
    }

    public function test_profile_email_must_be_unique_and_role_cannot_be_changed(): void
    {
        $this->user(3, 'taken@example.com');
        $admin = $this->user(1);

        $this->actingAs($admin)->post('/profile', [
            'first_name' => 'Jane',
            'email' => 'taken@example.com',
            'role_id' => 3,
        ])->assertSessionHasErrors('email');

        $this->actingAs($admin)->post('/profile', [
            'first_name' => 'Jane',
            'email' => $admin->email,
            'role_id' => 3,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, (int) $admin->fresh()->role_id);
    }

    public function test_admin_changes_password_and_can_sign_in_with_it(): void
    {
        $admin = $this->user(1);

        $this->actingAs($admin)->post('/profile/password', [
            'current_password' => 'old-password-1',
            'password' => 'new-password-2',
            'password_confirmation' => 'new-password-2',
        ])->assertRedirect(route('admin.profile', ['tab' => 'password']))->assertSessionHas('success');

        $this->assertTrue(Hash::check('new-password-2', $admin->fresh()->password));

        $this->post('/logout')->assertRedirect(route('admin.login'));
        $this->assertGuest();

        $this->post('/login', ['email' => $admin->email, 'password' => 'new-password-2'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin->fresh());
    }

    public function test_password_change_rejects_wrong_current_short_or_unconfirmed(): void
    {
        $admin = $this->user(1);

        $this->actingAs($admin)->post('/profile/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password-2',
            'password_confirmation' => 'new-password-2',
        ])->assertSessionHasErrors('current_password');

        $this->actingAs($admin)->post('/profile/password', [
            'current_password' => 'old-password-1',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');

        $this->actingAs($admin)->post('/profile/password', [
            'current_password' => 'old-password-1',
            'password' => 'new-password-2',
            'password_confirmation' => 'something-else',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('old-password-1', $admin->fresh()->password));
    }
}
