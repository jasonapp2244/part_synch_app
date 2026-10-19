<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Ordering the same part twice. Checkout leaves its cart rows behind as
 * 'inactive', and add-to-cart picked that row back up and added to it: a
 * customer who had bought 2 and then added 1 was sent an order for 3, and the
 * first order's cart row was rewritten under it (device run 2026-10-20).
 */
class CartReorderTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private int $productId;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        foreach ([1 => 'admin', 2 => 'vendor', 3 => 'user'] as $id => $type) {
            DB::table('roles')->insertOrIgnore(['id' => $id, 'role_type' => $type]);
        }

        $vendor = $this->user(2);
        $this->customer = $this->user(3);

        $this->productId = DB::table('products')->insertGetId([
            'user_id' => $vendor->id,
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
            'first_name' => 'Role' . $roleId,
            'email' => "role{$roleId}-" . uniqid() . '@example.com',
            'password' => Hash::make('secret-password'),
            'status' => 'active',
            'business_status' => 'active',
        ]);
    }

    private function buy(int $quantity): int
    {
        $this->actingAs($this->customer)
            ->postJson('/api/user/add-to-cart', ['product_id' => $this->productId, 'quantity' => $quantity])
            ->assertOk();

        $cartId = (int) DB::table('cart')->where('user_id', $this->customer->id)
            ->where('status', 'active')->value('id');

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

        return $cartId;
    }

    public function test_a_second_order_of_the_same_part_is_only_the_new_quantity(): void
    {
        $firstCart = $this->buy(2);
        $secondCart = $this->buy(1);

        $this->assertNotSame($firstCart, $secondCart);
        $this->assertSame(
            [2, 1],
            DB::table('order_items')->orderBy('id')->pluck('quantity')->map(fn ($q) => (int) $q)->all()
        );
        $this->assertDatabaseHas('cart', ['id' => $firstCart, 'quantity' => 2, 'status' => 'inactive']);
    }

    public function test_a_checked_out_line_cannot_be_removed_from_the_cart(): void
    {
        $cartId = $this->buy(2);

        $this->actingAs($this->customer)
            ->deleteJson('/api/user/remove-cart-item', ['cart_id' => $cartId])
            ->assertNotFound();

        $this->assertDatabaseHas('cart', ['id' => $cartId]);
        $this->assertDatabaseHas('delivery_addresses', ['cart_id' => $cartId]);
    }
}
