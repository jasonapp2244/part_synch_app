<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\ProductCategory;
use App\Models\CompanyProductCategoryLinks;
use App\Models\Product;
use App\Models\Service;
use App\Models\Stock;
use App\Models\SubCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Demo logins for the client to test every role, with a small catalogue in
 * stock and a service per seller.
 *
 *   php artisan db:seed --class=DemoAccountsSeeder --force
 *
 * Photos are not added here: they are uploaded in the admin panel (Products,
 * Photos), from database/seeders/demo-images/. Each product below names its
 * file there.
 *
 * Safe to run again: accounts are matched on email and their passwords reset
 * to the ones below, products on (seller, model number). A product's stock is
 * only written the first time, so orders placed against the demo catalogue
 * are never undone by a re-run.
 *
 * Emails are on example.com (reserved, never delivered) so no real inbox gets
 * the OTP, order or approval mail. The admin keeps its existing address.
 *
 * The catalogue rows (category, sub-category, brand, product category) are
 * looked up by name, never created: a brand missing on this server skips the
 * products filed under it and says so.
 */
class DemoAccountsSeeder extends Seeder
{
    public const ADMIN = ['email' => 'admin@gmail.com', 'password' => 'Admin12345'];

    public const VENDOR_PASSWORD = 'Vendor12345';

    public const CUSTOMER_PASSWORD = 'Customer12345';

    private const CATEGORY = 'Power Generation';

    private const SUB_CATEGORY = 'Diesel and Gas Generators';

    public static function vendors(): array
    {
        return [
            [
                'email' => 'vendor01@example.com',
                'first_name' => 'Ryan',
                'last_name' => 'Cooper',
                'business_name' => 'Lone Star Generator Supply',
                'phone_number' => '+17135550101',
                'address' => '4800 Westheimer Road',
                'city' => 'Houston',
                'state' => 'Texas',
                'zipcode' => '77056',
                'products' => [
                    ['CAT 3406 Piston Kit', 'CAT-3406-PK', 'Caterpillar', 'Pistons', 189.00, 12, '12 months', 'piston-kit',
                        'Complete piston kit for Caterpillar 3406 engines: piston, rings, pin and retainers.'],
                    ['CAT C15 Cylinder Liner', 'CAT-C15-CL', 'Caterpillar', 'Cylinders', 245.00, 8, '12 months', 'cylinder-liner',
                        'Wet cylinder liner for Caterpillar C15 generator sets, with seals.'],
                    ['Cummins QSK Oil Filter', 'CUM-QSK-OF', 'Cummins', 'Cylinders', 38.50, 40, '6 months', 'oil-filter',
                        'Full-flow lube oil filter for Cummins QSK series generators.'],
                    ['Cummins Fuel Water Separator', 'CUM-FWS-20', 'Cummins', 'Pistons', 54.00, 25, '6 months', 'fuel-filter',
                        'Fuel filter and water separator, 10 micron, for Cummins diesel gensets.'],
                    ['Generator Battery Charger 24V', 'GEN-BC-24V', 'Caterpillar', 'Cylinders', 329.00, 5, '24 months', 'battery-charger',
                        '10 A float charger for 24 V generator starting batteries, with alarm contacts.'],
                ],
                'service' => ['Generator load bank test', 'Two-hour load bank test with a written report.', 150.00, 'annually'],
            ],
            [
                'email' => 'vendor02@example.com',
                'first_name' => 'Jessica',
                'last_name' => 'Turner',
                'business_name' => 'Rocky Mountain Engine Parts',
                'phone_number' => '+13035550102',
                'address' => '1550 Wewatta Street',
                'city' => 'Denver',
                'state' => 'Colorado',
                'zipcode' => '80202',
                'products' => [
                    ['Kohler KD Camshaft', 'KOH-KD-CS', 'Kohler', 'Camshafts', 412.00, 4, '12 months', 'camshaft',
                        'Remanufactured camshaft for Kohler KD series diesel generators.'],
                    ['Kohler Intake Valve Set', 'KOH-IV-SET', 'Kohler', 'Valves', 96.00, 18, '12 months', 'valve-set',
                        'Set of intake valves with springs and keepers.'],
                    ['Cummins Head Gasket Kit', 'CUM-HG-KIT', 'Cummins', 'Cylinders', 149.00, 10, '6 months', 'gasket-kit',
                        'Upper engine gasket kit including head gasket and valve cover seals.'],
                    ['Cummins Coolant Water Pump', 'CUM-WP-60', 'Cummins', 'Pistons', 275.00, 6, '12 months', 'water-pump',
                        'Engine-driven coolant pump with gasket, for Cummins 6-cylinder gensets.'],
                    ['Kohler 30 kW Alternator Rotor', 'KOH-ALT-30', 'Kohler', 'Valves', 1180.00, 2, '24 months', 'alternator',
                        'Replacement alternator rotor for Kohler 30 kW standby generators.'],
                ],
                'service' => ['On-site oil and filter change', 'Oil, oil filter and fuel filter change at your site.', 95.00, 'quarterly'],
            ],
        ];
    }

    /** Model number => photo file in demo-images/, for the admin upload. */
    public static function photoFiles(): array
    {
        $files = [];
        foreach (self::vendors() as $v) {
            foreach ($v['products'] as $p) {
                $files[$p[1]] = $p[7] . '.png';
            }
        }

        return $files;
    }

