<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ApiError;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Full audit, 2026-11-02: uploads, the map endpoints, sign-up codes, sessions
 * after a password change, seller approval, chat input and Stripe webhooks.
 */
class AuditHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();

        foreach ([1 => 'admin', 2 => 'vendor', 3 => 'user'] as $id => $type) {
            DB::table('roles')->insertOrIgnore(['id' => $id, 'role_type' => $type]);
        }
    }

    private function user(int $roleId, array $extra = []): User
    {
        $user = User::create([
            'role_id' => $roleId,
            'first_name' => $roleId === 2 ? 'Daniel' : 'Emily',
            'last_name' => $roleId === 2 ? 'Brooks' : 'Parker',
            'business_name' => $roleId === 2 ? 'Brooks Heavy Equipment' : null,
            'email' => "audit{$roleId}-".uniqid().'@example.com',
            'password' => Hash::make('secret-password'),
            'phone_number' => '5550100123',
            'status' => 'active',
        ]);
        $user->forceFill(array_merge(['business_status' => $roleId === 2 ? 'active' : 'inactive'], $extra))->save();

        return $user;
    }

    private function product(User $vendor): int
    {
        return DB::table('products')->insertGetId([
            'user_id' => $vendor->id,
            'name' => 'Hydraulic pump',
            'price' => 120,
            'stock_quantity' => '2',
            'status' => 'active',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ── Uploads ────────────────────────────────────────────────────────────

    public function test_update_product_refuses_a_script_upload_and_stores_nothing(): void
    {
        $vendor = $this->user(2);
        $id = $this->product($vendor);

        $this->actingAs($vendor, 'sanctum')
            ->post("/api/vendor/update_product/{$id}", [
                'product_pic' => [UploadedFile::fake()->createWithContent('shell.php', '<?php echo "owned";')],
            ], ['Accept' => 'application/json'])
            ->assertStatus(422);

        $this->assertSame([], Storage::allFiles('product_images'));
        $this->assertSame(0, DB::table('product_images')->where('product_id', $id)->count());
    }

    public function test_an_image_disguised_as_html_is_refused(): void
    {
        $vendor = $this->user(2);
        $id = $this->product($vendor);

        $this->actingAs($vendor, 'sanctum')
            ->post("/api/vendor/update_product/{$id}", [
                'product_pic' => [UploadedFile::fake()->image('photo.html', 40, 40)],
            ], ['Accept' => 'application/json'])
            ->assertStatus(422);

        $this->assertSame([], Storage::allFiles('product_images'));
    }

    public function test_a_real_photo_is_stored_under_its_detected_type(): void
    {
        $vendor = $this->user(2);
        $id = $this->product($vendor);

        $this->actingAs($vendor, 'sanctum')
            ->post("/api/vendor/update_product/{$id}", [
                'product_pic' => [UploadedFile::fake()->image('photo.png', 40, 40)],
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $files = Storage::allFiles('product_images');
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.png', $files[0]);
    }

    public function test_add_product_no_longer_accepts_svg(): void
    {
        $vendor = $this->user(2);
        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $this->actingAs($vendor, 'sanctum')
            ->post('/api/vendor/add_product', [
                'name' => 'Pump',
                'price' => 10,
                'modal_number' => 'P-1',
                'product_pic' => [$svg],
            ], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_a_rejected_profile_photo_is_a_422_not_a_500(): void
    {
        $customer = $this->user(3);

        $this->actingAs($customer, 'sanctum')
            ->post('/api/update_profile', [
                'first_name' => 'Emily Parker',
                'profile_image' => UploadedFile::fake()->image('me.html', 40, 40),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422);

        $this->assertNull($customer->fresh()->profile_image);
    }

    public function test_profile_photo_is_stored_under_its_detected_type(): void
    {
        $customer = $this->user(3);

        $this->actingAs($customer, 'sanctum')
            ->post('/api/update_profile', [
                'first_name' => 'Emily Parker',
                'profile_image' => UploadedFile::fake()->image('me.jpg', 40, 40),
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $this->assertStringEndsWith('.jpg', $customer->fresh()->profile_image);
    }

    // ── Map endpoints ──────────────────────────────────────────────────────

    public function test_update_vendor_moves_only_the_caller_and_returns_no_contact_details(): void
    {
        $vendor = $this->user(2);
        $victim = $this->user(3, ['latitude' => 10, 'longitude' => 10]);

        $res = $this->actingAs($vendor, 'sanctum')->postJson('/api/vendor/map/update-vendor', [
            'user_id' => $victim->id,
            'latitude' => 39.74,
            'longitude' => -104.99,
        ])->assertOk();

        $this->assertEquals(10, (float) $victim->fresh()->latitude);
        $this->assertEquals(39.74, (float) $vendor->fresh()->latitude);
        $this->assertArrayNotHasKey('email', $res->json('data'));
    }

    public function test_map_lists_only_active_sellers_without_contact_details(): void
    {
        $vendor = $this->user(2, ['latitude' => 39.7, 'longitude' => -104.9]);
        $this->user(3, ['latitude' => 39.7, 'longitude' => -104.9]);
        $this->user(1, ['latitude' => 39.7, 'longitude' => -104.9]);
        $this->user(2, ['latitude' => 39.7, 'longitude' => -104.9, 'business_status' => 'inactive']);

        $all = $this->actingAs($vendor, 'sanctum')->getJson('/api/vendor/map/get-vendors')->assertOk()->json('vendors');
        $this->assertSame([$vendor->id], array_column($all, 'id'));
        $this->assertArrayNotHasKey('email', $all[0]);
        $this->assertArrayNotHasKey('phone_number', $all[0]);

        $near = $this->actingAs($vendor, 'sanctum')->postJson('/api/vendor/map/nearest-vendors', [
            'latitude' => 39.7, 'longitude' => -104.9,
        ])->assertOk()->json('nearest_vendors');
        $this->assertSame([$vendor->id], array_column($near, 'id'));
        $this->assertArrayNotHasKey('email', $near[0]);
    }

    // ── Seller approval ────────────────────────────────────────────────────

    public function test_a_seller_awaiting_approval_cannot_use_the_seller_api(): void
    {
        $pending = $this->user(2, ['business_status' => 'inactive']);

        $this->actingAs($pending, 'sanctum')->getJson('/api/vendor/dashboard')
            ->assertForbidden()
            ->assertJsonPath('message', 'Your business account is awaiting approval.');

        // The shared endpoints the waiting screen needs still answer.
        $this->actingAs($pending, 'sanctum')->getJson('/api/profile')->assertOk();
    }

    // ── Sign-up codes ──────────────────────────────────────────────────────

    public function test_resend_otp_does_not_touch_a_verified_or_blocked_account(): void
    {
        $verified = $this->user(3);
        $blocked = $this->user(3, ['status' => 'inactive']);

        foreach ([$verified, $blocked] as $user) {
            $this->postJson('/api/resend-otp', ['email' => $user->email])->assertOk();
            $this->assertNull($user->fresh()->otp);
        }

        // Same answer for an unknown address: no account enumeration.
        $unknown = $this->postJson('/api/resend-otp', ['email' => 'nobody-'.uniqid().'@example.com'])->assertOk();
        $known = $this->postJson('/api/resend-otp', ['email' => $verified->email]);
        $this->assertSame($unknown->json('message'), $known->json('message'));

        $this->assertSame('inactive', $blocked->fresh()->status);
    }

    public function test_five_wrong_codes_burn_the_sign_up_code(): void
    {
        $pending = $this->user(3, ['otp' => '4321', 'otp_expires_at' => now()->addMinutes(10)]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/otp-verification', ['email' => $pending->email, 'otp' => '0000'])->assertStatus(400);
        }

        $this->postJson('/api/otp-verification', ['email' => $pending->email, 'otp' => '4321'])->assertStatus(429);
        $this->assertNotSame('4321', $pending->fresh()->otp);
        $this->assertNotNull($pending->fresh()->otp, 'the account must stay unverified');
    }

    // ── Sessions ───────────────────────────────────────────────────────────

    public function test_changing_the_password_ends_every_session(): void
    {
        $user = $this->user(3);
        $user->createToken('other phone');
        $current = $user->createToken('this phone')->plainTextToken;

        $this->withToken($current)->postJson('/api/reset_password', [
            'old_password' => 'secret-password',
            'password' => 'new-secret-password',
        ])->assertOk();

        // The app signs in again after a change, so no token is left behind.
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_change_password_validation_is_a_422_not_a_500(): void
    {
        $this->actingAs($this->user(3), 'sanctum')->postJson('/api/reset_password', [])->assertStatus(422);
    }

    // ── Chat input (BCR-38) ────────────────────────────────────────────────

    public function test_send_message_validates_recipient_and_body(): void
    {
        $customer = $this->user(3);
        $vendor = $this->user(2);

        $this->actingAs($customer, 'sanctum')->postJson('/api/sendMessage', [])->assertStatus(422);
        $this->actingAs($customer, 'sanctum')->postJson('/api/sendMessage', ['id' => 999999, 'message' => 'hi'])->assertStatus(422);
        $this->actingAs($customer, 'sanctum')->postJson('/api/sendMessage', ['id' => $vendor->id, 'message' => '   '])->assertStatus(422);
        $this->assertSame(0, DB::table('ch_messages')->count());
    }

    // ── Vendor product read ────────────────────────────────────────────────

    public function test_a_vendor_cannot_open_another_vendors_product_in_the_edit_form(): void
    {
        $owner = $this->user(2);
        $other = $this->user(2);
        $id = $this->product($owner);

        $this->actingAs($other, 'sanctum')->getJson("/api/vendor/show_product/{$id}")->assertStatus(400);
        $this->actingAs($owner, 'sanctum')->getJson("/api/vendor/show_product/{$id}")->assertOk();
    }

    // ── Brand catalogue (BCR-21, company half) ────────────────────────────

    public function test_a_brand_catalogue_lists_openable_active_products_only(): void
    {
        $seller = $this->user(2);
        $blocked = $this->user(2, ['status' => 'inactive']);
        $categoryId = DB::table('categories')->insertGetId(['user_id' => $seller->id, 'name' => 'Power Generation']);
        $subId = DB::table('sub_categories')->insertGetId(['category_id' => $categoryId, 'sub_category_name' => 'Diesel', 'status' => 'active']);
        $companyId = DB::table('companies')->insertGetId([
            'user_id' => $seller->id, 'category_id' => $categoryId, 'sub_category_id' => $subId,
            'company_name' => 'Caterpillar', 'status' => 'active',
        ]);
        $listed = $this->product($seller);
        $inactive = $this->product($seller);
        $hidden = $this->product($blocked);
        DB::table('products')->whereIn('id', [$listed, $inactive, $hidden])->update(['company_id' => $companyId]);
        DB::table('products')->where('id', $inactive)->update(['status' => 'inactive']);

        $rows = $this->actingAs($this->user(3), 'sanctum')
            ->getJson("/api/user/view-more-product-related-company/{$companyId}")
            ->assertOk()
            ->json('data');

        $this->assertSame([$listed], array_column($rows, 'id'));
        $this->assertSame('Hydraulic pump', $rows[0]['name']);
        $this->assertSame('Brooks Heavy Equipment', $rows[0]['vendor_name']);
    }

    // ── Stripe webhook ─────────────────────────────────────────────────────

    public function test_webhooks_are_refused_when_no_secret_is_configured(): void
    {
        config(['services.stripe.webhook_secret' => '']);
        $payload = json_encode(['type' => 'payment_intent.succeeded', 'data' => ['object' => ['id' => 'pi_x']]]);
        $ts = time();
        // A signature computed with the empty key, which is what an attacker can do.
        $sig = 't='.$ts.',v1='.hash_hmac('sha256', $ts.'.'.$payload, '');

        $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $sig,
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertStatus(400);
    }

    // ── Error responses ────────────────────────────────────────────────────

    public function test_exception_text_is_not_sent_to_clients_outside_debug(): void
    {
        config(['app.debug' => false]);
        $this->assertSame(ApiError::GENERIC, ApiError::message(new \RuntimeException('SQLSTATE[42S22] secret table')));

        config(['app.debug' => true]);
        $this->assertSame('detail', ApiError::message(new \RuntimeException('detail')));
    }
}
