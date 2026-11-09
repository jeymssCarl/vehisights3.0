<?php

namespace App\Http\Controllers;

use App\Models\AutoSession;
use App\Models\AutoSessionDetail;
use App\Models\Camera;
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

    public function pending(Request $request)
    {
        $schedules = Schedule::query()
            ->with([
                'camera',
                'autoSessions' => fn ($query) => $query
                    ->with('detail')
                    ->whereIn('status', ['scheduled', 'active'])
                    ->latest('id'),
            ])
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereDate('start_date', '>=', today())
                    ->orWhereDate('end_date', '>=', today())
                    ->orWhere(function ($query) {
                        $query->whereNull('end_date')
                            ->where('recurrence', '!=', 'once');
                    })
                    ->orWhereHas(
                        'autoSessions',
                        fn ($sessionQuery) => $sessionQuery->whereIn('status', ['scheduled', 'active'])
                    );
            })
            ->when(
                ! $request->user()->isAdmin(),
                fn ($query) => $query->whereHas(
                    'camera',
                    fn ($cameraQuery) => $cameraQuery->where('created_by', $request->user()->id)
                )
            )
            ->orderBy('start_date')
            ->orderBy('start_time')
            ->get()
            ->map(function (Schedule $schedule) {
                $autoSession = $schedule->autoSessions->first();

                if ($autoSession) {
                    $autoSession = $this->activateIfDue($autoSession)->loadMissing('detail');
                }

                $schedule->setRelation('auto_session', $autoSession);
                $schedule->unsetRelation('autoSessions');

                return $schedule;
            })
            ->values();

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

            $this->authorizeScheduleAccess($request, $schedule);

            if (! $schedule->is_active) {
                throw ValidationException::withMessages(['schedule_id' => 'This schedule is inactive.']);
            }

            $existingSession = AutoSession::query()
                ->where('schedule_id', $schedule->id)
                ->whereIn('status', ['scheduled', 'active'])
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($existingSession) {
                return $this->activateIfDue($existingSession)->load(['schedule', 'camera', 'detail']);
            }

            $camera = $schedule->camera;
            $direction = collect([$schedule->direction_from, $schedule->direction_to])
                ->filter(fn ($value) => filled($value))
                ->implode(' → ');

            [$scheduledStart, $scheduledEnd] = $this->scheduleWindow($schedule);

            if (now()->gte($scheduledEnd)) {
                throw ValidationException::withMessages([
                    'schedule_id' => 'This schedule has already passed and can no longer be started.',
                ]);
            }

            $status = now()->gte($scheduledStart) ? 'active' : 'scheduled';

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
                'day_classification' => $schedule->day_classification,
                'lanes' => $schedule->lanes,
                'has_median' => $schedule->has_median,
                'base_capacity_per_lane' => $schedule->base_capacity_per_lane,
                'start_time' => $scheduledStart,
                'end_time' => $scheduledEnd,
                'status' => $status,
                'created_by' => $request->user()->id,
            ]);

            AutoSessionDetail::updateOrCreate(
                ['auto_session_id' => $autoSession->id],
                [
                    'total_pcu' => 0,
                    'vc_ratio' => 0,
                    'los' => 'A',
                    'counts' => array_fill(0, 8, 0),
                    'pcu_totals' => array_fill(0, 8, 0),
                    'source' => 'auto',
                ]
            );

            $log = ScheduleLog::query()
                ->where('schedule_id', $schedule->id)
                ->where('status', 'pending')
                ->latest('id')
                ->first();

            ($log ?: new ScheduleLog(['schedule_id' => $schedule->id]))->fill([
                'auto_session_id' => $autoSession->id,
                'status' => $status === 'active' ? 'running' : 'pending',
                'started_at' => $status === 'active' ? $autoSession->start_time : null,
                'error_message' => null,
            ])->save();

            return $autoSession->load(['schedule', 'camera', 'detail']);
        });

        return response()->json([
            'success' => true,
            'message' => match ($autoSession->status) {
                'scheduled' => 'Automatic counting session scheduled successfully.',
                'active' => 'Automatic counting session started successfully.',
                default => 'The scheduled counting window has already ended.',
            },
            'auto_session' => $autoSession,
        ], 201);
    }

    public function updateCounts(Request $request, AutoSession $autoSession)
    {
        $this->authorizeSessionAccess($request, $autoSession);

        $validated = $request->validate([
            'counts' => 'required|array|size:8',
            'counts.*' => 'required|integer|min:0',
        ]);

        $detail = DB::transaction(function () use ($autoSession, $validated) {
            $session = AutoSession::query()->lockForUpdate()->findOrFail($autoSession->id);

            if ($session->status === 'active' && now()->lt(Carbon::parse($session->start_time))) {
                $session->update(['status' => 'scheduled']);
                ScheduleLog::query()
                    ->where('auto_session_id', $session->id)
                    ->where('status', 'running')
                    ->update([
                        'status' => 'pending',
                        'started_at' => null,
                        'completed_at' => null,
                        'error_message' => null,
                    ]);
            }

            $session = $this->activateIfDue($session);

            if ($session->status !== 'active') {
                throw ValidationException::withMessages([
                    'session' => 'This automatic counting session is not active yet or has already ended.',
                ]);
            }

            $existingCounts = $session->detail?->counts ?? array_fill(0, 8, 0);
            $counts = array_map(
                fn ($count, $index) => max((int) ($existingCounts[$index] ?? 0), (int) $count),
                array_values($validated['counts']),
                array_keys($validated['counts'])
            );
            $pcuTotals = array_map(
                fn ($count, $index) => round($count * self::PCU_FACTORS[$index], 2),
                $counts,
                array_keys($counts)
            );
            $totalPcu = round(array_sum($pcuTotals), 2);
            $capacity = max(1, ($session->lanes ?: 1) * ($session->base_capacity_per_lane ?: 720));
            $vcRatio = round($totalPcu / $capacity, 2);

            return AutoSessionDetail::updateOrCreate(
                ['auto_session_id' => $session->id],
                [
                    'total_pcu' => $totalPcu,
                    'vc_ratio' => $vcRatio,
                    'los' => $this->levelOfService($vcRatio),
                    'counts' => $counts,
                    'pcu_totals' => $pcuTotals,
                    'source' => 'auto',
                ]
            )->fresh();
        });

        return response()->json([
            'success' => true,
            'message' => 'Live counts updated.',
            'auto_session_id' => $autoSession->id,
            'detail' => $detail,
        ]);
    }

    public function stop(Request $request, AutoSession $autoSession)
    {
        $this->authorizeSessionAccess($request, $autoSession);

        $session = AutoSession::query()->with('detail')->findOrFail($autoSession->id);

        if ($session->status !== 'active') {
            throw ValidationException::withMessages([
                'session' => 'This automatic counting session has already ended.',
            ]);
        }

        $request->merge([
            'end_time' => now()->toIso8601String(),
            'counts' => array_values($session->detail?->counts ?? array_fill(0, 8, 0)),
        ]);

        return $this->complete($request, $session);
    }

    public function complete(Request $request, AutoSession $autoSession)
    {
        $this->authorizeSessionAccess($request, $autoSession);

        $validated = $request->validate([
            'end_time' => 'nullable|date',
            'counts' => 'required|array|size:8',
            'counts.*' => 'required|integer|min:0',
        ]);

        $result = DB::transaction(function () use ($autoSession, $validated) {
            $session = AutoSession::query()->lockForUpdate()->findOrFail($autoSession->id);

            if ($session->status === 'ended') {
                return [
                    'session' => $session->fresh(),
                    'detail' => $session->detail()->first(),
                ];
            }

            if ($session->status !== 'active') {
                throw ValidationException::withMessages([
                    'session' => 'This automatic counting session has already ended.',
                ]);
            }

            $existingCounts = $session->detail?->counts ?? array_fill(0, 8, 0);
            $counts = array_map(
                fn ($count, $index) => max((int) ($existingCounts[$index] ?? 0), (int) $count),
                array_values($validated['counts']),
                array_keys($validated['counts'])
            );
            $pcuTotals = array_map(
                fn ($count, $index) => round($count * self::PCU_FACTORS[$index], 2),
                $counts,
                array_keys($counts)
            );
            $totalPcu = round(array_sum($pcuTotals), 2);
            $capacity = max(1, ($session->lanes ?: 1) * ($session->base_capacity_per_lane ?: 720));
            $vcRatio = round($totalPcu / $capacity, 2);
            $los = $this->levelOfService($vcRatio);
            $requestedEnd = isset($validated['end_time']) ? Carbon::parse($validated['end_time']) : now();
            $plannedEnd = $session->end_time ? Carbon::parse($session->end_time) : null;
            $endTime = $plannedEnd && $requestedEnd->greaterThan($plannedEnd)
                ? $plannedEnd
                : $requestedEnd;

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

    private function scheduleWindow(Schedule $schedule): array
    {
        $startDate = $schedule->start_date->format('Y-m-d');
        $endDate = ($schedule->end_date ?: $schedule->start_date)->format('Y-m-d');
        $start = Carbon::parse($startDate.' '.$schedule->start_time, 'Asia/Manila')->utc();
        $end = Carbon::parse($endDate.' '.$schedule->end_time, 'Asia/Manila')->utc();

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return [$start, $end];
    }

    private function activateIfDue(AutoSession $session): AutoSession
    {
        if ($session->status !== 'scheduled' || now()->lt(Carbon::parse($session->start_time))) {
            return $session;
        }

        if ($session->end_time && now()->gte(Carbon::parse($session->end_time))) {
            $session->update(['status' => 'ended']);

            ScheduleLog::query()
                ->where('auto_session_id', $session->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'failed',
                    'completed_at' => $session->end_time,
                    'error_message' => 'The camera did not start before the scheduled counting window ended.',
                ]);

            if ($session->schedule?->recurrence === 'once') {
                $session->schedule->update(['is_active' => false]);
            }

            return $session->fresh();
        }

        $session->update(['status' => 'active']);
        ScheduleLog::query()
            ->where('auto_session_id', $session->id)
            ->where('status', 'pending')
            ->update([
                'status' => 'running',
                'started_at' => $session->start_time,
                'error_message' => null,
            ]);

        return $session->fresh();
    }

    private function authorizeScheduleAccess(Request $request, Schedule $schedule): void
    {
        abort_unless(
            $request->user()->isAdmin()
                || $schedule->created_by === $request->user()->id
                || $schedule->camera?->created_by === $request->user()->id,
            403,
            'You are not allowed to run sessions for this camera.'
        );
    }

    private function authorizeSessionAccess(Request $request, AutoSession $autoSession): void
    {
        if (! $request->user() || $request->user()->isAdmin()) {
            return;
        }

        $cameraOwnerId = Camera::query()
            ->whereKey($autoSession->camera_id)
            ->value('created_by');

        abort_unless(
            $autoSession->created_by === $request->user()->id
                || $cameraOwnerId === $request->user()->id,
            403,
            'You are not allowed to update this camera session.'
        );
    }
}
