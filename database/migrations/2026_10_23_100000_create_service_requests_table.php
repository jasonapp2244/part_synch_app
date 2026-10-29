<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A customer asking a vendor for one of their services.
 *
 * Services existed only on the vendor's side: nothing let a customer see one,
 * let alone ask for it. A request is the service's equivalent of an order —
 * paid outside the app like an order, moved on by the vendor, tracked by the
 * admin. The service's title and price are copied in so a request still reads
 * correctly after the vendor edits or deletes the service.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number', 32)->unique();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 500);
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('quoted_price', 10, 2)->nullable();
            $table->date('preferred_date')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->string('address', 500);
            $table->string('phone', 40);
            $table->text('note')->nullable();
            // requested | accepted | declined | in_progress | completed | cancelled
            $table->string('status', 20)->default('requested');
            $table->string('decline_reason', 500)->nullable();
            // customer | vendor | admin
            $table->string('cancelled_by', 20)->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->boolean('paid_outside')->default(false);
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_requests');
    }
};
