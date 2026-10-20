<?php

namespace App\Http\Controllers;

use App\Models\Session;
use App\Models\SessionDetail;
use App\Models\AutoSession;
use App\Models\AutoSessionDetail;
use App\Models\ManualCount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        // Get filters from request
        $startDate = $request->get('start_date', today()->format('Y-m-d'));
        $endDate = $request->get('end_date', today()->format('Y-m-d'));
        $station = $request->get('station', 'all');

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        // Get data from MANUAL COUNTS table (directly)
        $manualCounts = ManualCount::whereBetween('count_date', [$start, $end])
            ->when($station !== 'all', function($query) use ($station) {
                return $query->where('road_name', 'like', '%' . $station . '%');
            })
            ->orderBy('hour_start')
            ->get();

        // Get data from AUTO SESSIONS
        try {
            $autoSessions = AutoSession::with('detail')
                ->whereBetween('start_time', [$start, $end])
                ->when($station !== 'all', function($query) use ($station) {
                    return $query->where('camera_name', 'like', '%' . $station . '%');
                })
                ->orderBy('start_time')
                ->get();
        } catch (\Exception $e) {
            $autoSessions = collect();
        }

        // Combine data from all sources
        $hourlyData = $this->combineAllData($manualCounts, $autoSessions);

        // Calculate summary stats
        $totalPcu = $hourlyData->sum('total_pcu');
        $avgVc = $hourlyData->avg('vc_ratio') ?: 0;
        $maxLos = $this->getMaxLos($hourlyData);
        $peakHourPcu = $hourlyData->max('total_pcu');
        $totalRecords = $hourlyData->count();

        // Get vehicle type totals
        $vehicleTotals = [
            'private' => $hourlyData->sum('private'),
            'jeepney' => $hourlyData->sum('jeepney'),
            'van' => $hourlyData->sum('van'),
            'bus' => $hourlyData->sum('bus'),
            'truck2' => $hourlyData->sum('truck2'),
            'truck3' => $hourlyData->sum('truck3'),
            'motorcycle' => $hourlyData->sum('motorcycle'),
            'tricycle' => $hourlyData->sum('tricycle'),
        ];

        // Get stations list for filter dropdown
        $stations = ManualCount::select('road_name')->distinct()->pluck('road_name')
            ->filter()
            ->values();

        // Chart data
        $chartLabels = $hourlyData->pluck('hour')->toArray();
        $chartData = $hourlyData->pluck('total_pcu')->toArray();

        return view('reports', compact(
            'hourlyData', 'totalPcu', 'avgVc', 'maxLos', 'peakHourPcu',
            'startDate', 'endDate', 'station', 'stations',
            'chartLabels', 'chartData', 'vehicleTotals', 'totalRecords'
        ));
    }

    private function combineAllData($manualCounts, $autoSessions)
    {
        $data = collect();

        // Process manual counts
        foreach ($manualCounts as $count) {
            $hour = Carbon::parse($count->hour_start)->format('H:i');
            $hourEnd = Carbon::parse($count->hour_end)->format('H:i');
            $counts = is_array($count->counts) ? $count->counts : json_decode($count->counts, true);
            
            $data->push([
                'hour' => $hour . '–' . $hourEnd,
                'datetime' => $count->hour_start,
                'road_name' => $count->road_name,
                'private' => $counts[0] ?? 0,
                'jeepney' => $counts[1] ?? 0,
                'van' => $counts[2] ?? 0,
                'bus' => $counts[3] ?? 0,
                'truck2' => $counts[4] ?? 0,
                'truck3' => $counts[5] ?? 0,
                'motorcycle' => $counts[6] ?? 0,
                'tricycle' => $counts[7] ?? 0,
                'total_pcu' => $count->total_pcu,
                'vc_ratio' => $count->vc_ratio,
                'los' => $count->los,
                'source' => 'manual'
            ]);
        }

        // Process auto sessions
        foreach ($autoSessions as $session) {
            if ($session->detail) {
                $hour = Carbon::parse($session->start_time)->format('H:i');
                $hourEnd = Carbon::parse($session->end_time)->format('H:i');
                $counts = is_array($session->detail->counts) ? $session->detail->counts : json_decode($session->detail->counts, true);
                
                $data->push([
                    'hour' => $hour . '–' . $hourEnd,
                    'datetime' => $session->start_time,
                    'road_name' => $session->camera_name,
                    'private' => $counts[0] ?? 0,
                    'jeepney' => $counts[1] ?? 0,
                    'van' => $counts[2] ?? 0,
                    'bus' => $counts[3] ?? 0,
                    'truck2' => $counts[4] ?? 0,
                    'truck3' => $counts[5] ?? 0,
                    'motorcycle' => $counts[6] ?? 0,
                    'tricycle' => $counts[7] ?? 0,
                    'total_pcu' => $session->detail->total_pcu,
                    'vc_ratio' => $session->detail->vc_ratio,
                    'los' => $session->detail->los,
                    'source' => 'auto'
                ]);
            }
        }

        return $data->sortBy('datetime')->values();
    }

    private function getMaxLos($hourlyData)
    {
        $losOrder = ['A' => 1, 'B' => 2, 'C' => 3, 'D' => 4, 'E' => 5, 'F' => 6];
        $max = $hourlyData->sortByDesc(function($item) use ($losOrder) {
            return $losOrder[$item['los']] ?? 0;
        })->first();
        
        return $max['los'] ?? 'N/A';
    }

    public function exportCsv(Request $request)
    {
        $startDate = $request->get('start_date', today()->format('Y-m-d'));
        $endDate = $request->get('end_date', today()->format('Y-m-d'));
        $station = $request->get('station', 'all');

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $manualCounts = ManualCount::whereBetween('count_date', [$start, $end])
            ->when($station !== 'all', function($query) use ($station) {
                return $query->where('road_name', 'like', '%' . $station . '%');
            })
            ->orderBy('hour_start')
            ->get();

        try {
            $autoSessions = AutoSession::with('detail')
                ->whereBetween('start_time', [$start, $end])
                ->when($station !== 'all', function($query) use ($station) {
                    return $query->where('camera_name', 'like', '%' . $station . '%');
                })
                ->get();
        } catch (\Exception $e) {
            $autoSessions = collect();
        }

        $hourlyData = $this->combineAllData($manualCounts, $autoSessions);

        // Create CSV
        $csvData = [];
        $csvData[] = ['VEHICLE COUNT SURVEY FORM - LTFRB/DILG COMPLIANT'];
        $csvData[] = [''];
        $csvData[] = ['Station:', $station === 'all' ? 'All Stations' : $station];
        $csvData[] = ['Date Range:', Carbon::parse($startDate)->format('F d, Y') . ' - ' . Carbon::parse($endDate)->format('F d, Y')];
        $csvData[] = ['Generated:', Carbon::now()->format('F d, Y H:i:s')];
        $csvData[] = [''];
        $csvData[] = ['Hour', 'Station', 'Private', 'Jeepney', 'Van', 'Bus', '2-Axle Truck', '3+ Axle Truck', 'Motorcycle', 'Tricycle', 'Total PCU', 'V/C Ratio', 'LOS', 'Source'];

        foreach ($hourlyData as $row) {
            $csvData[] = [
                $row['hour'],
                $row['road_name'],
                $row['private'],
                $row['jeepney'],
                $row['van'],
                $row['bus'],
                $row['truck2'],
                $row['truck3'],
                $row['motorcycle'],
                $row['tricycle'],
                number_format($row['total_pcu'], 2),
                number_format($row['vc_ratio'], 2),
                $row['los'],
                $row['source']
            ];
        }

        $csvData[] = [''];
        $csvData[] = ['SUMMARY'];
        $csvData[] = ['Total Records:', $hourlyData->count()];
        $csvData[] = ['Total PCU:', number_format($hourlyData->sum('total_pcu'), 2)];
        $csvData[] = ['Average V/C Ratio:', number_format($hourlyData->avg('vc_ratio'), 2)];
        $csvData[] = ['Peak Hour PCU:', number_format($hourlyData->max('total_pcu'), 2)];

        $filename = 'traffic_report_' . $startDate . '_to_' . $endDate . '.csv';
        
        $handle = fopen('php://temp', 'w+');
        foreach ($csvData as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    public function generatePdf(Request $request)
    {
        $startDate = $request->get('start_date', today()->format('Y-m-d'));
        $endDate = $request->get('end_date', today()->format('Y-m-d'));
        $station = $request->get('station', 'all');

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $manualCounts = ManualCount::whereBetween('count_date', [$start, $end])
            ->when($station !== 'all', function($query) use ($station) {
                return $query->where('road_name', 'like', '%' . $station . '%');
            })
            ->get();

        try {
            $autoSessions = AutoSession::with('detail')
                ->whereBetween('start_time', [$start, $end])
                ->when($station !== 'all', function($query) use ($station) {
                    return $query->where('camera_name', 'like', '%' . $station . '%');
                })
                ->get();
        } catch (\Exception $e) {
            $autoSessions = collect();
        }

        $hourlyData = $this->combineAllData($manualCounts, $autoSessions);

        $html = $this->generatePdfHtml($hourlyData, $startDate, $endDate, $station);
        
        return response($html)
            ->header('Content-Type', 'text/html')
            ->header('Content-Disposition', 'attachment; filename="traffic_report_' . $startDate . '.html"');
    }

    private function generatePdfHtml($hourlyData, $startDate, $endDate, $station)
    {
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>LTFRB Vehicle Count Survey Form</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .header h1 { font-size: 14px; margin: 5px 0; }
                .header h2 { font-size: 12px; margin: 5px 0; }
                .info-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
                .info-table td { padding: 5px; border: none; }
                .data-table { width: 100%; border-collapse: collapse; font-size: 9px; }
                .data-table th, .data-table td { border: 1px solid #000; padding: 4px; text-align: center; }
                .data-table th { background-color: #f0f0f0; font-weight: bold; }
                .summary { margin-top: 20px; font-size: 10px; }
                .footer { margin-top: 30px; text-align: center; font-size: 9px; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Republic of the Philippines</h1>
                <h2>Department of Transportation (DOTr)</h2>
                <h2>Land Transportation Franchising and Regulatory Board (LTFRB)</h2>
                <h3>VEHICLE COUNT SURVEY FORM</h3>
            </div>
            
            <table class="info-table">
                <tr>
                    <td width="50%"><strong>Station:</strong> ' . ($station === 'all' ? 'All Stations' : $station) . '</td>
                    <td width="50%"><strong>Date:</strong> ' . Carbon::parse($startDate)->format('F d, Y') . ($startDate != $endDate ? ' - ' . Carbon::parse($endDate)->format('F d, Y') : '') . '</td>
                </tr>
                <tr>
                    <td><strong>Surveyor:</strong> VehiSights System<br>' . Carbon::now()->format('F d, Y H:i:s') . '</td>
                    <td><strong>Total Records:</strong> ' . $hourlyData->count() . '</td>
                </tr>
            </table>
            
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Hour</th>
                        <th>Station</th>
                        <th>Private</th>
                        <th>Jeepney</th>
                        <th>Van</th>
                        <th>Bus</th>
                        <th>2-Axle Truck</th>
                        <th>3+ Axle Truck</th>
                        <th>Motorcycle</th>
                        <th>Tricycle</th>
                        <th>Total PCU</th>
                        <th>V/C</th>
                        <th>LOS</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($hourlyData as $row) {
            $html .= '
                    <tr>
                        <td>' . $row['hour'] . '</td>
                        <td>' . $row['road_name'] . '</td>
                        <td>' . number_format($row['private']) . '</td>
                        <td>' . number_format($row['jeepney']) . '</td>
                        <td>' . number_format($row['van']) . '</td>
                        <td>' . number_format($row['bus']) . '</td>
                        <td>' . number_format($row['truck2']) . '</td>
                        <td>' . number_format($row['truck3']) . '</td>
                        <td>' . number_format($row['motorcycle']) . '</td>
                        <td>' . number_format($row['tricycle']) . '</td>
                        <td>' . number_format($row['total_pcu'], 2) . '</td>
                        <td>' . number_format($row['vc_ratio'], 2) . '</td>
                        <td>' . $row['los'] . '</td>
                    </tr>';
        }
        
        $html .= '
                </tbody>
            </table>
            
            <div class="summary">
                <p><strong>Total PCU:</strong> ' . number_format($hourlyData->sum('total_pcu'), 2) . '</p>
                <p><strong>Average V/C Ratio:</strong> ' . number_format($hourlyData->avg('vc_ratio'), 2) . '</p>
                <p><strong>Peak Hour PCU:</strong> ' . number_format($hourlyData->max('total_pcu'), 2) . '</p>
                <p><strong>Peak LOS:</strong> ' . $this->getMaxLos($hourlyData) . '</p>
            </div>
            
            <div class="footer">
                <p>Prepared by: VehiSights Traffic Monitoring System</p>
            </div>
        </body>
        </html>';
        
        return $html;
    }
}