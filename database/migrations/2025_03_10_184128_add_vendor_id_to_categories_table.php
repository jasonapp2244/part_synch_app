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
    // Step 1: Add the column first
    Schema::table('categories', function (Blueprint $table) {
        $table->unsignedBigInteger('vendor_type_id')->nullable()->after('user_id');
    });

    // Step 2: Add foreign key constraint separately
    Schema::table('categories', function (Blueprint $table) {
        $table->foreign('vendor_type_id')->references('id')->on('vendor_type')->onDelete('cascade');
    });
}

/**
 * Reverse the migrations.
 */
public function down(): void
{
    Schema::table('categories', function (Blueprint $table) {
        $table->dropForeign(['vendor_type_id']);
        $table->dropColumn('vendor_type_id');
    });
}
};
