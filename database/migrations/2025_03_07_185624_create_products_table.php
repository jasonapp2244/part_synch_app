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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('vendor_id')->nullable()->constrained('vendor_type')->onDelete('cascade');
            $table->foreignId('category_id')->nullable()->constrained('categories')->onDelete('cascade');
            $table->foreignId('sub_category_id')->nullable()->constrained('sub_categories')->onDelete('cascade');
            $table->foreignId('company_id')->nullable()->constrained('companies')->onDelete('cascade');
            $table->foreignId('company_product_categories_id')->nullable()->constrained('company_product_categories')->onDelete('cascade');
          
            $table->string('sku', 50)->nullable();
            $table->string('barcode', 100)->nullable();
            $table->string('warranty', 100)->nullable();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->string('product_pic', 500)->nullable();
            
            $table->unsignedInteger('min_order_quantity')->nullable(); // Allow NULL values
            $table->unsignedInteger('max_order_quantity')->nullable();
            
            $table->string('modal_number', 100)->nullable();
            $table->date('expire_date')->nullable();
            $table->enum('discount_type', ['fixed', 'percentage'])->nullable();
            $table->json('size_options')->nullable();
            $table->json('tags')->nullable();
            $table->text('return_policy')->nullable();
            $table->boolean('installation_required')->default(false);
            $table->text('installation_guide_url')->nullable();
    
            $table->unsignedInteger('stock_quantity')->default(0); // Prevent negative stock
            $table->decimal('discount', 5, 2)->default(0);
            $table->string('brand')->nullable();
            $table->decimal('weight', 8, 2)->default(0.00); // Default weight 0
            $table->string('dimensions')->default(''); // Default empty string
            
            $table->boolean('is_active')->default(true);
            $table->decimal('tax_rate', 5, 2)->default(0.00); // Default tax rate
            $table->enum('status', ['active', 'inactive'])->default('active'); // Consider removing if redundant
            
            $table->timestamps();
        });
    }
    
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
    
};
