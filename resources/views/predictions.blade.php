<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>VehiSights — Traffic Predictions</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@vite(['resources/css/pages/predictions.css', 'resources/js/ui-enhancements.js'])
</head>
<body>
<div class="layout">
  <aside class="sidebar">
    <div class="sidebar-brand"><div class="brand-row"><div class="brand-icon"><svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg></div><div><div class="brand-name">VehiSights</div><div class="brand-tag">Traffic Intelligence</div></div></div></div>
    <nav class="nav">
      <div class="nav-section-label">Monitor</div>
      <a href="{{ url('/dashboard') }}" class="nav-item">
        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
        <span>Dashboard</span>
      </a>

      <div class="nav-section-label">Manual Counting</div>
      <a href="{{ url('/manual-count') }}" class="nav-item">
        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 6h18M8 6V4h8v2M10 11v6M14 11v6"/></svg>
        <span>Manual Sessions</span>
      </a>
      <a href="{{ url('/manual-records') }}" class="nav-item">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
        <span>Manual Records</span>
      </a>

      <div class="nav-section-label">Auto Counting</div>
      <a href="{{ url('/schedules') }}" class="nav-item">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        <span>Scheduling</span>
      </a>
      <a href="{{ url('/auto-records') }}" class="nav-item">
        <svg viewBox="0 0 24 24"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
        <span>Auto Records</span>
      </a>

      <div class="nav-section-label">Analysis</div>
       <a href="{{ url('/predictions') }}" class="nav-item active">
        <svg viewBox="0 0 24 24"><path d="M2 20L22 20"/><path d="M4 16L7 16"/><path d="M10 16L14 16"/><path d="M17 16L20 16"/><path d="M7 12L11 12"/><path d="M13 12L17 12"/><path d="M4 8L8 8"/><path d="M10 8L14 8"/><path d="M16 8L20 8"/></svg>
        <span>Predictions</span>
      </a>
      <a href="{{ url('/reports') }}" class="nav-item">
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        <span>Reports</span>
      </a>
    </nav>
    <div class="sidebar-footer"><div class="user-card"><div class="user-avatar">{{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 2)) }}</div><div><div class="user-name">{{ auth()->user()->name ?? 'User' }}</div><div class="user-role">{{ str_replace('_', ' ', auth()->user()->role ?? 'user') }}</div></div></div></div>
  </aside>
  <main class="main">
    <div class="topbar">
      <div class="topbar-left"><h1>VehiSights Intelligence</h1><p>Predictive traffic insights</p></div>
      <div class="topbar-right">
        <div class="auto-refresh"><span class="pulse"></span> Auto-updates every 15 min</div>
        <div class="icon-btn"><svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg></div>
        <div class="theme-toggle" id="themeToggle">
          <svg class="icon-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/></svg>
          <svg class="icon-moon" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </div>
        <form method="POST" action="{{ route('logout') }}" style="display: inline;">
          @csrf
          <button type="submit" class="logout-btn" title="Logout"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></button>
        </form>
      </div>
    </div>
    <div class="content">
      <div class="wrap"
           data-forecast-url="{{ route('predictions.get') }}"
           data-train-url="{{ route('predictions.train') }}"
           data-model-ready="{{ $modelStatus['ready'] ? '1' : '0' }}">
        <div class="topbar-dash">
          <div class="topbar-dash-left">
            <h1>Traffic Prediction</h1>
            <p>Hybrid Random Forest PCU forecasting and LOS classification</p>
          </div>
          <div class="prediction-actions">
            <div class="confidence-pill" id="modelStatusPill"><div class="dot"></div>
              @if ($modelStatus['ready'])
                Model ready · {{ number_format($modelStatus['metrics']['validation_confidence'] ?? 0, 0) }}%
              @else
                {{ $modelStatus['available_rows'] }}/{{ $modelStatus['minimum_rows'] }} training rows
              @endif
            </div>
            <button type="button" class="train-model-btn" id="trainModelBtn">Train Model</button>
          </div>
        </div>

        <div class="filters">
          <div class="fg"><label for="stationSel">Station / Road</label><select id="stationSel" @disabled($roads->isEmpty())>@forelse($roads as $road)<option value="{{ $road }}">{{ $road }}</option>@empty<option value="">No registered roads</option>@endforelse</select></div>
          <div class="fg"><label for="dateSel">Forecast Date</label><input id="dateSel" type="date" value="{{ now()->toDateString() }}"></div>
          <div class="fg"><label for="windowSel">Forecast Window</label><select id="windowSel"><option value="24">Next 24 Hours</option><option value="12">Next 12 Hours</option><option value="6">Next 6 Hours</option></select></div>
        </div>

        <div class="kpi-grid">
          <div class="kpi"><div class="kpi-label">Current Traffic</div><div class="kpi-value" id="kpiCurrent">—</div><div class="kpi-sub" id="kpiCurrentSub">Latest recorded PCU</div></div>
          <div class="kpi"><div class="kpi-label">Predicted Next Hour</div><div class="kpi-value" id="kpiNext">—</div><div class="kpi-sub" id="kpiNextSub">Awaiting forecast</div></div>
          <div class="kpi"><div class="kpi-label">Peak Forecast</div><div class="kpi-value" id="kpiPeak">—</div><div class="kpi-sub" id="kpiPeakSub">Awaiting forecast</div></div>
          <div class="kpi"><div class="kpi-label">Validation Confidence</div><div class="kpi-value" id="kpiConf">—</div><div class="kpi-sub" id="kpiConfSub">Time-series validation estimate</div></div>
        </div>

        <div class="two-col">
          <div class="card"><div class="card-head"><h3>Historical Traffic</h3><span id="historicalChartRange">Latest 24 recorded hours</span></div><div class="card-body"><div class="legend"><div class="leg-item"><div class="leg-swatch" style="background:#7B1D1D;"></div>Recorded PCU</div></div><div class="chart-wrap"><canvas id="histChart"></canvas></div></div></div>
          <div class="card"><div class="card-head"><h3>Prediction Trend</h3><span id="predictionChartRange">Selected forecast window</span></div><div class="card-body"><div class="legend"><div class="leg-item"><div class="leg-swatch" style="background:#7B1D1D;"></div>Actual</div><div class="leg-item"><div class="leg-dashed" style="border-color:#b45309;"></div>Predicted</div></div><div class="chart-wrap"><canvas id="predChart"></canvas></div></div></div>
        </div>

        <div class="full-card"><div class="card-head"><h3 id="forecastTableTitle">Traffic Forecast</h3><span id="forecastStation">{{ $roads->first() ?? 'No registered road' }}</span></div>
          <div class="prediction-message" id="predictionMessage" aria-live="polite"></div>
          <table class="forecast-table"><thead><tr><th>Hour</th><th>PCU</th><th>V/C</th><th>LOS</th><th>Status</th><th>RFC Classification</th></tr></thead><tbody id="forecastBody"><tr><td colspan="6" class="prediction-empty">No forecast has been generated.</td></tr></tbody></table>
        </div>

        <div class="bottom-section">
          <div class="insight-box">
            <div class="insight-head">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#7B1D1D" stroke-width="2">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
              </svg>
              <span>Prediction Insights</span>
            </div>
            <div id="insightItems"><div class="insight-item">Train the model after sufficient traffic-history records have been collected.</div></div>
            <div style="margin-top:12px;"><div class="confidence-pill" id="insightConfidence"><div class="dot"></div>Model not trained</div></div>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

@vite('resources/js/pages/predictions.js')
</body>
</html>
