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
            if (!Schema::hasTable('company_product_category_links')) {
                Schema::create('company_product_category_links', function (Blueprint $table) {
                    $table->id();

                    $table->unsignedBigInteger('company_id')->nullable();
                    $table->unsignedBigInteger('product_id')->nullable();
                    $table->unsignedBigInteger('com_pro_cat_id')->nullable();

                    $table->string('modal_number')->nullable();
                    $table->string('product_number')->nullable();
                    $table->decimal('custom_price', 10, 2)->nullable();
                    $table->decimal('original_price', 10, 2)->nullable();
                    $table->integer('stock_quantity')->default(0);
                    $table->string('warranty')->nullable();

                    $table->decimal('discount', 5, 2)->nullable();
                    $table->date('discount_expire_date')->nullable();
                    $table->boolean('is_featured')->default(false);
                    $table->boolean('is_active')->default(true);

                    $table->unsignedBigInteger('created_by')->nullable();
                    $table->text('notes')->nullable();

                    $table->timestamp('price_updated_at')->nullable();
                    $table->timestamp('stock_updated_at')->nullable();
                    $table->boolean('is_deleted')->default(false);
                    $table->string('sku')->nullable();
                    $table->boolean('is_approved')->default(false);
                    $table->enum('price_type', ['standard', 'bulk', 'dynamic'])->default('standard');
                    $table->string('tags')->nullable();

                    $table->timestamps();

                    $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
                    $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
                    $table->foreign('com_pro_cat_id')
                        ->references('id')
                        ->on('company_product_categories')
                        ->onDelete('cascade');
                });
            }
        }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
