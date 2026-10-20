<?php

namespace App\Http\Controllers;

use App\Models\Session;
use Illuminate\Http\Request;
use App\Models\ManualCount;
use App\Models\TrafficHistory;

class SessionController extends Controller
{
    // For Manual Records - only show sessions created from manual counting (source = 'manual')
    public function manualRecords()
    {
        $sessions = Session::with('detail')
            ->whereHas('detail', function($query) {
                $query->where('source', 'manual');
            })
            ->orderBy('created_at', 'desc')
            ->get();
        
        return view('manual-records', compact('sessions'));
    }

    // Update session (for manual records)
    public function update(Request $request, Session $session)
    {
        $validated = $request->validate([
            'road_name' => 'required|string|max:255',
            'barangay'  => 'nullable|string|max:255',
            'city'      => 'required|string|max:255',
            'direction' => 'required|string|max:255',
            'lanes'     => 'required|integer|min:1',
            'has_median'=> 'required|boolean',
            'start_time'=> 'required|date',
            'end_time'  => 'nullable|date',
            'status'    => 'required|in:active,ended',
        ]);

        $session->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Session updated successfully'
        ]);
    }

    // Delete session (for manual records)
    public function destroy(Session $session)
    {
        // Get the session details before deleting the session
        $details = $session->detail;
        
        // Find related manual count by matching road, date, and time
        if ($details) {
            $startTime = $session->start_time;
            $roadName = $session->road_name;
            
            // Delete from manual_counts table (original manual count data)
            ManualCount::where('road_name', $roadName)
                ->whereDate('count_date', $startTime->toDateString())
                ->whereTime('hour_start', $startTime->toTimeString())
                ->delete();
            
            // Delete from traffic_history (for reports)
            TrafficHistory::where('road_name', $roadName)
                ->whereDate('hour_timestamp', $startTime->toDateString())
                ->whereTime('hour_timestamp', $startTime->toTimeString())
                ->delete();
        }
        
        // Delete session details (if not cascade)
        if ($session->detail) {
            $session->detail->delete();
        }
        
        // Delete the main session
        $session->delete();

        return response()->json([
            'success' => true,
            'message' => 'Session and all related data deleted successfully',
            'refresh_dashboard' => true
        ]);
    }

    // Delete ALL manual sessions
    public function destroyAll()
    {
        // Get all manual sessions
        $sessions = Session::with('detail')
            ->whereHas('detail', function($query) {
                $query->where('source', 'manual');
            })
            ->get();
        
        foreach ($sessions as $session) {
            $details = $session->detail;
            
            if ($details) {
                $startTime = $session->start_time;
                $roadName = $session->road_name;
                
                // Delete from manual_counts
                ManualCount::where('road_name', $roadName)
                    ->whereDate('count_date', $startTime->toDateString())
                    ->whereTime('hour_start', $startTime->toTimeString())
                    ->delete();
                
                // Delete from traffic_history
                TrafficHistory::where('road_name', $roadName)
                    ->whereDate('hour_timestamp', $startTime->toDateString())
                    ->whereTime('hour_timestamp', $startTime->toTimeString())
                    ->delete();
            }
            
            // Delete session details
            if ($session->detail) {
                $session->detail->delete();
            }
            
            // Delete session
            $session->delete();
        }
        
        return response()->json([
            'success' => true,
            'message' => 'All manual sessions deleted successfully',
            'refresh_dashboard' => true
        ]);
    }
    public function fullUpdate(Request $request, Session $session)
{
    $validated = $request->validate([
        'road_name' => 'required|string|max:255',
        'barangay' => 'nullable|string|max:255',
        'city' => 'required|string|max:255',
        'direction' => 'required|string|max:255',
        'lanes' => 'required|integer|min:1',
        'has_median' => 'required|boolean',
        'start_time' => 'required|date',
        'end_time' => 'nullable|date',
        'status' => 'required|in:active,ended',
        'counts' => 'required|array',
        'pcu_totals' => 'required|array',
        'total_pcu' => 'required|numeric',
        'vc' => 'required|numeric',
        'los' => 'required|string|max:1',
    ]);
    
    // Update session
    $session->update([
        'road_name' => $validated['road_name'],
        'barangay' => $validated['barangay'],
        'city' => $validated['city'],
        'direction' => $validated['direction'],
        'lanes' => $validated['lanes'],
        'has_median' => $validated['has_median'],
        'start_time' => $validated['start_time'],
        'end_time' => $validated['end_time'],
        'status' => $validated['status'],
    ]);
    
    // Update session details
    if ($session->detail) {
        $session->detail->update([
            'counts' => $validated['counts'],
            'pcu_totals' => $validated['pcu_totals'],
            'total_pcu' => $validated['total_pcu'],
            'vc_ratio' => $validated['vc'],
            'los' => $validated['los'],
        ]);
    }
    
    // Also update manual_counts table if exists
    ManualCount::where('road_name', $session->road_name)
        ->whereDate('count_date', date('Y-m-d', strtotime($session->start_time)))
        ->update([
            'counts' => json_encode($validated['counts']),
            'pcu_totals' => json_encode($validated['pcu_totals']),
            'total_pcu' => $validated['total_pcu'],
            'vc_ratio' => $validated['vc'],
            'los' => $validated['los'],
        ]);
    
    // Update traffic_history for reports
    TrafficHistory::where('road_name', $session->road_name)
        ->whereDate('hour_timestamp', date('Y-m-d', strtotime($session->start_time)))
        ->update([
            'total_pcu' => $validated['total_pcu'],
            'vc_ratio' => $validated['vc'],
            'los' => $validated['los'],
            'counts' => json_encode($validated['counts']),
        ]);
    
    return response()->json([
        'success' => true,
        'message' => 'Session and all related data updated successfully'
    ]);
}
}