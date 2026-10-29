<?php

namespace Tests\Feature;

use App\Mail\ForgotPasswordMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Admin sign-in, forgot / reset password (6-digit code) and the branded
 * error pages.
 */
class AdminAuthPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([1 => 'admin', 2 => 'vendor', 3 => 'user'] as $id => $type) {
            DB::table('roles')->insertOrIgnore(['id' => $id, 'role_type' => $type]);
        }
    }

    private function user(int $roleId, string $email, string $password = 'old-password-1'): User
    {
        return User::create([
            'role_id' => $roleId,
            'first_name' => 'Test',
            'last_name' => 'Account',
            'email' => $email,
            'password' => Hash::make($password),
            'status' => 'active',
        ]);
    }

    /** Issue a code through the real form and read it from the captured mail. */
    private function codeFor(string $email): string
    {
        $code = null;
        Mail::fake();
        $this->post(route('admin.password.email'), ['email' => $email]);
        Mail::assertSent(ForgotPasswordMail::class, function (ForgotPasswordMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        return (string) $code;
    }

    public function test_login_page_is_branded_and_keeps_the_form_contract(): void
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('admin/images/brand/logo.png', false)
            ->assertSee('admin/images/brand/login-side.jpg', false)
            ->assertSee('admin/css/auth.css', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="remember"', false)
            ->assertSee(route('admin.password.request'), false)
            ->assertDontSee('fonts.googleapis.com', false);
    }

    public function test_flash_messages_show_on_the_login_page(): void
    {
        $this->withSession(['error' => 'Please sign in to continue.'])
            ->get(route('admin.login'))
            ->assertSee('Please sign in to continue.');
    }

    public function test_the_bare_host_opens_the_sign_in_page_or_the_dashboard(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk();

        $this->actingAs($this->user(1, 'admin@example.com'));
        $this->get('/login')->assertRedirect(route('admin.dashboard'));
    }

    public function test_an_admin_signs_in_and_a_wrong_password_is_refused(): void
    {
        $this->user(1, 'admin@example.com', 'right-password-1');

        $this->post(route('admin.login.submit'), ['email' => 'admin@example.com', 'password' => 'nope'])
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertGuest();

        $this->post(route('admin.login.submit'), ['email' => 'admin@example.com', 'password' => 'right-password-1'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated();
    }

    public function test_login_is_rate_limited(): void
    {
        $this->user(1, 'admin@example.com');

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('admin.login.submit'), ['email' => 'admin@example.com', 'password' => 'wrong']);
        }

        $this->post(route('admin.login.submit'), ['email' => 'admin@example.com', 'password' => 'wrong'])
            ->assertStatus(429)
            ->assertSee('Too many attempts');
    }

    public function test_forgot_password_emails_a_code_to_admins_only_and_answers_the_same_for_everyone(): void
    {
        Mail::fake();
        $this->user(1, 'admin@example.com');
        $this->user(2, 'vendor@example.com');

        $responses = [];
        foreach (['admin@example.com', 'vendor@example.com', 'nobody@example.com'] as $email) {
            $responses[] = $this->post(route('admin.password.email'), ['email' => $email])
                ->assertRedirect(route('admin.password.reset', ['email' => $email]))
                ->assertSessionHas('success')
                ->getSession()->get('success');
        }

        $this->assertCount(1, array_unique($responses), 'the message never reveals which addresses exist');
        Mail::assertSent(ForgotPasswordMail::class, 1);
        Mail::assertSent(ForgotPasswordMail::class, fn ($mail) => $mail->hasTo('admin@example.com'));
    }

    public function test_the_right_code_sets_a_new_password_the_admin_can_sign_in_with(): void
    {
        $admin = $this->user(1, 'admin@example.com');
        $code = $this->codeFor('admin@example.com');

        $this->get(route('admin.password.reset', ['email' => 'admin@example.com']))
            ->assertOk()
            ->assertSee('value="admin@example.com"', false)
            ->assertSee('autocomplete="one-time-code"', false);

        $this->post(route('admin.password.update'), [
            'email' => 'admin@example.com',
            'code' => $code,
            'password' => 'brand-new-pass-9',
            'password_confirmation' => 'brand-new-pass-9',
        ])->assertRedirect(route('admin.login'))->assertSessionHas('success');

        $this->assertTrue(Hash::check('brand-new-pass-9', $admin->fresh()->password));
        $this->post(route('admin.login.submit'), ['email' => 'admin@example.com', 'password' => 'brand-new-pass-9'])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_a_wrong_or_expired_code_changes_nothing(): void
    {
        $admin = $this->user(1, 'admin@example.com');
        $this->codeFor('admin@example.com');
        $payload = ['email' => 'admin@example.com', 'code' => '000000', 'password' => 'brand-new-pass-9', 'password_confirmation' => 'brand-new-pass-9'];

        $this->post(route('admin.password.update'), $payload)->assertSessionHas('error');
        $this->assertTrue(Hash::check('old-password-1', $admin->fresh()->password));

        $code = $this->codeFor('admin@example.com');
        DB::table('password_reset_tokens')->where('email', 'admin@example.com')->update(['created_at' => now()->subMinutes(16)]);
        $this->post(route('admin.password.update'), array_merge($payload, ['code' => $code]))->assertSessionHas('error');
        $this->assertTrue(Hash::check('old-password-1', $admin->fresh()->password));
    }

    public function test_a_vendor_cannot_reset_through_the_admin_form(): void
    {
        $vendor = $this->user(2, 'vendor@example.com');
        Mail::fake();
        $this->post(route('admin.password.email'), ['email' => 'vendor@example.com']);
        Mail::assertNothingSent();

        $this->post(route('admin.password.update'), [
            'email' => 'vendor@example.com', 'code' => '123456',
            'password' => 'brand-new-pass-9', 'password_confirmation' => 'brand-new-pass-9',
        ])->assertSessionHas('error');
        $this->assertTrue(Hash::check('old-password-1', $vendor->fresh()->password));
    }

    public function test_the_new_password_must_be_confirmed_and_eight_characters(): void
    {
        $this->user(1, 'admin@example.com');

        $this->post(route('admin.password.update'), [
            'email' => 'admin@example.com', 'code' => '123456',
            'password' => 'short', 'password_confirmation' => 'other',
        ])->assertSessionHasErrors('password');
    }

    public function test_error_pages_are_branded(): void
    {
        $this->get('/no-such-admin-page')
            ->assertNotFound()
            ->assertSee("This page doesn't exist")
            ->assertSee('admin/images/brand/logo.png', false);

        $html = view('errors.419')->render();
        $this->assertStringContainsString('Your session expired', $html);
        $this->assertStringContainsString('admin/css/auth.css', $html);

        foreach ([403, 429, 500, 503] as $code) {
            $this->assertStringContainsString("Error {$code}", view("errors.{$code}")->render());
        }
    }

    public function test_api_errors_stay_json(): void
    {
        $this->getJson('/api/no-such-endpoint')->assertNotFound()->assertJsonStructure(['message']);
    }
}
