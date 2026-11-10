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
        $sessions = $this->recordsQuery()->get();

        return view('auto-records', compact('sessions'));
    }

    public function data()
    {
        $this->closeExpiredSessions();

        return response()->json([
            'success' => true,
            'sessions' => $this->recordsQuery()->get(),
        ]);
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

            if ($autoSession->status === 'scheduled') {
                ScheduleLog::query()
                    ->where('auto_session_id', $autoSession->id)
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'failed',
                        'completed_at' => now(),
                        'error_message' => 'The scheduled session was cancelled from Auto Records.',
                    ]);

                if ($autoSession->schedule?->recurrence === 'once') {
                    $autoSession->schedule->update(['is_active' => false]);
                }
            }

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
        $deletedCount = AutoSession::query()->where('status', 'ended')->whereHas('detail')->count();
        DB::transaction(function () use ($historySync) {
            AutoSession::query()
                ->where('status', 'ended')
                ->whereHas('detail')
                ->get()
                ->each(function (AutoSession $session) use ($historySync) {
                    $historySync->deleteAuto($session);
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
            ->whereIn('status', ['scheduled', 'ended'])
            ->get();

        if ($sessions->count() !== count($validated['session_ids'])) {
            throw ValidationException::withMessages([
                'session_ids' => 'Active sessions cannot be deleted. Refresh the page and select only scheduled or ended records.',
            ]);
        }

        DB::transaction(function () use ($sessions, $historySync) {
            foreach ($sessions as $session) {
                $historySync->deleteAuto($session);

                if ($session->status === 'scheduled') {
                    ScheduleLog::query()
                        ->where('auto_session_id', $session->id)
                        ->where('status', 'pending')
                        ->update([
                            'status' => 'failed',
                            'completed_at' => now(),
                            'error_message' => 'The scheduled session was cancelled from Auto Records.',
                        ]);

                    if ($session->schedule?->recurrence === 'once') {
                        $session->schedule->update(['is_active' => false]);
                    }
                }

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
            ->with(['detail', 'schedule', 'camera', 'user'])
            ->whereIn('status', ['scheduled', 'active', 'ended'])
            ->whereHas('detail')
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'scheduled' THEN 1 ELSE 2 END")
            ->orderByDesc('start_time');
    }

    private function closeExpiredSessions(): void
    {
        AutoSession::query()
            ->where('status', 'active')
            ->where('start_time', '>', now())
            ->get()
            ->each(function (AutoSession $candidate) {
                DB::transaction(function () use ($candidate) {
                    $session = AutoSession::query()->lockForUpdate()->find($candidate->id);

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
                    $session = AutoSession::query()->lockForUpdate()->find($candidate->id);

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
}
