<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>VehiSights — Reports</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
@vite('resources/css/pages/reports.css')
</head>
<body>
<div class="layout">
  <aside class="sidebar">
    <div class="sidebar-brand">
      <div class="brand-row">
        <div class="brand-icon"><svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg></div>
        <div><div class="brand-name">VehiSights</div><div class="brand-tag">Traffic Intelligence</div></div>
      </div>
    </div>
    <nav class="nav">
      <div class="nav-section-label">Monitor</div>
      <a href="{{ url('/dashboard') }}" class="nav-item"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg><span>Dashboard</span></a>

      <div class="nav-section-label">Manual Counting</div>
      <a href="{{ url('/manual-count') }}" class="nav-item"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 6h18M8 6V4h8v2M10 11v6M14 11v6"/></svg><span>Manual Sessions</span></a>
      <a href="{{ url('/manual-records') }}" class="nav-item"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg><span>Manual Records</span></a>

      <div class="nav-section-label">Auto Counting</div>
      <a href="{{ url('/schedules') }}" class="nav-item"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><span>Scheduling</span></a>
      <a href="{{ url('/auto-records') }}" class="nav-item"><svg viewBox="0 0 24 24"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg><span>Auto Records</span></a>

      <div class="nav-section-label">Analysis</div>
       <a href="{{ url('/predictions') }}" class="nav-item"><svg viewBox="0 0 24 24"><path d="M2 20L22 20"/><path d="M4 16L7 16"/><path d="M10 16L14 16"/><path d="M17 16L20 16"/><path d="M7 12L11 12"/><path d="M13 12L17 12"/><path d="M4 8L8 8"/><path d="M10 8L14 8"/><path d="M16 8L20 8"/></svg><span>Predictions</span></a>
      <a href="{{ url('/reports') }}" class="nav-item active"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg><span>Reports</span></a>
    </nav>
    <div class="sidebar-footer">
      <div class="user-card">
        <div class="user-avatar">{{ strtoupper(substr(Auth::user()->first_name ?? 'JD', 0, 1)) }}{{ strtoupper(substr(Auth::user()->last_name ?? '', 0, 1)) }}</div>
        <div><div class="user-name">{{ Auth::user()->first_name ?? 'Juan' }} {{ Auth::user()->last_name ?? 'Dela Cruz' }}</div><div class="user-role">{{ Auth::user()->role ?? 'Traffic Analyst' }}</div></div>
      </div>
    </div>
  </aside>

  <main class="main">
    <div class="topbar">
      <div class="topbar-left"><h1>Reports</h1><div class="subtitle">Generate LTFRB/DILG vehicle count surveys</div></div>
      <div class="topbar-right">
        <div class="icon-btn"><svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg><div class="notif-dot"></div></div>
        <div class="theme-toggle" id="themeToggle"><svg class="icon-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg><svg class="icon-moon" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></div>
        <form method="POST" action="{{ route('logout') }}" style="display: inline;">@csrf<button type="submit" class="logout-btn" title="Logout"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></button></form>
      </div>
    </div>

    <div class="content">
      <!-- FILTERS -->
      <div class="filters-bar">
        <div class="filter-group">
          <label>Date From</label>
          <input type="date" id="start_date" value="{{ $startDate }}">
        </div>
        <div class="filter-group">
          <label>Date To</label>
          <input type="date" id="end_date" value="{{ $endDate }}">
        </div>
        <div class="filter-group">
          <label>Station / Road</label>
          <select id="station">
            <option value="all" {{ $station == 'all' ? 'selected' : '' }}>All Stations</option>
            @foreach($stations as $stat)
              <option value="{{ $stat }}" {{ $station == $stat ? 'selected' : '' }}>{{ $stat }}</option>
            @endforeach
          </select>
        </div>
        <button class="btn-primary" id="applyFilters">Apply Filters</button>
        <button class="btn-secondary" id="generateFormBtn">Generate Survey Form</button>
        <button class="btn-secondary" id="exportCsvBtn">Export CSV</button>
        <button class="btn-secondary" id="exportPdfBtn">Export PDF</button>
      </div>

      <!-- SUMMARY CARDS -->
      <div class="stats-row">
        <div class="stat-card c-green"><div class="stat-label">Total PCU</div><div class="stat-value" id="totalPcu">{{ number_format($totalPcu) }}</div></div>
        <div class="stat-card c-amber"><div class="stat-label">Avg V/C Ratio</div><div class="stat-value" id="avgVc">{{ number_format($avgVc, 2) }}</div></div>
        <div class="stat-card c-red"><div class="stat-label">Max LOS</div><div class="stat-value" id="maxLos">{{ $maxLos }}</div></div>
        <div class="stat-card c-blue"><div class="stat-label">Peak Hour PCU</div><div class="stat-value" id="peakPcu">{{ number_format($peakHourPcu) }}</div></div>
      </div>

      <!-- CHART -->
      <div class="chart-panel">
        <div class="panel-header"><h3>Hourly PCU Trend</h3><div class="badge">PCU / hour</div></div>
        <div class="chart-wrap">
          <canvas id="trendChart" style="display: {{ count($chartData) > 0 ? 'block' : 'none' }}"></canvas>
          <div id="chartEmptyState" class="chart-empty-state" style="display: {{ count($chartData) > 0 ? 'none' : 'flex' }}; flex-direction: column; align-items: center; justify-content: center; text-align: center;">
  <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin: 0 auto;">
    <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
    <path d="M16 2v4h4"/>
  </svg>
  <p style="margin-top: 12px;">No data available for the selected period</p>
  <small>Try changing the date range or add manual counts</small>
