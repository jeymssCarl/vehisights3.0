<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manual_counts', function (Blueprint $table) {
            $table->foreignId('session_id')
                ->nullable()
                ->after('id')
                ->unique()
                ->constrained('sessions')
                ->cascadeOnDelete();
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->string('road_name')->nullable()->after('schedule_name');
            $table->string('barangay')->nullable()->after('road_name');
            $table->string('city')->nullable()->after('barangay');
            $table->string('direction_from')->nullable()->after('city');
            $table->string('direction_to')->nullable()->after('direction_from');
            $table->unsignedInteger('lanes')->nullable()->after('direction_to');
            $table->boolean('has_median')->nullable()->after('lanes');
            $table->unsignedInteger('base_capacity_per_lane')->nullable()->after('has_median');
        });

        Schema::table('auto_sessions', function (Blueprint $table) {
            $table->foreignId('schedule_id')->nullable()->after('id')->constrained('schedules')->nullOnDelete();
            $table->foreignId('camera_id')->nullable()->after('schedule_id')->constrained('cameras')->nullOnDelete();
            $table->string('road_name')->nullable()->after('location');
            $table->string('barangay')->nullable()->after('road_name');
            $table->string('city')->nullable()->after('barangay');
            $table->string('direction')->nullable()->after('city');
            $table->unsignedInteger('lanes')->nullable()->after('direction');
            $table->boolean('has_median')->nullable()->after('lanes');
            $table->unsignedInteger('base_capacity_per_lane')->nullable()->after('has_median');
        });

        Schema::table('session_details', function (Blueprint $table) {
            $table->unique('session_id');
        });

        Schema::table('auto_session_details', function (Blueprint $table) {
            $table->unique('auto_session_id');
        });

        $this->linkExistingManualCounts();
    }

    public function down(): void
    {
        Schema::table('auto_session_details', function (Blueprint $table) {
            $table->dropUnique(['auto_session_id']);
        });

        Schema::table('session_details', function (Blueprint $table) {
            $table->dropUnique(['session_id']);
        });

        Schema::table('auto_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('schedule_id');
            $table->dropConstrainedForeignId('camera_id');
            $table->dropColumn([
                'road_name',
                'barangay',
                'city',
                'direction',
                'lanes',
                'has_median',
                'base_capacity_per_lane',
            ]);
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn([
                'road_name',
                'barangay',
                'city',
                'direction_from',
                'direction_to',
                'lanes',
                'has_median',
                'base_capacity_per_lane',
            ]);
        });

        Schema::table('manual_counts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('session_id');
        });
    }

    private function linkExistingManualCounts(): void
    {
        DB::table('manual_counts')->orderBy('id')->each(function ($count) {
            $session = DB::table('sessions')
                ->whereRaw('lower(trim(road_name)) = lower(trim(?))', [$count->road_name])
                ->whereDate('start_time', $count->count_date)
                ->whereRaw('start_time::time = ?::time', [$count->hour_start])
                ->whereNotIn('id', DB::table('manual_counts')->whereNotNull('session_id')->select('session_id'))
                ->orderBy('id')
                ->first();

            if (! $session) {
                $start = Carbon::parse($count->count_date.' '.$count->hour_start);
                $end = Carbon::parse($count->count_date.' '.$count->hour_end);
                if ($end->lessThanOrEqualTo($start)) {
                    $end->addDay();
                }

                $direction = collect([$count->direction_from, $count->direction_to])
                    ->filter(fn ($value) => filled($value))
                    ->implode(' → ');

                $sessionId = DB::table('sessions')->insertGetId([
                    'session_code' => 'S-MIG-'.str_pad((string) $count->id, 6, '0', STR_PAD_LEFT),
                    'road_name' => $count->road_name,
                    'barangay' => $count->barangay,
                    'city' => $count->city,
                    'direction' => $direction ?: 'Not specified',
                    'lanes' => $count->lanes,
                    'has_median' => $count->has_median,
                    'base_capacity_per_lane' => $count->has_median ? 900 : 720,
                    'start_time' => $start,
                    'end_time' => $end,
                    'status' => 'ended',
                    'created_by' => $count->created_by,
                    'created_at' => $count->created_at,
                    'updated_at' => $count->updated_at,
                ]);
            } else {
                $sessionId = $session->id;
            }

            DB::table('manual_counts')->where('id', $count->id)->update(['session_id' => $sessionId]);
        });
    }
};
