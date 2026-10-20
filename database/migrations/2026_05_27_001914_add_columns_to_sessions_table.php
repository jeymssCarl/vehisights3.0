<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('sessions', function (Blueprint $table) {
            // Check if columns exist before adding (to avoid duplicate column errors)
            if (!Schema::hasColumn('sessions', 'session_code')) {
                $table->string('session_code')->unique();
            }
            if (!Schema::hasColumn('sessions', 'road_name')) {
                $table->string('road_name');
            }
            if (!Schema::hasColumn('sessions', 'barangay')) {
                $table->string('barangay')->nullable();
            }
            if (!Schema::hasColumn('sessions', 'city')) {
                $table->string('city');
            }
            if (!Schema::hasColumn('sessions', 'direction')) {
                $table->string('direction');
            }
            if (!Schema::hasColumn('sessions', 'lanes')) {
                $table->integer('lanes');
            }
            if (!Schema::hasColumn('sessions', 'has_median')) {
                $table->boolean('has_median');
            }
            if (!Schema::hasColumn('sessions', 'base_capacity_per_lane')) {
                $table->integer('base_capacity_per_lane');
            }
            if (!Schema::hasColumn('sessions', 'start_time')) {
                $table->timestamp('start_time');
            }
            if (!Schema::hasColumn('sessions', 'end_time')) {
                $table->timestamp('end_time')->nullable();
            }
            if (!Schema::hasColumn('sessions', 'status')) {
                $table->enum('status', ['active', 'ended'])->default('active');
            }
            if (!Schema::hasColumn('sessions', 'created_by')) {
                $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            }
        });
    }

    public function down()
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropColumn([
                'session_code',
                'road_name',
                'barangay',
                'city',
                'direction',
                'lanes',
                'has_median',
                'base_capacity_per_lane',
                'start_time',
                'end_time',
                'status',
                'created_by'
            ]);
        });
    }
};