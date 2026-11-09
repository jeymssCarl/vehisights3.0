<?php

namespace App\Http\Controllers;

use App\Models\Camera;
use App\Models\Schedule;
use App\Models\ScheduleLog;
use App\Services\ActivityNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

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

        ActivityNotificationService::send('Schedule added', Auth::user()->name.' scheduled automatic counting for '.$schedule->road_name.'.', '/schedules', 'schedule', 'created', $schedule->id);

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

        ActivityNotificationService::send('Schedule updated', Auth::user()->name.' updated '.$schedule->schedule_name.'.', '/schedules', 'schedule', 'updated', $schedule->id);

        return response()->json([
            'success' => true,
            'message' => 'Schedule updated successfully',
            'schedule' => $schedule->fresh('camera'),
        ]);
    }

    public function destroy(Schedule $schedule)
    {
        $scheduleName = $schedule->schedule_name;
        $scheduleId = $schedule->id;
        $schedule->delete();

        ActivityNotificationService::send('Schedule deleted', Auth::user()->name.' deleted '.$scheduleName.'.', '/schedules', 'schedule', 'deleted', $scheduleId);

        return response()->json([
            'success' => true,
            'message' => 'Schedule deleted successfully',
        ]);
    }

    public function toggle(Schedule $schedule)
    {
        $schedule->is_active = ! $schedule->is_active;
        $schedule->save();

        ActivityNotificationService::send(
            $schedule->is_active ? 'Schedule activated' : 'Schedule paused',
            Auth::user()->name.($schedule->is_active ? ' activated ' : ' paused ').$schedule->schedule_name.'.',
            '/schedules',
            'schedule',
            $schedule->is_active ? 'activated' : 'paused',
            $schedule->id
        );

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
        $validator = Validator::make($request->all(), [
            'schedule_name' => 'required|string|max:255',
            'road_name' => 'required|string|max:255',
            'barangay' => 'nullable|string|max:255',
            'city' => 'required|string|max:255',
            'direction_from' => 'nullable|string|max:255',
            'direction_to' => 'nullable|string|max:255',
            'day_classification' => 'nullable|in:market_day,sunday',
            'lanes' => 'required|integer|min:1|max:20',
            'has_median' => 'required|boolean',
            'camera_id' => [
                'required',
                Rule::exists('cameras', 'id')->where(fn ($query) => $query->where('status', 'online')),
            ],
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'recurrence' => 'required|in:once,daily,weekly,monthly',
            'recurrence_days' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
        ]);

        $validator->after(function ($validator) use ($request) {
            if (! $request->filled(['start_date', 'start_time', 'end_time'])) {
                return;
            }

            try {
                $startAt = Carbon::createFromFormat(
                    'Y-m-d H:i',
                    $request->string('start_date').' '.$request->string('start_time'),
                    'Asia/Manila'
                );

                $endAt = Carbon::createFromFormat(
                    'Y-m-d H:i',
                    $request->string('start_date').' '.$request->string('end_time'),
                    'Asia/Manila'
                );
            } catch (\Throwable) {
                return;
            }

            if ($startAt->lt(now('Asia/Manila')->startOfMinute())) {
                $validator->errors()->add(
                    'start_time',
                    'The session start date and time cannot be in the past.'
                );
            }

            if ($endAt->lte($startAt)) {
                $validator->errors()->add(
                    'end_time',
                    'The session end time must be later than the start time.'
                );
            }
        });

        return $validator->validate();
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
