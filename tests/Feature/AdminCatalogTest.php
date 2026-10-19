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
 * Creating catalogue entries from the admin panel. Category and company
 * creates were a 500 on every attempt: `user_id` is a required foreign key on
 * both tables and the controllers never set it (device run 2026-10-20).
 */
class AdminCatalogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([1 => 'admin', 2 => 'vendor', 3 => 'user'] as $id => $type) {
            DB::table('roles')->insertOrIgnore(['id' => $id, 'role_type' => $type]);
        }

        Storage::fake('public');

        $this->admin = User::create([
            'role_id' => 1,
            'first_name' => 'Site',
            'last_name' => 'Admin',
            'email' => 'admin-' . uniqid() . '@example.com',
            'password' => Hash::make('secret-password'),
            'status' => 'active',
        ]);
    }

    public function test_admin_creates_a_category_with_an_image(): void
    {
        $this->actingAs($this->admin)->post('/category', [
            'name' => 'Filters',
            'description' => 'Fuel, oil and air filters',
            'category_image' => UploadedFile::fake()->image('filters.jpg'),
        ])->assertRedirect(route('category.records'))->assertSessionHas('success');

        $this->assertDatabaseHas('categories', ['name' => 'Filters', 'user_id' => $this->admin->id]);
    }

    public function test_admin_creates_a_sub_category_and_a_company_under_it(): void
    {
        $categoryId = DB::table('categories')->insertGetId(['user_id' => $this->admin->id, 'name' => 'Power Generation']);

        $this->actingAs($this->admin)->post('/sub-category', [
            'category_id' => $categoryId,
            'sub_category_name' => 'Diesel Generators',
            'status' => 'active',
        ])->assertRedirect()->assertSessionHas('success');

        $subCategoryId = DB::table('sub_categories')->where('sub_category_name', 'Diesel Generators')->value('id');

        $this->actingAs($this->admin)->post('/company', [
            'company_name' => 'Cummins',
            'category_id' => $categoryId,
            'sub_category_id' => $subCategoryId,
            'status' => 'active',
        ])->assertRedirect(route('company.records'))->assertSessionHas('success');

        $this->assertDatabaseHas('companies', ['company_name' => 'Cummins', 'user_id' => $this->admin->id]);
    }

    public function test_a_company_cannot_be_filed_under_another_categorys_sub_category(): void
    {
        // The form offered every sub-category whatever the category, and the
        // server only checked that the sub-category existed.
        $power = DB::table('categories')->insertGetId(['user_id' => $this->admin->id, 'name' => 'Power Generation']);
        $hydraulics = DB::table('categories')->insertGetId(['user_id' => $this->admin->id, 'name' => 'Hydraulics']);
        $pumps = DB::table('sub_categories')->insertGetId(['category_id' => $hydraulics, 'sub_category_name' => 'Pumps', 'status' => 'active']);

        $this->actingAs($this->admin)->post('/company', [
            'company_name' => 'Cummins',
            'category_id' => $power,
            'sub_category_id' => $pumps,
            'status' => 'active',
        ])->assertSessionHasErrors('sub_category_id');

        $this->assertDatabaseMissing('companies', ['company_name' => 'Cummins']);
    }

    public function test_product_images_are_rebased_onto_this_server(): void
    {
        // Uploads bake the uploading host into image_url; the order page
        // prefixed it with /storage/ again and every new image 404'd.
        $url = fn (string $stored) => (new \App\Models\ProductImage(['image_url' => $stored]))->url();

        $this->assertSame(asset('storage/product_images/a.png'), $url('http://10.0.2.2:877/storage/product_images/a.png'));
        $this->assertSame(asset('storage/product_images/b.jpg'), $url('product_images/b.jpg'));
        $this->assertSame(asset('storage/product_images/c.jpg'), $url('storage/product_images/c.jpg'));
        $this->assertNull($url(''));
    }
}