</div>
        </div>
      </div>

      <!-- HOURLY BREAKDOWN TABLE -->
      <div class="table-panel">
        <div class="panel-header">
          <h3>Hourly Breakdown (LTFRB/DILG Format)</h3>
          <div class="export-buttons">
            <button class="btn-secondary" id="copyTableBtn">Copy Table</button>
          </div>
        </div>
        <div style="overflow-x: auto;">
          <table class="hourly-table" id="hourlyTable">
            <thead>
              <tr>
                <th>Hour</th>
                <th>Station</th>
                <th>Private Car / Taxi / Pick-up</th>
<th>Jeepney</th>
<th>Van</th>
<th>Bus</th>
<th>Light Truck</th>
<th>Heavy Truck</th>
<th>Motorcycle</th>
<th>Tricycle</th>
                <th>Total PCU</th>
                <th>V/C Ratio</th>
                <th>LOS</th>
                <th>Source</th>
              </tr>
            </thead>
            <tbody id="reportTableBody">
              @forelse($hourlyData as $row)
              <tr>
                <td style="font-family:'DM Mono',monospace;">{{ $row['hour'] }}</td>
                <td>{{ $row['road_name'] }}</td>
                <td style="font-family:'DM Mono',monospace;">{{ number_format($row['private']) }}</td>
                <td style="font-family:'DM Mono',monospace;">{{ number_format($row['jeepney']) }}</td>
                <td style="font-family:'DM Mono',monospace;">{{ number_format($row['van']) }}</td>
                <td style="font-family:'DM Mono',monospace;">{{ number_format($row['bus']) }}</td>
                <td style="font-family:'DM Mono',monospace;">{{ number_format($row['truck2']) }}</td>
                <td style="font-family:'DM Mono',monospace;">{{ number_format($row['truck3']) }}</td>
                <td style="font-family:'DM Mono',monospace;">{{ number_format($row['motorcycle']) }}</td>
                <td style="font-family:'DM Mono',monospace;">{{ number_format($row['tricycle']) }}</td>
                <td style="font-family:'DM Mono',monospace; font-weight:700;">{{ number_format($row['total_pcu'], 2) }}</td>
                <td style="font-family:'DM Mono',monospace;">{{ number_format($row['vc_ratio'], 2) }}</td>
                <td><span class="los-badge los-{{ strtolower($row['los']) }}">{{ $row['los'] }}</span></td>
                <td><span class="source-badge {{ $row['source'] }}">{{ ucfirst($row['source']) }}</span></td>
              </tr>
              @empty
              <tr>
                <td colspan="14" class="empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        <path d="M16 2v4h4"/>
                    </svg>
                    <p>No data found for the selected date range.</p>
                    <p style="font-size:0.7rem; margin-top:8px;">Try changing the filters or add manual counts.</p>
                </td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- MODAL for Survey Form Preview -->
<div id="surveyModal" class="modal-overlay">
  <div class="modal">
    <div class="modal-header">
      <h3>LTFRB/DILG Vehicle Count Survey Form</h3>
      <button class="close-modal" id="closeModalBtn">&times;</button>
    </div>
    <div id="surveyContent" class="survey-form"></div>
    <div class="export-buttons" style="margin-top:20px;">
      <button class="btn-primary" id="printSurveyBtn">Print / Save as PDF</button>
    </div>
  </div>
</div>

<script type="application/json" id="page-data">@json(['chartLabels' => $chartLabels, 'chartData' => $chartData])</script>
@vite('resources/js/pages/reports.js')
</body>
</html>