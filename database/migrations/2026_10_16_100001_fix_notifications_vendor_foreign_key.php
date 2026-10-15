<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repoint notifications.vendor_id at the vendor's user row.
 *
 * Same defect as reviews.vendor_id (2026_08_26_100005): the column was
 * constrained against `vendor_type`, the lookup table of vendor categories.
 * Everything that writes or reads it uses a users.id (OrderController, the
 * vendor /notifications endpoints), so inserting a notification for a real
 * vendor failed with a foreign key error unless the vendor's id happened to
 * collide with one of the 14 vendor_type rows. Vendors never received an
 * in-app notification.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        if ($this->constraintExists('notifications_vendor_id_foreign')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropForeign('notifications_vendor_id_foreign');
            });
        }

        // Rows that survived point at a vendor_type id, meaningless as a
        // vendor reference; null it rather than hand it to the wrong user.
        DB::table('notifications')
            ->whereNotNull('vendor_id')
            ->whereNotIn('vendor_id', DB::table('users')->where('role_id', 2)->select('id'))
            ->update(['vendor_id' => null]);

        Schema::table('notifications', function (Blueprint $table) {
            $table->foreign('vendor_id')
                ->references('id')->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
        });

        DB::table('notifications')->update(['vendor_id' => null]);

        Schema::table('notifications', function (Blueprint $table) {
            $table->foreign('vendor_id')
                ->references('id')->on('vendor_type')
                ->cascadeOnDelete();
        });
    }

    private function constraintExists(string $name): bool
    {
        return ! empty(DB::select(
            'SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            ['notifications', $name]
        ));
    }
};
