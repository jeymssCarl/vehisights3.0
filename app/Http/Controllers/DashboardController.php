<?php

namespace App\Http\Controllers;

use App\Models\AutoSession;
use App\Models\Camera;
use App\Models\ManualCount;
use App\Models\Session;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->get('start_date', today()->format('Y-m-d'));
        $endDate = $request->get('end_date', today()->format('Y-m-d'));

        return view('dashboard', [
            'dashboardData' => $this->buildDashboardData($startDate, $endDate),
            'selectedStartDate' => $startDate,
            'selectedEndDate' => $endDate,
        ]);
    }

    public function getDashboardData(Request $request)
    {
        $startDate = $request->get('start_date', today()->format('Y-m-d'));
        $endDate = $request->get('end_date', today()->format('Y-m-d'));

        return response()->json($this->buildDashboardData($startDate, $endDate));
    }

    private function buildDashboardData(string $startDate, string $endDate): array
    {
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
            ->select(DB::raw('EXTRACT(HOUR FROM hour_start) as hour'), DB::raw('SUM(total_pcu) as total_pcu'))
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
        $recentManual = ManualCount::whereBetween('count_date', [$start, $end])->latest()->limit(10)->get()->map(function ($m) {
            return ['time' => Carbon::parse($m->hour_start)->format('H:i').'–'.Carbon::parse($m->hour_end)->format('H:i'), 'station' => $m->road_name, 'pcu' => $m->total_pcu, 'los' => $m->los];
        });

        // Active sessions
        $activeSessions = Session::where('status', 'active')->with('detail')->latest('start_time')->limit(5)->get()->filter(fn ($s) => $s->detail)->map(function ($s) {
            return ['code' => $s->session_code, 'road' => $s->road_name, 'vc' => number_format($s->detail->vc_ratio ?? 0, 2), 'los' => $s->detail->los ?? 'N/A'];
        })->values();

        // Recent auto counts
        $recentAuto = AutoSession::with('detail')->whereBetween('start_time', [$start, $end])->latest()->limit(10)->get()->filter(fn ($s) => $s->detail)->map(function ($a) {
            return ['time' => Carbon::parse($a->start_time)->format('H:i'), 'camera' => $a->camera_name, 'pcu' => $a->detail->total_pcu ?? 0, 'los' => $a->detail->los ?? '?'];
        })->values();

        return [
            'total_pcu' => $totalPcu,
            'avg_vc' => $avgVc,
            'peak_los' => $peakLos,
            'active_cameras' => Camera::where('status', 'online')->count(),
            'chart_labels' => $labels,
            'chart_data' => $data,
            'recent_manual' => $recentManual,
            'active_sessions' => $activeSessions,
            'recent_auto' => $recentAuto,
        ];
    }
}
