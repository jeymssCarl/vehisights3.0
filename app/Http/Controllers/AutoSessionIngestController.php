<?php

namespace App\Http\Controllers;

use App\Models\AutoSession;
use App\Models\AutoSessionDetail;
use App\Models\Schedule;
use App\Models\ScheduleLog;
use App\Models\TrafficHistory;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AutoSessionIngestController extends Controller
{
    private const PCU_FACTORS = [1.0, 1.5, 1.5, 2.5, 2.0, 3.0, 0.5, 0.7];

    public function pending()
    {
        $schedules = Schedule::query()
            ->with('camera')
            ->where('is_active', true)
            ->whereDate('start_date', '<=', today())
            ->where(function ($query) {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', today());
            })
            ->whereDoesntHave('autoSessions', fn ($query) => $query->where('status', 'active'))
            ->orderBy('start_date')
            ->orderBy('start_time')
            ->get();

        return response()->json(['schedules' => $schedules]);
    }

    public function start(Request $request)
    {
        $validated = $request->validate([
            'schedule_id' => 'required|integer|exists:schedules,id',
            'start_time' => 'nullable|date',
        ]);

        $autoSession = DB::transaction(function () use ($validated, $request) {
            $schedule = Schedule::query()->with('camera')->lockForUpdate()->findOrFail($validated['schedule_id']);

            if (! $schedule->is_active) {
                throw ValidationException::withMessages(['schedule_id' => 'This schedule is inactive.']);
            }

            if (AutoSession::where('schedule_id', $schedule->id)->where('status', 'active')->exists()) {
                throw ValidationException::withMessages(['schedule_id' => 'This schedule already has an active camera session.']);
            }

            $camera = $schedule->camera;
            $direction = collect([$schedule->direction_from, $schedule->direction_to])
                ->filter(fn ($value) => filled($value))
                ->implode(' → ');

            $autoSession = AutoSession::create([
                'schedule_id' => $schedule->id,
                'camera_id' => $camera->id,
                'session_code' => 'A-'.strtoupper(uniqid()),
                'camera_name' => $camera->name,
                'ip_address' => $camera->ip_address,
                'location' => $schedule->road_name ?: $camera->location,
                'road_name' => $schedule->road_name,
                'barangay' => $schedule->barangay,
                'city' => $schedule->city,
                'direction' => $direction ?: null,
                'lanes' => $schedule->lanes,
                'has_median' => $schedule->has_median,
                'base_capacity_per_lane' => $schedule->base_capacity_per_lane,
                'start_time' => $validated['start_time'] ?? now(),
                'status' => 'active',
                'created_by' => $request->user()->id,
            ]);

            $log = ScheduleLog::query()
                ->where('schedule_id', $schedule->id)
                ->where('status', 'pending')
                ->latest('id')
                ->first();

            ($log ?: new ScheduleLog(['schedule_id' => $schedule->id]))->fill([
                'auto_session_id' => $autoSession->id,
                'status' => 'running',
                'started_at' => $autoSession->start_time,
                'error_message' => null,
            ])->save();

            return $autoSession->load(['schedule', 'camera']);
        });

        return response()->json(['success' => true, 'auto_session' => $autoSession], 201);
    }

    public function complete(Request $request, AutoSession $autoSession)
    {
        $validated = $request->validate([
            'end_time' => 'nullable|date',
            'counts' => 'required|array|size:8',
            'counts.*' => 'required|integer|min:0',
        ]);

        $result = DB::transaction(function () use ($autoSession, $validated) {
            $session = AutoSession::query()->lockForUpdate()->findOrFail($autoSession->id);
            $counts = array_values($validated['counts']);
            $pcuTotals = array_map(
                fn ($count, $index) => round($count * self::PCU_FACTORS[$index], 2),
                $counts,
                array_keys($counts)
            );
            $totalPcu = round(array_sum($pcuTotals), 2);
            $capacity = max(1, ($session->lanes ?: 1) * ($session->base_capacity_per_lane ?: 720));
            $vcRatio = round($totalPcu / $capacity, 2);
            $los = $this->levelOfService($vcRatio);
            $endTime = isset($validated['end_time']) ? Carbon::parse($validated['end_time']) : now();

            if ($endTime->lessThan(Carbon::parse($session->start_time))) {
                throw ValidationException::withMessages(['end_time' => 'End time cannot be earlier than the session start time.']);
            }

            $session->update(['end_time' => $endTime, 'status' => 'ended']);

            $detail = AutoSessionDetail::updateOrCreate(
                ['auto_session_id' => $session->id],
                [
                    'total_pcu' => $totalPcu,
                    'vc_ratio' => $vcRatio,
                    'los' => $los,
                    'counts' => $counts,
                    'pcu_totals' => $pcuTotals,
                    'source' => 'auto',
                ]
            );

            ScheduleLog::query()
                ->where('auto_session_id', $session->id)
                ->where('status', 'running')
                ->update(['status' => 'success', 'completed_at' => $endTime, 'error_message' => null]);

            if ($session->schedule?->recurrence === 'once') {
                $session->schedule->update(['is_active' => false]);
            }

            if ($session->camera) {
                $session->camera->update(['last_detection_at' => $endTime]);
            }

            TrafficHistory::updateOrCreate(
                [
                    'camera_id' => $session->camera_id,
                    'hour_timestamp' => Carbon::parse($session->start_time)->startOfHour(),
                    'source' => 'auto',
                ],
                [
                    'road_name' => $session->road_name ?: $session->location,
                    'direction' => $session->direction,
                    'total_pcu' => $totalPcu,
                    'vc_ratio' => $vcRatio,
                    'los' => $los,
                    'counts' => $counts,
                ]
            );

            return ['session' => $session->fresh(), 'detail' => $detail];
        });

        return response()->json(['success' => true] + $result);
    }

    private function levelOfService(float $vcRatio): string
    {
        return match (true) {
            $vcRatio < 0.20 => 'A',
            $vcRatio < 0.45 => 'B',
            $vcRatio < 0.70 => 'C',
            $vcRatio < 0.85 => 'D',
            $vcRatio <= 1.00 => 'E',
            default => 'F',
        };
    }
}
