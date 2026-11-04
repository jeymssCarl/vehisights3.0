<?php

namespace App\Http\Controllers;

use App\Models\Camera;
use App\Models\Schedule;
use App\Models\ScheduleLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ScheduleController extends Controller
{
    public function index()
    {
        $schedules = Schedule::with(['camera', 'user'])->orderByDesc('created_at')->get();
        $cameras = Camera::where('status', 'online')->orderBy('name')->get();

        return view('schedules', compact('schedules', 'cameras'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateSchedule($request);

        $schedule = DB::transaction(function () use ($validated, $request) {
            $schedule = Schedule::create($this->schedulePayload($validated, $request));

            ScheduleLog::create([
                'schedule_id' => $schedule->id,
                'status' => 'pending',
            ]);

            return $schedule->load('camera');
        });

        return response()->json([
            'success' => true,
            'message' => 'Automatic counting schedule created and queued for the camera.',
            'schedule' => $schedule,
        ]);
    }

    public function update(Request $request, Schedule $schedule)
    {
        $validated = $this->validateSchedule($request);

        $schedule->update($this->schedulePayload($validated, $request, false));

        return response()->json([
            'success' => true,
            'message' => 'Schedule updated successfully',
            'schedule' => $schedule->fresh('camera'),
        ]);
    }

    public function destroy(Schedule $schedule)
    {
        $schedule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Schedule deleted successfully',
        ]);
    }

    public function toggle(Schedule $schedule)
    {
        $schedule->is_active = ! $schedule->is_active;
        $schedule->save();

        return response()->json([
            'success' => true,
            'is_active' => $schedule->is_active,
            'message' => $schedule->is_active ? 'Schedule activated' : 'Schedule deactivated',
        ]);
    }

    public function logs(Schedule $schedule)
    {
        $logs = $schedule->logs()->with('autoSession')->latest()->get();

        return response()->json(['logs' => $logs]);
    }

    private function validateSchedule(Request $request): array
    {
        return $request->validate([
            'schedule_name' => 'required|string|max:255',
            'road_name' => 'required|string|max:255',
            'barangay' => 'nullable|string|max:255',
            'city' => 'required|string|max:255',
            'direction_from' => 'nullable|string|max:255',
            'direction_to' => 'nullable|string|max:255',
            'lanes' => 'required|integer|min:1|max:20',
            'has_median' => 'required|boolean',
            'camera_id' => 'required|exists:cameras,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'recurrence' => 'required|in:once,daily,weekly,monthly',
            'recurrence_days' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
        ]);
    }

    private function schedulePayload(array $validated, Request $request, bool $includeCreator = true): array
    {
        $payload = $validated;
        $payload['base_capacity_per_lane'] = $validated['has_median'] ? 900 : 720;
        $payload['is_active'] = $request->boolean('is_active', true);

        if ($includeCreator) {
            $payload['created_by'] = Auth::id();
        }

        return $payload;
    }
}
