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
        //mujh laravel may apis chat api banni h
        //chat ki kuch asi requirment
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('phone_number');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->enum('map_status', ['active', 'inactive'])->default('active')->after('longitude');
        });
    }
    
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'map_status']);
        });
    }
    
};
