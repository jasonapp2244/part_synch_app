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
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('cascade');
            $table->foreignId('vendor_id')->nullable()->constrained('vendor_type')->onDelete('cascade'); 
            $table->integer('current_stock')->nullable();
            $table->integer('new_stock')->nullable();
            $table->integer('order_placed')->nullable();
            $table->integer('quantity_changed')->nullable();
            $table->integer('previous_stock')->nullable();
            $table->text('reason')->nullable();
            $table->enum('change_type', ['order_placed', 'order_canceled', 'return', 'manual_update', 'stock_adjustment', 'damaged','new_stock_added'])->default('new_stock_added');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    
    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
