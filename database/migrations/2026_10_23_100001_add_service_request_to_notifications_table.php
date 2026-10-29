<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which service request a notification is about, so tapping it in the app
 * can open the requests screen the way `order_id` opens the orders tab.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications') || Schema::hasColumn('notifications', 'service_request_id')) {
            return;
        }

        Schema::table('notifications', function (Blueprint $table) {
            $table->foreignId('service_request_id')->nullable()->after('order_id')
                ->constrained('service_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('notifications', 'service_request_id')) {
            return;
        }

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_request_id');
        });
    }
};
