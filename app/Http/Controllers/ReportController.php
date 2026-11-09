<?php

namespace App\Http\Controllers;

use App\Models\AutoSession;
use App\Models\ManualCount;
use App\Models\Schedule;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $stations = $this->stationOptions();
        [$startDate, $endDate, $station] = $this->resolveFilters($request, $stations);
        $report = $this->buildReportData($startDate, $endDate, $station);
        $report['periodType'] = $this->resolvePeriodType($request, $startDate, $endDate);
        $report['reportsPageData'] = [
            'chartLabels' => $report['chartLabels'],
            'chartData' => $report['chartData'],
            'startDate' => $startDate,
            'endDate' => $endDate,
            'station' => $station,
            'periodType' => $report['periodType'],
            'totalRecords' => $report['totalRecords'],
            'openSurvey' => $request->boolean('open_survey'),
        ];

        return view('reports', array_merge($report, [
            'stations' => $stations,
        ]));
    }

    public function exportCsv(Request $request)
    {
        [$startDate, $endDate, $station] = $this->resolveFilters($request, $this->stationOptions(), true);
        $report = $this->buildReportData($startDate, $endDate, $station);

        $rows = [
            ['VEHISIGHTS VEHICLE COUNT SURVEY FORM'],
            ['Department of Transportation (DOTr)'],
            ['Land Transportation Franchising and Regulatory Board (LTFRB)'],
            [],
            ['Station / Road', $report['stationLabel']],
            ['Date Range', $report['dateRangeLabel']],
            ['Generated', $report['generatedAt']],
            ['Total Records', $report['totalRecords']],
            [],
            ['Hour', 'Station / Road', 'Private Car / Taxi / Pick-up', 'Jeepney', 'Van', 'Bus & Mini Bus', 'Light Truck', 'Heavy Truck', 'Motorcycle', 'Tricycle', 'Total PCU', 'V/C Ratio', 'LOS', 'Day Classification', 'Source'],
        ];

        foreach ($report['hourlyData'] as $row) {
            $rows[] = [
                $row['hour'],
                $this->csvSafe($row['road_name']),
                (int) $row['private'],
                (int) $row['jeepney'],
                (int) $row['van'],
                (int) $row['bus'],
                (int) $row['truck2'],
                (int) $row['truck3'],
                (int) $row['motorcycle'],
                (int) $row['tricycle'],
                round((float) $row['total_pcu'], 2),
                round((float) $row['vc_ratio'], 2),
                $row['los'],
                $row['day_classification'],
                ucfirst($row['source']),
            ];
        }

        $rows[] = [];
        $rows[] = ['SUMMARY'];
        $rows[] = ['Total Records', $report['totalRecords']];
        $rows[] = ['Total PCU', round((float) $report['totalPcu'], 2)];
        $rows[] = ['Average V/C Ratio', round((float) $report['avgVc'], 2)];
        $rows[] = ['Peak Hour PCU', round((float) $report['peakHourPcu'], 2)];
        $rows[] = ['Peak LOS', $report['maxLos']];

        $handle = fopen('php://temp', 'w+');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = "\xEF\xBB\xBF".stream_get_contents($handle);
        fclose($handle);

        $filename = "vehisights_vehicle_count_{$startDate}_to_{$endDate}.csv";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    public function generatePdf(Request $request)
    {
        [$startDate, $endDate, $station] = $this->resolveFilters($request, $this->stationOptions(), true);
        $report = $this->buildReportData($startDate, $endDate, $station);
        $filename = "vehisights_vehicle_count_{$startDate}_to_{$endDate}.pdf";

        return Pdf::loadView('reports.pdf', $report)
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }

    private function resolveFilters(Request $request, $stationOptions = null, bool $strict = false): array
    {
        $stationRules = [$strict ? 'required' : 'nullable', 'string', 'max:255'];
        if ($strict) {
            $stationRules[] = Rule::in(collect(['all'])->merge($stationOptions ?? collect())->all());
        }

        $validated = $request->validate([
            'start_date' => [$strict ? 'required' : 'nullable', 'date'],
            'end_date' => [$strict ? 'required' : 'nullable', 'date', 'after_or_equal:start_date'],
            'station' => $stationRules,
        ]);

        $station = $validated['station'] ?? 'all';

        // A bookmarked filter may point to a road that has since been deleted.
        // Fall back to the complete current dataset instead of retaining a stale option.
        if (! $strict && $station !== 'all' && $stationOptions !== null && ! $stationOptions->contains($station)) {
            $station = 'all';
        }

        return [
            $validated['start_date'] ?? today()->format('Y-m-d'),
            $validated['end_date'] ?? today()->format('Y-m-d'),
            $station,
        ];
    }

    private function buildReportData(string $startDate, string $endDate, string $station): array
    {
        $start = Carbon::parse($startDate, 'Asia/Manila')->startOfDay()->utc();
        $end = Carbon::parse($endDate, 'Asia/Manila')->endOfDay()->utc();

        $manualCounts = ManualCount::query()
            ->whereBetween('count_date', [$startDate, $endDate])
            ->when($station !== 'all', fn ($query) => $query->where('road_name', $station))
            ->orderBy('hour_start')
            ->get();

        $autoSessions = AutoSession::query()
            ->with('detail')
            ->where('status', 'ended')
            ->whereHas('detail')
            ->whereBetween('start_time', [$start, $end])
            ->when($station !== 'all', function ($query) use ($station) {
                $query->where(function ($stationQuery) use ($station) {
                    $stationQuery->where('road_name', $station)
                        ->orWhere('location', $station)
                        ->orWhere(function ($fallbackQuery) use ($station) {
                            $fallbackQuery->whereNull('location')->where('camera_name', $station);
                        });
                });
            })
            ->orderBy('start_time')
            ->get();

        $hourlyData = $this->combineAllData($manualCounts, $autoSessions);
        $totalPcu = $hourlyData->sum('total_pcu');
        $avgVc = $hourlyData->avg('vc_ratio') ?: 0;
        $maxLos = $this->getMaxLos($hourlyData);
        $peakHourPcu = $hourlyData->max('total_pcu') ?: 0;

        return [
            'hourlyData' => $hourlyData,
            'totalPcu' => $totalPcu,
            'avgVc' => $avgVc,
            'maxLos' => $maxLos,
            'peakHourPcu' => $peakHourPcu,
            'totalRecords' => $hourlyData->count(),
            'startDate' => $startDate,
            'endDate' => $endDate,
            'station' => $station,
            'stationLabel' => $station === 'all' ? 'All Stations / Roads' : $station,
            'dateRangeLabel' => Carbon::parse($startDate)->format('F j, Y').($startDate !== $endDate ? ' - '.Carbon::parse($endDate)->format('F j, Y') : ''),
            'generatedAt' => now('Asia/Manila')->format('F j, Y g:i A'),
            'chartLabels' => $hourlyData->pluck('hour')->toArray(),
            'chartData' => $hourlyData->pluck('total_pcu')->map(fn ($value) => (float) $value)->toArray(),
        ];
    }

    private function stationOptions()
    {
        $manualRoads = ManualCount::query()
            ->whereNotNull('road_name')
            ->where('road_name', '<>', '')
            ->pluck('road_name');

        $scheduledRoads = Schedule::query()
            ->whereNotNull('road_name')
            ->where('road_name', '<>', '')
            ->pluck('road_name');

        return $manualRoads
            ->merge($scheduledRoads)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique(fn ($value) => mb_strtolower($value))
            ->sort(fn ($a, $b) => strcasecmp($a, $b))
            ->values();
    }

    private function combineAllData($manualCounts, $autoSessions)
    {
        $data = collect();

        foreach ($manualCounts as $count) {
            $counts = is_array($count->counts) ? $count->counts : (json_decode($count->counts, true) ?: []);

            $data->push($this->reportRow(
                Carbon::parse($count->hour_start),
                Carbon::parse($count->hour_end),
                $count->road_name,
                $counts,
                $count->total_pcu,
                $count->vc_ratio,
                $count->los,
                $count->day_classification,
                'manual'
            ));
        }

        foreach ($autoSessions as $session) {
            if (! $session->detail) {
                continue;
            }

            $counts = is_array($session->detail->counts) ? $session->detail->counts : (json_decode($session->detail->counts, true) ?: []);
            $start = Carbon::parse($session->start_time)->setTimezone('Asia/Manila');
            $end = $session->end_time
                ? Carbon::parse($session->end_time)->setTimezone('Asia/Manila')
                : $start->copy()->addHour();

            $data->push($this->reportRow(
                $start,
                $end,
                $session->road_name ?: ($session->location ?: $session->camera_name),
                $counts,
                $session->detail->total_pcu,
                $session->detail->vc_ratio,
                $session->detail->los,
                $session->day_classification,
                'auto'
            ));
        }

        return $data->sortBy('datetime')->values();
    }

    private function reportRow(Carbon $start, Carbon $end, string $station, array $counts, $totalPcu, $vcRatio, $los, ?string $dayClassification, string $source): array
    {
        return [
            'hour' => $start->format('H:i').'-'.$end->format('H:i'),
            'datetime' => $start->toDateTimeString(),
            'road_name' => $station,
            'private' => $counts[0] ?? 0,
            'jeepney' => $counts[1] ?? 0,
            'van' => $counts[2] ?? 0,
            'bus' => $counts[3] ?? 0,
            'truck2' => $counts[4] ?? 0,
            'truck3' => $counts[5] ?? 0,
            'motorcycle' => $counts[6] ?? 0,
            'tricycle' => $counts[7] ?? 0,
            'total_pcu' => $totalPcu ?? 0,
            'vc_ratio' => $vcRatio ?? 0,
            'los' => $los ?: 'N/A',
            'day_classification' => match ($dayClassification) {
                'market_day' => 'Market Day',
                'sunday' => 'Sunday',
                default => 'Not specified',
            },
            'source' => $source,
        ];
    }

    private function getMaxLos($hourlyData): string
    {
        $losOrder = ['A' => 1, 'B' => 2, 'C' => 3, 'D' => 4, 'E' => 5, 'F' => 6];
        $max = $hourlyData->sortByDesc(fn ($item) => $losOrder[$item['los']] ?? 0)->first();

        return $max['los'] ?? 'N/A';
    }

    private function resolvePeriodType(Request $request, string $startDate, string $endDate): string
    {
        $requested = $request->string('period_type')->toString();
        if (in_array($requested, ['date', 'month', 'range'], true)) {
            return $requested;
        }

        if ($startDate === $endDate) {
            return 'date';
        }

        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        if ($start->isSameMonth($end) && $start->isStartOfMonth() && $end->isEndOfMonth()) {
            return 'month';
        }

        return 'range';
    }

    private function csvSafe(string $value): string
    {
        return preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
    }
}
