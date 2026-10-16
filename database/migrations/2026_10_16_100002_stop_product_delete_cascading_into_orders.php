<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BCR-31: deleting a product deleted every order line it was part of.
 *
 * Every foreign key onto products.id was ON DELETE CASCADE. For order_items
 * and orders that meant one product delete (a vendor tapping Delete, or an
 * admin) silently emptied paid orders, and `orders` has no total column, so
 * those orders were left worth nothing. Sold products are now refused by the
 * database as well as by the controllers.
 *
 * services.product_id only references a product; a service is not owned by
 * it, so deleting the product unlinks the service instead of deleting it.
 */
return new class extends Migration
{
    private const RULES = [
        'order_items' => 'restrict',
        'orders' => 'restrict',
        'services' => 'set null',
    ];

    public function up(): void
    {
        foreach (self::RULES as $table => $rule) {
            $this->repoint($table, $rule);
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::RULES) as $table) {
            $this->repoint($table, 'cascade');
        }
    }

    private function repoint(string $table, string $rule): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'product_id')) {
            return;
        }

        $name = "{$table}_product_id_foreign";

        if ($this->constraintExists($table, $name)) {
            Schema::table($table, fn (Blueprint $t) => $t->dropForeign($name));
        }

        Schema::table($table, function (Blueprint $t) use ($rule) {
            $t->foreign('product_id')->references('id')->on('products')->onDelete($rule);
        });
    }

    private function constraintExists(string $table, string $name): bool
    {
        return ! empty(DB::select(
            'SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            [$table, $name]
        ));
    }
};
