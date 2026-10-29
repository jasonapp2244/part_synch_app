<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Services are user-generated content shown to customers now, so they can be
 * reported like a listing. `reportable_type` is a MySQL enum; the value has to
 * be added to the column, not only to the validation.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reports') || DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE reports MODIFY reportable_type ENUM('user','product','message','review','service') NOT NULL");
    }

    public function down(): void
    {
        if (! Schema::hasTable('reports') || DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('reports')->where('reportable_type', 'service')->delete();
        DB::statement("ALTER TABLE reports MODIFY reportable_type ENUM('user','product','message','review') NOT NULL");
    }
};
