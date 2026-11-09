<?php

namespace App\Http\Controllers;

use App\Models\ManualCount;
use App\Models\Session;
use App\Services\ActivityNotificationService;
use App\Services\TrafficHistorySyncService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManualCountController extends Controller
{
    public function store(Request $request, TrafficHistorySyncService $historySync)
    {
        try {
            $validated = $request->validate([
                'road' => 'required|string|max:255',
                'barangay' => 'nullable|string|max:255',
                'city' => 'required|string|max:255',
                'dir_from' => 'nullable|string|max:255',
                'dir_to' => 'nullable|string|max:255',
                'day_classification' => 'nullable|in:market_day,sunday',
                'date' => 'required|date',
                'hour_start' => 'required|date_format:H:i',
                'hour_end' => 'required|date_format:H:i',
                'has_median' => 'required|boolean',
                'lanes' => 'required|integer|min:1',
                'counts' => 'required|array',
                'pcu_totals' => 'required|array',
                'total_pcu' => 'required|numeric',
                'vc' => 'required|numeric',
                'los' => 'required|string|max:1',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server error: '.$e->getMessage(),
            ], 500);
        }

        if (! Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please log in again.',
            ], 401);
        }

        try {
            $validated['road'] = trim($validated['road']);
            $validated['barangay'] = filled($validated['barangay'] ?? null) ? trim($validated['barangay']) : null;
            $validated['city'] = trim($validated['city']);
            $validated['dir_from'] = filled($validated['dir_from'] ?? null) ? trim($validated['dir_from']) : null;
            $validated['dir_to'] = filled($validated['dir_to'] ?? null) ? trim($validated['dir_to']) : null;
            $hourStart = $validated['hour_start'];
            $hourEnd = $validated['hour_end'];

            $manualCount = DB::transaction(function () use ($validated, $hourStart, $hourEnd, $historySync) {
                $startDateTime = Carbon::parse($validated['date'].' '.$hourStart);
                $endDateTime = Carbon::parse($validated['date'].' '.$hourEnd);
                if ($endDateTime->lessThanOrEqualTo($startDateTime)) {
                    $endDateTime->addDay();
                }

                $direction = collect([$validated['dir_from'], $validated['dir_to']])
                    ->filter(fn ($value) => filled($value))
                    ->implode(' → ');
                $direction = $direction ?: 'Not specified';

                $overlappingSession = Session::query()
                    ->whereHas('manualCount')
                    ->whereRaw('LOWER(TRIM(road_name)) = ?', [mb_strtolower($validated['road'])])
                    ->where('start_time', '<', $endDateTime)
                    ->where('end_time', '>', $startDateTime)
                    ->exists();

                if ($overlappingSession) {
                    throw ValidationException::withMessages([
                        'hour_start' => 'This station already has a manual session during the selected date and time. Delete or edit the existing record first.',
                    ]);
                }

                $session = Session::create([
                    'session_code' => 'S-'.strtoupper(uniqid()),
                    'road_name' => $validated['road'],
                    'barangay' => $validated['barangay'],
                    'city' => $validated['city'],
                    'direction' => $direction,
                    'day_classification' => $validated['day_classification'] ?? null,
                    'lanes' => $validated['lanes'],
                    'has_median' => $validated['has_median'],
                    'base_capacity_per_lane' => $validated['has_median'] ? 900 : 720,
                    'start_time' => $startDateTime,
                    'end_time' => $endDateTime,
                    'status' => 'ended',
                    'created_by' => Auth::id(),
                ]);

                $manualCount = ManualCount::create([
                    'session_id' => $session->id,
                    'road_name' => $validated['road'],
                    'barangay' => $validated['barangay'],
                    'city' => $validated['city'],
                    'direction_from' => $validated['dir_from'],
                    'direction_to' => $validated['dir_to'],
                    'day_classification' => $validated['day_classification'] ?? null,
                    'count_date' => $validated['date'],
                    'hour_start' => $hourStart,
                    'hour_end' => $hourEnd,
                    'has_median' => $validated['has_median'],
                    'lanes' => $validated['lanes'],
                    'counts' => $validated['counts'],
                    'pcu_totals' => $validated['pcu_totals'],
                    'total_pcu' => $validated['total_pcu'],
                    'vc_ratio' => $validated['vc'],
                    'los' => $validated['los'],
                    'created_by' => Auth::id(),
                ]);

                $historySync->syncManual($session, $manualCount);

                return $manualCount;
            });

            ActivityNotificationService::send(
                'Manual session added',
                Auth::user()->name.' added a manual count for '.$validated['road'].'.',
                '/manual-records',
                'manual_session',
                'created',
                $manualCount->session_id
            );

            return response()->json([
                'success' => true,
                'message' => 'Manual count saved successfully',
                'data' => $manualCount,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?: 'The manual session conflicts with an existing record.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Database error: '.$e->getMessage(),
            ], 500);
        }
    }
}
