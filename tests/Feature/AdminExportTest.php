<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AdminExports;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Admin downloads: the order invoice PDF and the PDF / Excel export of every
 * admin list.
 */
class AdminExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([1 => 'admin', 2 => 'vendor', 3 => 'user'] as $id => $type) {
            DB::table('roles')->insertOrIgnore(['id' => $id, 'role_type' => $type]);
        }
    }

    private function user(int $roleId): User
    {
        return User::create([
            'role_id' => $roleId,
            'first_name' => ['', 'Site', 'Brake', 'Dana'][$roleId],
            'last_name' => ['', 'Admin', 'Barn', 'Cole'][$roleId],
            'email' => "role{$roleId}-" . uniqid() . '@example.com',
            'password' => Hash::make('secret-password'),
            'status' => 'active',
        ]);
    }

    /**
     * One order of 3 x 20.00 with shipping 5, discount 2 and tax 4.50,
     * so the grand total is 67.50.
     */
    private function order(User $vendor, User $customer): int
    {
        $productId = DB::table('products')->insertGetId([
            'user_id' => $vendor->id,
            'name' => 'Front brake pads',
            'price' => 20,
            'stock_quantity' => '5',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = DB::table('orders')->insertGetId([
            'vendor_id' => $vendor->id,
            'user_id' => $customer->id,
            'order_number' => 'ORD-TEST-0001',
            'shipping_charges' => 5,
            'discount' => 2,
            'tax' => 4.5,
            'order_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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

    private function review(User $customer, string $status): void
    {
        DB::table('reviews')->insert([
            'user_id' => $customer->id,
            'rating' => 4,
            'review_text' => "A {$status} review",
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ── Invoice ───────────────────────────────────────────────────────────

    public function test_admin_downloads_an_order_invoice_pdf(): void
    {
        $orderId = $this->order($this->user(2), $this->user(3));

        $response = $this->actingAs($this->user(1))->get("/order/{$orderId}/invoice");

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('invoice-ord-test-0001.pdf', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_invoice_is_admin_only_and_404s_for_a_missing_order(): void
    {
        $vendor = $this->user(2);
        $customer = $this->user(3);
        $orderId = $this->order($vendor, $customer);

        $this->get("/order/{$orderId}/invoice")->assertRedirect(route('admin.login'));
        $this->actingAs($vendor)->get("/order/{$orderId}/invoice")->assertRedirect();
        $this->actingAs($customer)->get("/order/{$orderId}/invoice")->assertRedirect();
        $this->actingAs($this->user(1))->get('/order/999999/invoice')->assertNotFound();
    }

    public function test_order_page_total_includes_tax_and_links_the_invoice(): void
    {
        $orderId = $this->order($this->user(2), $this->user(3));

        $this->actingAs($this->user(1))->get("/order/{$orderId}")
            ->assertOk()
            ->assertSee(route('order.invoice', $orderId), false)
            ->assertSee('Tax')
            ->assertSee('$67.50');
    }

    // ── List exports ──────────────────────────────────────────────────────

    public function test_every_list_downloads_as_pdf_and_excel(): void
    {
        $vendor = $this->user(2);
        $customer = $this->user(3);
        $this->order($vendor, $customer);
        $this->review($customer, 'approved');
        $admin = $this->user(1);

        foreach (AdminExports::lists() as $list) {
            $pdf = $this->actingAs($admin)->get("/export/{$list}.pdf");
            $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
            $this->assertStringStartsWith('%PDF', $pdf->getContent(), "{$list}.pdf");

            $xlsx = $this->actingAs($admin)->get("/export/{$list}.xlsx");
            $xlsx->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $this->assertStringStartsWith('PK', file_get_contents($xlsx->baseResponse->getFile()->getPathname()), "{$list}.xlsx");
        }
    }

    public function test_orders_export_carries_customer_vendor_and_grand_total(): void
    {
        $this->order($this->user(2), $this->user(3));

        $export = AdminExports::build('orders', Request::create('/export/orders.xlsx'));

        $row = array_combine($export['headings'], $export['rows'][0]);
        $this->assertSame('ORD-TEST-0001', $row['Order #']);
        $this->assertSame('Dana Cole', $row['Customer']);
        $this->assertSame('Brake Barn', $row['Vendor']);
        $this->assertSame(60.0, $row['Subtotal ($)']);
        $this->assertSame(67.5, $row['Grand total ($)']);
    }

    public function test_export_applies_the_page_status_filter(): void
    {
        $customer = $this->user(3);
        $this->review($customer, 'approved');
        $this->review($customer, 'pending');

        $all = AdminExports::build('reviews', Request::create('/export/reviews.pdf'));
        $approved = AdminExports::build('reviews', Request::create('/export/reviews.pdf', 'GET', ['status' => 'approved']));

        $this->assertCount(2, $all['rows']);
        $this->assertCount(1, $approved['rows']);
        $this->assertSame('Status: Approved', $approved['filter']);
    }

    public function test_exports_are_admin_only(): void
    {
        $this->get('/export/users.xlsx')->assertRedirect(route('admin.login'));
        $this->actingAs($this->user(2))->get('/export/users.xlsx')->assertRedirect();
    }

    public function test_unknown_list_or_format_is_404(): void
    {
        $admin = $this->user(1);

        $this->actingAs($admin)->get('/export/passwords.xlsx')->assertNotFound();
        $this->actingAs($admin)->get('/export/users.csv')->assertNotFound();
    }

    public function test_list_pages_show_the_download_buttons(): void
    {
        $admin = $this->user(1);

        foreach (['/vendor-records' => 'vendors', '/earning-records' => 'orders', '/reviews' => 'reviews', '/boost-requests' => 'boost-requests'] as $page => $list) {
            $this->actingAs($admin)->get($page)->assertOk()
                ->assertSee(route('admin.export', ['list' => $list, 'format' => 'pdf']), false)
                ->assertSee(route('admin.export', ['list' => $list, 'format' => 'xlsx']), false);
        }
    }
}
