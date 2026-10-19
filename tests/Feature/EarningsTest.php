<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A cancelled order is not money earned. Every revenue figure (vendor home,
 * vendor earnings, admin dashboard, admin earnings) summed every order line
 * regardless of status, and the admin dashboard counted cancelled orders by a
 * status value ('cancelled') that is never stored (device run 2026-10-20).
 */
class EarningsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([1 => 'admin', 2 => 'vendor', 3 => 'user'] as $id => $type) {
            DB::table('roles')->insertOrIgnore(['id' => $id, 'role_type' => $type]);
        }

        $this->admin = $this->user(1);
        $this->vendor = $this->user(2);
        $customer = $this->user(3);

        $productId = DB::table('products')->insertGetId([
            'user_id' => $this->vendor->id,
            'name' => 'Fuel filter',
            'price' => 47,
            'stock_quantity' => '8',
            'status' => 'active',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['completed' => 2, 'cancel' => 1] as $status => $quantity) {
            $orderId = DB::table('orders')->insertGetId([
                'user_id' => $customer->id,
                'vendor_id' => $this->vendor->id,
                'order_number' => "ORD-{$status}",
                'order_status' => $status,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('order_items')->insert([
                'order_id' => $orderId,
                'product_id' => $productId,
                'quantity' => $quantity,
                'price' => 47,
                'total_price' => 47 * $quantity,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function user(int $roleId): User
    {
        return User::create([
            'role_id' => $roleId,
            'first_name' => 'Role' . $roleId,
            'email' => "role{$roleId}-" . uniqid() . '@example.com',
            'password' => Hash::make('secret-password'),
            'status' => 'active',
            'business_status' => 'active',
        ]);
    }

    public function test_vendor_revenue_leaves_cancelled_orders_out(): void
    {
        $this->actingAs($this->vendor)->getJson('/api/vendor/dashboard')
            ->assertOk()
            ->assertJsonPath('data.revenue.total', 94);

        $earnings = $this->actingAs($this->vendor)->getJson('/api/vendor/earnings')->assertOk();
        $this->assertEquals(94, $earnings->json('data.total_revenue'));
        $this->assertEquals(94, $earnings->json('data.monthly_revenue.0.revenue'));
        $this->assertEquals(2, $earnings->json('data.monthly_revenue.0.items_sold'));
        $this->assertEquals(94, $earnings->json('data.top_products.0.total_revenue'));
    }

    public function test_admin_revenue_leaves_cancelled_orders_out_and_counts_them_separately(): void
    {
        $this->actingAs($this->admin)->get('/dashboard')
            ->assertOk()
            ->assertViewHas('totalRevenue', fn ($total) => (float) $total === 94.0)
            ->assertViewHas('cancelledOrders', 1)
            ->assertViewHas('deliveredOrders', 1);

        $this->actingAs($this->admin)->get('/earning-records')
            ->assertOk()
            ->assertViewHas('totalRevenue', fn ($total) => (float) $total === 94.0);
    }
}
