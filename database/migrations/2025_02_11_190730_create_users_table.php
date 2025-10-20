<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade');
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('business_name', 500)->nullable();
            $table->string('business_type', 200)->nullable();
            $table->string('business_description', 500)->nullable();
            $table->string('business_license', 100)->nullable();
            $table->string('business_logo', 100)->nullable();
            $table->enum('business_status', ['active', 'inactive'])->default('inactive');
            $table->time('business_start_time')->nullable(); // Opening time
            $table->time('business_end_time')->nullable(); // Closing time
            $table->text('business_start_day')->nullable(); // Store business days as JSON
            $table->text('business_end_day')->nullable(); // Store business days as JSON
            $table->string('email')->unique();
            $table->string('password');
            $table->string('web_token', 50)->nullable();
            $table->string('token', 20)->nullable();
            $table->string('otp', 100)->nullable();
            $table->string('phone_number', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 200)->nullable();
            $table->string('state', 200)->nullable();
            $table->string('country', 200)->nullable();
            $table->string('zipcode', 20)->nullable();
            $table->string('profile_image')->nullable();
            $table->string('facebook_auth_id', 100)->nullable();
            $table->string('google_auth_id', 100)->nullable();
            $table->string('apple_auth_id', 100)->nullable();
            $table->string('remember_token', 300)->nullable();
            $table->string('forgot_password_token', 300)->nullable();
            $table->string('reset_password_token', 300)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index()->constrained('users')->onDelete('cascade');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
