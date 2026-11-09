<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('traffic_history', function (Blueprint $table) {
            $table->foreignId('session_id')
                ->nullable()
                ->unique()
                ->after('id')
                ->constrained('sessions')
                ->cascadeOnDelete();
            $table->foreignId('auto_session_id')
                ->nullable()
                ->unique()
                ->after('session_id')
                ->constrained('auto_sessions')
                ->cascadeOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE traffic_history DROP CONSTRAINT IF EXISTS traffic_history_source_check');
            DB::statement("ALTER TABLE traffic_history ADD CONSTRAINT traffic_history_source_check CHECK (source IN ('manual', 'auto', 'historical'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::table('traffic_history')->where('source', 'historical')->update(['source' => 'manual']);
            DB::statement('ALTER TABLE traffic_history DROP CONSTRAINT IF EXISTS traffic_history_source_check');
            DB::statement("ALTER TABLE traffic_history ADD CONSTRAINT traffic_history_source_check CHECK (source IN ('manual', 'auto'))");
        }

        Schema::table('traffic_history', function (Blueprint $table) {
            $table->dropConstrainedForeignId('auto_session_id');
            $table->dropConstrainedForeignId('session_id');
        });
    }
};
