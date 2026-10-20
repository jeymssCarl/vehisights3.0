<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use App\Models\Camera;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScheduleController extends Controller
{
    public function index()
    {
        $schedules = Schedule::with(['camera', 'user'])->orderBy('created_at', 'desc')->get();
        $cameras = Camera::where('status', 'online')->get();
        return view('schedules', compact('schedules', 'cameras'));
    }

    public function store(Request $request)
{
    $validated = $request->validate([
        'schedule_name' => 'required|string|max:255',
        'camera_id' => 'required|exists:cameras,id',
        'start_date' => 'required|date',
        'end_date' => 'nullable|date|after_or_equal:start_date',
        'start_time' => 'required',
        'end_time' => 'required',
        'recurrence' => 'required|in:once,daily,weekly,monthly',
        'recurrence_days' => 'nullable|array',
        'is_active' => 'boolean',
        // New fields
        'road_name' => 'nullable|string',
        'barangay' => 'nullable|string',
        'city' => 'nullable|string',
        'direction' => 'nullable|string',
        'lanes' => 'nullable|integer',
        'has_median' => 'nullable|boolean',
    ]);

    $validated['created_by'] = Auth::id();
    $validated['is_active'] = $request->has('is_active') ? true : false;
    
    // Store additional fields in schedule_name or as JSON
    if ($request->has('road_name')) {
        $validated['schedule_name'] = $request->schedule_name . ' - ' . $request->road_name;
    }

    $schedule = Schedule::create($validated);

    // TODO: Send request to executable to start counting
    // This will be implemented when the executable API is ready

    return response()->json([
        'success' => true,
        'message' => 'Auto session started successfully',
        'schedule' => $schedule
    ]);
}

    public function update(Request $request, Schedule $schedule)
    {
        $validated = $request->validate([
            'schedule_name' => 'required|string|max:255',
            'camera_id' => 'required|exists:cameras,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'start_time' => 'required',
            'end_time' => 'required',
            'recurrence' => 'required|in:once,daily,weekly,monthly',
            'recurrence_days' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $schedule->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Schedule updated successfully'
        ]);
    }

    public function destroy(Schedule $schedule)
    {
        $schedule->delete();
        return response()->json([
            'success' => true,
            'message' => 'Schedule deleted successfully'
        ]);
    }

    public function toggle(Schedule $schedule)
    {
        $schedule->is_active = !$schedule->is_active;
        $schedule->save();
        
        return response()->json([
            'success' => true,
            'is_active' => $schedule->is_active,
            'message' => $schedule->is_active ? 'Schedule activated' : 'Schedule deactivated'
        ]);
    }

    public function logs(Schedule $schedule)
    {
        $logs = $schedule->logs()->with('autoSession')->latest()->get();
        return response()->json(['logs' => $logs]);
    }
}