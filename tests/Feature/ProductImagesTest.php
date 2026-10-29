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

    public function test_a_new_product_needs_at_least_one_photo(): void
    {
        // Refused as a 422 naming the photo, not the 500 "Something went
        // wrong!" every add_product validation error used to come back as.
        $this->actingAs($this->vendor)
            ->post('/api/vendor/add_product', [
                'name' => 'Oil filter',
                'price' => 12.5,
                'modal_number' => 'OF-100',
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJson(['status' => false, 'message' => 'Add at least one product photo.']);

        $this->assertDatabaseMissing('products', ['modal_number' => 'OF-100']);
    }

    public function test_a_new_product_with_a_photo_is_saved_with_it(): void
    {
        $id = $this->actingAs($this->vendor)
            ->post('/api/vendor/add_product', [
                'name' => 'Oil filter',
                'price' => 12.5,
                'modal_number' => 'OF-100',
                'product_pic' => [UploadedFile::fake()->image('oil.jpg', 40, 40)],
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data.id');

        $this->assertSame(1, DB::table('product_images')->where('product_id', $id)->count());
    }

    public function test_the_last_photo_cannot_be_removed(): void
    {
        $this->update(['product_pic' => [UploadedFile::fake()->image('front.jpg', 40, 40)]]);
        [$only] = $this->images();

        $this->actingAs($this->vendor)
            ->post("/api/vendor/update_product/{$this->productId}", [
                'remove_images' => [$only],
                'price' => 99,
                'company_id' => $this->companyId,
                'company_product_categories_id' => $this->productCategoryId,
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJson(['status' => false]);

        $this->assertSame([$only], $this->images());
        Storage::assertExists('product_images/' . basename($only));
        $this->assertEquals(47, DB::table('products')->where('id', $this->productId)->value('price'),
            'a refused update changes nothing');
    }

    public function test_the_last_photo_can_be_swapped_for_a_new_one(): void
    {
        $this->update(['product_pic' => [UploadedFile::fake()->image('front.jpg', 40, 40)]]);
        [$old] = $this->images();

        $this->update([
            'remove_images' => [$old],
            'product_pic' => [UploadedFile::fake()->image('new.jpg', 60, 60)],
        ]);

        $this->assertCount(1, $this->images());
        $this->assertNotSame($old, $this->images()[0]);
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
