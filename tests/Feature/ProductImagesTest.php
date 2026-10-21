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
 * Editing a listing's photos. The upload was taken as the complete set: adding
 * one photo deleted every photo the listing had, and a photo the vendor
 * removed in the app was never removed (device run 2026-10-20).
 */
class ProductImagesTest extends TestCase
{
    use RefreshDatabase;

    private User $vendor;

    private int $productId;

    private int $companyId;

    private int $productCategoryId;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();

        foreach ([1 => 'admin', 2 => 'vendor', 3 => 'user'] as $id => $type) {
            DB::table('roles')->insertOrIgnore(['id' => $id, 'role_type' => $type]);
        }

        $this->vendor = User::create([
            'role_id' => 2,
            'first_name' => 'Vendor',
            'email' => 'vendor-' . uniqid() . '@example.com',
            'password' => Hash::make('secret-password'),
            'status' => 'active',
            'business_status' => 'active',
        ]);

        $this->productId = DB::table('products')->insertGetId([
            'user_id' => $this->vendor->id,
            'name' => 'Fuel filter',
            'price' => 47,
            'stock_quantity' => '3',
            'status' => 'active',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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
    }

    private function update(array $fields): void
    {
        $this->actingAs($this->vendor)
            ->post("/api/vendor/update_product/{$this->productId}", $fields + [
                'company_id' => $this->companyId,
                'company_product_categories_id' => $this->productCategoryId,
            ], ['Accept' => 'application/json'])
            ->assertOk();
    }

    private function images(): array
    {
        return DB::table('product_images')->where('product_id', $this->productId)
            ->orderBy('id')->pluck('image_url')->all();
    }

    public function test_adding_a_photo_keeps_the_photos_already_there(): void
    {
        $this->update(['product_pic' => [UploadedFile::fake()->image('front.jpg', 40, 40)]]);
        $this->update(['product_pic' => [UploadedFile::fake()->image('side.jpg', 60, 60)]]);

        $this->assertCount(2, $this->images());
        foreach ($this->images() as $url) {
            Storage::assertExists('product_images/' . basename($url));
        }
    }

    public function test_only_the_photos_named_are_removed(): void
    {
        $this->update(['product_pic' => [
            UploadedFile::fake()->image('front.jpg', 40, 40),
            UploadedFile::fake()->image('side.jpg', 60, 60),
        ]]);
        [$front, $side] = $this->images();

        $this->update(['remove_images' => [$front]]);

        $this->assertSame([$side], $this->images());
        Storage::assertMissing('product_images/' . basename($front));
        Storage::assertExists('product_images/' . basename($side));
    }

    public function test_the_same_picture_twice_is_stored_once_and_still_loads(): void
    {
        $photo = UploadedFile::fake()->image('front.jpg', 40, 40);
        $copy = new UploadedFile($photo->getRealPath(), 'front-again.jpg', 'image/jpeg', null, true);

        $this->update(['product_pic' => [$photo]]);
        $this->update(['product_pic' => [$copy]]);

        $this->assertCount(1, $this->images());
        Storage::assertExists('product_images/' . basename($this->images()[0]));
    }

    public function test_a_photo_the_disk_refused_is_not_recorded(): void
    {
        // The disk is set not to throw, so a failed write came back as false
        // and the product kept a row for a file that was never written: a
        // broken photo for every customer (live run 2026-10-21, L14).
        $disk = \Mockery::mock(Storage::disk())->makePartial();
        $disk->shouldReceive('putFileAs')->andReturn(false);
        Storage::set(config('filesystems.default'), $disk);

        $this->update(['product_pic' => [UploadedFile::fake()->image('front.jpg', 40, 40)]]);

        $this->assertSame([], $this->images());
    }
}
