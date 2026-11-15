<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Supabase sends browser postgres_changes events only for tables in
        // its publication. The guards also keep this harmless on a regular
        // PostgreSQL database that does not have Supabase Realtime installed.
        DB::unprepared(<<<'SQL'
            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM pg_publication WHERE pubname = 'supabase_realtime')
                   AND NOT EXISTS (
                       SELECT 1
                       FROM pg_publication_tables
                       WHERE pubname = 'supabase_realtime'
                         AND schemaname = 'public'
                         AND tablename = 'cameras'
                   ) THEN
                    ALTER PUBLICATION supabase_realtime ADD TABLE public.cameras;
                END IF;
            END $$;
        SQL);
    }

    public function down(): void
    {
        // Do not remove the table from a shared Realtime publication: another
        // website feature may subscribe to camera changes too.
    }
};
