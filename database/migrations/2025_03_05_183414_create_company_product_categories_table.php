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
        Schema::create('company_product_categories', function (Blueprint $table) {
            $table->id();
            // Foreign keys with proper spelling of cascade
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade')->nullable();
            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade')->nullable();
            $table->foreignId('sub_category_id')->constrained('sub_categories')->onDelete('cascade')->nullable();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade')->nullable();
            $table->string('product_categories_name', 200)->nullable();
            $table->string('description', 500)->nullable();
            $table->string('product_categories_image', 300)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_product_categories');
    }
};
