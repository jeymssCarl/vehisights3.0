<?php

namespace App\Http\Controllers;

use App\Models\Prediction;
use App\Models\Schedule;
use App\Models\Session;
use App\Models\TrafficHistory;
use App\Services\TrafficPredictionService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\Request;
use RuntimeException;

class PredictionController extends Controller
{
    public function __construct(private readonly TrafficPredictionService $predictionService)
    {
    }

    public function index()
    {
        $roads = collect()
            ->merge(TrafficHistory::query()->whereNotNull('road_name')->distinct()->pluck('road_name'))
            ->merge(Session::query()->whereNotNull('road_name')->distinct()->pluck('road_name'))
            ->merge(Schedule::query()->whereNotNull('road_name')->distinct()->pluck('road_name'))
            ->filter()
            ->unique(fn ($road) => mb_strtolower(trim($road)))
            ->sort()
            ->values();

        return view('predictions', [
            'roads' => $roads,
            'modelStatus' => $this->predictionService->status(),
        ]);
    }

    public function getPrediction(Request $request)
    {
        $validated = $request->validate([
            'road_name' => 'required|string|max:255',
            'prediction_date' => 'required|date',
            'forecast_window' => 'required|integer|in:6,12,24',
        ]);

        try {
            $forecast = $this->predictionService->forecast(
                $validated['road_name'],
                Carbon::parse($validated['prediction_date'], 'Asia/Manila'),
                (int) $validated['forecast_window'],
            );
        } catch (DomainException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'model_status' => $this->predictionService->status(),
            ], 422);
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'The prediction engine could not complete the forecast. Check the Python environment and application log.',
            ], 500);
        }

        $predictionDate = Carbon::parse($validated['prediction_date'])->toDateString();
        foreach ($forecast['predictions'] as $row) {
            Prediction::query()->updateOrCreate(
                [
                    'road_name' => $validated['road_name'],
                    'prediction_type' => 'hourly',
                    'prediction_date' => $predictionDate,
                    'prediction_hour' => $row['hour'],
                ],
                [
                    'direction' => null,
                    'predicted_pcu' => $row['predicted_pcu'],
                    'predicted_vc_ratio' => $row['predicted_vc_ratio'],
                    'predicted_los' => $row['predicted_los'],
                    'confidence_score' => $row['confidence_score'],
                    'model_version' => $forecast['model_version'],
                ]
            );
        }

        $actualData = TrafficHistory::query()
            ->where('road_name', $validated['road_name'])
            ->whereDate('hour_timestamp', $predictionDate)
            ->orderBy('hour_timestamp')
            ->get()
            ->map(fn (TrafficHistory $row) => [
                'timestamp' => $row->hour_timestamp->toIso8601String(),
                'total_pcu' => (float) $row->total_pcu,
                'vc_ratio' => (float) $row->vc_ratio,
                'los' => $row->los,
            ])
            ->values();

        $historicalData = TrafficHistory::query()
            ->where('road_name', $validated['road_name'])
            ->where('hour_timestamp', '<', Carbon::parse($validated['prediction_date'])->endOfDay())
            ->latest('hour_timestamp')
            ->limit(24)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (TrafficHistory $row) => [
                'timestamp' => $row->hour_timestamp->toIso8601String(),
                'total_pcu' => (float) $row->total_pcu,
            ]);

        return response()->json([
            'success' => true,
            'forecast' => $forecast,
            'actual_data' => $actualData,
            'historical_data' => $historicalData,
            'model_status' => $this->predictionService->status(),
        ]);
    }

    public function train()
    {
        try {
            $result = $this->predictionService->train();
        } catch (DomainException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'model_status' => $this->predictionService->status(),
            ], 422);
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Training failed. Install the Python dependencies and check the application log.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'RandomForestClassifier trained successfully.',
            'training' => $result,
            'model_status' => $this->predictionService->status(),
        ]);
    }
}
