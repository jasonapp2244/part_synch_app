<?php

namespace Tests\Feature;

use App\Mail\ProductBackInStockMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Checkout tells a customer when a part is out of stock and marks them to hear
 * when it is back. Only `update-stock` sent that notice, and the app restocks
 * through Edit Product (`update_product`), so nobody was ever told
 * (device run 2026-10-20).
 */
class RestockNoticeTest extends TestCase
{
    use RefreshDatabase;

    private User $vendor;

    private User $customer;

    private int $productId;

    private int $companyId;

    private int $productCategoryId;

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
            'stock_quantity' => '0',
            'status' => 'active',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        // Every product the app creates has its catalogue link; Edit Product
        // sends the ids that find it.
        $categoryId = DB::table('categories')->insertGetId(['user_id' => $this->vendor->id, 'name' => 'Power Generation']);
        $subCategoryId = DB::table('sub_categories')->insertGetId(['category_id' => $categoryId, 'sub_category_name' => 'Diesel', 'status' => 'active']);
        $this->companyId = DB::table('companies')->insertGetId([
            'user_id' => $this->vendor->id,
            'category_id' => $categoryId,
            'sub_category_id' => $subCategoryId,
            'company_name' => 'Cummins',
            'status' => 'active',
        ]);
        $this->productCategoryId = DB::table('company_product_categories')->insertGetId([
            'user_id' => $this->vendor->id,
            'category_id' => $categoryId,
            'sub_category_id' => $subCategoryId,
            'company_id' => $this->companyId,
            'product_categories_name' => 'Fuel Filters',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('company_product_category_links')->insert([
            'company_id' => $this->companyId,
            'com_pro_cat_id' => $this->productCategoryId,
            'product_id' => $this->productId,
            'price_type' => 'standard',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('wishlists')->insert([
            'user_id' => $this->customer->id,
            'product_id' => $this->productId,
            'type' => 'product',
            'status' => 'notified',
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

    public function test_restocking_from_edit_product_tells_waiting_customers_once(): void
    {
        $this->actingAs($this->vendor)
            ->postJson("/api/vendor/update_product/{$this->productId}", ['new_stock' => 4, 'company_id' => $this->companyId, 'company_product_categories_id' => $this->productCategoryId])
            ->assertOk();

        Mail::assertSent(ProductBackInStockMail::class, 1);
        $this->assertDatabaseHas('wishlists', ['user_id' => $this->customer->id, 'status' => 'active']);

        // A second top-up of a product already in stock tells nobody again.
        $this->actingAs($this->vendor)
            ->postJson("/api/vendor/update_product/{$this->productId}", ['new_stock' => 2, 'company_id' => $this->companyId, 'company_product_categories_id' => $this->productCategoryId])
            ->assertOk();

        Mail::assertSent(ProductBackInStockMail::class, 1);
    }
}
