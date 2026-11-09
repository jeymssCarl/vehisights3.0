<?php

namespace App\Services;

use App\Models\AutoSession;
use App\Models\AutoSessionDetail;
use App\Models\ManualCount;
use App\Models\Session;
use App\Models\TrafficHistory;
use Carbon\Carbon;

class TrafficHistorySyncService
{
    public function syncManual(Session $session, ManualCount $manualCount): TrafficHistory
    {
        $hour = Carbon::parse($session->start_time)->startOfHour();
        $history = TrafficHistory::query()->where('session_id', $session->id)->first()
            ?? TrafficHistory::query()
                ->whereNull('session_id')
                ->where('source', 'manual')
                ->where('road_name', $session->road_name)
                ->whereBetween('hour_timestamp', [$hour, $hour->copy()->endOfHour()])
                ->oldest('id')
                ->first()
            ?? new TrafficHistory();

        $history->fill([
            'session_id' => $session->id,
            'auto_session_id' => null,
            'camera_id' => null,
            'road_name' => $session->road_name,
            'direction' => $session->direction,
            'hour_timestamp' => $hour,
            'total_pcu' => $manualCount->total_pcu,
            'vc_ratio' => $manualCount->vc_ratio,
            'los' => $manualCount->los,
            'counts' => array_values($manualCount->counts ?? array_fill(0, 8, 0)),
            'source' => 'manual',
        ])->save();

        return $history->fresh();
    }

    public function syncAuto(AutoSession $session, AutoSessionDetail $detail): TrafficHistory
    {
        $hour = Carbon::parse($session->start_time)->startOfHour();
        $history = TrafficHistory::query()->where('auto_session_id', $session->id)->first()
            ?? TrafficHistory::query()
                ->whereNull('auto_session_id')
                ->where('source', 'auto')
                ->where('camera_id', $session->camera_id)
                ->whereBetween('hour_timestamp', [$hour, $hour->copy()->endOfHour()])
                ->oldest('id')
                ->first()
            ?? new TrafficHistory();

        $history->fill([
            'session_id' => null,
            'auto_session_id' => $session->id,
            'camera_id' => $session->camera_id,
            'road_name' => $session->road_name ?: $session->location,
            'direction' => $session->direction,
            'hour_timestamp' => $hour,
            'total_pcu' => $detail->total_pcu,
            'vc_ratio' => $detail->vc_ratio,
            'los' => $detail->los,
            'counts' => array_values($detail->counts ?? array_fill(0, 8, 0)),
            'source' => 'auto',
        ])->save();

        return $history->fresh();
    }

    public function deleteManual(Session $session): void
    {
        $hour = Carbon::parse($session->start_time)->startOfHour();
        TrafficHistory::query()
            ->where(function ($query) use ($session, $hour) {
                $query->where('session_id', $session->id)
                    ->orWhere(function ($legacy) use ($session, $hour) {
                        $legacy->whereNull('session_id')
                            ->where('source', 'manual')
                            ->where('road_name', $session->road_name)
                            ->whereBetween('hour_timestamp', [$hour, $hour->copy()->endOfHour()]);
                    });
            })
            ->delete();
    }

    public function deleteAuto(AutoSession $session): void
    {
        $hour = Carbon::parse($session->start_time)->startOfHour();
        TrafficHistory::query()
            ->where(function ($query) use ($session, $hour) {
                $query->where('auto_session_id', $session->id)
                    ->orWhere(function ($legacy) use ($session, $hour) {
                        $legacy->whereNull('auto_session_id')
                            ->where('source', 'auto')
                            ->where('camera_id', $session->camera_id)
                            ->whereBetween('hour_timestamp', [$hour, $hour->copy()->endOfHour()]);
                    });
            })
            ->delete();
    }
}
