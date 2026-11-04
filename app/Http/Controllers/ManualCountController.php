<?php

namespace App\Http\Controllers;

use App\Models\ManualCount;
use App\Models\Session;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManualCountController extends Controller
{
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'road' => 'required|string|max:255',
                'barangay' => 'nullable|string|max:255',
                'city' => 'required|string|max:255',
                'dir_from' => 'nullable|string|max:255',
                'dir_to' => 'nullable|string|max:255',
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
            $hourStart = $validated['hour_start'];
            $hourEnd = $validated['hour_end'];

            $manualCount = DB::transaction(function () use ($validated, $hourStart, $hourEnd) {
                $startDateTime = Carbon::parse($validated['date'].' '.$hourStart);
                $endDateTime = Carbon::parse($validated['date'].' '.$hourEnd);
                if ($endDateTime->lessThanOrEqualTo($startDateTime)) {
                    $endDateTime->addDay();
                }

                $direction = collect([$validated['dir_from'], $validated['dir_to']])
                    ->filter(fn ($value) => filled($value))
                    ->implode(' → ');

                $session = Session::create([
                    'session_code' => 'S-'.strtoupper(uniqid()),
                    'road_name' => $validated['road'],
                    'barangay' => $validated['barangay'],
                    'city' => $validated['city'],
                    'direction' => $direction ?: 'Not specified',
                    'lanes' => $validated['lanes'],
                    'has_median' => $validated['has_median'],
                    'base_capacity_per_lane' => $validated['has_median'] ? 900 : 720,
                    'start_time' => $startDateTime,
                    'end_time' => $endDateTime,
                    'status' => 'ended',
                    'created_by' => Auth::id(),
                ]);

                return ManualCount::create([
                    'session_id' => $session->id,
                    'road_name' => $validated['road'],
                    'barangay' => $validated['barangay'],
                    'city' => $validated['city'],
                    'direction_from' => $validated['dir_from'],
                    'direction_to' => $validated['dir_to'],
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
            });

            return response()->json([
                'success' => true,
                'message' => 'Manual count saved successfully',
                'data' => $manualCount,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Database error: '.$e->getMessage(),
            ], 500);
        }
    }
}
