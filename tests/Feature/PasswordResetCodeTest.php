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
 * BCR-1. `/forgot_password` used to write a `<name><4 digits>` password over
 * the account and email it, so anyone who knew an address could lock its
 * owner out (reproduced on the device run, 2026-10-20). It now emails a code
 * and changes nothing until the code comes back.
 */
class PasswordResetCodeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        DB::table('roles')->insertOrIgnore(['id' => 3, 'role_type' => 'user']);

        $this->user = User::create([
            'role_id' => 3,
            'first_name' => 'Dana',
            'email' => 'dana-' . uniqid() . '@example.com',
            'password' => Hash::make('original-password'),
            'status' => 'active',
        ]);
    }

    private function requestCode(): string
    {
        $this->postJson('/api/forgot_password', ['email' => $this->user->email])
            ->assertOk()->assertJsonPath('status', true);

        $code = null;
        Mail::assertSent(ForgotPasswordMail::class, function ($mail) use (&$code) {
            $code = $mail->code;

            return $mail->hasTo($this->user->email);
        });

        return $code;
    }

    private function reset(string $code, string $password = 'brand-new-pass')
    {
        return $this->postJson('/api/reset_password_otp', [
            'email' => $this->user->email,
            'otp' => $code,
            'password' => $password,
            'password_confirmation' => $password,
        ]);
    }

    public function test_requesting_a_code_does_not_touch_the_password(): void
    {
        $code = $this->requestCode();

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertTrue(Hash::check('original-password', $this->user->fresh()->password));
        $this->assertNotSame($code, DB::table('password_reset_tokens')->value('token'), 'stored hashed');
    }

    public function test_an_unknown_email_gets_the_same_answer_and_no_mail(): void
    {
        $known = $this->postJson('/api/forgot_password', ['email' => $this->user->email])->json('message');
        $unknown = $this->postJson('/api/forgot_password', ['email' => 'nobody@example.com'])
            ->assertOk()->json('message');

        $this->assertSame($known, $unknown);
        Mail::assertSentCount(1);
    }

    public function test_the_right_code_sets_the_password_and_signs_out_everywhere(): void
    {
        $this->user->createToken('phone');
        $code = $this->requestCode();

        $this->reset($code)->assertOk()->assertJsonPath('status', true);

        $this->assertTrue(Hash::check('brand-new-pass', $this->user->fresh()->password));
        $this->assertSame(0, $this->user->tokens()->count());
        $this->reset($code)->assertStatus(422); // single use
    }

    public function test_a_wrong_code_changes_nothing_and_five_kill_the_code(): void
    {
        // The auth limiter (5/min per IP+email) would answer 429 first; this
        // checks the code's own limit, which outlasts the minute.
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $code = $this->requestCode();
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $this->reset($wrong)->assertStatus(422);
        }

        $this->reset($code)->assertStatus(422);
        $this->assertTrue(Hash::check('original-password', $this->user->fresh()->password));
    }

    public function test_an_expired_code_is_refused(): void
    {
        $code = $this->requestCode();
        DB::table('password_reset_tokens')->update(['created_at' => now()->subMinutes(16)]);

        $this->reset($code)->assertStatus(422);
        $this->assertTrue(Hash::check('original-password', $this->user->fresh()->password));
    }

    public function test_the_new_password_must_be_eight_characters_and_confirmed(): void
    {
        $code = $this->requestCode();

        $this->reset($code, 'short')->assertStatus(422)->assertJsonValidationErrors('password');
        $this->postJson('/api/reset_password_otp', [
            'email' => $this->user->email, 'otp' => $code,
            'password' => 'brand-new-pass', 'password_confirmation' => 'different-pass',
        ])->assertJsonValidationErrors('password');
    }
}
