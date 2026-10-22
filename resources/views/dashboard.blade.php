<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>VehiSights — Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@vite(['resources/css/pages/dashboard.css', 'resources/js/ui-enhancements.js'])
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
      <a href="{{ url('/dashboard') }}" class="nav-item active">
        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
        <span>Dashboard</span>
      </a>
      <div class="nav-section-label">Manual Counting</div>
      <a href="{{ url('/manual-count') }}" class="nav-item"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 6h18M8 6V4h8v2M10 11v6M14 11v6"/></svg><span>Manual Sessions</span></a>
      <a href="{{ url('/manual-records') }}" class="nav-item"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg><span>Manual Records</span></a>
      <div class="nav-section-label">Auto Counting</div>
      <a href="{{ url('/schedules') }}" class="nav-item"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><span>Scheduling</span></a>
      <a href="{{ url('/auto-records') }}" class="nav-item"><svg viewBox="0 0 24 24"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg><span>Auto Records</span></a>
      <div class="nav-section-label">Analysis</div>
      <a href="{{ url('/predictions') }}" class="nav-item"><svg viewBox="0 0 24 24"><path d="M2 20L22 20"/><path d="M4 16L7 16"/><path d="M10 16L14 16"/><path d="M17 16L20 16"/><path d="M7 12L11 12"/><path d="M13 12L17 12"/><path d="M4 8L8 8"/><path d="M10 8L14 8"/><path d="M16 8L20 8"/></svg><span>Predictions</span></a>
      <a href="{{ url('/reports') }}" class="nav-item"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg><span>Reports</span></a>
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
      <div class="topbar-left"><h1>Overview</h1><div class="subtitle">{{ now()->format('l, F j, Y') }} — Traffic Analytics</div></div>
      <div class="topbar-right">
        <div class="status-pill"><div class="pulse"></div><span id="activeCamerasCount">{{ $dashboardData['active_cameras'] ?? 0 }}</span> cameras live</div>
        <div class="icon-btn"><svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg><div class="notif-dot"></div></div>
        <div class="theme-toggle" id="themeToggle"><svg class="icon-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg><svg class="icon-moon" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></div>
        <form method="POST" action="{{ route('logout') }}" style="display: inline;">@csrf<button type="submit" class="logout-btn"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></button></form>
      </div>
    </div>

    <div class="content">
      <div class="date-filter-panel">
        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
          <div style="display: flex; align-items: center; gap: 8px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
              <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
            <span style="font-size: 0.75rem; font-weight: 600; color: var(--text-dim);">DATE RANGE</span>
          </div>
          <div style="display: flex; align-items: center; gap: 8px;">
            <input type="date" id="start_date" value="{{ $selectedStartDate }}">
            <span style="color: var(--text-muted);">→</span>
            <input type="date" id="end_date" value="{{ $selectedEndDate }}">
          </div>
          <div style="display: flex; gap: 8px;">
            <button id="applyFilterBtn" style="background: var(--maroon); color: white; border: none; padding: 8px 16px; border-radius: 8px; font-size: 0.75rem; font-weight: 600; cursor: pointer;">Apply Filter</button>
            <button id="resetTodayBtn" style="background: var(--surface2); border: 1px solid var(--border); padding: 8px 16px; border-radius: 8px; font-size: 0.75rem; cursor: pointer;">Today</button>
          </div>
        </div>  
      </div>

      <div class="stats-row" id="statsRow">
        <div class="stat-card c-green"><div class="stat-icon c-green"><svg viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div><div class="stat-label">Total PCU</div><div class="stat-value" id="totalPcu">{{ number_format($dashboardData['total_pcu'] ?? 0) }}</div><div class="stat-meta up" id="totalPcuMeta">{{ \Carbon\Carbon::parse($selectedStartDate)->format('M j') }}{{ $selectedStartDate !== $selectedEndDate ? ' – '.\Carbon\Carbon::parse($selectedEndDate)->format('M j') : '' }}</div></div>
        <div class="stat-card c-amber"><div class="stat-icon c-amber"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg></div><div class="stat-label">Avg V/C Ratio</div><div class="stat-value" id="avgVc">{{ number_format($dashboardData['avg_vc'] ?? 0, 2) }}</div><div class="stat-meta warn" id="avgVcMeta">{{ ($dashboardData['avg_vc'] ?? 0) >= 0.7 ? 'Level D/E — heavy' : (($dashboardData['avg_vc'] ?? 0) >= 0.45 ? 'Level C — moderate' : 'Level A/B — light') }}</div></div>
        <div class="stat-card c-red"><div class="stat-icon c-red"><svg viewBox="0 0 24 24"><polygon points="7.86 2 16.14 2 22 7.86 22 16.14 16.14 22 7.86 22 2 16.14 2 7.86 7.86 2"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div><div class="stat-label">Peak LOS</div><div class="stat-value" id="peakLos">{{ $dashboardData['peak_los'] ?? 'N/A' }}</div><div class="stat-meta danger" id="peakLosMeta">{{ in_array($dashboardData['peak_los'] ?? 'N/A', ['A', 'B']) ? 'Free flow' : (($dashboardData['peak_los'] ?? 'N/A') === 'C' ? 'Stable near capacity' : (($dashboardData['peak_los'] ?? 'N/A') === 'D' ? 'Approaching unstable' : 'Near capacity/Breakdown')) }}</div></div>
        <div class="stat-card c-blue"><div class="stat-icon c-blue"><svg viewBox="0 0 24 24"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></div><div class="stat-label">Active Cameras</div><div class="stat-value" id="activeCameras">{{ $dashboardData['active_cameras'] ?? 0 }}</div><div class="stat-meta">Camera status</div></div>
      </div>

      <div class="mid-row">
        <div class="panel"><div class="panel-header"><div><div class="panel-title">Hourly PCU Volume</div><div class="panel-sub">Passenger Car Units per hour</div></div></div><div class="chart-wrap"><canvas id="trafficChart"></canvas></div></div>
        <div class="panel"><div class="panel-header"><div><div class="panel-title">Recent Manual Counts</div><div class="panel-sub">Latest entries</div></div></div><div id="recentManualContainer" class="dashboard-data-region" aria-live="polite">@if (!empty($dashboardData['recent_manual']))<table class="vtable"><thead><tr><th>Time</th><th>Station</th><th>PCU</th><th>LOS</th></tr></thead><tbody>@foreach ($dashboardData['recent_manual'] as $record)<tr><td class="mono-cell">{{ $record['time'] }}</td><td>{{ $record['station'] }}</td><td class="mono-cell">{{ number_format($record['pcu']) }}</td><td><span class="los-badge los-{{ strtolower($record['los']) }}">{{ $record['los'] }}</span></td></tr>@endforeach</tbody></table><div class="panel-footer-link"><a href="{{ url('/manual-records') }}" class="view-all">View all →</a></div>@else<div class="dashboard-empty-state">No manual counts found</div>@endif</div></div>
      </div>

      <div class="bottom-row">
        <div class="panel"><div class="panel-header"><div><div class="panel-title">Active Sessions</div><div class="panel-sub">Live road conditions</div></div><div class="status-pill" style="font-size:0.7rem;padding:5px 11px;"><div class="pulse"></div>Live</div></div><div id="activeSessionsContainer" class="dashboard-data-region" aria-live="polite">@if (!empty($dashboardData['active_sessions']))<table class="vtable"><thead><tr><th>Session</th><th>Road</th><th>V/C</th><th>LOS</th><th>Status</th></tr></thead><tbody>@foreach ($dashboardData['active_sessions'] as $session)<tr><td class="mono-cell">{{ $session['code'] }}</td><td>{{ $session['road'] }}</td><td><div class="vc-wrap"><span class="vc-num">{{ $session['vc'] }}</span><div class="vc-track"><div class="vc-fill" style="background:{{ (float) $session['vc'] >= 0.85 ? '#dc2626' : '#16a34a' }};width:{{ min((float) $session['vc'] * 100, 100) }}%"></div></div></div></td><td><span class="los-badge los-{{ strtolower($session['los'] ?? 'a') }}">{{ $session['los'] ?? 'N/A' }}</span></td><td><span class="status-tag ok"><span class="dot"></span>Active</span></td></tr>@endforeach</tbody></table>@else<div class="dashboard-empty-state">No active sessions</div>@endif</div></div>
        <div class="panel"><div class="panel-header"><div><div class="panel-title">Recent Auto Counts</div><div class="panel-sub">From cameras</div></div><a href="{{ url('/auto-records') }}" class="view-all">View all →</a></div><div id="recentAutoContainer" class="dashboard-data-region" aria-live="polite">@if (!empty($dashboardData['recent_auto']))<table class="vtable"><thead><tr><th>Time</th><th>Camera</th><th>PCU</th><th>LOS</th></tr></thead><tbody>@foreach ($dashboardData['recent_auto'] as $record)<tr><td class="mono-cell">{{ $record['time'] }}</td><td>{{ $record['camera'] }}</td><td class="mono-cell">{{ $record['pcu'] }}</td><td><span class="los-badge los-{{ strtolower($record['los'] ?? 'a') }}">{{ $record['los'] ?? '?' }}</span></td></tr>@endforeach</tbody></table>@else<div class="dashboard-empty-state">No auto counts yet</div>@endif</div></div>
      </div>
    </div>
  </main>
</div>

<script type="application/json" id="dashboard-data">@json($dashboardData)</script>
@vite('resources/js/pages/dashboard.js')
</body>
</html>
