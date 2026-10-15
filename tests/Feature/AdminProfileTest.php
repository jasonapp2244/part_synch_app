<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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

    public function test_admin_uploads_replaces_and_removes_profile_photo(): void
    {
        Storage::fake('public');
        $admin = $this->user(1);

        $this->actingAs($admin)->post('/profile/photo', [
            'photo' => UploadedFile::fake()->image('me.jpg', 300, 300),
        ])->assertRedirect(route('admin.profile'))->assertSessionHas('success');

        $first = $admin->fresh()->profile_image;
        $this->assertNotNull($first);
        Storage::disk('public')->assertExists('profile_images/' . $first);

        // The header on every page and the profile card show the new photo.
        $this->actingAs($admin->fresh())->get('/profile')->assertOk()
            ->assertSee('storage/profile_images/' . $first, false);

        // Replacing deletes the old file so uploads don't pile up.
        $this->travel(2)->seconds();
        $this->actingAs($admin->fresh())->post('/profile/photo', [
            'photo' => UploadedFile::fake()->image('new.png', 200, 200),
        ])->assertSessionHasNoErrors();

        $second = $admin->fresh()->profile_image;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing('profile_images/' . $first);
        Storage::disk('public')->assertExists('profile_images/' . $second);

        $this->actingAs($admin->fresh())->delete('/profile/photo')
            ->assertRedirect(route('admin.profile'))->assertSessionHas('success');

        $this->assertNull($admin->fresh()->profile_image);
        Storage::disk('public')->assertMissing('profile_images/' . $second);
        $this->actingAs($admin->fresh())->get('/profile')
            ->assertSee('admin/images/avatars/avatar-2.png', false);
    }

    public function test_photo_upload_rejects_non_images_oversized_and_tiny_files(): void
    {
        Storage::fake('public');
        $admin = $this->user(1);

        $this->actingAs($admin)->post('/profile/photo', [])->assertSessionHasErrors('photo');

        $this->actingAs($admin)->post('/profile/photo', [
            'photo' => UploadedFile::fake()->create('script.php', 10, 'application/x-php'),
        ])->assertSessionHasErrors('photo');

        $this->actingAs($admin)->post('/profile/photo', [
            'photo' => UploadedFile::fake()->image('huge.jpg', 500, 500)->size(3000),
        ])->assertSessionHasErrors('photo');

        $this->actingAs($admin)->post('/profile/photo', [
            'photo' => UploadedFile::fake()->image('tiny.png', 10, 10),
        ])->assertSessionHasErrors('photo');

        $this->assertNull($admin->fresh()->profile_image);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_photo_routes_require_an_admin(): void
    {
        Storage::fake('public');

        $this->post('/profile/photo', ['photo' => UploadedFile::fake()->image('me.jpg', 100, 100)])
            ->assertRedirect(route('admin.login'));
        $this->delete('/profile/photo')->assertRedirect(route('admin.login'));
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_missing_photo_file_falls_back_to_default_avatar(): void
    {
        Storage::fake('public');
        $admin = $this->user(1);
        $admin->forceFill(['profile_image' => 'gone.jpg'])->save();

        $this->assertStringEndsWith('admin/images/avatars/avatar-2.png', $admin->avatarUrl());
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
