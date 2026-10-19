<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;

/**
 * Emails are a courtesy, never a precondition. With the mail server refusing
 * logins (as the local SMTP account did on 2026-10-20), a vendor could not
 * move an order on, the status change was rolled back, and a customer could
 * not save an item: each request sent mail inline and failed with it.
 */
class MailOutageTest extends TestCase
{
    use RefreshDatabase;

    private User $vendor;

    private User $customer;

    private int $productId;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::extend('down', fn () => new class extends AbstractTransport
        {
            protected function doSend(SentMessage $message): void
            {
                throw new TransportException('535 Username and Password not accepted');
            }

            public function __toString(): string
            {
                return 'down://';
            }
        });
        config(['mail.mailers.down' => ['transport' => 'down'], 'mail.default' => 'down']);

        foreach ([1 => 'admin', 2 => 'vendor', 3 => 'user'] as $id => $type) {
            DB::table('roles')->insertOrIgnore(['id' => $id, 'role_type' => $type]);
        }

        $this->vendor = $this->user(2);
        $this->customer = $this->user(3);

        $this->productId = DB::table('products')->insertGetId([
            'user_id' => $this->vendor->id,
            'name' => 'Fuel filter',
            'price' => 47,
            'stock_quantity' => '8',
            'status' => 'active',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function user(int $roleId): User
    {
        return User::create([
            'role_id' => $roleId,
            'first_name' => $roleId === 2 ? 'Brake' : 'Dana',
            'last_name' => $roleId === 2 ? 'Barn' : 'Cole',
            'email' => "role{$roleId}-" . uniqid() . '@example.com',
            'password' => Hash::make('secret-password'),
            'status' => 'active',
            'business_status' => 'active',
        ]);
    }

    public function test_vendor_moves_an_order_on_while_mail_is_down_and_the_customer_is_told_in_app(): void
    {
        $orderId = DB::table('orders')->insertGetId([
            'user_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'order_number' => 'ORD-OUTAGE-1',
            'order_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->vendor)
            ->postJson('/api/vendor/order-manage', ['order_id' => $orderId, 'order_status' => 'accept'])
            ->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'order_status' => 'accept']);
        $this->assertDatabaseHas('notifications', ['user_id' => $this->customer->id, 'order_id' => $orderId]);
    }

    public function test_customer_saves_an_item_while_mail_is_down(): void
    {
        $this->actingAs($this->customer)
            ->postJson('/api/user/store-wishlist', ['type' => 'product', 'product_id' => $this->productId])
            ->assertCreated()
            ->assertJsonMissingPath('error');

        $this->assertDatabaseHas('wishlists', ['user_id' => $this->customer->id, 'product_id' => $this->productId]);
    }

    public function test_vendor_restocks_while_mail_is_down(): void
    {
        DB::table('stocks')->insert([
            'product_id' => $this->productId,
            'current_stock' => 0,
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

        $this->actingAs($this->vendor)
            ->postJson('/api/vendor/update-stock', ['product_id' => $this->productId, 'new_stock' => 5])
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('wishlists', ['user_id' => $this->customer->id, 'status' => 'active']);
    }
}
