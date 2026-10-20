<?php

namespace Tests\Feature;

use App\Mail\OtpMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Sign-up emails the verification code; the admin "new user" notice is
 * separate, so a missing or failing admin address never makes the app say
 * the user's code was not sent (found on the live server, 2026-10-21).
 */
class SignupEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([1 => 'admin', 2 => 'vendor', 3 => 'user'] as $id => $type) {
            DB::table('roles')->insertOrIgnore(['id' => $id, 'role_type' => $type]);
        }
        Mail::fake();
    }

    private function signup(string $email)
    {
        return $this->postJson('/api/signup', [
            'email' => $email,
            'password' => 'secret-pass-1',
            'phone_number' => '5550101021',
        ]);
    }

    public function test_the_code_is_reported_sent_even_without_an_admin_address(): void
    {
        config(['app.admin_email' => null]);

        $this->signup('new.customer@example.com')
            ->assertCreated()
            ->assertJsonPath('otp_email_sent', true);

        Mail::assertSent(OtpMail::class, 1);
        Mail::assertSent(OtpMail::class, fn (OtpMail $mail) => $mail->hasTo('new.customer@example.com'));
    }

    public function test_a_valid_admin_address_also_gets_the_new_user_notice(): void
    {
        config(['app.admin_email' => 'ops@example.com']);

        $this->signup('second.customer@example.com')
            ->assertCreated()
            ->assertJsonPath('otp_email_sent', true);

        Mail::assertSent(OtpMail::class, 2);
        Mail::assertSent(OtpMail::class, fn (OtpMail $mail) => $mail->hasTo('ops@example.com'));
    }
}
