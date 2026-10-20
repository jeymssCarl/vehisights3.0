<?php

namespace App\Http\Controllers;

use App\Models\Prediction;
use App\Models\Camera;
use App\Models\TrafficHistory;
use Illuminate\Http\Request;

class PredictionController extends Controller
{
    public function index()
    {
        $cameras = Camera::all();
        $predictions = Prediction::with('camera')->latest()->take(10)->get();
        return view('predictions', compact('cameras', 'predictions'));
    }

    public function getPrediction(Request $request)
    {
        $validated = $request->validate([
            'camera_id' => 'nullable|exists:cameras,id',
            'road_name' => 'nullable|string',
            'prediction_date' => 'required|date',
            'prediction_hour' => 'nullable|integer|min:0|max:23',
        ]);

        $query = Prediction::query();

        if (!empty($validated['camera_id'])) {
            $query->where('camera_id', $validated['camera_id']);
        } elseif (!empty($validated['road_name'])) {
            $query->where('road_name', 'like', '%' . $validated['road_name'] . '%');
        }

        $query->whereDate('prediction_date', $validated['prediction_date']);

        if (isset($validated['prediction_hour'])) {
            $query->where('prediction_hour', $validated['prediction_hour']);
        }

        $predictions = $query->orderBy('prediction_hour')->get();

        // Get actual data for comparison
        $actualQuery = TrafficHistory::whereDate('hour_timestamp', $validated['prediction_date']);
        
        if (!empty($validated['camera_id'])) {
            $actualQuery->where('camera_id', $validated['camera_id']);
        } elseif (!empty($validated['road_name'])) {
            $actualQuery->where('road_name', 'like', '%' . $validated['road_name'] . '%');
        }
        
        if (isset($validated['prediction_hour'])) {
            $actualQuery->whereRaw('EXTRACT(HOUR FROM hour_timestamp) = ?', [$validated['prediction_hour']]);
        }
        
        $actualData = $actualQuery->get();

        return response()->json([
            'predictions' => $predictions,
            'actual_data' => $actualData,
        ]);
    }

    public function train(Request $request)
    {
        // Placeholder for LSTM training logic
        // This will be implemented when the Python executable is ready
        return response()->json([
            'success' => true,
            'message' => 'Model training initiated. This may take a few minutes.',
        ]);
    }
}