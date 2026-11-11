<?php

namespace App\Http\Controllers;

use App\Models\Camera;
use App\Models\AutoSession;
use App\Models\AutoSessionDetail;
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

        [$schedule, $autoSession] = DB::transaction(function () use ($validated, $request) {
            $schedule = Schedule::create($this->schedulePayload($validated, $request))->load('camera');

            $scheduledStart = Carbon::parse(
                $schedule->start_date->format('Y-m-d').' '.$schedule->start_time,
                'Asia/Manila'
            )->utc();
            $scheduledEnd = Carbon::parse(
                ($schedule->end_date ?: $schedule->start_date)->format('Y-m-d').' '.$schedule->end_time,
                'Asia/Manila'
            )->utc();
            $status = now()->gte($scheduledStart) ? 'active' : 'scheduled';
            $direction = collect([$schedule->direction_from, $schedule->direction_to])
                ->filter(fn ($value) => filled($value))
                ->implode(' → ');

            $autoSession = AutoSession::create([
                'schedule_id' => $schedule->id,
                'camera_id' => $schedule->camera->id,
                'session_code' => 'A-'.strtoupper(uniqid()),
                'camera_name' => $schedule->camera->name,
                'ip_address' => $schedule->camera->ip_address,
                'location' => $schedule->road_name ?: $schedule->camera->location,
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
                'created_by' => Auth::id(),
            ]);

            AutoSessionDetail::create([
                'auto_session_id' => $autoSession->id,
                'total_pcu' => 0,
                'vc_ratio' => 0,
                'los' => 'A',
                'counts' => array_fill(0, 8, 0),
                'pcu_totals' => array_fill(0, 8, 0),
                'source' => 'auto',
            ]);

            ScheduleLog::create([
                'schedule_id' => $schedule->id,
                'auto_session_id' => $autoSession->id,
                'status' => $status === 'active' ? 'running' : 'pending',
                'started_at' => $status === 'active' ? $scheduledStart : null,
            ]);

            return [
                $schedule,
                $autoSession->load(['schedule', 'camera', 'detail']),
            ];
        });

        ActivityNotificationService::send('Schedule added', Auth::user()->name.' scheduled automatic counting for '.$schedule->road_name.'.', '/schedules', 'schedule', 'created', $schedule->id);

        return response()->json([
            'success' => true,
            'message' => 'Automatic counting schedule created and queued for the camera.',
            'schedule' => $schedule,
            'auto_session' => $autoSession,
        ]);
    }

    public function update(Request $request, Schedule $schedule)
    {
        $validated = $this->validateSchedule($request, $schedule);

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

    private function validateSchedule(Request $request, ?Schedule $ignoredSchedule = null): array
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

        $validator->after(function ($validator) use ($request, $ignoredSchedule) {
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

                return;
            }

            $normalizedRoad = mb_strtolower(trim((string) $request->input('road_name')));
            $normalizedBarangay = mb_strtolower(trim((string) $request->input('barangay')));
            $requestedStartDate = $request->string('start_date')->toString();
            $requestedEndDate = $request->filled('end_date')
                ? $request->string('end_date')->toString()
                : $requestedStartDate;

            $overlapExists = Schedule::query()
                ->where('is_active', true)
                ->whereDate('start_date', '<=', $requestedEndDate)
                ->where(function ($query) use ($requestedStartDate) {
                    $query->whereDate('end_date', '>=', $requestedStartDate)
                        ->orWhere(function ($query) use ($requestedStartDate) {
                            $query->whereNull('end_date')
                                ->where(function ($query) use ($requestedStartDate) {
                                    $query->where('recurrence', '!=', 'once')
                                        ->orWhereDate('start_date', '>=', $requestedStartDate);
                                });
                        });
                })
                ->where('start_time', '<', $request->string('end_time'))
                ->where('end_time', '>', $request->string('start_time'))
                ->where(function ($query) use ($request, $normalizedRoad, $normalizedBarangay) {
                    $query->where('camera_id', $request->integer('camera_id'))
                        ->orWhere(function ($query) use ($normalizedRoad, $normalizedBarangay) {
                            $query->whereRaw('LOWER(TRIM(road_name)) = ?', [$normalizedRoad])
                                ->whereRaw("LOWER(TRIM(COALESCE(barangay, ''))) = ?", [$normalizedBarangay]);
                        });
                })
                ->when($ignoredSchedule, fn ($query) => $query->where('id', '!=', $ignoredSchedule->id))
                ->exists();

            if ($overlapExists) {
                $validator->errors()->add(
                    'start_time',
                    'This camera or the same station and barangay already has an overlapping schedule. Choose another time, camera, or barangay.'
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
