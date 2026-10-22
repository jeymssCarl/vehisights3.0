<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>VehiSights — Manual Records</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
@vite(['resources/css/pages/manual-records.css', 'resources/js/ui-enhancements.js'])
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
      <a href="{{ url('/manual-records') }}" class="nav-item active"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg><span>Manual Records</span></a>

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
      <div class="topbar-left"><h1>Manual Records</h1><div class="subtitle">View and manage manually counted traffic sessions</div></div>
      <div class="topbar-right">
        <div class="icon-btn"><svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg><div class="notif-dot"></div></div>
        <div class="theme-toggle" id="themeToggle"><svg class="icon-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg><svg class="icon-moon" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></div>
        <form method="POST" action="{{ route('logout') }}" style="display: inline;">@csrf<button type="submit" class="logout-btn" title="Logout"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></button></form>
      </div>
    </div>

    <div class="content">
      <!-- Search and date-range filters -->
      <div class="filter-bar">
        <div class="filter-left">
          <div class="search-wrap">
            <svg class="search-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="searchInput" class="search-input" placeholder="Search session ID or road...">
          </div>
          <div class="records-date-filter" aria-label="Filter records by date range">
            <span class="date-filter-label"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>Date Range</span>
            <input type="date" id="startDateFilter" class="date-filter-input" aria-label="Start date">
            <span class="date-filter-arrow" aria-hidden="true">→</span>
            <input type="date" id="endDateFilter" class="date-filter-input" aria-label="End date">
            <button type="button" id="applyDateFilterBtn" class="btn-apply-filter">Apply Filter</button>
            <button type="button" id="todayFilterBtn" class="btn-today-filter">Today</button>
          </div>
        </div>
        <button id="deleteAllBtn" class="btn-delete-all" type="button" disabled title="No records to delete">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
          Delete All
        </button>
      </div>

      <div class="table-wrapper">
        <table class="sessions-table">
          <thead>
            <tr>
              <th>Session ID</th>
              <th>Road Name</th>
              <th>Direction</th>
              <th>Date</th>
              <th>Time</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="sessionsTableBody"></tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<!-- DETAILS MODAL -->
<div id="detailsModal" class="modal-overlay">
  <div class="modal">
    <div class="modal-header">
      <h3>Session Details</h3>
      <button class="modal-close" id="closeDetailsModalBtn">&times;</button>
    </div>
    <div class="modal-body" id="detailsModalBody">
      <div style="text-align:center; padding:20px;">Loading...</div>
    </div>
  </div>
</div>

