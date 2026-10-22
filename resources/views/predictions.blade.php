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
    <div class="sidebar-footer"><div class="user-card"><div class="user-avatar">JD</div><div><div class="user-name">Juan Dela Cruz</div><div class="user-role">Traffic Analyst</div></div></div></div>
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
      <div class="wrap">
        <div class="topbar-dash">
          <div class="topbar-dash-left">
            <h1>Traffic Prediction</h1>
            <p>AI-powered forecasts based on historical patterns</p>
          </div>
          <div class="confidence-pill"><div class="dot"></div>Model confidence: 89%</div>
        </div>

        <!-- Filters - No Run Forecast button -->
        <div class="filters">
          <div class="fg"><label>Station / Road</label><select id="stationSel" onchange="refreshForecast()"><option>Timog Avenue</option><option>Apokon Intersection</option><option>Pioneer Street</option></select></div>
          <div class="fg"><label>Date</label><select id="dateSel" onchange="refreshForecast()"><option>Today — Monday, Jun 2</option><option>Tomorrow — Tuesday, Jun 3</option><option>Wednesday, Jun 4</option></select></div>
          <div class="fg"><label>Forecast Window</label><select id="windowSel" onchange="refreshForecast()"><option>Next 24 Hours</option><option>Next 12 Hours</option><option>Next 6 Hours</option></select></div>
        </div>

        <!-- KPI Cards -->
        <div class="kpi-grid">
          <div class="kpi"><div class="kpi-label">Current Traffic</div><div class="kpi-value" id="kpiCurrent">845</div><div class="kpi-sub">PCU right now &nbsp;<span class="kpi-badge badge-up">+12% vs avg</span></div></div>
          <div class="kpi"><div class="kpi-label">Predicted Next Hour</div><div class="kpi-value" id="kpiNext">1,020</div><div class="kpi-sub">PCU at 5:00 PM &nbsp;<span class="kpi-badge badge-pred">Forecast</span></div></div>
          <div class="kpi"><div class="kpi-label">Peak Forecast</div><div class="kpi-value" id="kpiPeak">5:00 PM</div><div class="kpi-sub">Expected peak &nbsp;<span class="kpi-badge badge-up">LOS E</span></div></div>
          <div class="kpi"><div class="kpi-label">Forecast Confidence</div><div class="kpi-value" id="kpiConf">89%</div><div class="kpi-sub">Based on 52 Mondays &nbsp;<span class="kpi-badge badge-stable">High</span></div></div>
        </div>

        <!-- CHARTS ROW -->
        <div class="two-col">
          <div class="card"><div class="card-head"><h3>Historical Traffic</h3><span>Past 16 hours · Mondays avg</span></div><div class="card-body"><div class="legend"><div class="leg-item"><div class="leg-swatch" style="background:#7B1D1D;"></div>Historical avg</div></div><div class="chart-wrap"><canvas id="histChart"></canvas></div></div></div>
          <div class="card"><div class="card-head"><h3>Prediction Trend</h3><span>Next 8 hours · AI forecast</span></div><div class="card-body"><div class="legend"><div class="leg-item"><div class="leg-swatch" style="background:#7B1D1D;"></div>Actual</div><div class="leg-item"><div class="leg-dashed" style="border-color:#b45309;"></div>Predicted</div></div><div class="chart-wrap"><canvas id="predChart"></canvas></div></div></div>
        </div>

        <!-- FORECAST TABLE - Removed Risk column -->
        <div class="full-card"><div class="card-head"><h3>Traffic Forecast — Next 24 Hours</h3><span id="forecastStation">Timog Avenue</span></div>
          <table class="forecast-table"><thead><tr><th>Hour</th><th>PCU</th><th>V/C</th><th>LOS</th><th>Status</th><th>Type</th></tr></thead><tbody id="forecastBody"></tbody></table>
        </div>

        <!-- BOTTOM SECTION - Only Insights (removed Congestion Risk) -->
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
            <div class="insight-item">Traffic expected to surge 22% between 4PM–6PM today.</div>
            <div class="insight-item">LOS at Timog Avenue likely to degrade to E by 5PM.</div>
            <div class="insight-item">Based on 52 historical Mondays in the last 90 days.</div>
            <div class="insight-item">Heavy rain correlation detected — may increase by additional 8%.</div>
            <div style="margin-top:12px;"><div class="confidence-pill"><div class="dot"></div>Confidence: 89%</div></div>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

@vite('resources/js/pages/predictions.js')
</body>
</html>
