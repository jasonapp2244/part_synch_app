<?php

namespace Tests\Feature;

use App\Mail\VendorApprovalMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The app only lets a vendor in once `business_status` is active. Before this
 * the admin panel had no way to set it, so a vendor who registered in the app
 * stayed on "Await Approval" forever.
 */
class VendorApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([1 => 'admin', 2 => 'vendor', 3 => 'user'] as $id => $type) {
            DB::table('roles')->insertOrIgnore(['id' => $id, 'role_type' => $type]);
        }
    }

    private function user(int $roleId, string $businessStatus = 'inactive'): User
    {
        return User::create([
            'role_id' => $roleId,
            'first_name' => 'Pat',
            'last_name' => 'Lee',
            'email' => "role{$roleId}-" . uniqid() . '@example.com',
            'password' => Hash::make('secret-password'),
            'status' => 'active',
            'business_status' => $businessStatus,
        ]);
    }

    public function test_new_vendor_is_listed_as_awaiting_approval_with_an_approve_button(): void
    {
        $vendor = $this->user(2);

        $this->actingAs($this->user(1))->get('/vendor-records')
            ->assertOk()
            ->assertSee('1 Awaiting Approval')
            ->assertSee(route('vendor.toggle.approval', $vendor->id), false);
    }

    public function test_admin_approves_a_vendor_and_the_app_login_lets_them_in(): void
    {
        Mail::fake();
        $vendor = $this->user(2);

        $this->postJson('/api/signin', ['email' => $vendor->email, 'password' => 'secret-password'])
            ->assertJsonPath('user.business_status', 'inactive');

        $this->actingAs($this->user(1))->post("/vendor/{$vendor->id}/toggle-approval")
            ->assertRedirect(route('vendor.records'));

        $this->assertSame('active', $vendor->fresh()->business_status);
        Mail::assertSent(VendorApprovalMail::class, fn ($mail) => $mail->hasTo($vendor->email) && $mail->approved);
        $this->assertDatabaseHas('notifications', ['vendor_id' => $vendor->id, 'email_subject' => VendorApprovalMail::summary(true)[0]]);

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/signin', ['email' => $vendor->email, 'password' => 'secret-password'])
            ->assertJsonPath('user.business_status', 'active');
    }

    public function test_revoking_approval_signs_the_vendor_out(): void
    {
        Mail::fake();
        $vendor = $this->user(2, 'active');
        $vendor->createToken('app');

        $this->actingAs($this->user(1))->post("/vendor/{$vendor->id}/toggle-approval");

        $this->assertSame('inactive', $vendor->fresh()->business_status);
        $this->assertSame(0, $vendor->tokens()->count());
        Mail::assertSent(VendorApprovalMail::class, fn ($mail) => ! $mail->approved);
    }

    public function test_approval_is_admin_only_and_vendor_only(): void
    {
        $vendor = $this->user(2);
        $customer = $this->user(3);

        $this->post("/vendor/{$vendor->id}/toggle-approval")->assertRedirect(route('admin.login'));
        $this->actingAs($vendor)->post("/vendor/{$vendor->id}/toggle-approval")->assertRedirect();
        $this->assertSame('inactive', $vendor->fresh()->business_status);

        $this->actingAs($this->user(1))->post("/vendor/{$customer->id}/toggle-approval")->assertNotFound();
    }

    public function test_a_failing_mailer_does_not_block_approval(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));
        $vendor = $this->user(2);

        $this->actingAs($this->user(1))->post("/vendor/{$vendor->id}/toggle-approval")
            ->assertRedirect(route('vendor.records'));

        $this->assertSame('active', $vendor->fresh()->business_status);
    }
}
