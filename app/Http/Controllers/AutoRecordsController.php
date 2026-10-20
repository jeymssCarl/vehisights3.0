<?php

namespace App\Http\Controllers;

use App\Models\AutoSession;
use Illuminate\Http\Request;
use App\Models\TrafficHistory;

class AutoRecordsController extends Controller
{
    public function index()
    {
        $sessions = AutoSession::with('detail')
            ->orderBy('created_at', 'desc')
            ->get();
        
        return view('auto-records', compact('sessions'));
    }

    public function update(Request $request, AutoSession $autoSession)
    {
        $validated = $request->validate([
            'camera_name' => 'required|string|max:255',
            'ip_address'  => 'required|ip',
            'location'    => 'required|string|max:255',
            'start_time'  => 'required|date',
            'end_time'    => 'nullable|date',
            'status'      => 'required|in:active,ended',
        ]);

        $autoSession->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Auto session updated successfully'
        ]);
    }

    public function destroy(AutoSession $autoSession)
{
    // Get the details before deleting
    $details = $autoSession->detail;
    
    // Delete from traffic_history (for reports)
    if ($details) {
        TrafficHistory::where('road_name', $autoSession->camera_name)
            ->whereDate('hour_timestamp', $autoSession->start_time)
            ->delete();
    }
    
    // Delete auto session details
    if ($autoSession->detail) {
        $autoSession->detail->delete();
    }
    
    // Delete the main auto session
    $autoSession->delete();

    return response()->json([
        'success' => true,
        'message' => 'Auto session and all related data deleted successfully',
        'refresh_dashboard' => true

        
    ]);
}

// Delete ALL auto sessions
public function destroyAll()
{
    $sessions = AutoSession::all();
    
    foreach ($sessions as $session) {
        // Delete from traffic_history
        TrafficHistory::where('road_name', $session->camera_name)
            ->whereDate('hour_timestamp', $session->start_time)
            ->delete();
        
        // Delete auto session details
        if ($session->detail) {
            $session->detail->delete();
        }
        
        // Delete the main auto session
        $session->delete();
    }
    
    return response()->json([
        'success' => true,
        'message' => 'All auto sessions deleted successfully',
        'refresh_dashboard' => true
    ]);
}
}