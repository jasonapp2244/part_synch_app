<?php

namespace App\Support;

use App\Models\BoostPackage;
use App\Models\Category;
use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Report;
use App\Models\Review;
use App\Models\Setting;
use App\Models\SubCategory;
use App\Models\User;
use App\Models\VendorBoost;
use Illuminate\Http\Request;

/**
 * The admin list pages as downloadable tables (PDF and Excel).
 *
 * Each list returns the same rows, with the same filters, as its page in the
 * panel, minus images and action buttons. Money columns stay numeric so Excel
 * can sum them; the PDF view formats them.
 */
class AdminExports
{
    /** URL key => builder method. */
    private const LISTS = [
        'vendors' => 'vendors',
        'users' => 'users',
        'featured' => 'featured',
        'categories' => 'categories',
        'sub-categories' => 'subCategories',
        'companies' => 'companies',
        'product-categories' => 'productCategories',
        'products' => 'products',
        'orders' => 'orders',
        'reports' => 'reports',
        'reviews' => 'reviews',
        'boost-packages' => 'boostPackages',
        'boost-requests' => 'boostRequests',
    ];

    public static function lists(): array
    {
        return array_keys(self::LISTS);
    }

    /**
     * @return array{title: string, filter: ?string, headings: array<int, string>, rows: array<int, array<int, mixed>>}|null
     *         null when the list does not exist
     */
    public static function build(string $list, Request $request): ?array
    {
        $method = self::LISTS[$list] ?? null;
        if (! $method) {
            return null;
        }

        $definition = (new self)->{$method}($request);

        return [
            'title' => $definition['title'],
            'filter' => $definition['filter'] ?? null,
            'headings' => array_keys($definition['columns']),
            'rows' => $definition['records']->values()->map(
                fn ($record, $index) => array_map(
                    fn ($column) => $column($record, $index),
                    array_values($definition['columns'])
                )
            )->all(),
        ];
    }

    private function vendors(Request $request): array
    {
        return [
            'title' => 'Vendors',
            'records' => User::where('role_id', 2)->get(),
            'columns' => [
                'S.no' => fn ($u, $i) => $i + 1,
                'ID' => fn ($u) => $u->id,
                'Name' => fn ($u) => self::name($u),
                'Business name' => fn ($u) => $u->business_name,
                'Email' => fn ($u) => $u->email,
                'Business type' => fn ($u) => $u->business_type,
                'Phone' => fn ($u) => $u->phone_number,
                'Approval' => fn ($u) => $u->business_status === 'active' ? 'Approved' : 'Awaiting approval',
                'Status' => fn ($u) => ucfirst($u->status ?? 'inactive'),
                'Joined' => fn ($u) => self::date($u->created_at),
            ],
        ];
    }

    private function users(Request $request): array
    {
        return [
            'title' => 'Users',
            'records' => User::where('role_id', 3)->get(),
            'columns' => [
                'S.no' => fn ($u, $i) => $i + 1,
                'ID' => fn ($u) => $u->id,
                'Name' => fn ($u) => self::name($u),
                'Email' => fn ($u) => $u->email,
                'Phone' => fn ($u) => $u->phone_number,
                'Status' => fn ($u) => ucfirst($u->status ?? 'inactive'),
                'Joined' => fn ($u) => self::date($u->created_at),
            ],
        ];
    }

    private function featured(Request $request): array
    {
        return [
            'title' => 'Featured products',
            'records' => Product::where('is_top', 1)->with('user')->get(),
            'columns' => [
                'S.no' => fn ($p, $i) => $i + 1,
                'ID' => fn ($p) => $p->id,
                'Product' => fn ($p) => $p->name,
                'Vendor' => fn ($p) => self::name($p->user),
                self::money('Price') => fn ($p) => (float) ($p->price ?? 0),
                'Stock' => fn ($p) => (int) ($p->stock_quantity ?? 0),
            ],
        ];
    }

    private function categories(Request $request): array
    {
        return [
            'title' => 'Categories',
            'records' => Category::withCount('subCategories')->get(),
            'columns' => [
                'S.no' => fn ($c, $i) => $i + 1,
                'ID' => fn ($c) => $c->id,
                'Name' => fn ($c) => $c->name,
                'Description' => fn ($c) => $c->description,
                'Sub categories' => fn ($c) => (int) $c->sub_categories_count,
                'Created' => fn ($c) => self::date($c->created_at),
            ],
        ];
    }

