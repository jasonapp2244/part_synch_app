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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('vendor_id')->nullable()->constrained('vendor_type')->onDelete('cascade');
            $table->foreignId('cart_id')->nullable()->constrained('cart')->onDelete('cascade');
            $table->unsignedBigInteger('order_id')->nullable();
            // $table->foreignId('order_id')->nullable()->constrained()->onDelete('set null');
            // $table->foreignId('service_id')->nullable()->constrained('service')->onDelete('cascade');
            $table->string('transaction_id')->nullable();
            $table->string('payment_method')->nullable();
            $table->dateTime('payment_date')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('currency')->default('USD')->nullable();
            $table->text('notes')->nullable();
            $table->string('payment_status')->nullable(); // pending, completed, failed
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
