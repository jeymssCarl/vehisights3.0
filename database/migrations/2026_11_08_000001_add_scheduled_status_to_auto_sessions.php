<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE auto_sessions DROP CONSTRAINT IF EXISTS auto_sessions_status_check');
            DB::statement("ALTER TABLE auto_sessions ADD CONSTRAINT auto_sessions_status_check CHECK (status IN ('scheduled', 'active', 'ended'))");
            DB::statement("ALTER TABLE auto_sessions ALTER COLUMN status SET DEFAULT 'scheduled'");
        } elseif ($driver === 'mysql') {
            DB::statement("ALTER TABLE auto_sessions MODIFY status ENUM('scheduled', 'active', 'ended') NOT NULL DEFAULT 'scheduled'");
        }
    }

    public function down(): void
    {
        DB::table('auto_sessions')->where('status', 'scheduled')->update(['status' => 'active']);
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE auto_sessions DROP CONSTRAINT IF EXISTS auto_sessions_status_check');
            DB::statement("ALTER TABLE auto_sessions ADD CONSTRAINT auto_sessions_status_check CHECK (status IN ('active', 'ended'))");
            DB::statement("ALTER TABLE auto_sessions ALTER COLUMN status SET DEFAULT 'active'");
        } elseif ($driver === 'mysql') {
            DB::statement("ALTER TABLE auto_sessions MODIFY status ENUM('active', 'ended') NOT NULL DEFAULT 'active'");
        }
    }
};