    private function subCategories(Request $request): array
    {
        return [
            'title' => 'Sub categories',
            'records' => SubCategory::with('category')->get(),
            'columns' => [
                'S.no' => fn ($s, $i) => $i + 1,
                'ID' => fn ($s) => $s->id,
                'Name' => fn ($s) => $s->sub_category_name,
                'Parent category' => fn ($s) => $s->category?->name,
                'Status' => fn ($s) => ucfirst($s->status ?? 'inactive'),
                'Created' => fn ($s) => self::date($s->created_at),
            ],
        ];
    }

    private function companies(Request $request): array
    {
        return [
            'title' => 'Companies',
            'records' => Company::with('user')->get(),
            'columns' => [
                'S.no' => fn ($c, $i) => $i + 1,
                'ID' => fn ($c) => $c->id,
                'Company' => fn ($c) => $c->company_name,
                'Vendor' => fn ($c) => self::name($c->user),
                'Description' => fn ($c) => $c->description,
                'Status' => fn ($c) => ucfirst($c->status ?? 'inactive'),
            ],
        ];
    }

    private function productCategories(Request $request): array
    {
        $records = ProductCategory::with('company')->latest('id')->get();

        // ProductCategory has no category relations; look the names up once.
        $categories = Category::whereIn('id', $records->pluck('category_id')->unique())->pluck('name', 'id');
        $subCategories = SubCategory::whereIn('id', $records->pluck('sub_category_id')->unique())->pluck('sub_category_name', 'id');

        return [
            'title' => 'Product categories',
            'records' => $records,
            'columns' => [
                'S.no' => fn ($c, $i) => $i + 1,
                'ID' => fn ($c) => $c->id,
                'Name' => fn ($c) => $c->product_categories_name,
                'Company' => fn ($c) => $c->company?->company_name,
                'Category' => fn ($c) => $categories->get($c->category_id),
                'Sub category' => fn ($c) => $subCategories->get($c->sub_category_id),
                'Status' => fn ($c) => ucfirst($c->status ?? 'inactive'),
                'Created' => fn ($c) => self::date($c->created_at),
            ],
        ];
    }

    private function products(Request $request): array
    {
        return [
            'title' => 'Products',
            'records' => Product::with('user')->get(),
            'columns' => [
                'S.no' => fn ($p, $i) => $i + 1,
                'ID' => fn ($p) => $p->id,
                'Product' => fn ($p) => $p->name,
                self::money('Price') => fn ($p) => (float) ($p->price ?? 0),
                'Stock' => fn ($p) => (int) ($p->stock_quantity ?? 0),
                'Vendor' => fn ($p) => self::name($p->user),
                'Featured' => fn ($p) => $p->is_top ? 'Yes' : 'No',
                'Status' => fn ($p) => ucfirst($p->status ?? 'inactive'),
                'Created' => fn ($p) => self::date($p->created_at),
            ],
        ];
    }

    private function orders(Request $request): array
    {
        $orders = Order::with('orderItems')->latest()->get();

        // Order has no customer/vendor relations; look the people up once.
        $people = User::whereIn('id', $orders->pluck('user_id')->merge($orders->pluck('vendor_id'))->filter()->unique())
            ->get(['id', 'first_name', 'last_name', 'business_name', 'email'])
            ->keyBy('id');

        return [
            'title' => 'Orders',
            'records' => $orders,
            'columns' => [
                'S.no' => fn ($o, $i) => $i + 1,
                'Order #' => fn ($o) => $o->order_number,
                'Customer' => fn ($o) => self::name($people->get($o->user_id)),
                'Vendor' => fn ($o) => self::name($people->get($o->vendor_id)),
                'Items' => fn ($o) => $o->orderItems->count(),
                self::money('Subtotal') => fn ($o) => round(OrderTotals::of($o)['subtotal'], 2),
                self::money('Grand total') => fn ($o) => round(OrderTotals::of($o)['grand_total'], 2),
                'Payment method' => fn ($o) => ucfirst($o->payment_method ?? 'N/A'),
                'Status' => fn ($o) => ucfirst(str_replace('_', ' ', (string) $o->order_status)),
                'Date' => fn ($o) => self::date($o->created_at),
            ],
        ];
    }

