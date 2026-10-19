<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression tests for the S0 backend defects that needed no client decision:
 * BCR-35 (chat contacts leaked credentials), BCR-18 (vendor services were
 * readable and writable by any vendor), BCR-31 (deleting a product emptied
 * every order it was in) and BCR-40 (a route to a missing method).
 */
class S0SecurityFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([1 => 'admin', 2 => 'vendor', 3 => 'user'] as $id => $type) {
            DB::table('roles')->insertOrIgnore(['id' => $id, 'role_type' => $type]);
        }
    }

    private function user(int $roleId, array $extra = []): User
    {
        $user = User::create([
            'role_id' => $roleId,
            'first_name' => $roleId === 2 ? 'Vendor' : 'Test',
            'last_name' => 'Account',
            'business_name' => $roleId === 2 ? 'Brake Barn' : null,
            'email' => "role{$roleId}-" . uniqid() . '@example.com',
            'password' => Hash::make('secret-password'),
            'status' => 'active',
        ]);

        if ($extra) {
            $user->forceFill($extra)->save();
        }

        return $user;
    }

    private function product(User $vendor): int
    {
        return DB::table('products')->insertGetId([
            'user_id' => $vendor->id,
            'name' => 'Part ' . uniqid(),
            'price' => 20,
            'stock_quantity' => '5',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function message(User $from, User $to): void
    {
        DB::table('ch_messages')->insert([
            'id' => (string) Str::uuid(),
            'from_id' => $from->id,
            'to_id' => $to->id,
            'body' => 'Is this still available?',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function service(User $vendor, array $extra = []): int
    {
        return DB::table('services')->insertGetId(array_merge([
            'user_id' => $vendor->id,
            'title' => 'Brake fitting',
            'price' => 40,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ], $extra));
    }

    private function orderFor(int $productId, User $vendor, User $customer): int
    {
        $orderId = DB::table('orders')->insertGetId([
            'vendor_id' => $vendor->id,
            'user_id' => $customer->id,
            'order_number' => 'ORD-' . uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Shaped like CheckoutController's rows: order_items.vendor_id is left
        // null there (its foreign key points at vendor_type, not users).
        DB::table('order_items')->insert([
            'order_id' => $orderId,
            'product_id' => $productId,
            'quantity' => 3,
            'price' => 20,
            'total_price' => 60,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $orderId;
    }

    // ── BCR-35 ────────────────────────────────────────────────────────────

    public function test_chat_contacts_never_carry_credentials(): void
    {
        $customer = $this->user(3);
        $vendor = $this->user(2, [
            'otp' => '482913',
            'otp_expires_at' => now()->addMinutes(10),
            'remember_token' => 'remember-me-token',
            'forgot_password_token' => 'forgot-token',
            'reset_password_token' => 'reset-token',
        ]);
        $this->message($customer, $vendor);

        $response = $this->actingAs($customer, 'sanctum')->getJson('/api/getContacts?page=1')->assertOk();

        $contact = $response->json('contacts.0');
        $this->assertSame($vendor->id, $contact['id']);
        $this->assertSame('Vendor', $contact['first_name']);
        $this->assertSame('Brake Barn', $contact['business_name']);
        $this->assertSame(2, (int) $contact['role_id']);

        foreach (['password', 'otp', 'otp_expires_at', 'remember_token', 'forgot_password_token', 'reset_password_token', 'email', 'phone'] as $field) {
            $this->assertArrayNotHasKey($field, $contact, "contact leaked {$field}");
        }

        $body = $response->getContent();
        $this->assertStringNotContainsString('482913', $body);
        $this->assertStringNotContainsString('$2y$', $body);
        $this->assertSame(1, $response->json('total'));
        $this->assertSame(1, $response->json('last_page'));
    }

    public function test_blocked_contacts_are_still_filtered_out(): void
    {
        $customer = $this->user(3);
        $kept = $this->user(2);
        $blocked = $this->user(2);
        $this->message($customer, $kept);
        $this->message($blocked, $customer);
        UserBlock::create(['blocker_id' => $customer->id, 'blocked_id' => $blocked->id]);

        $ids = collect($this->actingAs($customer, 'sanctum')->getJson('/api/getContacts')
            ->assertOk()->json('contacts'))->pluck('id')->all();

        $this->assertSame([$kept->id], $ids);
    }

    // ── BCR-18 ────────────────────────────────────────────────────────────

    public function test_vendor_lists_only_their_own_services(): void
    {
        $mine = $this->user(2);
        $theirs = $this->user(2);
        $own = $this->service($mine, ['title' => 'Mine']);
        $this->service($theirs, ['title' => 'Theirs']);

        $ids = collect($this->actingAs($mine, 'sanctum')->getJson('/api/vendor/show_service')
            ->assertOk()->json('data'))->pluck('id')->all();

        $this->assertSame([$own], $ids);
    }

    public function test_vendor_cannot_read_update_or_delete_another_vendors_service(): void
    {
        $attacker = $this->user(2);
        $owner = $this->user(2);
        $id = $this->service($owner, ['title' => 'Original', 'price' => 40]);

        $this->actingAs($attacker, 'sanctum');
        $this->getJson("/api/vendor/show_service/{$id}")->assertStatus(400);
        $this->postJson("/api/vendor/update_service/{$id}", ['title' => 'Hijacked', 'price' => 1])->assertStatus(400);
        $this->deleteJson("/api/vendor/delete_service/{$id}")->assertStatus(400);

        $this->assertDatabaseHas('services', ['id' => $id, 'title' => 'Original', 'price' => 40]);
    }

    public function test_owner_still_manages_their_service(): void
    {
        $owner = $this->user(2);
        $id = $this->service($owner);

        $this->actingAs($owner, 'sanctum');
        $this->getJson("/api/vendor/show_service/{$id}")->assertOk()->assertJsonPath('data.id', $id);
        $this->postJson("/api/vendor/update_service/{$id}", ['title' => 'Renamed'])->assertOk();
        $this->assertDatabaseHas('services', ['id' => $id, 'title' => 'Renamed']);
        $this->deleteJson("/api/vendor/delete_service/{$id}")->assertOk();
        $this->assertDatabaseMissing('services', ['id' => $id]);
    }

    public function test_service_cannot_be_attached_to_another_vendors_product(): void
    {
        $vendor = $this->user(2);
        $foreignProduct = $this->product($this->user(2));
        $ownProduct = $this->product($vendor);

        $this->actingAs($vendor, 'sanctum');
        $this->postJson('/api/vendor/add_service', ['title' => 'Fitting', 'product_id' => $foreignProduct])
            ->assertStatus(400);
        $this->postJson('/api/vendor/add_service', ['title' => 'Fitting', 'product_id' => $ownProduct])
            ->assertStatus(201);

        $id = $this->service($vendor);
        $this->postJson("/api/vendor/update_service/{$id}", ['product_id' => $foreignProduct])->assertStatus(400);
        $this->assertDatabaseHas('services', ['id' => $id, 'product_id' => null]);
    }

    public function test_vendors_can_each_add_standalone_services(): void
    {
        // A service with no product used to collide with every other vendor's
        // product-less service ("This Product service already exists!").
        $this->actingAs($this->user(2), 'sanctum')
            ->postJson('/api/vendor/add_service', ['title' => 'Inspection'])->assertStatus(201);

        $second = $this->user(2);
        $this->app['auth']->forgetGuards();
        $this->actingAs($second, 'sanctum');
        $this->postJson('/api/vendor/add_service', ['title' => 'Fuel system service'])->assertStatus(201);
        $this->postJson('/api/vendor/add_service', ['title' => 'Annual check'])->assertStatus(201);

        $this->assertSame(2, \App\Models\Service::where('user_id', $second->id)->count());
    }

    // ── BCR-31 ────────────────────────────────────────────────────────────

    public function test_vendor_cannot_delete_a_product_that_has_orders(): void
    {
        $vendor = $this->user(2);
        $product = $this->product($vendor);
        $orderId = $this->orderFor($product, $vendor, $this->user(3));

        $this->actingAs($vendor, 'sanctum')->deleteJson("/api/vendor/delete_product/{$product}")
            ->assertStatus(409)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'This product has orders and cannot be deleted. Set it inactive instead.');

        $this->assertDatabaseHas('products', ['id' => $product]);
        $this->assertDatabaseHas('order_items', ['order_id' => $orderId, 'product_id' => $product]);
    }

    public function test_vendor_can_still_delete_an_unsold_product(): void
    {
        $vendor = $this->user(2);
        $product = $this->product($vendor);

        $this->actingAs($vendor, 'sanctum')->deleteJson("/api/vendor/delete_product/{$product}")->assertOk();

        $this->assertDatabaseMissing('products', ['id' => $product]);
    }

    public function test_admin_cannot_delete_a_product_that_has_orders(): void
    {
        $vendor = $this->user(2);
        $product = $this->product($vendor);
        $this->orderFor($product, $vendor, $this->user(3));

        $this->actingAs($this->user(1))->delete("/product/{$product}")
            ->assertRedirect(route('product.records'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('products', ['id' => $product]);
    }

    public function test_database_refuses_to_cascade_a_product_delete_into_orders(): void
    {
        $vendor = $this->user(2);
        $product = $this->product($vendor);
        $orderId = $this->orderFor($product, $vendor, $this->user(3));

        try {
            DB::table('products')->where('id', $product)->delete();
            $this->fail('Deleting a sold product should be refused by the order_items foreign key.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertStringContainsString('foreign key constraint', $e->getMessage());
        }

        $this->assertDatabaseHas('order_items', ['order_id' => $orderId, 'product_id' => $product]);
    }

    public function test_deleting_a_product_unlinks_its_service_instead_of_deleting_it(): void
    {
        $vendor = $this->user(2);
        $product = $this->product($vendor);
        $service = $this->service($vendor, ['product_id' => $product]);

        DB::table('products')->where('id', $product)->delete();

        $this->assertDatabaseHas('services', ['id' => $service, 'product_id' => null]);
    }

    // ── BCR-40 ────────────────────────────────────────────────────────────

    public function test_dead_product_home_screen_route_is_gone(): void
    {
        $this->actingAs($this->user(2), 'sanctum')
            ->getJson('/api/vendor/get_product_home_screen')
            ->assertNotFound();
    }
}
