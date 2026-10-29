<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * GET /api/vendor/order-view feeds the app's vendor order list and its order
 * detail screen. The detail needs the customer, the full address, when the
 * order was placed and the unit price; the keys older app builds read must
 * stay as they were.
 */
class VendorOrderViewTest extends TestCase
{
    use RefreshDatabase;

    private User $vendor;

    private User $otherVendor;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([1 => 'admin', 2 => 'vendor', 3 => 'user'] as $id => $type) {
            DB::table('roles')->insertOrIgnore(['id' => $id, 'role_type' => $type]);
        }

        $this->vendor = $this->user(2, 'Daniel', 'Brooks', 'Brooks Heavy Equipment');
        $this->otherVendor = $this->user(2, 'Laura', 'Hayes', 'Hayes Parts');
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

    private function order(User $vendor, string $number): int
    {
        $productId = DB::table('products')->insertGetId([
            'user_id' => $vendor->id,
            'name' => 'Brake pad',
            'price' => 20,
            'stock_quantity' => '8',
            'status' => 'active',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $addressId = DB::table('delivery_addresses')->insertGetId([
            'user_id' => $this->customer->id,
            'full_name' => 'Michael Reed',
            'phone_number' => '+1 713 555 0142',
            'address_line1' => '12 Commerce Street',
            'address_line2' => 'Suite 4',
            'city' => 'Houston',
            'state' => 'TX',
            'country' => 'United States',
            'postal_code' => '77002',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = DB::table('orders')->insertGetId([
            'vendor_id' => $vendor->id,
            'user_id' => $this->customer->id,
            'order_number' => $number,
            'delivery_address_id' => $addressId,
            'shipping_charges' => 5,
            'order_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // order_items.vendor_id is left null: its FK targets vendor_type, not
        // users, and order-view filters on orders.vendor_id anyway.
        DB::table('order_items')->insert([
            'order_id' => $orderId,
            'product_id' => $productId,
            'quantity' => 2,
            'price' => 20,
            'total_price' => 40,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $orderId;
    }

    public function test_order_view_carries_the_detail_fields_and_keeps_the_old_keys(): void
    {
        $this->order($this->vendor, '#ORD-20261029-1');

        $row = $this->actingAs($this->vendor)
            ->getJson('/api/vendor/order-view')
            ->assertOk()
            ->json('orders.0');

        foreach ([
            'user_id', 'vendor_id', 'order_id', 'order_number', 'quantity', 'total_price',
            'product_name', 'image_url', 'address_line1', 'address_line2', 'city',
            'order_status', 'delivery_date',
        ] as $key) {
            $this->assertArrayHasKey($key, $row, "existing key $key must stay");
        }

        $this->assertSame('Michael Reed', $row['full_name']);
        $this->assertSame('+1 713 555 0142', $row['phone_number']);
        $this->assertSame('TX', $row['state']);
        $this->assertSame('77002', $row['postal_code']);
        $this->assertSame('United States', $row['country']);
        $this->assertEquals(5, $row['shipping_charges']);
        $this->assertEquals(20, $row['unit_price']);
        $this->assertNotNull($row['placed_at']);
    }

    public function test_a_vendor_only_sees_their_own_orders(): void
    {
        $this->order($this->vendor, '#ORD-20261029-1');
        $this->order($this->otherVendor, '#ORD-20261029-2');

        $numbers = collect(
            $this->actingAs($this->vendor)->getJson('/api/vendor/order-view')->assertOk()->json('orders')
        )->pluck('order_number')->unique()->values()->all();

        $this->assertSame(['#ORD-20261029-1'], $numbers);
    }
}
