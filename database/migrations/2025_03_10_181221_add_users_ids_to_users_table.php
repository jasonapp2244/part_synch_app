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
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('vendor_type_id')->nullable()->after('role_id');
            $table->unsignedBigInteger('category_id')->nullable()->after('vendor_type_id');
            $table->unsignedBigInteger('sub_category_id')->nullable()->after('category_id');
            $table->unsignedBigInteger('companies_id')->nullable()->after('sub_category_id');
            $table->unsignedBigInteger('company_product_categories_id')->nullable()->after('companies_id');
        });

        // Add foreign key constraints separately
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('vendor_type_id')->references('id')->on('vendor_type')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
            $table->foreign('sub_category_id')->references('id')->on('sub_categories')->onDelete('cascade');
            $table->foreign('companies_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('company_product_categories_id')->references('id')->on('company_product_categories')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['vendor_type_id']);
            $table->dropForeign(['category_id']);
            $table->dropForeign(['sub_category_id']);
            $table->dropForeign(['companies_id']);
            $table->dropForeign(['company_product_categories_id']);

            $table->dropColumn([
                'vendor_type_id',
                'category_id',
                'sub_category_id',
                'companies_id',
                'company_product_categories_id',
            ]);
        });
    }
};
