<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Admin "Product Categories" screen. Product categories belong to a company
 * and vendors pick one when adding a product; until now nothing could create
 * them. Category and sub-category are copied from the chosen company.
 */
class AdminProductCategoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private int $categoryId;

    private int $subCategoryId;

    private int $companyId;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([1 => 'admin', 2 => 'vendor', 3 => 'user'] as $id => $type) {
            DB::table('roles')->insertOrIgnore(['id' => $id, 'role_type' => $type]);
        }

        Storage::fake('public');

        $this->admin = $this->user(1);

        $this->categoryId = DB::table('categories')->insertGetId(['user_id' => $this->admin->id, 'name' => 'Power Generation']);
        $this->subCategoryId = DB::table('sub_categories')->insertGetId([
            'category_id' => $this->categoryId,
            'sub_category_name' => 'Diesel Generators',
            'status' => 'active',
        ]);
        $this->companyId = $this->company('Cummins');
    }

    private function user(int $role): User
    {
        return User::create([
            'role_id' => $role,
            'first_name' => 'Test',
            'last_name' => 'User' . $role,
            'email' => 'user' . $role . '-' . uniqid() . '@example.com',
            'password' => Hash::make('secret-password'),
            'status' => 'active',
        ]);
    }

    private function company(string $name): int
    {
        return DB::table('companies')->insertGetId([
            'user_id' => $this->admin->id,
            'category_id' => $this->categoryId,
            'sub_category_id' => $this->subCategoryId,
            'company_name' => $name,
            'status' => 'active',
        ]);
    }

    private function productCategory(string $name, ?int $companyId = null, ?string $image = null): int
    {
        return DB::table('company_product_categories')->insertGetId([
            'user_id' => $this->admin->id,
            'category_id' => $this->categoryId,
            'sub_category_id' => $this->subCategoryId,
            'company_id' => $companyId ?? $this->companyId,
            'product_categories_name' => $name,
            'product_categories_image' => $image,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_admin_creates_a_product_category_with_category_copied_from_company(): void
    {
        $this->actingAs($this->admin)->post('/product-category', [
            'company_id' => $this->companyId,
            'product_categories_name' => 'Fuel Filters',
            'description' => 'Primary and secondary fuel filters',
            'product_categories_image' => UploadedFile::fake()->image('filters.jpg'),
            'status' => 'active',
        ])->assertRedirect(route('product.category.records'))->assertSessionHas('success');

        $this->assertDatabaseHas('company_product_categories', [
            'product_categories_name' => 'Fuel Filters',
            'company_id' => $this->companyId,
            'category_id' => $this->categoryId,
            'sub_category_id' => $this->subCategoryId,
            'user_id' => $this->admin->id,
            'status' => 'active',
        ]);

        $image = DB::table('company_product_categories')->where('product_categories_name', 'Fuel Filters')->value('product_categories_image');
        $this->assertStringStartsWith('product_categories/', $image);
        Storage::disk('public')->assertExists($image);

        // The vendor app's list for that company now returns it.
        $vendor = $this->user(2);
        $this->actingAs($vendor, 'sanctum')
            ->getJson("/api/vendor/product-categories/{$this->companyId}")
            ->assertOk()
            ->assertSee('Fuel Filters');
    }

    public function test_duplicate_name_for_the_same_company_is_rejected_but_allowed_for_another(): void
    {
        $this->productCategory('Fuel Filters');

        $this->actingAs($this->admin)->post('/product-category', [
            'company_id' => $this->companyId,
            'product_categories_name' => 'Fuel Filters',
            'status' => 'active',
        ])->assertSessionHasErrors('product_categories_name');

        $this->assertSame(1, DB::table('company_product_categories')->where('product_categories_name', 'Fuel Filters')->count());

        $otherCompany = $this->company('Perkins');
        $this->actingAs($this->admin)->post('/product-category', [
            'company_id' => $otherCompany,
            'product_categories_name' => 'Fuel Filters',
            'status' => 'active',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
    }

    public function test_create_requires_company_and_name(): void
    {
        $this->actingAs($this->admin)->post('/product-category', ['status' => 'active'])
            ->assertSessionHasErrors(['company_id', 'product_categories_name']);

        $this->assertSame(0, DB::table('company_product_categories')->count());
    }

    public function test_admin_updates_a_product_category(): void
    {
        $id = $this->productCategory('Fuel Filters', null, 'product_categories/old.jpg');
        Storage::disk('public')->put('product_categories/old.jpg', 'old');
        $otherCompany = $this->company('Perkins');

        $this->actingAs($this->admin)->put("/product-category/{$id}", [
            'company_id' => $otherCompany,
            'product_categories_name' => 'Oil Filters',
            'description' => 'Spin-on oil filters',
            'product_categories_image' => UploadedFile::fake()->image('oil.png'),
            'status' => 'inactive',
        ])->assertRedirect(route('product.category.records'))->assertSessionHas('success');

        $row = DB::table('company_product_categories')->find($id);
        $this->assertSame('Oil Filters', $row->product_categories_name);
        $this->assertSame('Spin-on oil filters', $row->description);
        $this->assertSame('inactive', $row->status);
        $this->assertEquals($otherCompany, $row->company_id);
        Storage::disk('public')->assertMissing('product_categories/old.jpg');
        Storage::disk('public')->assertExists($row->product_categories_image);
    }

    public function test_update_keeping_its_own_name_is_not_a_duplicate(): void
    {
        $id = $this->productCategory('Fuel Filters');

        $this->actingAs($this->admin)->put("/product-category/{$id}", [
            'company_id' => $this->companyId,
            'product_categories_name' => 'Fuel Filters',
            'status' => 'active',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
    }

    public function test_admin_toggles_status(): void
    {
        $id = $this->productCategory('Fuel Filters');

        $this->actingAs($this->admin)->post("/product-category/{$id}/toggle-status")
            ->assertRedirect(route('product.category.records'));
        $this->assertSame('inactive', DB::table('company_product_categories')->where('id', $id)->value('status'));

        $this->actingAs($this->admin)->post("/product-category/{$id}/toggle-status");
        $this->assertSame('active', DB::table('company_product_categories')->where('id', $id)->value('status'));
    }

    public function test_delete_is_blocked_while_a_product_uses_it(): void
    {
        $id = $this->productCategory('Fuel Filters');
        $productId = DB::table('products')->insertGetId([
            'user_id' => $this->admin->id,
            'company_product_categories_id' => $id,
            'name' => 'FF5052 fuel filter',
            'price' => 20,
            'stock_quantity' => '5',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)->delete("/product-category/{$id}")
            ->assertRedirect(route('product.category.records'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('company_product_categories', ['id' => $id]);
        $this->assertDatabaseHas('products', ['id' => $productId]);
    }

    public function test_delete_removes_an_unused_category_and_its_image(): void
    {
        Storage::disk('public')->put('product_categories/ff.jpg', 'img');
        $id = $this->productCategory('Fuel Filters', null, 'product_categories/ff.jpg');

        $this->actingAs($this->admin)->delete("/product-category/{$id}")
            ->assertRedirect(route('product.category.records'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('company_product_categories', ['id' => $id]);
        Storage::disk('public')->assertMissing('product_categories/ff.jpg');
    }

    public function test_non_admin_is_redirected(): void
    {
        $vendor = $this->user(2);
        $id = $this->productCategory('Fuel Filters');

        $this->actingAs($vendor)->get('/product-category-records')->assertRedirect(route('admin.login'));
        $this->actingAs($vendor)->post('/product-category', [
            'company_id' => $this->companyId,
            'product_categories_name' => 'Sneaky',
            'status' => 'active',
        ])->assertRedirect(route('admin.login'));
        $this->actingAs($vendor)->delete("/product-category/{$id}")->assertRedirect(route('admin.login'));

        $this->assertDatabaseMissing('company_product_categories', ['product_categories_name' => 'Sneaky']);
        $this->assertDatabaseHas('company_product_categories', ['id' => $id]);
    }

    public function test_page_lists_categories_with_company_and_export_links(): void
    {
        $this->productCategory('Fuel Filters');

        $this->actingAs($this->admin)->get('/product-category-records')
            ->assertOk()
            ->assertSee('Product Categories')
            ->assertSee('Fuel Filters')
            ->assertSee('Cummins')
            ->assertSee('Power Generation')
            ->assertSee('Diesel Generators')
            ->assertSee(route('admin.export', ['list' => 'product-categories', 'format' => 'pdf']), false)
            ->assertSee(route('admin.export', ['list' => 'product-categories', 'format' => 'xlsx']), false);
    }

    public function test_empty_page_shows_empty_state(): void
    {
        $this->actingAs($this->admin)->get('/product-category-records')
            ->assertOk()
            ->assertSee('No product category records found.');
    }

    public function test_excel_export_downloads(): void
    {
        $this->productCategory('Fuel Filters');

        $this->actingAs($this->admin)->get('/export/product-categories.xlsx')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($this->admin)->get('/export/product-categories.pdf')->assertOk();
    }
}
