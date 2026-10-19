<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * BCR-14. `cart.price` is the line total, and every reader multiplied it by
 * the quantity again: an order of 2 x 47.00 was stored as unit 94.00, line
 * 188.00, and the vendor's earnings, the order email, the admin order page
 * and the invoice all said 188.00 (device run 2026-10-20).
 */
class OrderMoneyTest extends TestCase
{
    use RefreshDatabase;

    private User $vendor;

    private User $customer;

    private int $productId;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        foreach ([1 => 'admin', 2 => 'vendor', 3 => 'user'] as $id => $type) {
            DB::table('roles')->insertOrIgnore(['id' => $id, 'role_type' => $type]);
        }

        $this->vendor = $this->user(2);
        $this->customer = $this->user(3);

        $this->productId = DB::table('products')->insertGetId([
            'user_id' => $this->vendor->id,
            'name' => 'Fuel filter',
            'price' => 47,
            'stock_quantity' => '8',
            'status' => 'active',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('stocks')->insert([
            'product_id' => $this->productId,
            'current_stock' => 8,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function user(int $roleId): User
    {
        return User::create([
            'role_id' => $roleId,
            'first_name' => $roleId === 2 ? 'Brake' : 'Dana',
            'last_name' => $roleId === 2 ? 'Barn' : 'Cole',
            'email' => "role{$roleId}-" . uniqid() . '@example.com',
            'password' => Hash::make('secret-password'),
            'status' => 'active',
            'business_status' => 'active',
        ]);
    }

    private function cartTwo(): int
    {
        $this->actingAs($this->customer)
            ->postJson('/api/user/add-to-cart', ['product_id' => $this->productId, 'quantity' => 2])
            ->assertOk();

        return (int) DB::table('cart')->where('user_id', $this->customer->id)->value('id');
    }

    public function test_the_cart_reports_unit_and_line_prices_once(): void
    {
        $this->cartTwo();

        $item = $this->actingAs($this->customer)->getJson('/api/user/cart-products')
            ->assertOk()->json('data.0');

        // The shape varies by grouping; find the line wherever it is.
        $line = collect(data_get($item, 'items', [$item]))->firstWhere('product_id', $this->productId);
        $this->assertEquals(47.0, $line['unit_price']);
        $this->assertEquals(94.0, $line['line_total']);
        $this->assertEquals(94.0, $line['total_price']);
    }

    public function test_checkout_stores_unit_and_line_prices_and_every_reader_agrees(): void
    {
        $cartId = $this->cartTwo();

        $this->actingAs($this->customer)->postJson('/api/user/add-delivery-address', [
            'cart_id' => $cartId,
            'full_name' => 'Dana Cole',
            'phone_number' => '5550100',
            'address_line1' => '1 Main St',
            'city' => 'Austin',
            'state' => 'TX',
            'country' => 'US',
            'postal_code' => '73301',
        ])->assertOk();

        $this->actingAs($this->customer)->postJson('/api/user/checkout-products')
            ->assertOk()->assertJsonPath('status', true);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $this->productId,
            'quantity' => 2,
            'price' => 47,
            'total_price' => 94,
        ]);

        $order = $this->actingAs($this->customer)->getJson('/api/user/order-history')
            ->assertOk()->json('data.0');
        $this->assertEquals(94.0, $order['total']);
        $this->assertEquals(94.0, $order['items'][0]['line_total']);

        $this->app['auth']->forgetGuards();
        $dashboard = $this->actingAs($this->vendor)->getJson('/api/vendor/dashboard')->assertOk()->json();
        $this->assertEquals(94.0, (float) data_get($dashboard, 'data.revenue.total'));
    }

    public function test_repair_command_fixes_only_provably_squared_lines(): void
    {
        $orderId = DB::table('orders')->insertGetId([
            'vendor_id' => $this->vendor->id,
            'user_id' => $this->customer->id,
            'order_number' => 'ORD-OLD',
            'order_status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $line = fn (int $qty, float $price, float $total) => DB::table('order_items')->insertGetId([
            'order_id' => $orderId, 'product_id' => $this->productId, 'quantity' => $qty,
            'price' => $price, 'total_price' => $total, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $squared = $line(2, 94, 188);   // old checkout: line total, then squared
        $buyNow = $line(2, 47, 94);     // old buy-now: already right
        $single = $line(1, 47, 47);     // quantity 1 never showed the bug

        $this->artisan('orders:repair-squared-totals')->assertSuccessful();
        $this->assertDatabaseHas('order_items', ['id' => $squared, 'price' => 94, 'total_price' => 188]);

        $this->artisan('orders:repair-squared-totals', ['--apply' => true])->assertSuccessful();

        $this->assertDatabaseHas('order_items', ['id' => $squared, 'price' => 47, 'total_price' => 94]);
        $this->assertDatabaseHas('order_items', ['id' => $buyNow, 'price' => 47, 'total_price' => 94]);
        $this->assertDatabaseHas('order_items', ['id' => $single, 'price' => 47, 'total_price' => 47]);

        $this->artisan('orders:repair-squared-totals', ['--apply' => true])->assertSuccessful();
        $this->assertDatabaseHas('order_items', ['id' => $squared, 'price' => 47, 'total_price' => 94]);
    }
}
