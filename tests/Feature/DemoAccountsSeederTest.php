<?php

namespace Tests\Feature;

use Database\Seeders\DemoAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The client's demo logins: one per role, known passwords, and a catalogue
 * that a customer can actually buy from (photo, stock ledger, brand link).
 */
class DemoAccountsSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        foreach ([1 => 'admin', 2 => 'vendor', 3 => 'user'] as $id => $type) {
            DB::table('roles')->insertOrIgnore(['id' => $id, 'role_type' => $type]);
        }

        $owner = DB::table('users')->insertGetId([
            'role_id' => 1, 'email' => 'owner@example.com', 'password' => Hash::make('x'),
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $category = DB::table('categories')->insertGetId(['user_id' => $owner, 'name' => 'Power Generation']);
        $sub = DB::table('sub_categories')->insertGetId([
            'category_id' => $category, 'sub_category_name' => 'Diesel and Gas Generators', 'status' => 'active',
        ]);
        $catalogue = ['Caterpillar' => ['Cylinders', 'Pistons'], 'Cummins' => ['Cylinders', 'Pistons'], 'Kohler' => ['Camshafts', 'Valves']];
        foreach ($catalogue as $brand => $productCategories) {
            $company = DB::table('companies')->insertGetId([
                'user_id' => $owner, 'category_id' => $category, 'sub_category_id' => $sub,
                'company_name' => $brand, 'status' => 'active',
            ]);
            foreach ($productCategories as $name) {
                DB::table('company_product_categories')->insert([
                    'user_id' => $owner, 'category_id' => $category, 'sub_category_id' => $sub,
                    'company_id' => $company, 'product_categories_name' => $name, 'status' => 'active',
                ]);
            }
        }
    }

    public function test_every_role_signs_in_with_its_demo_password(): void
    {
        $this->seed(DemoAccountsSeeder::class);

        foreach (['vendor01@example.com', 'vendor02@example.com'] as $email) {
            $this->postJson('/api/signin', ['email' => $email, 'password' => 'Vendor12345'])
                ->assertOk()->assertJsonPath('user.role_id', 2);
        }
        foreach (['customer01@example.com', 'customer02@example.com'] as $email) {
            $this->postJson('/api/signin', ['email' => $email, 'password' => 'Customer12345'])
                ->assertOk()->assertJsonPath('user.role_id', 3);
        }

        $admin = DB::table('users')->where('email', 'admin@gmail.com')->first();
        $this->assertSame(1, (int) $admin->role_id);
        $this->assertTrue(Hash::check('Admin12345', $admin->password));
        $this->post('/login', ['email' => 'admin@gmail.com', 'password' => 'Admin12345'])
            ->assertRedirect();
        $this->assertAuthenticated();
    }

    public function test_each_seller_has_products_in_stock_with_a_photo_file_and_a_service(): void
    {
        $this->seed(DemoAccountsSeeder::class);

        foreach (['vendor01@example.com', 'vendor02@example.com'] as $email) {
            $vendor = DB::table('users')->where('email', $email)->first();
            $this->assertSame('active', $vendor->business_status, 'sellers are approved');

            $products = DB::table('products')->where('user_id', $vendor->id)->get();
            $this->assertCount(5, $products);
            foreach ($products as $product) {
                $this->assertArrayHasKey($product->modal_number, DemoAccountsSeeder::photoFiles());
                $this->assertFileExists(database_path('seeders/demo-images/' . DemoAccountsSeeder::photoFiles()[$product->modal_number]));

                $ledger = DB::table('stocks')->where('product_id', $product->id)->value('current_stock');
                $this->assertSame((int) $product->stock_quantity, (int) $ledger, "{$product->name} stock ledger matches");
                $this->assertTrue(DB::table('company_product_category_links')->where('product_id', $product->id)->exists());
            }
            $this->assertSame(1, DB::table('services')->where('user_id', $vendor->id)->count());
        }
    }

    public function test_a_customer_can_add_a_demo_product_to_the_cart_as_in_stock(): void
    {
        $this->seed(DemoAccountsSeeder::class);
        $token = $this->postJson('/api/signin', ['email' => 'customer01@example.com', 'password' => 'Customer12345'])
            ->json('token');
        $product = DB::table('products')->where('modal_number', 'CAT-3406-PK')->first();

        $this->withToken($token)->postJson('/api/user/add-to-cart', ['product_id' => $product->id, 'quantity' => 2])
            ->assertOk();
        $this->withToken($token)->getJson('/api/user/cart-products')
            ->assertOk()
            ->assertJsonPath('data.0.stock.is_in_stock', true);
    }

    public function test_running_it_again_duplicates_nothing_and_keeps_sold_stock(): void
    {
        $this->seed(DemoAccountsSeeder::class);
        $product = DB::table('products')->where('modal_number', 'CAT-3406-PK')->first();
        DB::table('products')->where('id', $product->id)->update(['stock_quantity' => 9]);
        DB::table('users')->where('email', 'vendor01@example.com')->update(['password' => Hash::make('changed')]);

        $this->seed(DemoAccountsSeeder::class);

        $this->assertSame(4, DB::table('users')->whereIn('email', [
            'vendor01@example.com', 'vendor02@example.com', 'customer01@example.com', 'customer02@example.com',
        ])->count());
        $this->assertSame(1, DB::table('users')->where('email', 'admin@gmail.com')->count());
        $this->assertSame(10, DB::table('products')->count());
        $this->assertSame(0, DB::table('product_images')->count(), 'photos are added in the admin panel');
        $this->assertSame(10, DB::table('stocks')->count());
        $this->assertSame(2, DB::table('services')->count());
        $this->assertSame(9, (int) DB::table('products')->where('id', $product->id)->value('stock_quantity'));
        $this->postJson('/api/signin', ['email' => 'vendor01@example.com', 'password' => 'Vendor12345'])->assertOk();
    }
}