    private function reports(Request $request): array
    {
        $status = $request->query('status');

        return [
            'title' => 'Reported content',
            'filter' => $status ? 'Status: ' . ucfirst($status) : null,
            'records' => Report::with('reporter:id,first_name,last_name,email')
                ->when($status, fn ($q) => $q->where('status', $status))
                ->latest()
                ->get(),
            'columns' => [
                'S.no' => fn ($r, $i) => $i + 1,
                'Type' => fn ($r) => ucfirst($r->reportable_type),
                'Reported item' => fn ($r) => $r->subjectLabel() . ' (#' . $r->reportable_id . ')',
                'Reason' => fn ($r) => ucwords(str_replace('_', ' ', $r->reason)),
                'Details' => fn ($r) => $r->details,
                'Reported by' => fn ($r) => $r->reporter ? self::name($r->reporter) : 'Deleted user',
                'When' => fn ($r) => self::date($r->created_at),
                'Status' => fn ($r) => ucfirst($r->status),
            ],
        ];
    }

    private function reviews(Request $request): array
    {
        $status = $request->query('status');

        return [
            'title' => 'Product reviews',
            'filter' => $status ? 'Status: ' . ucfirst($status) : null,
            'records' => Review::with([
                    'user:id,first_name,last_name,email',
                    'product:id,name',
                ])
                ->when($status, fn ($q) => $q->where('status', $status))
                ->latest()
                ->get(),
            'columns' => [
                'S.no' => fn ($r, $i) => $i + 1,
                'Product' => fn ($r) => $r->product->name ?? 'Deleted product',
                'Customer' => fn ($r) => $r->user ? self::name($r->user) : 'Deleted user',
                'Rating' => fn ($r) => (int) $r->rating,
                'Title' => fn ($r) => $r->title,
                'Review' => fn ($r) => $r->review_text,
                'Verified purchase' => fn ($r) => $r->verified ? 'Yes' : 'No',
                'Status' => fn ($r) => ucfirst($r->status),
                'Date' => fn ($r) => self::date($r->created_at),
            ],
        ];
    }

    private function boostPackages(Request $request): array
    {
        return [
            'title' => 'Boost packages',
            'records' => BoostPackage::all(),
            'columns' => [
                'S.no' => fn ($p, $i) => $i + 1,
                'Name' => fn ($p) => $p->name,
                'Price' => fn ($p) => (float) $p->price,
                'Currency' => fn ($p) => strtoupper((string) $p->currency),
                'Product limit' => fn ($p) => (int) $p->product_limit,
                'Duration (days)' => fn ($p) => (int) $p->duration_days,
                'Status' => fn ($p) => $p->status ? 'Active' : 'Inactive',
            ],
        ];
    }

    private function boostRequests(Request $request): array
    {
        $tab = in_array($request->query('tab'), ['pending', 'current', 'history'], true) ? $request->query('tab') : 'pending';
        $with = ['vendor:id,first_name,last_name,email,phone_number', 'package', 'position', 'boostedProducts.product'];

        $records = match ($tab) {
            'pending' => VendorBoost::with($with)->where('approval_status', 'pending')->oldest()->get(),
            'current' => VendorBoost::with($with)->current()->get(),
            'history' => VendorBoost::with($with)->history()->get(),
        };

        return [
            'title' => 'Boost requests',
            'filter' => ['pending' => 'Pending', 'current' => 'Live & scheduled', 'history' => 'History'][$tab],
            'records' => $records,
            'columns' => [
                'S.no' => fn ($b, $i) => $i + 1,
                'Vendor' => fn ($b) => $b->vendor ? self::name($b->vendor) : 'Deleted vendor',
                'Vendor email' => fn ($b) => $b->vendor?->email,
                'Products' => fn ($b) => $b->boostedProducts
                    ->map(fn ($item) => $item->product?->name ?? 'Deleted product #' . $item->product_id)
                    ->implode(', '),
                'Package' => fn ($b) => $b->package?->name,
                'Position' => fn ($b) => $b->position?->name,
                'Status' => fn ($b) => ucfirst($b->status),
                'Amount' => fn ($b) => (float) $b->amount,
                'Currency' => fn ($b) => strtoupper($b->currency ?? 'usd'),
                'Payment method' => fn ($b) => VendorBoost::methodLabel($b->payment_method),
                'Starts' => fn ($b) => self::date($b->start_date),
                'Ends' => fn ($b) => self::date($b->end_date),
                'Requested' => fn ($b) => self::date($b->created_at),
                'Note' => fn ($b) => $b->rejection_reason ?: $b->vendor_note,
            ],
        ];
    }

    private static function name(?User $user): string
    {
        if (! $user) {
            return 'N/A';
        }

        return trim($user->first_name . ' ' . $user->last_name) ?: ($user->business_name ?: (string) $user->email);
    }

    private static function date($value): ?string
    {
        return $value ? $value->format('Y-m-d H:i') : null;
    }

    private static function money(string $heading): string
    {
        return $heading . ' (' . Setting::get('currency_symbol', '$') . ')';
    }
}
