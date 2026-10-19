<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The buyer's "Find a part" filter. Each company keeps its own copy of a
 * product category, so step one read "Cylinders, Cylinders" and choosing one
 * reached only that copy's company; a catalogue link with no company came back
 * as a null entry in step two (device run 2026-10-20).
 */
class ProductFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private array $cylinders = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([1 => 'admin', 2 => 'vendor', 3 => 'user'] as $id => $type) {
            DB::table('roles')->insertOrIgnore(['id' => $id, 'role_type' => $type]);
        }

        $this->customer = User::create([
            'role_id' => 3,
            'first_name' => 'Buyer',
            'email' => 'buyer-' . uniqid() . '@example.com',
            'password' => Hash::make('secret-password'),
            'status' => 'active',
        ]);

        $categoryId = DB::table('categories')->insertGetId(['user_id' => $this->customer->id, 'name' => 'Power Generation']);
        $subCategoryId = DB::table('sub_categories')->insertGetId(['category_id' => $categoryId, 'sub_category_name' => 'Diesel', 'status' => 'active']);

        foreach (['Caterpillar', 'Cummins'] as $name) {
            $companyId = DB::table('companies')->insertGetId([
                'user_id' => $this->customer->id,
                'category_id' => $categoryId,
                'sub_category_id' => $subCategoryId,
                'company_name' => $name,
                'status' => 'active',
            ]);
            $productCategoryId = DB::table('company_product_categories')->insertGetId([
                'user_id' => $this->customer->id,
                'category_id' => $categoryId,
                'sub_category_id' => $subCategoryId,
                'company_id' => $companyId,
                'product_categories_name' => 'Cylinders',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('company_product_category_links')->insert([
                'company_id' => $companyId,
                'com_pro_cat_id' => $productCategoryId,
                'modal_number' => "CYL-{$name}",
                'price_type' => 'standard',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->cylinders[] = $productCategoryId;
        }

        // A link left behind with no company.
        DB::table('company_product_category_links')->insert([
            'com_pro_cat_id' => $this->cylinders[0],
            'price_type' => 'standard',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_one_category_name_is_one_choice_carrying_every_id(): void
    {
        $data = $this->actingAs($this->customer)
            ->getJson('/api/user/get-all-companies-products-categories')
            ->assertOk()->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('Cylinders', $data[0]['product_categories_name']);
        $this->assertSame($this->cylinders, $data[0]['ids']);
    }

    public function test_every_company_behind_the_name_is_offered_and_no_nulls(): void
    {
        $names = collect($this->actingAs($this->customer)
            ->getJson('/api/user/get-all-related-companies-products-categories?com_pro_cat_id=' . implode(',', $this->cylinders))
            ->assertOk()->json('data'))->pluck('company_name')->sort()->values()->all();

        $this->assertSame(['Caterpillar', 'Cummins'], $names);
    }

    public function test_a_single_id_still_works_for_older_apps(): void
    {
        $data = $this->actingAs($this->customer)
            ->getJson("/api/user/get-all-related-companies-products-categories?com_pro_cat_id={$this->cylinders[0]}")
            ->assertOk()->json('data');

        $this->assertSame(['Caterpillar'], array_column($data, 'company_name'));
    }
}
