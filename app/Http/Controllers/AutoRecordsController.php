<?php

namespace App\Http\Controllers;

use App\Models\AutoSession;
use App\Models\TrafficHistory;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AutoRecordsController extends Controller
{
    public function index()
    {
        $sessions = AutoSession::query()
            ->with(['detail', 'schedule', 'camera'])
            ->where('status', 'ended')
            ->whereHas('detail')
            ->orderByDesc('end_time')
            ->get();

        return view('auto-records', compact('sessions'));
    }

    public function update(Request $request, AutoSession $autoSession)
    {
        $validated = $request->validate([
            'camera_name' => 'required|string|max:255',
            'ip_address' => 'required|ip',
            'location' => 'required|string|max:255',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'status' => 'required|in:ended',
        ]);

        $autoSession->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Automatic record updated successfully',
        ]);
    }

    public function destroy(AutoSession $autoSession)
    {
        DB::transaction(function () use ($autoSession) {
            $this->deleteTrafficHistory($autoSession);
            $autoSession->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Automatic record deleted successfully',
            'refresh_dashboard' => true,
        ]);
    }

    public function destroyAll()
    {
        DB::transaction(function () {
            AutoSession::query()
                ->where('status', 'ended')
                ->whereHas('detail')
                ->get()
                ->each(function (AutoSession $session) {
                    $this->deleteTrafficHistory($session);
                    $session->delete();
                });
        });

        return response()->json([
            'success' => true,
            'message' => 'All automatic records deleted successfully',
            'refresh_dashboard' => true,
        ]);
    }

    private function deleteTrafficHistory(AutoSession $session): void
    {
        $start = Carbon::parse($session->start_time);

        TrafficHistory::query()
            ->when(
                $session->camera_id,
                fn ($query) => $query->where('camera_id', $session->camera_id),
                fn ($query) => $query->where('road_name', $session->road_name ?: $session->location)
            )
            ->where('source', 'auto')
            ->whereDate('hour_timestamp', $start->toDateString())
            ->whereTime('hour_timestamp', $start->copy()->startOfHour()->format('H:i:s'))
            ->delete();
    }
}
