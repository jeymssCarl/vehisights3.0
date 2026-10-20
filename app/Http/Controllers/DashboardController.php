<?php

namespace App\Http\Controllers;

use App\Models\Session;
use App\Models\SessionDetail;
use App\Models\AutoSession;
use App\Models\ManualCount;
use App\Models\Camera;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Get date range from request, default to today
        $startDate = $request->get('start_date', today()->format('Y-m-d'));
        $endDate = $request->get('end_date', today()->format('Y-m-d'));
        
        // Convert to Carbon instances
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();
        
        // For display in the view
        $selectedStartDate = $startDate;
        $selectedEndDate = $endDate;
        
        // Flag if showing today's data (for UI)
        $isToday = ($startDate == today()->format('Y-m-d') && $endDate == today()->format('Y-m-d'));
        
        // 1. Total PCU for date range - from manual_counts table
        $todayPcu = ManualCount::whereBetween('count_date', [$start, $end])->sum('total_pcu');
        
        // If no manual counts, check sessions as fallback
        if ($todayPcu == 0) {
            $todayPcu = SessionDetail::whereHas('session', function($query) use ($start, $end) {
                $query->whereBetween('start_time', [$start, $end]);
            })->sum('total_pcu');
        }
        
        // 2. Average V/C ratio for date range
        $avgVcRatio = ManualCount::whereBetween('count_date', [$start, $end])->avg('vc_ratio') ?: 0;
        
        if ($avgVcRatio == 0) {
            $avgVcRatio = SessionDetail::whereHas('session', function($query) use ($start, $end) {
                $query->whereBetween('start_time', [$start, $end]);
            })->avg('vc_ratio') ?: 0;
        }
        
        // 3. Current/Peak LOS – from latest in date range or overall
        $latestManual = ManualCount::whereBetween('count_date', [$start, $end])->latest()->first();
        $latestSessionDetail = SessionDetail::whereHas('session', function($query) use ($start, $end) {
            $query->whereBetween('start_time', [$start, $end]);
        })->latest()->first();
        
        if ($latestManual) {
            $currentLos = $latestManual->los;
        } elseif ($latestSessionDetail) {
            $currentLos = $latestSessionDetail->los;
        } else {
            $currentLos = 'N/A';
        }
        
        // 4. Active cameras count (safe even if no cameras table or empty)
        try {
            $activeCameras = Camera::where('status', 'online')->count();
        } catch (\Exception $e) {
            $activeCameras = 0;
        }
        
        // 5. Hourly PCU data - for date range
        $hourlyRecords = ManualCount::whereBetween('count_date', [$start, $end])
            ->select(
                DB::raw("EXTRACT(HOUR FROM hour_start) as hour"),
                DB::raw("SUM(total_pcu) as total_pcu")
            )
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->keyBy('hour');
        
        // Build labels & data
        $labels = [];
        $data = [];
        
        if ($hourlyRecords->count() > 0) {
            foreach ($hourlyRecords as $hour => $record) {
                $labels[] = sprintf('%02d:00', $hour);
                $data[] = (float) $record->total_pcu;
            }
        } else {
            // Try to get from sessions as fallback
            $sessionHourly = Session::whereBetween('start_time', [$start, $end])
                ->select(
                    DB::raw("EXTRACT(HOUR FROM start_time) as hour"),
                    DB::raw("SUM((SELECT total_pcu FROM session_details WHERE session_details.session_id = sessions.id)) as total_pcu")
                )
                ->groupBy('hour')
                ->orderBy('hour')
                ->get();
            
            foreach ($sessionHourly as $record) {
                $hour = $record->hour;
                $labels[] = sprintf('%02d:00', $hour);
                $data[] = (float) $record->total_pcu;
            }
        }
        
        // 6. Recent Manual Counts (last 10 for date range)
        $recentCounts = ManualCount::whereBetween('count_date', [$start, $end])
            ->latest()
            ->limit(10)
            ->get();
        
        // If no manual counts, get from sessions with details
        if ($recentCounts->count() == 0) {
            $recentCounts = Session::with('detail')
                ->whereHas('detail', function($query) {
                    $query->where('source', 'manual');
                })
                ->whereBetween('start_time', [$start, $end])
                ->latest()
                ->limit(10)
                ->get()
                ->filter(function($session) {
                    return $session->detail !== null;
                });
        }
        
        // 7. Recent Auto Counts (last 10 for date range)
        try {
            $recentAutoCounts = AutoSession::with('detail')
                ->whereBetween('start_time', [$start, $end])
                ->latest()
                ->limit(10)
                ->get()
                ->filter(function($session) {
                    return $session->detail !== null;
                });
        } catch (\Exception $e) {
            $recentAutoCounts = collect();
        }
        
        // 8. Active monitoring sessions (not date dependent, but show active ones)
        $activeSessions = Session::where('status', 'active')
            ->with('detail')
            ->latest('start_time')
            ->limit(5)
            ->get()
            ->filter(function($session) {
                return $session->detail !== null;
            });
        
        // 9. Check if there's any data
        $hasData = ($recentCounts->count() > 0) || ($recentAutoCounts->count() > 0);
        
        // 10. Summary stats for date range
        $totalManualCounts = ManualCount::whereBetween('count_date', [$start, $end])->count();
        try {
            $totalAutoSessions = AutoSession::whereBetween('start_time', [$start, $end])->count();
        } catch (\Exception $e) {
            $totalAutoSessions = 0;
        }
        $peakHour = !empty($data) ? max($data) : 0;
        
        return view('dashboard', compact(
            'todayPcu', 'avgVcRatio', 'currentLos', 'activeCameras',
            'labels', 'data', 'recentCounts', 'recentAutoCounts', 
            'activeSessions', 'hasData', 'selectedStartDate', 'selectedEndDate',
            'isToday', 'totalManualCounts', 'totalAutoSessions', 'peakHour'
        ));
    }

    public function getDashboardData(Request $request)
{
    $startDate = $request->get('start_date', today()->format('Y-m-d'));
    $endDate = $request->get('end_date', today()->format('Y-m-d'));
    $start = Carbon::parse($startDate)->startOfDay();
    $end = Carbon::parse($endDate)->endOfDay();
    
    // Get data (same as your index method but return JSON)
    $totalPcu = ManualCount::whereBetween('count_date', [$start, $end])->sum('total_pcu') ?: 0;
    $avgVc = ManualCount::whereBetween('count_date', [$start, $end])->avg('vc_ratio') ?: 0;
    
    // Get peak LOS
    $latestManual = ManualCount::whereBetween('count_date', [$start, $end])->latest()->first();
    $peakLos = $latestManual ? $latestManual->los : 'N/A';
    
    // Get chart data
    $hourlyRecords = ManualCount::whereBetween('count_date', [$start, $end])
        ->select(DB::raw("EXTRACT(HOUR FROM hour_start) as hour"), DB::raw("SUM(total_pcu) as total_pcu"))
        ->groupBy('hour')->orderBy('hour')->get()->keyBy('hour');
    
    $labels = [];
    $data = [];
    $minHour = $hourlyRecords->keys()->min();
    $maxHour = $hourlyRecords->keys()->max();
    if ($minHour !== null) {
        for ($hour = $minHour; $hour <= $maxHour; $hour++) {
            $labels[] = sprintf('%02d:00', $hour);
            $data[] = (float) ($hourlyRecords[$hour]->total_pcu ?? 0);
        }
    }
    
    // Recent manual counts
    $recentManual = ManualCount::whereBetween('count_date', [$start, $end])->latest()->limit(10)->get()->map(function($m) {
        return ['time' => Carbon::parse($m->hour_start)->format('H:i') . '–' . Carbon::parse($m->hour_end)->format('H:i'), 'station' => $m->road_name, 'pcu' => $m->total_pcu, 'los' => $m->los];
    });
    
    // Active sessions
    $activeSessions = Session::where('status', 'active')->with('detail')->latest('start_time')->limit(5)->get()->filter(fn($s) => $s->detail)->map(function($s) {
        return ['code' => $s->session_code, 'road' => $s->road_name, 'vc' => number_format($s->detail->vc_ratio ?? 0, 2), 'los' => $s->detail->los ?? 'N/A'];
    });
    
    // Recent auto counts
    $recentAuto = AutoSession::with('detail')->whereBetween('start_time', [$start, $end])->latest()->limit(10)->get()->filter(fn($s) => $s->detail)->map(function($a) {
        return ['time' => Carbon::parse($a->start_time)->format('H:i'), 'camera' => $a->camera_name, 'pcu' => $a->detail->total_pcu ?? 0, 'los' => $a->detail->los ?? '?'];
    });
    
    return response()->json([
        'total_pcu' => $totalPcu,
        'avg_vc' => $avgVc,
        'peak_los' => $peakLos,
        'active_cameras' => Camera::where('status', 'online')->count(),
        'chart_labels' => $labels,
        'chart_data' => $data,
        'recent_manual' => $recentManual,
        'active_sessions' => $activeSessions,
        'recent_auto' => $recentAuto,
    ]);
}
}