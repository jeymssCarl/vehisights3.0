<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>VehiSights — Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
@vite('resources/css/pages/admin.css')
</head>
<body>
<div class="layout">
  <!-- SIDEBAR - dynamic user -->
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
      <div class="topbar-left"><h1>Admin Panel</h1><div class="subtitle">User management & system configuration</div></div>
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
      <div class="tab-bar">
        <div class="tab active" data-tab="users">Users</div>
        <div class="tab" data-tab="settings">System Settings</div>
      </div>

      <!-- USERS TAB -->
      <div id="tab-users" class="tab-content active">
        <div class="page-header">
          <h2>System Users</h2>
          <button class="btn-primary" id="newUserBtn">+ Add User</button>
        </div>
        <div class="table-wrapper">
          <table class="admin-table">
            <thead>
              <tr><th>ID</th><th>Full Name</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody id="usersTableBody"></tbody>
          </table>
        </div>
      </div>

      <!-- SETTINGS TAB -->
      <div id="tab-settings" class="tab-content">
        <div class="settings-group">
          <div class="setting-item">
            <div class="setting-label">System Name</div>
            <div class="setting-control"><input type="text" value="VehiSights Traffic Intelligence" id="sysName"></div>
          </div>
          <div class="setting-item">
            <div class="setting-label">Camera Polling Interval (seconds)</div>
            <div class="setting-control"><input type="number" value="60" id="pollInterval" step="10"></div>
          </div>
          <div class="setting-item">
            <div class="setting-label">Default Road Capacity (PCU/lane/hr)</div>
            <div class="setting-control"><input type="number" value="720" id="defaultCapacity"></div>
          </div>
          <div class="setting-item">
            <div class="setting-label">Maintenance Mode</div>
            <div class="setting-control"><select id="maintenanceMode"><option value="0">Disabled</option><option value="1">Enabled</option></select></div>
          </div>
          <div class="setting-item">
            <div class="setting-label"></div>
            <div class="setting-control"><button class="btn-primary" id="saveSettingsBtn">Save Settings</button></div>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- MODAL for Add/Edit User -->
<div id="userModal" class="modal-overlay">
  <div class="modal">
    <h3 id="modalTitle">Add User</h3>
    <form id="userForm">
      <div class="form-group"><label>Full Name</label><input type="text" id="userFullname" required></div>
      <div class="form-group"><label>Email</label><input type="email" id="userEmail" required></div>
      <div class="form-group"><label>Role</label><select id="userRole"><option value="CPD Admin">CPD Admin</option><option value="IT Admin">IT Admin</option></select></div>
      <div class="form-group"><label>Password</label><input type="password" id="userPassword" placeholder="Leave blank to keep unchanged (edit) or set for new user"></div>
      <div class="modal-actions">
        <button type="button" class="btn-secondary" id="closeModalBtn">Cancel</button>
        <button type="submit" class="btn-primary">Save User</button>
      </div>
    </form>
  </div>
</div>

@vite('resources/js/pages/admin.js')
</body>
</html>