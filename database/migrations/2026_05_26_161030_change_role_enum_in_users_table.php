<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Drop the old enum and recreate with new values
        DB::statement("ALTER TABLE users DROP COLUMN role CASCADE");
        DB::statement("ALTER TABLE users ADD COLUMN role VARCHAR(20) DEFAULT 'viewer'");
        // Or use this if your PostgreSQL supports enum type alteration
        // Simpler: use string column with check constraint or just VARCHAR
    }

    public function down()
    {
        DB::statement("ALTER TABLE users DROP COLUMN role CASCADE");
        DB::statement("ALTER TABLE users ADD COLUMN role VARCHAR(20) DEFAULT 'viewer'");
    }
};