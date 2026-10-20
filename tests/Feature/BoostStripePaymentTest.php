<?php

namespace Tests\Feature;

use App\Models\BoostPackage;
use App\Models\BoostPosition;
use App\Models\Setting;
use App\Models\User;
use App\Models\VendorBoost;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Tests\TestCase;

/**
 * Boosts are paid in the app with a one-time Stripe card payment (client
 * decision 2026-10-21); products and services are paid outside the app.
 * Stripe is faked here: no request leaves the test.
 */
class BoostStripePaymentTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'whsec_test_secret';

    private BoostPackage $package;
    private BoostPosition $top;
    private FakeStripe $stripe;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        foreach ([1 => 'admin', 2 => 'vendor', 3 => 'user'] as $id => $type) {
            DB::table('roles')->insertOrIgnore(['id' => $id, 'role_type' => $type]);
        }

        config([
            'services.stripe.key' => 'pk_test_fake',
            'services.stripe.secret' => 'sk_test_fake',
            'services.stripe.webhook_secret' => self::WEBHOOK_SECRET,
        ]);
        $this->stripe = new FakeStripe();
        $this->app->instance(StripeService::class, $this->stripe);

        // 10.00 × 1.5 = 15.00 → 1500 cents.
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

    private function product(User $vendor): int
    {
        return DB::table('products')->insertGetId([
            'user_id' => $vendor->id,
            'name' => 'Part ' . uniqid(),
            'price' => 20,
            'stock_quantity' => '5',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function requestedBoost(User $vendor, int $products = 1): VendorBoost
    {
        $ids = array_map(fn () => $this->product($vendor), range(1, $products));
        $id = $this->actingAs($vendor, 'sanctum')->postJson('/api/vendor/boost/request', [
            'package_id' => $this->package->id,
            'position_id' => $this->top->id,
            'product_ids' => $ids,
        ])->assertCreated()->json('data.id');

        return VendorBoost::findOrFail($id);
    }

    private function pay(User $vendor, VendorBoost $boost)
    {
        $this->actingAs($vendor, 'sanctum')->postJson("/api/vendor/boost/{$boost->id}/payment-intent")->assertOk();
        $this->stripe->succeed($boost->fresh()->transaction_id);

        return $this->actingAs($vendor, 'sanctum')->postJson("/api/vendor/boost/{$boost->id}/payment/confirm");
    }

    private function webhook(string $type, array $intent, ?string $secret = null)
    {
        $payload = json_encode([
            'id' => 'evt_test', 'object' => 'event', 'type' => $type,
            'data' => ['object' => array_merge(['object' => 'payment_intent'], $intent)],
        ]);
        $time = time();
        $signature = hash_hmac('sha256', "{$time}.{$payload}", $secret ?? self::WEBHOOK_SECRET);

        return $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$time},v1={$signature}",
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
    }

    public function test_payment_intent_is_for_the_server_price_and_the_owner_only(): void
    {
        $vendor = $this->user(2);
        $boost = $this->requestedBoost($vendor);

        $this->actingAs($vendor, 'sanctum')->postJson("/api/vendor/boost/{$boost->id}/payment-intent")
            ->assertOk()
            ->assertJsonPath('data.client_secret', fn ($secret) => str_starts_with($secret, 'pi_fake_'))
            ->assertJsonPath('data.publishable_key', 'pk_test_fake')
            ->assertJsonPath('data.amount', 15)
            ->assertJsonPath('data.currency', 'usd');
        $this->assertSame(1500, $this->stripe->created[0]['amount']);
        $this->assertSame((string) $boost->id, $this->stripe->created[0]['metadata']['vendor_boost_id']);

        $this->actingAs($this->user(2), 'sanctum')->postJson("/api/vendor/boost/{$boost->id}/payment-intent")->assertNotFound();
        $this->actingAs($this->user(3), 'sanctum')->postJson("/api/vendor/boost/{$boost->id}/payment-intent")->assertForbidden();
    }

    public function test_confirmed_payment_approves_the_boost_and_it_goes_live(): void
    {
        $vendor = $this->user(2);
        $boost = $this->requestedBoost($vendor);

        $this->pay($vendor, $boost)->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.payment_status', 'success')
            ->assertJsonPath('data.payment_method', 'stripe');

        $boost->refresh();
        $this->assertSame('approved', $boost->approval_status);
        $this->assertNull($boost->approved_by);
        $this->assertStringStartsWith('pi_fake_', $boost->payment_reference);
        $this->assertEquals(15.00, (float) $boost->amount);
    }

    public function test_a_full_position_schedules_the_paid_boost_at_the_next_free_slot(): void
    {
        $other = $this->user(2);
        $this->pay($other, $this->requestedBoost($other, 2))->assertOk(); // fills both Top slots

        $vendor = $this->user(2);
        $boost = $this->requestedBoost($vendor);
        $this->pay($vendor, $boost)->assertOk()->assertJsonPath('data.status', 'scheduled');

        $this->assertTrue($boost->fresh()->start_date->gt(now()->addDays(6)));
    }

    public function test_confirm_refuses_a_payment_that_has_not_succeeded_or_does_not_match(): void
    {
        $vendor = $this->user(2);
        $boost = $this->requestedBoost($vendor);
        $this->actingAs($vendor, 'sanctum')->postJson("/api/vendor/boost/{$boost->id}/payment-intent")->assertOk();
        $intentId = $boost->fresh()->transaction_id;

        $this->actingAs($vendor, 'sanctum')->postJson("/api/vendor/boost/{$boost->id}/payment/confirm")->assertStatus(409);
        $this->assertSame('pending', $boost->fresh()->approval_status);

        $this->stripe->succeed($intentId, 100); // paid 1.00 for a 15.00 boost
        $this->actingAs($vendor, 'sanctum')->postJson("/api/vendor/boost/{$boost->id}/payment/confirm")->assertStatus(422);
        $this->assertSame('pending', $boost->fresh()->approval_status);
    }

    public function test_confirming_twice_and_the_webhook_approve_once(): void
    {
        $vendor = $this->user(2);
        $boost = $this->requestedBoost($vendor);
        $this->pay($vendor, $boost)->assertOk();
        $approvedAt = $boost->fresh()->approved_at;

        $this->actingAs($vendor, 'sanctum')->postJson("/api/vendor/boost/{$boost->id}/payment/confirm")->assertOk();
        $this->webhook('payment_intent.succeeded', [
            'id' => $boost->fresh()->transaction_id, 'status' => 'succeeded', 'amount_received' => 1500,
            'currency' => 'usd', 'metadata' => ['vendor_boost_id' => (string) $boost->id],
        ])->assertOk();

        $this->assertEquals($approvedAt, $boost->fresh()->approved_at);
        $this->assertSame(1, DB::table('notifications')->where('vendor_id', $vendor->id)->where('email_subject', 'like', '%live%')->count());
    }

    public function test_webhook_approves_when_the_app_never_confirmed(): void
    {
        $vendor = $this->user(2);
        $boost = $this->requestedBoost($vendor);
        $this->actingAs($vendor, 'sanctum')->postJson("/api/vendor/boost/{$boost->id}/payment-intent")->assertOk();

        $this->webhook('payment_intent.succeeded', [
            'id' => $boost->fresh()->transaction_id, 'status' => 'succeeded', 'amount_received' => 1500,
            'currency' => 'usd', 'metadata' => ['vendor_boost_id' => (string) $boost->id],
        ])->assertOk();

        $this->assertSame('active', $boost->fresh()->status);
    }

    public function test_webhook_with_a_bad_signature_is_refused(): void
    {
        $vendor = $this->user(2);
        $boost = $this->requestedBoost($vendor);

        $this->webhook('payment_intent.succeeded', [
            'id' => 'pi_forged', 'status' => 'succeeded', 'amount_received' => 1500,
            'currency' => 'usd', 'metadata' => ['vendor_boost_id' => (string) $boost->id],
        ], 'whsec_wrong')->assertStatus(400);

        $this->assertSame('pending', $boost->fresh()->approval_status);
    }

    public function test_card_payment_switched_off_falls_back_to_request_and_approve(): void
    {
        Setting::set('boost_card_payment', '0', 'payments');
        $vendor = $this->user(2);

        $this->getJson('/api/app-config')->assertJsonPath('data.boost_card_payment_enabled', false);
        $this->actingAs($vendor, 'sanctum')->getJson('/api/vendor/boost/packages')->assertJsonPath('card_payment_enabled', false);

        $boost = $this->requestedBoost($vendor);
        $this->actingAs($vendor, 'sanctum')->postJson("/api/vendor/boost/{$boost->id}/payment-intent")->assertStatus(409);
        $this->assertSame([], $this->stripe->created);
    }

    public function test_admin_cancel_with_refund_refunds_the_card(): void
    {
        $vendor = $this->user(2);
        $boost = $this->requestedBoost($vendor);
        $this->pay($vendor, $boost)->assertOk();
        $admin = $this->user(1);

        $this->actingAs($admin)->post(route('admin.boosts.cancel', $boost->id), ['reason' => 'Refund requested', 'refunded' => 1])
            ->assertRedirect();

        $this->assertSame([$boost->fresh()->transaction_id], $this->stripe->refunded);
        $this->assertSame('refunded', $boost->fresh()->payment_status);
        $this->assertSame('cancelled', $boost->fresh()->approval_status);
    }

    public function test_a_paid_boost_cannot_be_withdrawn_but_an_unpaid_one_cancels_its_payment(): void
    {
        $vendor = $this->user(2);
        $paid = $this->requestedBoost($vendor);
        $this->pay($vendor, $paid)->assertOk();
        $this->actingAs($vendor, 'sanctum')->postJson('/api/vendor/boost/cancel', ['vendor_boost_id' => $paid->id])->assertStatus(400);

        $unpaid = $this->requestedBoost($vendor);
        $this->actingAs($vendor, 'sanctum')->postJson("/api/vendor/boost/{$unpaid->id}/payment-intent")->assertOk();
        $this->actingAs($vendor, 'sanctum')->postJson('/api/vendor/boost/cancel', ['vendor_boost_id' => $unpaid->id])->assertOk();

        $this->assertSame([$unpaid->fresh()->transaction_id], $this->stripe->cancelled);
    }
}

/**
 * In-memory Stripe: payment intents start unpaid and succeed when told to.
 */
class FakeStripe extends StripeService
{
    public array $created = [];
    public array $intents = [];
    public array $refunded = [];
    public array $cancelled = [];

    public function createBoostPaymentIntent(VendorBoost $boost): PaymentIntent
    {
        $id = 'pi_fake_' . $boost->id . '_' . count($this->created);
        $this->created[] = [
            'amount' => self::minorUnits($boost),
            'metadata' => ['vendor_boost_id' => (string) $boost->id],
        ];
        $this->intents[$id] = [
            'id' => $id, 'object' => 'payment_intent', 'status' => 'requires_payment_method',
            'client_secret' => $id . '_secret', 'amount' => self::minorUnits($boost), 'amount_received' => 0,
            'currency' => 'usd', 'metadata' => ['vendor_boost_id' => (string) $boost->id],
        ];

        return PaymentIntent::constructFrom($this->intents[$id]);
    }

    public function succeed(string $id, ?int $amountReceived = null): void
    {
        $this->intents[$id]['status'] = 'succeeded';
        $this->intents[$id]['amount_received'] = $amountReceived ?? $this->intents[$id]['amount'];
    }

    public function retrievePaymentIntent(string $id): PaymentIntent
    {
        return PaymentIntent::constructFrom($this->intents[$id]);
    }

    public function cancelPaymentIntent(string $id): PaymentIntent
    {
        $this->cancelled[] = $id;
        $this->intents[$id]['status'] = 'canceled';

        return PaymentIntent::constructFrom($this->intents[$id]);
    }

    public function refund(string $paymentIntentId): Refund
    {
        $this->refunded[] = $paymentIntentId;

        return Refund::constructFrom(['id' => 're_fake', 'object' => 'refund', 'status' => 'succeeded']);
    }
}
