<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->char('service_status', 1)->default('N')->nullable()->after('company_product_categories_id');
            $table->enum('service_type', ['free', 'paid'])->nullable()->after('service_status');
        });
    }
    
        /**
         * Reverse the migrations.
         */
        public function down(): void
        {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn(['service_status', 'service_type']);
    
            });
        }
};
