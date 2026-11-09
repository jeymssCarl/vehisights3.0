<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->string('day_classification', 30)->nullable()->after('direction');
        });

        Schema::table('manual_counts', function (Blueprint $table) {
            $table->string('day_classification', 30)->nullable()->after('direction_to');
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->string('day_classification', 30)->nullable()->after('direction_to');
        });

        Schema::table('auto_sessions', function (Blueprint $table) {
            $table->string('day_classification', 30)->nullable()->after('direction');
        });
    }

    public function down(): void
    {
        Schema::table('auto_sessions', fn (Blueprint $table) => $table->dropColumn('day_classification'));
        Schema::table('schedules', fn (Blueprint $table) => $table->dropColumn('day_classification'));
        Schema::table('manual_counts', fn (Blueprint $table) => $table->dropColumn('day_classification'));
        Schema::table('sessions', fn (Blueprint $table) => $table->dropColumn('day_classification'));
    }
};
