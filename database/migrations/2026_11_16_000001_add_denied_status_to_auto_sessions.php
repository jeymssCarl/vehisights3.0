<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE auto_sessions DROP CONSTRAINT IF EXISTS auto_sessions_status_check');
            DB::statement("ALTER TABLE auto_sessions ADD CONSTRAINT auto_sessions_status_check CHECK (status IN ('scheduled', 'active', 'ended', 'denied'))");
        } elseif (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE auto_sessions MODIFY status ENUM('scheduled', 'active', 'ended', 'denied') NOT NULL DEFAULT 'scheduled'");
        }
    }

    public function down(): void
    {
        DB::table('auto_sessions')->where('status', 'denied')->update(['status' => 'ended']);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE auto_sessions DROP CONSTRAINT IF EXISTS auto_sessions_status_check');
            DB::statement("ALTER TABLE auto_sessions ADD CONSTRAINT auto_sessions_status_check CHECK (status IN ('scheduled', 'active', 'ended'))");
        } elseif (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE auto_sessions MODIFY status ENUM('scheduled', 'active', 'ended') NOT NULL DEFAULT 'scheduled'");
        }
    }
};
