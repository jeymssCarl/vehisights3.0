<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Change the column type from enum (or unknown) to VARCHAR(20)
        // Using raw SQL because PostgreSQL enum conversion needs special handling
        DB::statement("ALTER TABLE users ALTER COLUMN role TYPE VARCHAR(20) USING role::text");
        
        // Optional: Add a check constraint to restrict values (good for data integrity)
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('super_admin', 'admin', 'analyst', 'viewer'))");
    }

    public function down()
    {
        // Remove the check constraint first
        DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check");
        
        // Change back to a generic VARCHAR (or you could revert to enum, but not necessary)
        DB::statement("ALTER TABLE users ALTER COLUMN role TYPE VARCHAR(20)");
    }
};