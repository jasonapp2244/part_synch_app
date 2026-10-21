<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Findings from the live run on 2026-10-21 that were code, not data:
 * L22 the vendor got no in-app notice of a new order placed from the app's
 * checkout, and the customer got one notice per line; L17 an accepted but
 * unpaid order counted as 0 pending; L20 the cart named the seller by person
 * while the product page used the business, and the model number was stored
 * lowercased.
 */
class LiveRunFixesTest extends TestCase
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

    private function product(string $name): int
    {
        $id = DB::table('products')->insertGetId([
            'user_id' => $this->vendor->id,
            'name' => $name,
            'price' => 40,
            'stock_quantity' => '8',
            'status' => 'active',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('stocks')->insert([
            'product_id' => $id,
            'current_stock' => 8,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function addToCartWithAddress(int $productId): void
    {
        $this->actingAs($this->customer)
            ->postJson('/api/user/add-to-cart', ['product_id' => $productId, 'quantity' => 1])
            ->assertOk();
        $cartId = (int) DB::table('cart')->where('user_id', $this->customer->id)
            ->where('product_id', $productId)->value('id');

        $this->actingAs($this->customer)->postJson('/api/user/add-delivery-address', [
            'cart_id' => $cartId,
            'full_name' => 'Michael Reed',
            'phone_number' => '5550101021',
            'address_line1' => '350 Fifth Avenue',
            'city' => 'New York',
            'state' => 'NY',
            'country' => 'US',
            'postal_code' => '10118',
        ])->assertOk();
    }

    public function test_checkout_notifies_the_vendor_once_and_the_customer_once_per_order(): void
    {
        $this->addToCartWithAddress($this->product('Piston kit'));
        $this->addToCartWithAddress($this->product('Gasket set'));

        $this->actingAs($this->customer)->postJson('/api/user/checkout-products')
            ->assertOk()->assertJsonPath('status', true);

        $orderId = (int) DB::table('orders')->where('user_id', $this->customer->id)->value('id');
        $this->assertSame(2, DB::table('order_items')->where('order_id', $orderId)->count());

        $this->assertSame(1, DB::table('notifications')
            ->where('user_id', $this->customer->id)->where('email_subject', 'Order Placed')->count(),
            'one notice per order, not per line');
        $this->assertSame(1, DB::table('notifications')
            ->where('vendor_id', $this->vendor->id)->where('order_id', $orderId)
            ->where('email_subject', 'New Order Received')->count());

        $this->app['auth']->forgetGuards();
        $listed = $this->actingAs($this->vendor)->getJson('/api/vendor/notifications')
            ->assertOk()->json();
        $this->assertStringContainsString('New Order Received', json_encode($listed));
    }

    public function test_an_accepted_unpaid_order_counts_as_pending_on_the_vendor_home(): void
    {
        foreach (['pending', 'accept', 'payment', 'completed', 'delivered', 'cancel'] as $status) {
            DB::table('orders')->insert([
                'user_id' => $this->customer->id,
                'vendor_id' => $this->vendor->id,
                'order_number' => "ORD-{$status}",
                'order_status' => $status,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($this->vendor)->getJson('/api/vendor/dashboard')
            ->assertOk()
            ->assertJsonPath('data.orders.total', 6)
            ->assertJsonPath('data.orders.pending', 3)
            ->assertJsonPath('data.orders.completed', 2);
    }

    public function test_the_cart_names_the_seller_the_way_the_product_page_does(): void
    {
        $productId = $this->product('Piston kit');

        $seller = $this->actingAs($this->customer)->getJson("/api/user/get-product-details/{$productId}")
            ->json('data.seller.name');
        $this->assertSame('Brooks Heavy Equipment', $seller);

        $this->actingAs($this->customer)
            ->postJson('/api/user/add-to-cart', ['product_id' => $productId, 'quantity' => 1])
            ->assertOk();
        $cart = $this->actingAs($this->customer)->getJson('/api/user/cart-products')->assertOk()->json();

        $this->assertStringContainsString('"vendor_name":"Brooks Heavy Equipment"', json_encode($cart));
    }

    public function test_a_seller_without_a_business_name_is_shown_by_name(): void
    {
        $this->vendor->update(['business_name' => null]);

        $this->assertSame('Daniel Brooks', $this->vendor->fresh()->sellerName());
    }

    public function test_the_model_number_is_kept_as_typed(): void
    {
        $this->actingAs($this->vendor)->postJson('/api/vendor/add_product', [
            'name' => 'CAT C18 Piston Kit',
            'price' => 349.99,
            'modal_number' => 'C18-PK-2207',
            'new_stock' => 25,
        ])->assertCreated();

        $this->assertDatabaseHas('products', ['name' => 'CAT C18 Piston Kit', 'modal_number' => 'C18-PK-2207']);
    }

    public function test_saving_the_name_from_the_app_does_not_leave_a_stale_last_name(): void
    {
        $this->customer->update(['last_name' => 'Reed']);

        $this->actingAs($this->customer)->postJson('/api/update_profile', [
            'first_name' => 'Michael Reed',
            'phone_number' => '5550101021',
        ])->assertOk();

        $fresh = $this->customer->fresh();
        $this->assertSame('Michael Reed', $fresh->first_name);
        $this->assertNull($fresh->last_name, 'the app sends the whole name in one field (L23)');
    }

    public function test_an_order_paid_outside_the_app_is_labelled_so_in_the_admin(): void
    {
        $admin = $this->user(1, 'Sarah', 'Mitchell');
        $orderId = DB::table('orders')->insertGetId([
            'user_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'order_number' => '#ORD-OUTSIDE',
            'order_status' => 'payment',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('orders')->insert([
            'user_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'order_number' => '#ORD-DONE',
            'order_status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->get("/order/{$orderId}")
            ->assertOk()->assertSee('Paid outside the app');

        // L25: a paid order is not a completed one, on either admin page.
        $this->actingAs($admin)->get('/earning-records')
            ->assertOk()
            ->assertSee('Paid outside the app')
            ->assertViewHas('completedOrders', 1);
        $this->actingAs($admin)->get('/dashboard')
            ->assertOk()->assertViewHas('deliveredOrders', 1);
    }
}
