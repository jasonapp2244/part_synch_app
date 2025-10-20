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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->constrained('products')->onDelete('cascade');
            
            
            $table->enum('service_types', ['quarterly', 'semi_annually', 'three_quarters', 'annually'])->nullable();
    
         
            $table->string('title', 500)->nullable();
            $table->text('description')->nullable(); 
            $table->decimal('price', 10, 2)->nullable();
    
            
            $table->date('start_day')->nullable(); 
            $table->date('end_day')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->enum('duration_type', ['weekly', 'monthly', 'yearly'])->nullable();
    
         
            $table->boolean('is_recurring')->default(false)->nullable();
            $table->enum('service_mode', ['online', 'offline'])->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
    
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
