<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>VehiSights — New Auto Session</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
@vite(['resources/css/pages/schedules.css', 'resources/js/ui-enhancements.js'])
</head>
<body>
<div class="layout">

  <!-- ═══ SIDEBAR ═══════════════════════════════════════════════ -->
  <aside class="sidebar">
    <div class="sidebar-brand">
      <div class="brand-row">
        <div class="brand-icon">
          <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
        </div>
        <div>
          <div class="brand-name">VehiSights</div>
          <div class="brand-tag">Traffic Intelligence</div>
        </div>
      </div>
    </div>

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
      <a href="{{ url('/schedules') }}" class="nav-item active">
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
          {{ strtoupper(substr(Auth::user()->first_name ?? 'J', 0, 1)) }}{{ strtoupper(substr(Auth::user()->last_name ?? 'D', 0, 1)) }}
        </div>
        <div>
          <div class="user-name">{{ Auth::user()->first_name ?? 'Juan' }} {{ Auth::user()->last_name ?? 'Dela Cruz' }}</div>
          <div class="user-role">{{ Auth::user()->role ?? 'Traffic Analyst' }}</div>
        </div>
      </div>
    </div>
  </aside>

  <!-- ═══ MAIN ═══════════════════════════════════════════════════ -->
  <main class="main">

    <!-- Topbar -->
    <div class="topbar">
      <div class="topbar-left">
        <h1>Auto Counting</h1>
        <div class="subtitle">Schedule automatic vehicle detection</div>
      </div>
      <div class="topbar-right">
        <div class="icon-btn">
          <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        </div>
        <div class="theme-toggle" id="themeToggle">
          <svg class="icon-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
          <svg class="icon-moon" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </div>
        <form method="POST" action="{{ route('logout') }}" style="display:inline;">
          @csrf
          <button type="submit" class="logout-btn">
            <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          </button>
        </form>
      </div>
    </div>

    <!-- Content -->
    <div class="content">
      <div class="panel">

        <div class="panel-header">
          <div class="panel-icon">
            <svg viewBox="0 0 24 24"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
          </div>
          <div>
            <div class="panel-title">New Auto Session</div>
            <div class="panel-subtitle">Schedule automatic vehicle detection via camera</div>
          </div>
        </div>

        <div class="panel-body">
          <form id="scheduleForm">
            @csrf

            <!-- Section 1: Location -->
            <div class="form-section">
              <div class="section-label">Location</div>
              <div class="grid-2">
                <div class="field">
                  <label>Station / Road Name <span class="req">*</span></label>
                  <div class="input-wrap">
                    <svg class="input-icon" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <input type="text" id="road_name" placeholder="e.g., Timog Avenue Intersection" required>
                  </div>
                </div>
                <div class="field">
                  <label>Barangay</label>
                  <input type="text" id="barangay" placeholder="e.g., Poblacion">
                </div>
              </div>
            </div>

            <!-- Section 2: Area & Direction (2 columns x 1 row for Direction From/To, then full width City) -->
            <div class="form-section">
              <div class="section-label">Area &amp; Direction</div>
              
              <!-- Row 1: Direction From and Direction To (2 columns) -->
              <div class="grid-2">
                <div class="field">
                  <label>Direction From <span class="req">*</span></label>
                  <div class="input-wrap">
                    <svg class="input-icon" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    <input type="text" id="direction_from" placeholder="e.g., Bincungan" required>
                  </div>
                </div>
                <div class="field">
                  <label>Direction To <span class="req">*</span></label>
                  <div class="input-wrap">
                    <svg class="input-icon" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                    <input type="text" id="direction_to" placeholder="e.g., Tagum" required>
                  </div>
                </div>
              </div>

              <!-- Row 2: City/Municipality (full width, kasing lapad ng Select Camera) -->
              <div class="grid-1" style="margin-top: 14px;">
                <div class="field">
                  <label>City / Municipality <span class="req">*</span></label>
                  <input type="text" id="city" placeholder="e.g., Sto. Tomas" value="Sto. Tomas" required>
                </div>
              </div>
            </div>

            <!-- Section 3: Schedule -->
            <div class="form-section">
              <div class="section-label">Schedule</div>
              <div class="grid-3">
                <div class="field">
                  <label>Date <span class="req">*</span></label>
                  <input type="date" id="date" value="{{ date('Y-m-d') }}" required>
                </div>
                <div class="field">
                  <label>Start Time <span class="req">*</span></label>
                  <input type="time" id="start_time" value="06:00" required>
                </div>
                <div class="field">
                  <label>End Time <span class="req">*</span></label>
                  <input type="time" id="end_time" value="07:00" required>
                </div>
              </div>
            </div>

            <!-- Section 4: Road Config -->
            <div class="form-section">
              <div class="section-label">Road Configuration</div>
              <div class="grid-2">
                <div class="field">
                  <label>Median Type</label>
                  <select id="has_median">
                    <option value="0">No median — 720 PCU/lane/hr</option>
                    <option value="1">With median — 900 PCU/lane/hr</option>
                  </select>
                </div>
                <div class="field">
                  <label>Number of Lanes <span class="req">*</span></label>
                  <input type="number" id="lanes" value="2" min="1" required>
                </div>
              </div>
            </div>

            <!-- Section 5: Camera -->
            <div class="form-section">
              <div class="section-label">Camera</div>
              <div class="grid-1">
                <div class="field">
                  <label>Select Camera <span class="req">*</span></label>
                  <select id="camera_id" required>
                    <option value="">Choose a camera...</option>
                    @foreach($cameras as $camera)
                    <option value="{{ $camera->id }}">
                      {{ $camera->name }} ({{ $camera->location }}) — {{ $camera->status }}
                    </option>
                    @endforeach
                  </select>
                </div>
              </div>

              <div class="info-banner">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                <span>Once started, the system will automatically count vehicles (Private Car/Taxi/Pick-up, Jeepney, Van, Bus, Light Truck, Heavy Truck, Motorcycle, Tricycle) using the selected camera. The session will run from the specified start time to end time.</span>
              </div>
            </div>

            <!-- Actions -->
            <div class="form-actions">
              <button type="button" class="btn btn-ghost" id="cancelBtn">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                Cancel
              </button>
              <button type="submit" class="btn btn-primary" id="startSessionBtn">
                <svg viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                Start Session
              </button>
            </div>

          </form>
        </div><!-- /panel-body -->
      </div><!-- /panel -->
    </div><!-- /content -->
  </main>
</div><!-- /layout -->

<script type="application/json" id="page-data">@json(['today' => date('Y-m-d')])</script>
@vite('resources/js/pages/schedules.js')
</body>
</html>
