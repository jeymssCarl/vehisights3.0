<?php

namespace App\Http\Controllers;

use App\Models\Session;
use App\Services\ActivityNotificationService;
use App\Services\TrafficHistorySyncService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SessionController extends Controller
{
    public function manualRecords()
    {
        $sessions = Session::query()
            ->with('manualCount')
            ->whereHas('manualCount')
            ->orderByDesc('created_at')
            ->get()
            ->each(function (Session $session) {
                $session->setRelation('detail', $session->manualCount);
                $session->unsetRelation('manualCount');
            });

        return view('manual-records', compact('sessions'));
    }

    public function update(Request $request, Session $session, TrafficHistorySyncService $historySync)
    {
        $validated = $request->validate([
            'road_name' => 'required|string|max:255',
            'barangay' => 'nullable|string|max:255',
            'city' => 'required|string|max:255',
            'direction' => 'required|string|max:255',
            'lanes' => 'required|integer|min:1',
            'has_median' => 'required|boolean',
            'day_classification' => 'nullable|in:market_day,sunday',
            'start_time' => 'required|date',
            'end_time' => 'nullable|date|after:start_time',
            'status' => 'required|in:ended',
        ]);

        $requestedStart = Carbon::parse($validated['start_time']);
        $requestedEnd = $validated['end_time'] ? Carbon::parse($validated['end_time']) : $requestedStart->copy()->addHour();
        $this->ensureStationTimeIsAvailable($session, $validated['road_name'], $requestedStart, $requestedEnd);

        DB::transaction(function () use ($session, $validated, $historySync) {
            $session->update($validated + [
                'base_capacity_per_lane' => $validated['has_median'] ? 900 : 720,
            ]);

            if ($session->manualCount) {
                [$directionFrom, $directionTo] = $this->splitDirection($validated['direction']);
                $start = Carbon::parse($validated['start_time']);
                $end = $validated['end_time'] ? Carbon::parse($validated['end_time']) : $start->copy()->addHour();

                $session->manualCount->update([
                    'road_name' => $validated['road_name'],
                    'barangay' => $validated['barangay'],
                    'city' => $validated['city'],
                    'direction_from' => $directionFrom,
                    'direction_to' => $directionTo,
                    'count_date' => $start->toDateString(),
                    'hour_start' => $start->format('H:i:s'),
                    'hour_end' => $end->format('H:i:s'),
                    'has_median' => $validated['has_median'],
                    'lanes' => $validated['lanes'],
                    'day_classification' => $validated['day_classification'] ?? null,
                ]);

                $historySync->syncManual($session->fresh(), $session->manualCount->fresh());
            }
        });

        ActivityNotificationService::send('Manual session updated', auth()->user()->name.' updated '.$session->session_code.'.', '/manual-records', 'manual_session', 'updated', $session->id);

        return response()->json([
            'success' => true,
            'message' => 'Manual record updated successfully',
        ]);
    }

    public function destroy(Session $session, TrafficHistorySyncService $historySync)
    {
        $sessionCode = $session->session_code;
        $sessionId = $session->id;
        DB::transaction(function () use ($session, $historySync) {
            $historySync->deleteManual($session);
            $session->delete();
        });

        ActivityNotificationService::send('Manual session deleted', auth()->user()->name.' deleted '.$sessionCode.'.', '/manual-records', 'manual_session', 'deleted', $sessionId);

        return response()->json([
            'success' => true,
            'message' => 'Manual record deleted successfully',
            'refresh_dashboard' => true,
        ]);
    }

    public function destroyAll(TrafficHistorySyncService $historySync)
    {
        $deletedCount = Session::query()->whereHas('manualCount')->count();
        DB::transaction(function () use ($historySync) {
            $sessions = Session::query()->whereHas('manualCount')->get();

            foreach ($sessions as $session) {
                $historySync->deleteManual($session);
                $session->delete();
            }
        });

        ActivityNotificationService::send('Manual records cleared', auth()->user()->name.' deleted '.$deletedCount.' manual record(s).', '/manual-records', 'manual_session', 'deleted');

        return response()->json([
            'success' => true,
            'message' => 'All manual records deleted successfully',
            'refresh_dashboard' => true,
        ]);
    }

    public function destroySelected(Request $request, TrafficHistorySyncService $historySync)
    {
        $validated = $request->validate([
            'session_ids' => 'required|array|min:1',
            'session_ids.*' => 'required|integer|distinct|exists:sessions,id',
        ]);

        $sessions = Session::query()
            ->whereIn('id', $validated['session_ids'])
            ->whereHas('manualCount')
            ->get();

        if ($sessions->isEmpty()) {
            throw ValidationException::withMessages([
                'session_ids' => 'No valid manual records were selected.',
            ]);
        }

        DB::transaction(function () use ($sessions, $historySync) {
            foreach ($sessions as $session) {
                $historySync->deleteManual($session);
                $session->delete();
            }
        });

        ActivityNotificationService::send(
            'Manual records deleted',
            auth()->user()->name.' deleted '.$sessions->count().' selected manual record(s).',
            '/manual-records',
            'manual_session',
            'deleted'
        );

        return response()->json([
            'success' => true,
            'message' => $sessions->count().' selected manual record(s) deleted successfully',
            'deleted_count' => $sessions->count(),
            'refresh_dashboard' => true,
        ]);
    }

    public function fullUpdate(Request $request, Session $session, TrafficHistorySyncService $historySync)
    {
        $validated = $request->validate([
            'road_name' => 'required|string|max:255',
            'barangay' => 'nullable|string|max:255',
            'city' => 'required|string|max:255',
            'direction' => 'required|string|max:255',
            'lanes' => 'required|integer|min:1',
            'has_median' => 'required|boolean',
            'day_classification' => 'nullable|in:market_day,sunday',
            'start_time' => 'required|date',
            'end_time' => 'nullable|date|after:start_time',
            'status' => 'required|in:ended',
            'counts' => 'required|array|size:8',
            'counts.*' => 'integer|min:0',
            'pcu_totals' => 'required|array|size:8',
            'pcu_totals.*' => 'numeric|min:0',
            'total_pcu' => 'required|numeric|min:0',
            'vc' => 'required|numeric|min:0',
            'los' => 'required|in:A,B,C,D,E,F',
        ]);

        if (! $session->manualCount) {
            throw ValidationException::withMessages([
                'session' => 'This session is not linked to a manual record.',
            ]);
        }

        $requestedStart = Carbon::parse($validated['start_time']);
        $requestedEnd = $validated['end_time'] ? Carbon::parse($validated['end_time']) : $requestedStart->copy()->addHour();
        $this->ensureStationTimeIsAvailable($session, $validated['road_name'], $requestedStart, $requestedEnd);

        DB::transaction(function () use ($session, $validated, $historySync) {
            $newStart = Carbon::parse($validated['start_time']);
            $newEnd = $validated['end_time'] ? Carbon::parse($validated['end_time']) : $newStart->copy()->addHour();
            [$directionFrom, $directionTo] = $this->splitDirection($validated['direction']);

            $session->update([
                'road_name' => $validated['road_name'],
                'barangay' => $validated['barangay'],
                'city' => $validated['city'],
                'direction' => $validated['direction'],
                'lanes' => $validated['lanes'],
                'has_median' => $validated['has_median'],
                'base_capacity_per_lane' => $validated['has_median'] ? 900 : 720,
                'day_classification' => $validated['day_classification'] ?? null,
                'start_time' => $newStart,
                'end_time' => $newEnd,
                'status' => 'ended',
            ]);

            $session->manualCount->update([
                'road_name' => $validated['road_name'],
                'barangay' => $validated['barangay'],
                'city' => $validated['city'],
                'direction_from' => $directionFrom,
                'direction_to' => $directionTo,
                'count_date' => $newStart->toDateString(),
                'hour_start' => $newStart->format('H:i:s'),
                'hour_end' => $newEnd->format('H:i:s'),
                'has_median' => $validated['has_median'],
                'lanes' => $validated['lanes'],
                'day_classification' => $validated['day_classification'] ?? null,
                'counts' => $validated['counts'],
                'pcu_totals' => $validated['pcu_totals'],
                'total_pcu' => $validated['total_pcu'],
                'vc_ratio' => $validated['vc'],
                'los' => $validated['los'],
            ]);

            if ($session->detail) {
                $session->detail->update([
                    'counts' => $validated['counts'],
                    'pcu_totals' => $validated['pcu_totals'],
                    'total_pcu' => $validated['total_pcu'],
                    'vc_ratio' => $validated['vc'],
                    'los' => $validated['los'],
                ]);
            }

            $historySync->syncManual($session->fresh(), $session->manualCount->fresh());
        });

        ActivityNotificationService::send('Manual session updated', auth()->user()->name.' updated '.$session->session_code.' and its vehicle counts.', '/manual-records', 'manual_session', 'updated', $session->id);

        return response()->json([
            'success' => true,
            'message' => 'Manual record and related analytics updated successfully',
        ]);
    }

    private function splitDirection(string $direction): array
    {
        $parts = preg_split('/\s*(?:→|->)\s*/u', trim($direction), 2);

        return [
            filled($parts[0] ?? null) ? trim($parts[0]) : null,
            filled($parts[1] ?? null) ? trim($parts[1]) : null,
        ];
    }

    private function ensureStationTimeIsAvailable(Session $session, string $roadName, Carbon $start, Carbon $end): void
    {
        $overlapExists = Session::query()
            ->whereHas('manualCount')
            ->where('id', '!=', $session->id)
            ->whereRaw('LOWER(TRIM(road_name)) = ?', [mb_strtolower(trim($roadName))])
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start)
            ->exists();

        if ($overlapExists) {
            throw ValidationException::withMessages([
                'start_time' => 'This station already has another manual session during the selected date and time. Delete, move, or edit the conflicting record first.',
            ]);
        }
    }
}