    public static function customers(): array
    {
        return [
            ['email' => 'customer01@example.com', 'first_name' => 'Olivia', 'last_name' => 'Bennett',
                'phone_number' => '+12145550111', 'address' => '2100 Ross Avenue', 'city' => 'Dallas', 'state' => 'Texas', 'zipcode' => '75201'],
            ['email' => 'customer02@example.com', 'first_name' => 'James', 'last_name' => 'Parker',
                'phone_number' => '+16025550112', 'address' => '400 East Van Buren Street', 'city' => 'Phoenix', 'state' => 'Arizona', 'zipcode' => '85004'],
        ];
    }

    public function run(): void
    {
        DB::transaction(function () {
            $this->user(self::ADMIN['email'], 1, self::ADMIN['password'], [
                'first_name' => 'Super',
                'last_name' => 'Admin',
            ]);

            $sub = SubCategory::where('sub_category_name', self::SUB_CATEGORY)->first();

            foreach (self::vendors() as $v) {
                $vendor = $this->user($v['email'], 2, self::VENDOR_PASSWORD, [
                    'first_name' => $v['first_name'],
                    'last_name' => $v['last_name'],
                    'business_name' => $v['business_name'],
                    'business_status' => 'active',
                    // A foreign key: the first vendor type this server has.
                    'vendor_type_id' => DB::table('vendor_type')->orderBy('id')->value('id'),
                    'category_id' => $sub?->category_id,
                    'sub_category_id' => $sub?->id,
                    'phone_number' => $v['phone_number'],
                    'address' => $v['address'],
                    'city' => $v['city'],
                    'state' => $v['state'],
                    'country' => 'United States',
                    'zipcode' => $v['zipcode'],
                ]);

                foreach ($v['products'] as $p) {
                    unset($p[7]); // the photo file, uploaded in the admin panel
                    $this->product($vendor, $sub, ...array_values($p));
                }

                [$title, $description, $price, $type] = $v['service'];
                Service::updateOrCreate(
                    ['user_id' => $vendor->id, 'title' => $title],
                    [
                        'description' => $description,
                        'price' => $price,
                        'service_types' => $type,
                        'duration_type' => 'weekly',
                        'is_recurring' => 0,
                        'service_mode' => 'offline',
                        'status' => 'active',
                    ]
                );
            }

            foreach (self::customers() as $c) {
                $this->user($c['email'], 3, self::CUSTOMER_PASSWORD, [
                    'first_name' => $c['first_name'],
                    'last_name' => $c['last_name'],
                    'phone_number' => $c['phone_number'],
                    'address' => $c['address'],
                    'city' => $c['city'],
                    'state' => $c['state'],
                    'country' => 'United States',
                    'zipcode' => $c['zipcode'],
                ]);
            }
        });
    }

    /** An active, verified account with a known password. */
    private function user(string $email, int $role, string $password, array $fields): User
    {
        $user = User::firstOrNew(['email' => $email]);
        $user->forceFill($fields + [
            'role_id' => $role,
            'password' => Hash::make($password),
            'status' => 'active',
            'otp' => null,
            'otp_expires_at' => null,
        ]);
        $user->role_id = $role;
        $user->save();

        return $user;
    }

    private function product(
        User $vendor,
        ?SubCategory $sub,
        string $name,
        string $model,
        string $brand,
        string $productCategory,
        float $price,
        int $stock,
        string $warranty,
        string $description,
    ): void {
        $company = Company::where('company_name', $brand)->first();
        $category = $company
            ? ProductCategory::where('company_id', $company->id)
                ->where('product_categories_name', $productCategory)->first()
            : null;
        if (! $sub || ! $company || ! $category) {
            $this->command?->warn("Skipped {$name}: {$brand} / {$productCategory} is not in this catalogue.");

            return;
        }

        $product = Product::firstOrNew(['user_id' => $vendor->id, 'modal_number' => $model]);
        $isNew = ! $product->exists;
        $product->forceFill([
            'category_id' => $sub->category_id,
            'sub_category_id' => $sub->id,
            'company_id' => $company->id,
            'company_product_categories_id' => $category->id,
            'name' => $name,
            'sku' => $product->sku ?: $model . '-DEMO',
            'warranty' => $warranty,
            'description' => $description,
            'price' => $price,
            'status' => 'active',
            'is_active' => 1,
        ]);
        if ($isNew) {
            $product->stock_quantity = $stock;
        }
        $product->save();

        CompanyProductCategoryLinks::updateOrCreate(
            ['product_id' => $product->id],
            [
                'company_id' => $company->id,
                'com_pro_cat_id' => $category->id,
                'modal_number' => $model,
                'product_number' => $product->sku,
                'original_price' => $price,
                'stock_quantity' => $product->stock_quantity,
                'warranty' => $warranty,
                'created_by' => $vendor->id,
            ]
        );

        // Checkout reads the stock ledger, not `stock_quantity`; a product
        // with no ledger row shows as out of stock in the cart.
        if (! Stock::where('product_id', $product->id)->exists()) {
            $ledger = new Stock();
            $ledger->forceFill([
                'product_id' => $product->id,
                'user_id' => $vendor->id,
                'new_stock' => $stock,
                'current_stock' => $stock,
                'change_type' => 'new_stock_added',
                'status' => 'active',
            ])->save();
        }

    }
}
