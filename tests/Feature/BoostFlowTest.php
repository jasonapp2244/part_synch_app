<?php

namespace Tests\Feature;

use App\Mail\BoostStatusMail;
use App\Models\BoostPackage;
use App\Models\BoostPosition;
use App\Models\User;
use App\Models\VendorBoost;
use App\Services\BoostCapacity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Product boosts: requested in the app, paid outside it, approved by an
 * admin, scheduled when a home-page position is full.
 */
class BoostFlowTest extends TestCase
{
    use RefreshDatabase;

    private BoostPackage $package;
    private BoostPosition $top;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['app.admin_email' => 'tauseefchoohan0401@gmail.com']);

        foreach ([1 => 'admin', 2 => 'vendor', 3 => 'user'] as $id => $type) {
            DB::table('roles')->insertOrIgnore(['id' => $id, 'role_type' => $type]);
        }

        $this->package = BoostPackage::create([
            'name' => 'Weekly', 'slug' => 'weekly', 'price' => 10, 'product_limit' => 3,
            'duration_days' => 7, 'currency' => 'usd', 'status' => true,
        ]);
        $this->top = BoostPosition::create([
            'name' => 'Top Section', 'priority' => 1, 'display_limit' => 2, 'price_multiplier' => 1.5, 'status' => true,
        ]);
    }

    private function user(int $roleId): User
    {
        return User::create([
            'role_id' => $roleId,
            'first_name' => $roleId === 2 ? 'Vendor' : 'Test',
            'last_name' => 'Account',
            'email' => "role{$roleId}-" . uniqid() . '@example.com',
            'password' => Hash::make('secret-password'),
            'status' => 'active',
        ]);
    }

    private function product(User $vendor, string $status = 'active'): int
    {
        return DB::table('products')->insertGetId([
            'user_id' => $vendor->id,
            'name' => 'Part ' . uniqid(),
            'price' => 20,
            'stock_quantity' => '5',
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function requestBoost(User $vendor, array $productIds, ?int $positionId = null)
    {
        return $this->actingAs($vendor, 'sanctum')->postJson('/api/vendor/boost/request', [
            'package_id' => $this->package->id,
            'position_id' => $positionId ?? $this->top->id,
            'product_ids' => $productIds,
            'note' => 'Please feature these',
        ]);
    }

    private function approve(User $admin, VendorBoost $boost, array $extra = [])
    {
        return $this->actingAs($admin)->post(route('admin.boosts.approve', $boost->id), array_merge([
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'INV-1001',
            'amount' => '15.00',
        ], $extra));
    }

    private function homeTopIds(): array
    {
        $buyer = $this->user(3);

        return collect($this->actingAs($buyer, 'sanctum')->getJson('/api/user/home')
            ->assertOk()->json('data.boosted_products.top_section'))->pluck('id')->all();
    }

    // ── Vendor request ──────────────────────────────────────────────────

    public function test_vendor_requests_a_boost_and_price_is_computed_on_the_server(): void
    {
        $vendor = $this->user(2);
        $p = $this->product($vendor);

        $this->requestBoost($vendor, [$p])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.products.0.id', $p);

        $boost = VendorBoost::firstOrFail();
        $this->assertSame('pending', $boost->approval_status);
        $this->assertSame('15.00', (string) $boost->amount);
        $this->assertFalse($boost->is_active);
        $this->assertSame('Please feature these', $boost->vendor_note);

        // Not shown to buyers before approval.
        $this->assertSame([], $this->homeTopIds());

        Mail::assertQueued(BoostStatusMail::class, fn ($m) => $m->event === 'requested' && $m->hasTo($vendor->email));
        Mail::assertQueued(BoostStatusMail::class, fn ($m) => $m->event === 'admin_new_request' && $m->hasTo('tauseefchoohan0401@gmail.com'));
        $this->assertDatabaseHas('notifications', ['vendor_id' => $vendor->id, 'email_subject' => 'Boost request received']);
    }

    public function test_request_rejects_foreign_inactive_too_many_and_duplicate_products(): void
    {
        $vendor = $this->user(2);
        $other = $this->user(2);
        [$a, $b, $c] = [$this->product($vendor), $this->product($vendor), $this->product($vendor)];

        $this->requestBoost($vendor, [$this->product($other)])->assertStatus(422);
        $this->requestBoost($vendor, [$this->product($vendor, 'inactive')])->assertStatus(422);
        // Top shows 2 at a time, so 3 can never fit.
        $this->requestBoost($vendor, [$a, $b, $c])->assertStatus(422)->assertJsonPath('status', false);

        $this->requestBoost($vendor, [$a])->assertCreated();
        $this->requestBoost($vendor, [$a])->assertStatus(422); // already pending in Top

        $this->assertSame(1, VendorBoost::count());
    }

    public function test_only_vendors_can_request(): void
    {
        $buyer = $this->user(3);
        $this->actingAs($buyer, 'sanctum')->postJson('/api/vendor/boost/request', [])->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/vendor/boost/request', [])->assertUnauthorized();
    }

    public function test_card_payment_endpoints_are_gone_and_packages_api_needs_sign_in(): void
    {
        $vendor = $this->user(2);
        foreach (['purchase', 'confirm-payment', 'test-confirm-payment', 'boost-existing-products'] as $path) {
            $this->actingAs($vendor, 'sanctum')->postJson("/api/vendor/boost/{$path}")->assertNotFound();
        }

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/admin/boost-packages')->assertUnauthorized();
    }

    public function test_vendor_can_withdraw_a_pending_request_only(): void
    {
        $vendor = $this->user(2);
        $this->requestBoost($vendor, [$this->product($vendor)])->assertCreated();
        $boost = VendorBoost::firstOrFail();

        $this->actingAs($vendor, 'sanctum')->postJson('/api/vendor/boost/cancel', ['vendor_boost_id' => $boost->id])
            ->assertOk();
        $this->assertSame('cancelled', $boost->fresh()->approval_status);

        $this->actingAs($vendor, 'sanctum')->postJson('/api/vendor/boost/cancel', ['vendor_boost_id' => $boost->id])
            ->assertStatus(400);
    }

    // ── Admin approval ──────────────────────────────────────────────────

    public function test_admin_approves_and_boost_goes_live_on_the_home_feed(): void
    {
        $admin = $this->user(1);
        $vendor = $this->user(2);
        $p = $this->product($vendor);
        $this->requestBoost($vendor, [$p]);
        $boost = VendorBoost::firstOrFail();

        $this->actingAs($admin)->get(route('admin.boosts'))->assertOk()
            ->assertSee('Waiting for approval')->assertSee($vendor->email);

        $this->approve($admin, $boost)->assertRedirect(route('admin.boosts', ['tab' => 'current']));

        $boost->refresh();
        $this->assertSame('active', $boost->status);
        $this->assertSame('success', $boost->payment_status);
        $this->assertSame('INV-1001', $boost->payment_reference);
        $this->assertSame($admin->id, $boost->approved_by);
        $this->assertEquals(7, (int) round($boost->start_date->diffInDays($boost->end_date)));
        $this->assertSame([$p], $this->homeTopIds());
        $this->assertSame(1, (int) DB::table('products')->where('id', $p)->value('is_top'));

        Mail::assertQueued(BoostStatusMail::class, fn ($m) => $m->event === 'approved');

        $this->actingAs($vendor, 'sanctum')->getJson('/api/vendor/boost/my-boosts')
            ->assertOk()->assertJsonPath('data.0.status', 'active');
    }

    public function test_full_position_schedules_the_next_boost_and_hides_it_until_it_starts(): void
    {
        $admin = $this->user(1);
        $v1 = $this->user(2);
        $v2 = $this->user(2);
        [$a, $b] = [$this->product($v1), $this->product($v1)];
        $c = $this->product($v2);

        // Vendor 1 fills both Top slots.
        $this->requestBoost($v1, [$a, $b]);
        $this->approve($admin, VendorBoost::firstOrFail())->assertSessionHasNoErrors();

        // Vendor 2 is waitlisted to when vendor 1's week ends.
        $this->requestBoost($v2, [$c]);
        $second = VendorBoost::where('vendor_id', $v2->id)->firstOrFail();
        $firstEnd = VendorBoost::where('vendor_id', $v1->id)->value('end_date');

        $next = app(BoostCapacity::class)->nextAvailableStart($this->top, 1, 7);
        $this->assertEquals($firstEnd, $next->toDateTimeString());

        // Approving for "now" would overfill Top, so it is refused...
        $this->approve($admin, $second, ['start_date' => now()->format('Y-m-d\TH:i')])
            ->assertSessionHasErrors('start_date');
        $this->assertSame('pending', $second->fresh()->approval_status);

        // ...and approving at the suggested date schedules it.
        $this->approve($admin, $second, ['start_date' => $next->format('Y-m-d H:i:s')])->assertSessionHasNoErrors();
        $this->assertSame('scheduled', $second->fresh()->status);
        Mail::assertQueued(BoostStatusMail::class, fn ($m) => $m->event === 'scheduled');

        $this->assertEqualsCanonicalizing([$a, $b], $this->homeTopIds());

        // After the first week: vendor 1 is gone, vendor 2 is live and told so.
        $this->travelTo($next->copy()->addMinute());
        Artisan::call('boost:expire');
        $this->assertSame([$c], $this->homeTopIds());
        $this->assertSame('active', $second->fresh()->status);
        $this->assertNotNull($second->fresh()->live_notified_at);
        Mail::assertQueued(BoostStatusMail::class, fn ($m) => $m->event === 'live');
    }

    public function test_ending_soon_reminder_is_sent_once_and_expiry_hides_the_boost(): void
    {
        $admin = $this->user(1);
        $vendor = $this->user(2);
        $p = $this->product($vendor);
        $this->requestBoost($vendor, [$p]);
        $boost = VendorBoost::firstOrFail();
        $this->approve($admin, $boost);

        $this->travel(5)->days();
        Artisan::call('boost:expire');
        Artisan::call('boost:expire');
        Mail::assertQueued(BoostStatusMail::class, fn ($m) => $m->event === 'ending_soon');
        $this->assertSame(1, DB::table('notifications')->where('vendor_id', $vendor->id)->where('email_subject', 'Your boost ends soon')->count());

        $this->travel(3)->days();
        Artisan::call('boost:expire');
        $this->assertSame('expired', $boost->fresh()->status);
        $this->assertSame([], $this->homeTopIds());
        $this->assertSame(0, (int) DB::table('products')->where('id', $p)->value('is_top'));
    }

    public function test_admin_rejects_with_reason_and_vendor_sees_it(): void
    {
        $admin = $this->user(1);
        $vendor = $this->user(2);
        $this->requestBoost($vendor, [$this->product($vendor)]);
        $boost = VendorBoost::firstOrFail();

        $this->actingAs($admin)->post(route('admin.boosts.reject', $boost->id), [])->assertSessionHasErrors('reason');
        $this->actingAs($admin)->post(route('admin.boosts.reject', $boost->id), ['reason' => 'No payment received'])
            ->assertRedirect(route('admin.boosts'));

        $this->assertSame('rejected', $boost->fresh()->status);
        Mail::assertQueued(BoostStatusMail::class, fn ($m) => $m->event === 'rejected');
        $this->actingAs($vendor, 'sanctum')->getJson('/api/vendor/boost/my-boosts')
            ->assertJsonPath('data.0.status', 'rejected')
            ->assertJsonPath('data.0.rejection_reason', 'No payment received');

        // A rejected request cannot then be approved.
        $this->approve($admin, $boost)->assertSessionHasErrors('boost');
    }

    public function test_admin_cancels_a_live_boost_and_frees_the_slot(): void
    {
        $admin = $this->user(1);
        $vendor = $this->user(2);
        $p = $this->product($vendor);
        $this->requestBoost($vendor, [$p]);
        $boost = VendorBoost::firstOrFail();
        $this->approve($admin, $boost);

        $this->actingAs($admin)->post(route('admin.boosts.cancel', $boost->id), ['reason' => 'Listing breaks the rules', 'refunded' => 1])
            ->assertRedirect(route('admin.boosts', ['tab' => 'history']));

        $boost->refresh();
        $this->assertSame('cancelled', $boost->status);
        $this->assertSame('refunded', $boost->payment_status);
        $this->assertSame([], $this->homeTopIds());
        $this->assertSame(0, app(BoostCapacity::class)->inUseNow($this->top));
        $this->assertSame(0, (int) DB::table('products')->where('id', $p)->value('is_top'));
    }

    public function test_admin_creates_a_boost_for_a_vendor_directly(): void
    {
        $admin = $this->user(1);
        $vendor = $this->user(2);
        $p = $this->product($vendor);

        $this->actingAs($admin)->getJson(route('admin.boosts.vendor.products', $vendor->id))
            ->assertOk()->assertJsonPath('0.id', $p);

        $this->actingAs($admin)->post(route('admin.boosts.store'), [
            'vendor_id' => $vendor->id,
            'package_id' => $this->package->id,
            'position_id' => $this->top->id,
            'product_ids' => [$p],
            'payment_method' => 'cash',
            'amount' => '12.00',
        ])->assertRedirect(route('admin.boosts', ['tab' => 'current']));

        $boost = VendorBoost::firstOrFail();
        $this->assertSame('active', $boost->status);
        $this->assertSame('12.00', (string) $boost->amount);
        // Admin-created: no "request received" email.
        Mail::assertNotQueued(BoostStatusMail::class, fn ($m) => $m->event === 'requested');
    }

    public function test_boost_admin_pages_require_an_admin(): void
    {
        $vendor = $this->user(2);
        $this->get(route('admin.boosts'))->assertRedirect(route('admin.login'));
        $this->actingAs($vendor)->get(route('admin.boosts'))->assertRedirect();
        $this->actingAs($vendor)->post(route('admin.boosts.approve', 1), [])->assertRedirect();
        $this->assertSame(0, VendorBoost::count());
    }

    public function test_position_capacity_is_editable_and_used_packages_cannot_be_deleted(): void
    {
        $admin = $this->user(1);
        $vendor = $this->user(2);
        $this->requestBoost($vendor, [$this->product($vendor)]);

        $this->actingAs($admin)->put(route('boost.position.update', $this->top->id), [
            'display_limit' => 4, 'price_multiplier' => '2.00', 'status' => 1,
        ])->assertRedirect(route('boost.packages'));
        $this->assertSame(4, $this->top->fresh()->display_limit);

        $this->actingAs($admin)->delete(route('boost.package.destroy', $this->package->id))->assertSessionHasErrors('package');
        $this->assertNotNull($this->package->fresh());

        $this->actingAs($admin)->get(route('boost.packages'))->assertOk()->assertSee('Slots in use now');
    }

    public function test_price_preview_reports_the_next_free_date(): void
    {
        $vendor = $this->user(2);
        $this->actingAs($vendor, 'sanctum')->getJson('/api/vendor/boost/price-preview?' . http_build_query([
            'package_id' => $this->package->id, 'position_id' => $this->top->id, 'product_count' => 1,
        ]))->assertOk()
            ->assertJsonPath('data.final_price', 15)
            ->assertJsonPath('data.available_now', true)
            ->assertJsonPath('data.product_limit', 2);
    }
}