<!-- EDIT MODAL (with vehicle counts editing) -->
<div id="editModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="editModalTitle" aria-describedby="editModalDescription">
  <div class="modal edit-session-modal" role="document">
    <div class="modal-header">
      <div class="modal-heading">
        <div class="modal-heading-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
        </div>
        <div>
          <div class="modal-eyebrow">Manual traffic record</div>
          <h3 id="editModalTitle">Edit Session</h3>
          <p id="editModalDescription">Update the survey details and vehicle counts. Calculated values refresh automatically.</p>
        </div>
      </div>
      <button type="button" class="modal-close" id="closeEditModalBtn" aria-label="Close edit session dialog">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <form id="editSessionForm">
        <input type="hidden" id="edit_session_id">
        <div class="edit-modal-content">
          <section class="edit-section" aria-labelledby="sessionDetailsHeading">
            <div class="edit-section-header">
              <div>
                <h4 id="sessionDetailsHeading">Location and road setup</h4>
                <p>Identify where the traffic survey was conducted.</p>
              </div>
              <span class="section-step">01</span>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="edit_road_name">Road name <span class="required-mark" aria-hidden="true">*</span></label>
                <input type="text" id="edit_road_name" required>
              </div>
              <div class="form-group">
                <label for="edit_barangay">Barangay</label>
                <input type="text" id="edit_barangay">
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="edit_city">City <span class="required-mark" aria-hidden="true">*</span></label>
                <input type="text" id="edit_city" required>
              </div>
              <div class="form-group">
                <label for="edit_direction">Direction <span class="required-mark" aria-hidden="true">*</span></label>
                <input type="text" id="edit_direction" required>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="edit_lanes">Number of lanes <span class="required-mark" aria-hidden="true">*</span></label>
                <input type="number" id="edit_lanes" min="1" required>
              </div>
              <div class="form-group">
                <label for="edit_has_median">Road configuration</label>
                <select id="edit_has_median">
                  <option value="0">No median (720 PCU/lane/hr)</option>
                  <option value="1">With median (900 PCU/lane/hr)</option>
                </select>
              </div>
            </div>
          </section>

          <section class="edit-section" aria-labelledby="surveyPeriodHeading">
            <div class="edit-section-header">
              <div>
                <h4 id="surveyPeriodHeading">Survey period</h4>
                <p>Confirm when counting started and ended.</p>
              </div>
              <span class="section-step">02</span>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="edit_start_time">Start time <span class="required-mark" aria-hidden="true">*</span></label>
                <input type="datetime-local" id="edit_start_time" required>
              </div>
              <div class="form-group">
                <label for="edit_end_time">End time</label>
                <input type="datetime-local" id="edit_end_time">
              </div>
            </div>
          </section>

          <section class="edit-section edit-section-counts" aria-labelledby="vehicleCountsHeading">
            <div class="edit-section-header">
              <div>
                <h4 id="vehicleCountsHeading">Vehicle counts</h4>
                <p>Edit the observed quantity. PCU totals recalculate as you type.</p>
              </div>
              <span class="section-step">03</span>
            </div>

            <div class="vehicle-table-scroll" tabindex="0" aria-label="Editable vehicle count table">
              <table class="vehicle-edit-table" id="vehicleEditTable">
                <thead>
                  <tr><th scope="col">Vehicle type</th><th scope="col">PCU factor</th><th scope="col">Observed count</th><th scope="col">PCU total</th></tr>
                </thead>
                <tbody id="vehicleEditTableBody"></tbody>
              </table>
            </div>
          </section>

          <section class="edit-summary" aria-labelledby="calculatedSummaryHeading">
            <div class="edit-section-header edit-summary-header">
              <div>
                <h4 id="calculatedSummaryHeading">Calculated traffic summary</h4>
                <p>Live results based on the road setup and counts above.</p>
              </div>
              <span class="live-calculation"><span aria-hidden="true"></span> Live calculation</span>
            </div>

            <div class="edit-stats">
              <div class="edit-stat-card">
                <div class="stat-label">Total PCU</div>
                <div class="edit-stat-value" id="edit_total_pcu" aria-live="polite">0</div>
                <div class="edit-stat-hint">Passenger car units</div>
              </div>
              <div class="edit-stat-card">
                <div class="stat-label">V/C Ratio</div>
                <div class="edit-stat-value" id="edit_vc_ratio" aria-live="polite">0.00</div>
                <div class="edit-stat-hint">Volume to capacity</div>
              </div>
              <div class="edit-stat-card edit-stat-los">
                <div class="stat-label">Level of service</div>
                <div class="edit-stat-value" id="edit_los" data-los="A" aria-live="polite">A</div>
                <div class="edit-stat-hint">Traffic operating condition</div>
              </div>
              <div class="edit-stat-card edit-stat-status">
                <label class="stat-label" for="edit_status">Session status</label>
                <select id="edit_status" disabled aria-describedby="editStatusHint">
                  <option value="ended" selected>Ended</option>
                </select>
                <div class="edit-stat-hint" id="editStatusHint">Records cannot be reactivated</div>
              </div>
            </div>
          </section>
        </div>

        <div class="modal-actions">
          <div class="action-context">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 9v4m0 4h.01M10.3 3.7 2.6 17a2 2 0 0 0 1.7 3h15.4a2 2 0 0 0 1.7-3L13.7 3.7a2 2 0 0 0-3.4 0Z"/></svg>
            <span>Changes will update the dashboard and generated reports.</span>
          </div>
          <div class="action-buttons">
            <button type="button" class="btn-secondary" id="cancelEditBtn">Cancel</button>
            <button type="submit" class="btn-primary">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
              Update Session
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- CONFIRM MODAL -->
<div id="confirmModal" class="modal-overlay confirm-modal">
  <div class="modal">
    <div class="modal-body">
      <div class="confirm-icon"><svg viewBox="0 0 24 24"><path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
      <div class="confirm-title" id="confirmTitle">Delete Session?</div>
      <div class="confirm-message" id="confirmMessage">Are you sure you want to delete this session? This action cannot be undone.</div>
      <div class="confirm-buttons">
        <button class="btn-secondary" id="confirmCancelBtn">Cancel</button>
        <button class="btn-primary" id="confirmOkBtn">Confirm</button>
      </div>
    </div>
  </div>
</div>

<!-- CONFIRM DELETE ALL MODAL -->
<div id="confirmDeleteAllModal" class="modal-overlay confirm-modal">
  <div class="modal">
    <div class="modal-body">
      <div class="confirm-icon"><svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg></div>
      <div class="confirm-title">Delete All Records?</div>
      <div class="confirm-message">Are you sure you want to delete ALL manual records? This action cannot be undone.</div>
      <div class="confirm-buttons">
        <button class="btn-secondary" id="confirmDeleteAllCancelBtn">Cancel</button>
        <button class="btn-primary" id="confirmDeleteAllOkBtn" style="background: var(--red);">Delete All</button>
      </div>
    </div>
  </div>
</div>

<script type="application/json" id="page-data">@json(['sessions' => $sessions, 'today' => date('Y-m-d')])</script>
@vite('resources/js/pages/manual-records.js')
</body>
</html>
