<?php

namespace App\Services;

use App\Models\AutoSession;
use App\Models\ScheduleLog;
use Illuminate\Support\Facades\DB;

class AutoSessionLifecycleService
{
    public function __construct(private readonly TrafficHistorySyncService $historySync)
    {
    }

    public function synchronize(): void
    {
        $now = now();

        AutoSession::query()
            ->where('status', 'active')
            ->where('start_time', '>', $now)
            ->pluck('id')
            ->each(fn (int $id) => $this->returnToScheduled($id));

        AutoSession::query()
            ->where('status', 'scheduled')
            ->where('start_time', '<=', $now)
            ->pluck('id')
            ->each(fn (int $id) => $this->activateOrExpire($id));

        AutoSession::query()
            ->where('status', 'active')
            ->whereNotNull('end_time')
            ->where('end_time', '<=', $now)
            ->pluck('id')
            ->each(fn (int $id) => $this->complete($id));
    }

    private function returnToScheduled(int $id): void
    {
        DB::transaction(function () use ($id) {
            $session = AutoSession::query()->lockForUpdate()->find($id);

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
    }

    private function activateOrExpire(int $id): void
    {
        DB::transaction(function () use ($id) {
            $session = AutoSession::query()
                ->with('schedule')
                ->lockForUpdate()
                ->find($id);

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
    }

    private function complete(int $id): void
    {
        DB::transaction(function () use ($id) {
            $session = AutoSession::query()
                ->with(['schedule', 'detail', 'camera'])
                ->lockForUpdate()
                ->find($id);

            if (! $session || $session->status !== 'active' || ! $session->end_time || now()->lt($session->end_time)) {
                return;
            }

            $session->update(['status' => 'ended']);
            ScheduleLog::query()
                ->where('auto_session_id', $session->id)
                ->where('status', 'running')
                ->update([
                    'status' => 'success',
                    'completed_at' => $session->end_time,
                    'error_message' => null,
                ]);

            if ($session->schedule?->recurrence === 'once') {
                $session->schedule->update(['is_active' => false]);
            }

            $session->camera?->update(['last_detection_at' => $session->end_time]);

            if ($session->detail) {
                $this->historySync->syncAuto($session, $session->detail);
            }
        });
    }
}
