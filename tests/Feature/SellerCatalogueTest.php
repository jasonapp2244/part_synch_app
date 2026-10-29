<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A seller's catalogue on their page in the app. Its rows had no id, so the
 * app could not open any of them and showed reviews only (BCR-21).
 */
class SellerCatalogueTest extends TestCase
{
    use RefreshDatabase;

    private User $vendor;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([1 => 'admin', 2 => 'vendor', 3 => 'user'] as $id => $type) {
            DB::table('roles')->insertOrIgnore(['id' => $id, 'role_type' => $type]);
        }

        $this->vendor = $this->user(2, 'Brooks Heavy Equipment');
        $this->customer = $this->user(3, 'Emily');
    }

    private function user(int $roleId, string $first, string $status = 'active'): User
    {
        return User::create([
            'role_id' => $roleId,
            'first_name' => $first,
            'email' => strtolower(str_replace(' ', '.', $first)) . '-' . uniqid() . '@example.com',
            'password' => Hash::make('secret-password'),
            'status' => $status,
            'business_status' => 'active',
        ]);
    }

    private function product(User $owner, string $name, string $status = 'active'): int
    {
        $id = DB::table('products')->insertGetId([
            'user_id' => $owner->id,
            'name' => $name,
            'price' => 47,
            'stock_quantity' => '3',
            'status' => $status,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('product_images')->insert(['product_id' => $id, 'image_url' => "products/{$id}.jpg"]);

        return $id;
    }

    public function test_rows_carry_an_id_and_the_feed_shape(): void
    {
        $id = $this->product($this->vendor, 'Fuel filter');

        $this->actingAs($this->customer, 'sanctum')
            ->getJson("/api/user/view-more-product-related-vendor/{$this->vendor->id}")
            ->assertOk()
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.name', 'Fuel filter')
            ->assertJsonPath('data.0.product_name', 'Fuel filter')
            ->assertJsonPath('data.0.product_images.0.image_url', "products/{$id}.jpg")
            ->assertJsonPath('data.0.images.0', "products/{$id}.jpg");
    }

    public function test_only_active_listings_of_that_vendor_are_listed(): void
    {
        $this->product($this->vendor, 'Fuel filter');
        $this->product($this->vendor, 'Withdrawn part', 'inactive');
        $this->product($this->user(2, 'Other Seller'), 'Someone else');

        $this->actingAs($this->customer, 'sanctum')
            ->getJson("/api/user/view-more-product-related-vendor/{$this->vendor->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Fuel filter');
    }

    public function test_the_sellers_directory_sends_public_columns_only(): void
    {
        $this->vendor->forceFill(['phone_number' => '+12025550100', 'address' => '12 Elm Street', 'token' => 'push-token'])->save();

        $row = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/user/view-more-vendors')
            ->assertOk()
            ->json('data.data.0');

        $this->assertSame($this->vendor->id, $row['id']);
        $this->assertSame('Brooks Heavy Equipment', $row['first_name']);
        foreach (['email', 'phone_number', 'address', 'token', 'latitude'] as $field) {
            $this->assertArrayNotHasKey($field, $row);
        }
    }

    public function test_a_suspended_vendor_lists_nothing(): void
    {
        $suspended = $this->user(2, 'Suspended Seller', 'inactive');
        $this->product($suspended, 'Hidden part');

        $this->actingAs($this->customer, 'sanctum')
            ->getJson("/api/user/view-more-product-related-vendor/{$suspended->id}")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
