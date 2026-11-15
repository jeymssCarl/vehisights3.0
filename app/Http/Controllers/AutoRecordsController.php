<?php

namespace App\Http\Controllers;

use App\Models\AutoSession;
use App\Models\ScheduleLog;
use App\Services\ActivityNotificationService;
use App\Services\TrafficHistorySyncService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AutoRecordsController extends Controller
{
    public function index()
    {
        $this->closeExpiredSessions();
        $this->synchronizeDeniedSessions();
        $this->synchronizeDesktopCompletedSessions();
        $sessions = $this->recordsQuery()->get();
        $today = Carbon::now('Asia/Manila')->toDateString();

        return view('auto-records', compact('sessions', 'today'));
    }

    public function data()
    {
        $this->closeExpiredSessions();
        $this->synchronizeDeniedSessions();
        $this->synchronizeDesktopCompletedSessions();

        return response()->json([
            'success' => true,
            'sessions' => $this->recordsQuery()->get(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function update(Request $request, AutoSession $autoSession, TrafficHistorySyncService $historySync)
    {
        if ($autoSession->status !== 'ended') {
            throw ValidationException::withMessages([
                'session' => 'Active automatic sessions cannot be edited. Stop the session first.',
            ]);
        }

        $validated = $request->validate([
            'camera_name' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'day_classification' => 'nullable|in:market_day,sunday',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
        ]);

        $autoSession->update($validated);

        if ($autoSession->detail) {
            $historySync->syncAuto($autoSession->fresh(), $autoSession->detail->fresh());
        }

        ActivityNotificationService::send('Automatic record updated', auth()->user()->name.' updated '.$autoSession->session_code.'.', '/auto-records', 'auto_session', 'updated', $autoSession->id);

        return response()->json([
            'success' => true,
            'message' => 'Automatic record updated successfully',
        ]);
    }

    public function destroy(AutoSession $autoSession, TrafficHistorySyncService $historySync)
    {
        if ($autoSession->status === 'active') {
            throw ValidationException::withMessages([
                'session' => 'An active automatic session cannot be cancelled, edited, or deleted.',
            ]);
        }

        $sessionCode = $autoSession->session_code;
        $sessionId = $autoSession->id;

        DB::transaction(function () use ($autoSession, $historySync) {
            $historySync->deleteAuto($autoSession);
            $this->retireDeletedSession($autoSession);
            $autoSession->delete();
        });

        ActivityNotificationService::send('Automatic record deleted', auth()->user()->name.' deleted '.$sessionCode.'.', '/auto-records', 'auto_session', 'deleted', $sessionId);

        return response()->json([
            'success' => true,
            'message' => 'Automatic record deleted successfully',
            'refresh_dashboard' => true,
        ]);
    }

    public function destroyAll(TrafficHistorySyncService $historySync)
    {
        $sessions = AutoSession::query()
            ->with(['schedule', 'detail'])
            ->whereIn('status', ['scheduled', 'ended', 'denied'])
            ->get();
        $deletedCount = $sessions->count();

        DB::transaction(function () use ($historySync) {
            AutoSession::query()
                ->with(['schedule', 'detail'])
                ->whereIn('status', ['scheduled', 'ended', 'denied'])
                ->get()
                ->each(function (AutoSession $session) use ($historySync) {
                    $historySync->deleteAuto($session);
                    $this->retireDeletedSession($session);
                    $session->delete();
                });
        });

        ActivityNotificationService::send('Automatic records cleared', auth()->user()->name.' deleted '.$deletedCount.' completed automatic record(s).', '/auto-records', 'auto_session', 'deleted');

        return response()->json([
            'success' => true,
            'message' => 'All automatic records deleted successfully',
            'refresh_dashboard' => true,
        ]);
    }

    public function destroySelected(Request $request, TrafficHistorySyncService $historySync)
    {
        $validated = $request->validate([
            'session_ids' => 'required|array|min:1',
            'session_ids.*' => 'required|integer|distinct|exists:auto_sessions,id',
        ]);

        $sessions = AutoSession::query()
            ->with(['schedule', 'detail'])
            ->whereIn('id', $validated['session_ids'])
            ->whereIn('status', ['scheduled', 'ended', 'denied'])
            ->get();

        if ($sessions->count() !== count($validated['session_ids'])) {
            throw ValidationException::withMessages([
                'session_ids' => 'Active sessions cannot be deleted. Refresh the page and select only scheduled or ended records.',
            ]);
        }

        DB::transaction(function () use ($sessions, $historySync) {
            foreach ($sessions as $session) {
                $historySync->deleteAuto($session);
                $this->retireDeletedSession($session);
                $session->delete();
            }
        });

        ActivityNotificationService::send(
            'Automatic records deleted',
            auth()->user()->name.' deleted '.$sessions->count().' selected automatic record(s).',
            '/auto-records',
            'auto_session',
            'deleted'
        );

        return response()->json([
            'success' => true,
            'message' => $sessions->count().' selected automatic record(s) deleted successfully',
            'deleted_count' => $sessions->count(),
            'refresh_dashboard' => true,
        ]);
    }

    private function recordsQuery()
    {
        return AutoSession::query()
            ->with(['detail', 'schedule', 'camera', 'user', 'latestScheduleLog'])
            ->whereIn('status', ['scheduled', 'active', 'ended', 'denied'])
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'scheduled' THEN 1 WHEN 'denied' THEN 2 ELSE 3 END")
            ->orderByDesc('start_time');
    }

    private function retireDeletedSession(AutoSession $session): void
    {
        if ($session->status === 'scheduled') {
            ScheduleLog::query()
                ->where('auto_session_id', $session->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'failed',
                    'completed_at' => now(),
                    'error_message' => 'The scheduled session was cancelled from Auto Records.',
                ]);
        }

        $schedule = $session->relationLoaded('schedule')
            ? $session->schedule
            : $session->schedule()->first();

        if ($schedule?->recurrence === 'once' && $schedule->is_active) {
            $schedule->update(['is_active' => false]);
        }
    }

    private function closeExpiredSessions(): void
    {
        // The desktop polls once per second and may mark a due session active
        // just before this request evaluates the camera. During the short
        // start-up window, a still-pending log proves counting has not begun;
        // deny that session when its assigned camera is offline. Restricting
        // this check to 15 seconds prevents a later network interruption from
        // rewriting a genuinely running session as a start-time denial.
        $pendingSessionIds = ScheduleLog::query()
            ->where('status', 'pending')
            ->whereNotNull('auto_session_id')
            ->pluck('auto_session_id');

        AutoSession::query()
            ->with(['schedule', 'camera'])
            ->whereIn('id', $pendingSessionIds)
            ->where('status', 'active')
            ->where('start_time', '<=', now())
            ->where('start_time', '>=', now()->subSeconds(15))
            ->get()
            ->each(function (AutoSession $candidate) {
                if (strtolower((string) $candidate->camera?->status) === 'online') {
                    return;
                }

                DB::transaction(function () use ($candidate) {
                    $session = AutoSession::query()
                        ->with(['schedule', 'camera'])
                        ->lockForUpdate()
                        ->find($candidate->id);

                    $hasPendingLog = ScheduleLog::query()
                        ->where('auto_session_id', $candidate->id)
                        ->where('status', 'pending')
                        ->exists();

                    if (! $session || $session->status !== 'active' || ! $hasPendingLog
                        || strtolower((string) $session->camera?->status) === 'online') {
                        return;
                    }

                    $session->update(['status' => 'denied']);
                    ScheduleLog::query()
                        ->where('auto_session_id', $session->id)
                        ->where('status', 'pending')
                        ->update([
                            'status' => 'failed',
                            'completed_at' => now(),
                            'error_message' => 'Session denied because the assigned camera is unreachable.',
                        ]);

                    if ($session->schedule?->recurrence === 'once') {
                        $session->schedule->update(['is_active' => false]);
                    }
                });
            });

        AutoSession::query()
            ->where('status', 'active')
            ->where('start_time', '>', now())
            ->get()
            ->each(function (AutoSession $candidate) {
                DB::transaction(function () use ($candidate) {
                    $session = AutoSession::query()->with(['schedule', 'camera'])->lockForUpdate()->find($candidate->id);

                    if (! $session || $session->status !== 'active' || now()->gte($session->start_time)) {
                        return;
                    }

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
                });
            });

        AutoSession::query()
            ->where('status', 'scheduled')
            ->where('start_time', '<=', now())
            ->get()
            ->each(function (AutoSession $candidate) {
                DB::transaction(function () use ($candidate) {
                    $session = AutoSession::query()->with(['schedule', 'camera'])->lockForUpdate()->find($candidate->id);

                    if (! $session || $session->status !== 'scheduled' || now()->lt($session->start_time)) {
                        return;
                    }

                    if ($session->end_time && now()->gte($session->end_time)) {
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

                        return;
                    }

                    if (strtolower((string) $session->camera?->status) !== 'online') {
                        $session->update(['status' => 'denied']);
                        ScheduleLog::query()
                            ->where('auto_session_id', $session->id)
                            ->where('status', 'pending')
                            ->update([
                                'status' => 'failed',
                                'completed_at' => now(),
                                'error_message' => 'Session denied because the assigned camera is unreachable.',
                            ]);

                        if ($session->schedule?->recurrence === 'once') {
                            $session->schedule->update(['is_active' => false]);
                        }

                        return;
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
                });
            });

        AutoSession::query()
            ->with(['schedule', 'detail', 'camera'])
            ->where('status', 'active')
            ->whereNotNull('schedule_id')
            ->get()
            ->each(function (AutoSession $candidate) {
                $schedule = $candidate->schedule;
                if (! $schedule || ! $schedule->end_time) {
                    return;
                }

                $endDate = ($schedule->end_date ?: $schedule->start_date)->format('Y-m-d');
                $scheduledEnd = Carbon::parse(
                    $endDate.' '.$schedule->end_time,
                    'Asia/Manila'
                )->utc();
                $scheduledStart = Carbon::parse(
                    $schedule->start_date->format('Y-m-d').' '.$schedule->start_time,
                    'Asia/Manila'
                )->utc();

                if ($scheduledEnd->lessThanOrEqualTo($scheduledStart)) {
                    $scheduledEnd->addDay();
                }

                if (now()->lt($scheduledEnd)) {
                    return;
                }

                DB::transaction(function () use ($candidate, $scheduledEnd) {
                    $session = AutoSession::query()
                        ->with(['schedule', 'detail', 'camera'])
                        ->lockForUpdate()
                        ->find($candidate->id);

                    if (! $session || $session->status !== 'active') {
                        return;
                    }

                    $detail = $session->detail;
                    $counts = $detail?->counts ?? array_fill(0, 8, 0);
                    $totalPcu = (float) ($detail?->total_pcu ?? 0);
                    $vcRatio = (float) ($detail?->vc_ratio ?? 0);
                    $los = $detail?->los ?? 'A';

                    $session->update([
                        'end_time' => $scheduledEnd,
                        'status' => 'ended',
                    ]);

                    ScheduleLog::query()
                        ->where('auto_session_id', $session->id)
                        ->where('status', 'running')
                        ->update([
                            'status' => 'success',
                            'completed_at' => $scheduledEnd,
                            'error_message' => null,
                        ]);

                    if ($session->schedule?->recurrence === 'once') {
                        $session->schedule->update(['is_active' => false]);
                    }

                    if ($session->camera) {
                        $session->camera->update(['last_detection_at' => $scheduledEnd]);
                    }

                    app(TrafficHistorySyncService::class)->syncAuto($session, $detail);
                });
            });
    }

    /**
     * The desktop writes its final automatic-session detail directly to the
     * shared Supabase database, then marks the session ended. That is the same
     * database this Laravel application reads, but it bypasses the normal
     * Laravel completion endpoint. Reconcile those completed rows once so
     * Reports and traffic_history retain the final desktop count too.
     */
    private function synchronizeDesktopCompletedSessions(): void
    {
        AutoSession::query()
            ->with(['schedule', 'detail', 'camera'])
            ->where('status', 'ended')
            ->whereHas('detail')
            ->whereDoesntHave('trafficHistory')
            ->pluck('id')
            ->each(function (int $id) {
                DB::transaction(function () use ($id) {
                    $session = AutoSession::query()
                        ->with(['schedule', 'detail', 'camera', 'trafficHistory'])
                        ->lockForUpdate()
                        ->find($id);

                    if (! $session || $session->status !== 'ended' || ! $session->detail || $session->trafficHistory) {
                        return;
                    }

                    $completedAt = $session->end_time ?? now();

                    ScheduleLog::query()
                        ->where('auto_session_id', $session->id)
                        ->whereIn('status', ['pending', 'running'])
                        ->update([
                            'status' => 'success',
                            'completed_at' => $completedAt,
                            'error_message' => null,
                        ]);

                    if ($session->schedule?->recurrence === 'once') {
                        $session->schedule->update(['is_active' => false]);
                    }

                    $session->camera?->update(['last_detection_at' => $completedAt]);

                    app(TrafficHistorySyncService::class)->syncAuto($session, $session->detail);
                });
            });
    }

    /**
     * The desktop can deny a due session directly through the shared database
     * when the selected camera is offline. Keep the website-owned schedule log
     * and one-time schedule state consistent with that authoritative result.
     */
    private function synchronizeDeniedSessions(): void
    {
        AutoSession::query()
            ->with('schedule')
            ->where('status', 'denied')
            ->pluck('id')
            ->each(function (int $id) {
                DB::transaction(function () use ($id) {
                    $session = AutoSession::query()
                        ->with('schedule')
                        ->lockForUpdate()
                        ->find($id);

                    if (! $session || $session->status !== 'denied') {
                        return;
                    }

                    ScheduleLog::query()
                        ->where('auto_session_id', $session->id)
                        ->whereIn('status', ['pending', 'running'])
                        ->update([
                            'status' => 'failed',
                            'completed_at' => now(),
                            'error_message' => 'Session denied because the assigned camera is unreachable.',
                        ]);

                    if ($session->schedule?->recurrence === 'once') {
                        $session->schedule->update(['is_active' => false]);
                    }
                });
            });
    }
}
