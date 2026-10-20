<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>VehiSights — Live Traffic</title>
<!-- Use same fonts as dashboard -->
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@vite('resources/css/pages/live-traffic.css')
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
  <a href="{{ url('/dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
    <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
    <span>Dashboard</span>
  </a>

  <div class="nav-section-label">Manual Counting</div>
  <a href="{{ url('/manual-count') }}" class="nav-item {{ request()->routeIs('manual-count') ? 'active' : '' }}">
    <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 6h18M8 6V4h8v2M10 11v6M14 11v6"/></svg>
    <span>Manual Sessions</span>
  </a>
  <a href="{{ url('/manual-records') }}" class="nav-item {{ request()->routeIs('manual-records') ? 'active' : '' }}">
    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
    <span>Manual Records</span>
  </a>

  <div class="nav-section-label">Auto Counting</div>
  <a href="{{ url('/schedules') }}" class="nav-item {{ request()->routeIs('schedules') ? 'active' : '' }}">
    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
    <span>Scheduling</span>
  </a>
  <a href="{{ url('/auto-records') }}" class="nav-item {{ request()->routeIs('auto-records') ? 'active' : '' }}">
    <svg viewBox="0 0 24 24"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
    <span>Auto Records</span>
  </a>

  <div class="nav-section-label">Analysis</div>
  <a href="{{ url('/reports') }}" class="nav-item {{ request()->routeIs('reports') ? 'active' : '' }}">
    <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
    <span>Reports</span>
  </a>
  <a href="{{ url('/predictions') }}" class="nav-item {{ request()->routeIs('predictions') ? 'active' : '' }}">
    <svg viewBox="0 0 24 24"><path d="M2 20L22 20"/><path d="M4 16L7 16"/><path d="M10 16L14 16"/><path d="M17 16L20 16"/><path d="M7 12L11 12"/><path d="M13 12L17 12"/><path d="M4 8L8 8"/><path d="M10 8L14 8"/><path d="M16 8L20 8"/></svg>
    <span>Predictions</span>
  </a>

  <div class="nav-section-label">Settings</div>
  <a href="{{ url('/admin') }}" class="nav-item {{ request()->routeIs('admin') ? 'active' : '' }}">
    <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
    <span>Admin</span>
  </a>
</nav>
    <div class="sidebar-footer">
      <div class="user-card">
        <div class="user-avatar">
          {{ strtoupper(substr(Auth::user()->first_name ?? 'JD', 0, 1)) }}{{ strtoupper(substr(Auth::user()->last_name ?? '', 0, 1)) }}
        </div>
        <div>
          <div class="user-name">{{ Auth::user()->first_name ?? 'Juan' }} {{ Auth::user()->last_name ?? 'Dela Cruz' }}</div>
          <div class="user-role">{{ Auth::user()->role ?? 'Traffic Analyst' }}</div>
        </div>
      </div>
    </div>
  </aside>

  <main class="main">
    <div class="topbar">
      <div class="topbar-left"><h1>Live Traffic</h1><div class="subtitle">Real‑time vehicle detections & camera feeds</div></div>
      <div class="topbar-right">
        <div class="status-pill"><div class="pulse"></div>Streaming live</div>
        <div class="icon-btn"><svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg><div class="notif-dot"></div></div>
        <div class="theme-toggle" id="themeToggle"><svg class="icon-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg><svg class="icon-moon" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></div>
        <!-- LOGOUT BUTTON -->
        <form method="POST" action="{{ route('logout') }}" style="display: inline;">
          @csrf
          <button type="submit" class="logout-btn" title="Logout">
            <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          </button>
        </form>
      </div>
    </div>

    <div class="content">
      <!-- STATS (initial values set to 0, will count up) -->
      <div class="stats-row">
        <div class="stat-card c-green"><div class="stat-icon c-green"><svg viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div><div class="stat-label">Current PCU (last hour)</div><div class="stat-value" id="livePcu" data-target="1847">0</div><div class="stat-meta up">▲ +8% vs previous hour</div></div>
        <div class="stat-card c-amber"><div class="stat-icon c-amber"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg></div><div class="stat-label">Live V/C Ratio</div><div class="stat-value" id="liveVc" data-target="0.68">0.00</div><div class="stat-meta warn">● Level C (stable near capacity)</div></div>
        <div class="stat-card c-red"><div class="stat-icon c-red"><svg viewBox="0 0 24 24"><polygon points="7.86 2 16.14 2 22 7.86 22 16.14 16.14 22 7.86 22 2 16.14 2 7.86 7.86 2"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div><div class="stat-label">Current LOS</div><div class="stat-value">C</div><div class="stat-meta danger">Approaching D</div></div>
        <div class="stat-card c-blue"><div class="stat-icon c-blue"><svg viewBox="0 0 24 24"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></div><div class="stat-label">Active Cameras</div><div class="stat-value">8</div><div class="stat-meta">All online</div></div>
      </div>

      <!-- CAMERAS GRID -->
      <div class="cameras-section">
        <h3>Cameras (Live feeds)</h3>
        <div class="cameras-grid" id="camerasGrid"></div>
      </div>

      <!-- LIVE DETECTIONS TABLE -->
      <div class="live-table-section">
        <h3>Recent Detections (real‑time)</h3>
        <div class="live-table-wrapper">
          <table class="live-table">
            <thead><tr><th>Timestamp</th><th>Camera</th><th>Vehicle Type</th><th>PCU</th><th>Lane/Dir</th><th>LOS impact</th></tr></thead>
            <tbody id="detectionsBody"></tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</div>

@vite('resources/js/pages/live-traffic.js')
</body>
</html>