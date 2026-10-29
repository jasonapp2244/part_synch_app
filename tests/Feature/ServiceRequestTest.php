<?php

namespace Tests\Feature;

use App\Mail\ServiceRequestMail;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Services end to end: a vendor offers one, a customer requests it, the vendor
 * moves the request on, the admin tracks it and can step in. Before this,
 * services existed only on the vendor's side.
 */
class ServiceRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $vendor;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        foreach ([1 => 'admin', 2 => 'vendor', 3 => 'user'] as $id => $type) {
            DB::table('roles')->insertOrIgnore(['id' => $id, 'role_type' => $type]);
        }

        $this->vendor = $this->user(2, 'Daniel', 'Brooks', 'Brooks Heavy Equipment');
        $this->customer = $this->user(3, 'Michael', 'Reed');
    }

    private function user(int $roleId, string $first, string $last, ?string $business = null): User
    {
        return User::create([
            'role_id' => $roleId,
            'first_name' => $first,
            'last_name' => $last,
            'business_name' => $business,
            'email' => "role{$roleId}-" . uniqid() . '@example.com',
            'password' => Hash::make('secret-password'),
            'status' => 'active',
            'business_status' => 'active',
        ]);
    }

    private function service(array $extra = []): int
    {
        return DB::table('services')->insertGetId(array_merge([
            'user_id' => $this->vendor->id,
            'title' => 'Generator fitting',
            'price' => 120,
            'service_mode' => 'offline',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ], $extra));
    }

    private function request(int $serviceId, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->customer, 'sanctum')->postJson('/api/user/service-requests', [
            'service_id' => $serviceId,
            'preferred_date' => now()->addDays(3)->format('Y-m-d'),
            'address' => '350 Fifth Avenue, New York',
            'phone' => '5550101021',
            'note' => 'Standby generator, 20 kW.',
        ]);
    }

    private function vendorMove(int $id, array $body, ?User $as = null)
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($as ?? $this->vendor, 'sanctum')
            ->postJson("/api/vendor/service-requests/{$id}/status", $body);
    }

    public function test_a_customer_sees_only_a_sellers_active_services(): void
    {
        $this->service(['title' => 'Generator fitting']);
        $this->service(['title' => 'Old plan', 'status' => 'inactive']);

        $this->actingAs($this->customer, 'sanctum')
            ->getJson("/api/user/vendor/{$this->vendor->id}/services")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Generator fitting');
    }

    public function test_an_unapproved_seller_has_no_public_services(): void
    {
        $this->service();
        $this->vendor->update(['business_status' => 'inactive']);

        $this->actingAs($this->customer, 'sanctum')
            ->getJson("/api/user/vendor/{$this->vendor->id}/services")
            ->assertNotFound();
    }

    public function test_the_product_page_carries_the_linked_service(): void
    {
        $productId = DB::table('products')->insertGetId([
            'user_id' => $this->vendor->id,
            'name' => 'Standby generator',
            'price' => 900,
            'stock_quantity' => '3',
            'status' => 'active',
            'service_status' => 'Y',
            'service_type' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->service(['product_id' => $productId]);

        $this->actingAs($this->customer, 'sanctum')
            ->getJson("/api/user/get-product-details/{$productId}")
            ->assertOk()
            ->assertJsonPath('data.service_status', 'Y')
            ->assertJsonPath('data.linked_service.title', 'Generator fitting');
    }

    public function test_a_request_is_created_and_the_vendor_is_told(): void
    {
        $serviceId = $this->service();

        $this->request($serviceId)
            ->assertCreated()
            ->assertJsonPath('data.status', 'requested')
            ->assertJsonPath('data.title', 'Generator fitting')
            ->assertJsonPath('data.vendor.name', 'Brooks Heavy Equipment');

        $request = ServiceRequest::firstOrFail();
        $this->assertStringStartsWith('SRV-', $request->request_number);

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->vendor, 'sanctum')->getJson('/api/vendor/dashboard')
            ->assertOk()
            ->assertJsonPath('data.service_requests.open', 1);
        $this->assertDatabaseHas('notifications', [
            'vendor_id' => $this->vendor->id,
            'service_request_id' => $request->id,
            'email_subject' => 'New service request',
        ]);
    }

    public function test_a_second_open_request_for_the_same_service_is_refused(): void
    {
        $serviceId = $this->service();
        $this->request($serviceId)->assertCreated();

        $this->request($serviceId)->assertStatus(409);
    }

    public function test_an_inactive_service_cannot_be_requested(): void
    {
        $this->request($this->service(['status' => 'inactive']))->assertNotFound();
    }

    public function test_blocked_users_cannot_request_from_each_other(): void
    {
        UserBlock::create(['blocker_id' => $this->vendor->id, 'blocked_id' => $this->customer->id]);

        $this->request($this->service())->assertForbidden();
    }

    public function test_the_vendor_moves_a_request_through_to_completed_and_the_customer_is_told(): void
    {
        $this->request($this->service())->assertCreated();
        $id = ServiceRequest::firstOrFail()->id;

        $this->vendorMove($id, [
            'status' => 'accepted',
            'scheduled_at' => now()->addDays(4)->format('Y-m-d') . ' 10:00',
            'quoted_price' => 150,
        ])->assertOk()->assertJsonPath('data.status', 'accepted');

        $this->vendorMove($id, ['status' => 'in_progress'])->assertOk();
        $this->vendorMove($id, ['status' => 'completed'])->assertOk()->assertJsonPath('data.status', 'completed');

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->vendor, 'sanctum')
            ->postJson("/api/vendor/service-requests/{$id}/paid")
            ->assertOk()
            ->assertJsonPath('data.paid_outside', true);

        $request = ServiceRequest::findOrFail($id);
        $this->assertSame('150.00', $request->quoted_price);
        $this->assertSame(4, DB::table('notifications')
            ->where('user_id', $this->customer->id)
            ->where('service_request_id', $id)
            ->count());
    }

    public function test_an_illegal_move_is_refused_with_the_allowed_ones(): void
    {
        $this->request($this->service())->assertCreated();
        $id = ServiceRequest::firstOrFail()->id;

        $this->vendorMove($id, ['status' => 'completed'])
            ->assertStatus(422)
            ->assertJsonPath('current_status', 'requested')
            ->assertJsonPath('allowed_statuses', ['accepted', 'declined', 'cancelled']);
    }

    public function test_accepting_needs_a_date_and_declining_needs_a_reason(): void
    {
        $this->request($this->service())->assertCreated();
        $id = ServiceRequest::firstOrFail()->id;

        $this->vendorMove($id, ['status' => 'accepted'])->assertStatus(422);
        $this->vendorMove($id, ['status' => 'declined'])->assertStatus(422);

        $this->vendorMove($id, ['status' => 'declined', 'decline_reason' => 'Fully booked this month.'])
            ->assertOk()
            ->assertJsonPath('data.decline_reason', 'Fully booked this month.');
    }

    public function test_another_vendor_cannot_see_or_move_the_request(): void
    {
        $this->request($this->service())->assertCreated();
        $id = ServiceRequest::firstOrFail()->id;
        $other = $this->user(2, 'Ava', 'Stone', 'Stone Power');

        $this->app['auth']->forgetGuards();
        $this->actingAs($other, 'sanctum')->getJson('/api/vendor/service-requests')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->vendorMove($id, ['status' => 'declined', 'decline_reason' => 'x'], $other)->assertNotFound();
    }

    public function test_another_customer_cannot_see_or_cancel_the_request(): void
    {
        $this->request($this->service())->assertCreated();
        $id = ServiceRequest::firstOrFail()->id;
        $other = $this->user(3, 'Liam', 'Hart');

        $this->app['auth']->forgetGuards();
        $this->actingAs($other, 'sanctum')->getJson('/api/user/service-requests')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->actingAs($other, 'sanctum')->postJson("/api/user/service-requests/{$id}/cancel")->assertNotFound();
    }

    public function test_the_customer_can_cancel_only_before_the_work_starts(): void
    {
        $this->request($this->service())->assertCreated();
        $id = ServiceRequest::firstOrFail()->id;
        $this->vendorMove($id, ['status' => 'accepted', 'scheduled_at' => now()->addDay()->format('Y-m-d') . ' 09:00'])->assertOk();
        $this->vendorMove($id, ['status' => 'in_progress'])->assertOk();

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/user/service-requests/{$id}/cancel")
            ->assertStatus(422);

        $second = $this->service(['title' => 'Annual inspection']);
        $this->request($second)->assertCreated();
        $secondId = ServiceRequest::where('service_id', $second)->value('id');

        $this->actingAs($this->customer, 'sanctum')
            ->postJson("/api/user/service-requests/{$secondId}/cancel", ['reason' => 'Changed plans.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancelled_by', 'customer');
        $this->assertDatabaseHas('notifications', [
            'vendor_id' => $this->vendor->id,
            'service_request_id' => $secondId,
            'email_subject' => 'Service request cancelled',
        ]);
    }

    public function test_the_admin_sees_the_pages_and_can_cancel_an_open_request(): void
    {
        $this->request($this->service())->assertCreated();
        $id = ServiceRequest::firstOrFail()->id;
        $admin = $this->user(1, 'Sarah', 'Mitchell');

        $this->actingAs($admin)->get('/services')->assertOk()->assertSee('Generator fitting');
        $this->actingAs($admin)->get('/service-requests')->assertOk()->assertSee(ServiceRequest::firstOrFail()->request_number);
        $this->actingAs($admin)->get('/dashboard')->assertOk()->assertSee('Service Requests');

        $this->actingAs($admin)
            ->post("/service-request/{$id}/cancel", ['cancel_reason' => 'Duplicate of an earlier request.'])
            ->assertRedirect();

        $request = ServiceRequest::findOrFail($id);
        $this->assertSame('cancelled', $request->status);
        $this->assertSame('admin', $request->cancelled_by);
        $this->assertDatabaseHas('notifications', ['user_id' => $this->customer->id, 'service_request_id' => $id, 'email_subject' => 'Service request cancelled']);
        $this->assertDatabaseHas('notifications', ['vendor_id' => $this->vendor->id, 'service_request_id' => $id, 'email_subject' => 'Service request cancelled']);
    }

    public function test_the_dashboard_counts_open_service_requests_and_links_to_them(): void
    {
        $this->request($this->service())->assertCreated();
        $this->request($this->service(['title' => 'Battery check']))->assertCreated();
        ServiceRequest::latest('id')->firstOrFail()->update(['status' => 'completed']);
        $admin = $this->user(1, 'Sarah', 'Mitchell');

        $this->actingAs($admin)->get('/dashboard')
            ->assertOk()
            ->assertViewHas('openServiceRequests', 1)
            ->assertSee('Open service requests')
            ->assertSee(route('admin.service.requests'), false);
    }

    public function test_the_admin_can_switch_a_service_off(): void
    {
        $serviceId = $this->service();
        $admin = $this->user(1, 'Sarah', 'Mitchell');

        $this->actingAs($admin)->post("/service/{$serviceId}/toggle-status")->assertRedirect();

        $this->assertSame('inactive', DB::table('services')->where('id', $serviceId)->value('status'));
    }

    public function test_both_lists_export(): void
    {
        $this->request($this->service())->assertCreated();
        $admin = $this->user(1, 'Sarah', 'Mitchell');

        $this->actingAs($admin)->get('/export/services.xlsx')->assertOk();
        $this->actingAs($admin)->get('/export/service-requests.pdf')->assertOk();
    }

    public function test_a_service_can_be_reported_and_taken_down(): void
    {
        $serviceId = $this->service();

        $this->actingAs($this->customer, 'sanctum')->postJson('/api/report', [
            'reportable_type' => 'service',
            'reportable_id' => (string) $serviceId,
            'reason' => 'scam_or_fraud',
        ])->assertSuccessful();

        $reportId = DB::table('reports')->where('reportable_type', 'service')->value('id');
        $admin = $this->user(1, 'Sarah', 'Mitchell');
        $this->actingAs($admin)->post("/report/{$reportId}/take-down")->assertRedirect();

        $this->assertSame('inactive', DB::table('services')->where('id', $serviceId)->value('status'));
    }

    public function test_a_vendor_links_and_unlinks_a_service_and_a_product_keeps_one_service(): void
    {
        $productId = DB::table('products')->insertGetId([
            'user_id' => $this->vendor->id,
            'name' => 'Standby generator',
            'price' => 900,
            'stock_quantity' => '3',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $first = $this->service();
        $second = $this->service(['title' => 'Annual inspection']);

        $this->actingAs($this->vendor, 'sanctum')
            ->postJson("/api/vendor/update_service/{$first}", ['product_id' => $productId])
            ->assertOk();
        $this->assertSame($productId, (int) DB::table('services')->where('id', $first)->value('product_id'));

        $this->actingAs($this->vendor, 'sanctum')
            ->postJson("/api/vendor/update_service/{$second}", ['product_id' => $productId])
            ->assertStatus(400);

        // A build that does not send the key keeps the link.
        $this->actingAs($this->vendor, 'sanctum')
            ->postJson("/api/vendor/update_service/{$first}", ['title' => 'Generator fitting and test'])
            ->assertOk();
        $this->assertSame($productId, (int) DB::table('services')->where('id', $first)->value('product_id'));

        $this->actingAs($this->vendor, 'sanctum')
            ->postJson("/api/vendor/update_service/{$first}", ['product_id' => null])
            ->assertOk();
        $this->assertNull(DB::table('services')->where('id', $first)->value('product_id'));
    }

    public function test_the_email_renders_with_the_same_words_as_the_notification(): void
    {
        $this->request($this->service())->assertCreated();
        $request = ServiceRequest::firstOrFail();
        $request->update(['status' => 'accepted', 'scheduled_at' => now()->addDay()->setTime(10, 0)]);

        $html = (new ServiceRequestMail($request->fresh(), 'accepted', 'customer'))->render();

        [$subject, $body] = ServiceRequestMail::summary($request->fresh(), 'accepted', 'customer');
        $this->assertSame('Service request accepted', $subject);
        $this->assertStringContainsString('Brooks Heavy Equipment accepted your request', $body);
        $this->assertStringContainsString($request->request_number, $html);
        $this->assertStringContainsString('Booked for', $html);
    }

    public function test_a_completed_service_makes_a_review_of_its_product_verified(): void
    {
        $productId = DB::table('products')->insertGetId([
            'user_id' => $this->vendor->id,
            'name' => 'Standby generator',
            'price' => 900,
            'stock_quantity' => '3',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->request($this->service(['product_id' => $productId]))->assertCreated();
        $id = ServiceRequest::firstOrFail()->id;
        $this->vendorMove($id, ['status' => 'accepted', 'scheduled_at' => now()->addDay()->format('Y-m-d') . ' 09:00'])->assertOk();
        $this->vendorMove($id, ['status' => 'in_progress'])->assertOk();
        $this->vendorMove($id, ['status' => 'completed'])->assertOk();

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/user/reviews', [
            'product_id' => $productId,
            'rating' => 5,
            'review_text' => 'Fitted on time.',
        ])->assertCreated()->assertJsonPath('data.verified', true);
    }
}
