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
 * Admin, Products, Photos: the admin adds and removes a listing's photos,
 * stored exactly like a seller's own uploads and shown by the app.
 */
class AdminProductImagesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $vendor;

    private int $productId;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        foreach ([1 => 'admin', 2 => 'vendor', 3 => 'user'] as $id => $type) {
            DB::table('roles')->insertOrIgnore(['id' => $id, 'role_type' => $type]);
        }
        $make = fn (int $role, string $email) => User::create([
            'role_id' => $role, 'first_name' => 'Test', 'last_name' => 'User', 'email' => $email,
            'password' => Hash::make('secret-password'), 'status' => 'active', 'business_status' => 'active',
        ]);
        $this->admin = $make(1, 'admin-photos@example.com');
        $this->vendor = $make(2, 'seller-photos@example.com');

        $this->productId = DB::table('products')->insertGetId([
            'user_id' => $this->vendor->id, 'name' => 'CAT 3406 Piston Kit', 'sku' => 'CAT-3406-PK-DEMO',
            'price' => 189, 'stock_quantity' => 12, 'status' => 'active', 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_admin_uploads_photos_and_they_belong_to_the_seller(): void
    {
        $this->actingAs($this->admin)
            ->post("/product/{$this->productId}/images", [
                'photos' => [UploadedFile::fake()->image('a.png', 800, 600), UploadedFile::fake()->image('b.jpg', 800, 600)],
            ])
            ->assertRedirect('/product-records')
            ->assertSessionHas('success', '2 photos added to CAT 3406 Piston Kit.');

        $images = DB::table('product_images')->where('product_id', $this->productId)->get();
        $this->assertCount(2, $images);
        foreach ($images as $image) {
            $this->assertSame($this->vendor->id, (int) $image->user_id);
            $this->assertStringEndsWith('/storage/product_images/' . $image->name, $image->image_url);
            Storage::disk('public')->assertExists('product_images/' . $image->name);
        }

        // The app's product detail returns them (as the seller, not the
        // admin whose web session the requests above used).
        $this->app['auth']->forgetGuards();
        $token = $this->vendor->createToken('t')->plainTextToken;
        $this->withToken($token)->getJson("/api/vendor/show_product/{$this->productId}")
            ->assertOk()
            ->assertJsonCount(2, 'product.0.product_images');
    }

    public function test_products_page_shows_the_photo_and_the_manager(): void
    {
        $this->actingAs($this->admin)->post("/product/{$this->productId}/images", [
            'photos' => [UploadedFile::fake()->image('a.png')],
        ]);
        $name = DB::table('product_images')->value('name');

        $this->actingAs($this->admin)->get('/product-records')
            ->assertOk()
            ->assertSee('Photos: CAT 3406 Piston Kit')
            ->assertSee('product_images/' . $name)
            ->assertSee('Only photo: add another to replace it');
    }

    public function test_a_file_that_is_not_a_photo_is_refused(): void
    {
        $this->actingAs($this->admin)
            ->from('/product-records')
            ->post("/product/{$this->productId}/images", [
                'photos' => [UploadedFile::fake()->create('shell.php', 10, 'application/x-php')],
            ])
            ->assertRedirect('/product-records')
            ->assertSessionHasErrors('photos.0');

        $this->assertSame(0, DB::table('product_images')->count());
    }

    public function test_a_photo_is_removed_but_never_the_last_one(): void
    {
        // Different sizes, so different content and so different files.
        $this->actingAs($this->admin)->post("/product/{$this->productId}/images", [
            'photos' => [UploadedFile::fake()->image('a.png', 100, 100), UploadedFile::fake()->image('b.png', 200, 200)],
        ]);
        [$first, $second] = DB::table('product_images')->orderBy('id')->get()->all();

        $this->actingAs($this->admin)->delete("/product/{$this->productId}/images/{$first->id}")
            ->assertSessionHas('success');
        Storage::disk('public')->assertMissing('product_images/' . $first->name);

        $this->actingAs($this->admin)->delete("/product/{$this->productId}/images/{$second->id}")
            ->assertSessionHas('error', 'A product needs at least one photo. Add another before removing this one.');
        $this->assertSame(1, DB::table('product_images')->count());
        Storage::disk('public')->assertExists('product_images/' . $second->name);
    }

    public function test_only_the_admin_can_change_photos(): void
    {
        $this->actingAs($this->vendor)->post("/product/{$this->productId}/images", [
            'photos' => [UploadedFile::fake()->image('a.png')],
        ])->assertRedirect();

        $this->post("/product/{$this->productId}/images", [
            'photos' => [UploadedFile::fake()->image('a.png')],
        ])->assertRedirect();

        $this->assertSame(0, DB::table('product_images')->count());
    }

    public function test_a_photo_of_another_product_cannot_be_removed_through_this_one(): void
    {
        $other = DB::table('products')->insertGetId([
            'user_id' => $this->vendor->id, 'name' => 'Other', 'sku' => 'OTHER-1', 'price' => 1,
            'stock_quantity' => 1, 'status' => 'active', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($this->admin)->post("/product/{$other}/images", [
            'photos' => [UploadedFile::fake()->image('a.png'), UploadedFile::fake()->image('b.png')],
        ]);
        $foreign = DB::table('product_images')->where('product_id', $other)->value('id');

        $this->actingAs($this->admin)->delete("/product/{$this->productId}/images/{$foreign}")->assertNotFound();
        $this->assertSame(2, DB::table('product_images')->where('product_id', $other)->count());
    }
}
