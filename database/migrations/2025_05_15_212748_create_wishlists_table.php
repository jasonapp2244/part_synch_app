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
    Schema::create('wishlists', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
        $table->foreignId('product_id')->nullable()->constrained('products')->onDelete('cascade');
        $table->foreignId('vendor_id')->nullable()->constrained('users')->onDelete('cascade');
        $table->foreignId('company_id')->nullable()->constrained('companies')->onDelete('cascade');
        $table->string('type')->nullable();
        $table->string('status')->default('saved');
        $table->string('process_status')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wishlists');
    }
};
