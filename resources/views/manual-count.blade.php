<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>VehiSights — Manual Counting</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
@vite('resources/css/pages/manual-count.css')
</head>
<body>
<div class="layout">
  <aside class="sidebar">
    <div class="sidebar-brand"><div class="brand-row"><div class="brand-icon"><svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg></div><div><div class="brand-name">VehiSights</div><div class="brand-tag">Traffic Intelligence</div></div></div></div>
    <nav class="nav">
  <div class="nav-section-label">Monitor</div>
  <a href="{{ url('/dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
    <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
    <span>Dashboard</span>
  </a>

  <div class="nav-section-label">Manual Counting</div>
  <a href="{{ url('/manual-count') }}" class="nav-item active">
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
    <a href="{{ url('/predictions') }}" class="nav-item">
    <svg viewBox="0 0 24 24"><path d="M2 20L22 20"/><path d="M4 16L7 16"/><path d="M10 16L14 16"/><path d="M17 16L20 16"/><path d="M7 12L11 12"/><path d="M13 12L17 12"/><path d="M4 8L8 8"/><path d="M10 8L14 8"/><path d="M16 8L20 8"/></svg>
    <span>Predictions</span>
  </a>
  <a href="{{ url('/reports') }}" class="nav-item">
    <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
    <span>Reports</span>
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
      <div class="topbar-left"><h1>Manual Vehicle Count</h1><div class="subtitle">LTFRB/DILG compliant tally sheet</div></div>
      <div class="topbar-right">
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
      <div class="form-panel">
        <div class="form-row">
          <div class="form-group"><label>Station / Road Name</label><input type="text" id="roadName" placeholder="e.g., Timog Avenue Intersection"></div>
          <div class="form-group"><label>Barangay</label><input type="text" id="barangay" placeholder="Barangay"></div>
          <div class="form-group"><label>City/Municipality</label><input type="text" id="city" value="Tagum City"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Direction From</label><input type="text" id="dirFrom" placeholder="From: Bincungan"></div>
          <div class="form-group"><label>Direction To</label><input type="text" id="dirTo" placeholder="To: Tagum"></div>
          <div class="form-group"><label>Date</label><input type="date" id="date" value="{{ date('Y-m-d') }}"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Hour Interval (Start)</label><input type="time" id="hourStart" value="06:00"></div>
          <div class="form-group"><label>Hour Interval (End)</label><input type="time" id="hourEnd" value="07:00"></div>
          <div class="form-group"><label>Has median?</label>
            <select id="hasMedian">
              <option value="0">No median (720 PCU/lane/hr)</option>
              <option value="1">With median (900 PCU/lane/hr)</option>
            </select>
          </div>
          <div class="form-group"><label>Number of Lanes</label><input type="number" id="lanes" value="2" min="1"></div>
        </div>

        <table class="vehicle-table">
          <thead>
            <tr><th>Vehicle Type</th><th>PCU Factor</th><th style="width:140px">Count</th><th>Total PCU</th></tr>
          </thead>
          <tbody id="vehicleRows"></tbody>
        </table>

        <div class="results-box">
          <div class="result-item"><span class="result-label">Base Capacity (per lane)</span><span class="result-value" id="baseCapDisplay">720</span></div>
          <div class="result-item"><span class="result-label">Total PCU</span><span class="result-value" id="totalPcuDisplay">0</span></div>
          <div class="result-item"><span class="result-label">Total Road Capacity</span><span class="result-value" id="totalCapacityDisplay">1440</span></div>
          <div class="result-item"><span class="result-label">V/C Ratio</span><span class="result-value" id="vcRatioDisplay">0.00</span></div>
          <div class="result-item"><span class="result-label">Level of Service (LOS)</span><span id="losDisplay" class="los-badge-large">A</span></div>
          <button class="btn-submit" id="submitManualCount">Save Manual Count</button>
        </div>
      </div>

      <div class="form-panel" style="margin-top: 0;">
        <h3 style="margin-bottom: 12px; font-size:0.9rem;">ℹ️ How to use</h3>
        <div class="help-text">
          <p>Use the + / - buttons or type a number directly. The PCU and V/C ratio are automatically calculated based on standard PCU factors from LTFRB/DILG (Private=1.0, Jeepney=1.5, Van=1.5, Bus=2.5, Light Truck=2.0, Heavy Truck=3.0, Motorcycle=0.5, Tricycle=0.7). Road capacity follows DPWH standards: no median = 720 PCU/lane/hr, with median = 900 PCU/lane/hr.</p>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- CUSTOM SUCCESS MODAL -->
<div id="successModal" class="modal-overlay">
  <div class="modal">
    <div class="modal-icon">
      <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
    </div>
    <div class="modal-title">Manual Count Saved!</div>
    <div class="modal-message" id="successMessage">Your manual count has been successfully saved to the database.</div>
    <div class="modal-buttons">
      <button class="btn-primary" id="closeSuccessModalBtn">OK</button>
    </div>
  </div>
</div>

<script type="application/json" id="page-data">@json(['storeUrl' => route('manual-count.store')])</script>
@vite('resources/js/pages/manual-count.js')
</body>
</html>