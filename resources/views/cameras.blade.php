<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>VehiSights — Cameras</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
@vite('resources/css/pages/cameras.css')
</head>
<body>
<div class="layout">

  <!-- SIDEBAR (dynamic user) -->
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
      <div class="topbar-left">
        <h1>Cameras</h1>
        <div class="subtitle">Manage IP cameras for vehicle detection</div>
      </div>
      <div class="topbar-right">
        <div class="icon-btn">
          <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
          <div class="notif-dot"></div>
        </div>
        <div class="theme-toggle" id="themeToggle">
          <svg class="icon-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
          <svg class="icon-moon" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </div>
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

      <!-- STAT CARDS — same style as dashboard, 3 side by side -->
      <div class="stats-row" id="statsRow"></div>

      <!-- TOOLBAR: search + pills in one row -->
      <div class="toolbar">
        <div class="search-wrap">
          <svg class="s-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" id="searchInput" class="search-input" placeholder="Search by name, IP or location…">
        </div>
        <div class="filter-pills">
          <button class="pill active" data-filter="all">All</button>
          <button class="pill" data-filter="online">
            <span class="pill-dot" style="background:var(--green)"></span>Online
          </button>
          <button class="pill" data-filter="offline">
            <span class="pill-dot" style="background:var(--red)"></span>Offline
          </button>
        </div>
        <div class="toolbar-right">
          <button class="btn-secondary" id="refreshBtn">
            <svg viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
            Refresh
          </button>
          <button class="btn-primary" id="newCameraBtn">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Camera
          </button>
        </div>
      </div>

      <!-- TABLE -->
      <div class="table-wrapper">
        <table class="cameras-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Camera</th>
              <th>IP Address</th>
              <th>Location</th>
              <th>Status</th>
              <th>Last Detection</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="camerasTableBody"></tbody>
        </table>
      </div>

    </div>
  </main>
</div>

<!-- MODAL -->
<div id="cameraModal" class="modal-overlay">
  <div class="modal">
    <div class="modal-header">
      <h3 id="modalTitle">Add Camera</h3>
      <button class="modal-close" id="closeModalBtn">
        <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form id="cameraForm">
      <div class="form-row">
        <div class="form-group">
          <label>Camera Name</label>
          <input type="text" id="camName" placeholder="e.g. Timog Ave North" required>
        </div>
        <div class="form-group">
          <label>IP Address</label>
          <input type="text" id="camIp" placeholder="192.168.1.100" required>
        </div>
      </div>
      <div class="form-group">
        <label>Location</label>
        <input type="text" id="camLocation" placeholder="e.g. Timog & National Highway" required>
      </div>
      <div class="form-group">
        <label>Stream URL (RTSP / HTTP)</label>
        <input type="text" id="camStreamUrl" placeholder="rtsp://user:pass@ip:554/stream">
        <div class="input-hint">Leave blank if not yet configured</div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn-secondary" id="cancelModalBtn">Cancel</button>
        <button type="submit" class="btn-primary">
          <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
          Save Camera
        </button>
      </div>
    </form>
  </div>
</div>

<!-- TOAST -->
<div id="toast" class="toast">
  <div class="toast-dot"></div>
  <span id="toastMsg"></span>
</div>

@vite('resources/js/pages/cameras.js')
</body>
</html>