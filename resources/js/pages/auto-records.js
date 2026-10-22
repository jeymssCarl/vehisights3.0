// Dark mode
const html = document.documentElement;
const toggle = document.getElementById('themeToggle');
const savedTheme = localStorage.getItem('vehisights-theme');
html.setAttribute('data-theme', savedTheme || 'light');
toggle.addEventListener('click', () => {
  const newTheme = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
  html.setAttribute('data-theme', newTheme);
  localStorage.setItem('vehisights-theme', newTheme);
});

const pageData = JSON.parse(document.getElementById('page-data').textContent);
const sessionsData = pageData.sessions;
const urlState = new URLSearchParams(window.location.search);
const isValidDate = value => /^\d{4}-\d{2}-\d{2}$/.test(value || '');

let customStartDate = isValidDate(urlState.get('start_date')) ? urlState.get('start_date') : pageData.today;
let customEndDate = isValidDate(urlState.get('end_date')) ? urlState.get('end_date') : pageData.today;
if (customStartDate > customEndDate) customEndDate = customStartDate;

function filterByDate(session) {
  const sessionDate = session.start_time ? session.start_time.split('T')[0] : null;
  if (!sessionDate) return false;
  return sessionDate >= customStartDate && sessionDate <= customEndDate;
}

// FIXED: formatDateTime without timezone conversion
function formatDateTime(timestamp) {
  if (!timestamp) return { date: '—', time: '—' };
  
  const parts = timestamp.split('T');
  const datePart = parts[0];
  const timePart = parts[1] ? parts[1].split('.')[0] : '00:00:00';
  
  const date = datePart;
  let hour = parseInt(timePart.substring(0, 2));
  const minute = timePart.substring(3, 5);
  const ampm = hour >= 12 ? 'PM' : 'AM';
  hour = hour % 12 || 12;
  const time = `${hour}:${minute} ${ampm}`;
  
  return { date: date, time: time };
}

function escapeHtml(str) {
  if (!str) return '';
  return str.replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}

const VEHICLE_NAMES = [
  "Private Car / Taxi / Pick-up",
  "Jeepney",
  "Van for Hire (AUV, GT Express)",
  "Bus & Mini Bus",
  "Light Truck",
  "Heavy Truck",
  "Motorcycle",
  "Tricycle"
];

function updateDeleteAllButton(hasVisibleRecords) {
  const deleteAllBtn = document.getElementById('deleteAllBtn');
  deleteAllBtn.disabled = !hasVisibleRecords;
  deleteAllBtn.title = hasVisibleRecords ? 'Delete all records' : 'No records to delete';
}

function renderTable() {
  const searchTerm = document.getElementById('searchInput').value.toLowerCase();
  let filtered = sessionsData.filter(s => {
    if (searchTerm && !s.camera_name?.toLowerCase().includes(searchTerm) && !s.ip_address?.toLowerCase().includes(searchTerm) && !s.location?.toLowerCase().includes(searchTerm)) return false;
    if (!filterByDate(s)) return false;
    return true;
  });
  
  const tbody = document.getElementById('autoTableBody');
  updateDeleteAllButton(filtered.length > 0);

  if (filtered.length === 0) {
    tbody.innerHTML = '<tr><td colspan="8" class="empty-message">No auto records found</td></tr>';
    return;
  }
  
  tbody.innerHTML = filtered.map(s => {
    const dateTime = formatDateTime(s.start_time);
    return `
      <tr>
        <td style="font-family:'DM Mono',monospace;">${escapeHtml(s.session_code)}</td>
        <td style="font-weight:500;">${escapeHtml(s.camera_name)}</td>
        <td style="font-family:'DM Mono',monospace;">${escapeHtml(s.ip_address)}</td>
        <td>${escapeHtml(s.location)}</td>
        <td style="font-family:'DM Mono',monospace; font-size:0.75rem;">${dateTime.date}</td>
        <td style="font-family:'DM Mono',monospace; font-size:0.75rem;">${dateTime.time}</td>
        <td><span class="status-badge ${s.status}">${s.status === 'active' ? 'Active' : 'Ended'}</span></td>
        <td class="action-icons">
          <div class="action-icon edit" data-id="${s.id}" title="Edit Session">
            <svg viewBox="0 0 24 24"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
          </div>
          <div class="action-icon delete" data-id="${s.id}" title="Delete Session">
            <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
          </div>
        </td>
      </tr>
    `;
  }).join('');
  attachRowClickEvents();
  attachActionEvents();
}

function attachRowClickEvents() {
  document.querySelectorAll('#autoTableBody tr').forEach(row => {
    row.removeEventListener('click', rowClickHandler);
    row.addEventListener('click', rowClickHandler);
  });
}

function rowClickHandler(e) {
  if (e.target.closest('.action-icon')) return;
  const row = e.currentTarget;
  const sessionCode = row.cells[0].innerText;
  const session = sessionsData.find(s => s.session_code === sessionCode);
  if (session) showSessionDetails(session);
}

function attachActionEvents() {
  document.querySelectorAll('.action-icon.edit').forEach(btn => {
    btn.removeEventListener('click', handleEdit);
    btn.addEventListener('click', handleEdit);
  });
  document.querySelectorAll('.action-icon.delete').forEach(btn => {
    btn.removeEventListener('click', handleDelete);
    btn.addEventListener('click', handleDelete);
  });
}

function handleEdit(e) {
  e.stopPropagation();
  const id = parseInt(e.currentTarget.dataset.id);
  const session = sessionsData.find(s => s.id === id);
  if (!session) return;
  document.getElementById('edit_session_id').value = session.id;
  document.getElementById('edit_camera_name').value = session.camera_name || '';
  document.getElementById('edit_ip_address').value = session.ip_address || '';
  document.getElementById('edit_location').value = session.location || '';
  if (session.start_time) {
    const start = new Date(session.start_time);
    document.getElementById('edit_start_time').value = start.toISOString().slice(0, 16);
  } else document.getElementById('edit_start_time').value = '';
  if (session.end_time) {
    const end = new Date(session.end_time);
    document.getElementById('edit_end_time').value = end.toISOString().slice(0, 16);
  } else document.getElementById('edit_end_time').value = '';
  document.getElementById('edit_status').value = session.status;
  document.getElementById('editModal').classList.add('active');
}

let pendingAction = null;
function showConfirmModal(title, message, onConfirm, isDeleteAll = false) {
  const modalId = isDeleteAll ? 'confirmDeleteAllModal' : 'confirmModal';
  document.getElementById('confirmTitle').innerText = title;
  document.getElementById('confirmMessage').innerHTML = message;
  pendingAction = onConfirm;
  document.getElementById(modalId).classList.add('active');
}
function closeConfirmModal(isDeleteAll = false) {
  const modalId = isDeleteAll ? 'confirmDeleteAllModal' : 'confirmModal';
  document.getElementById(modalId).classList.remove('active');
  pendingAction = null;
}

async function handleDelete(e) {
  e.stopPropagation();
  const id = parseInt(e.currentTarget.dataset.id);
  const session = sessionsData.find(s => s.id === id);
  if (!session) return;
  showConfirmModal(
    'Delete Auto Session?',
    `Are you sure you want to delete session <strong>${escapeHtml(session.session_code)}</strong>?<br>This action cannot be undone.`,
    async () => {
      try {
        const response = await fetch(`/auto-sessions/${id}`, {
          method: 'DELETE',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
            'Content-Type': 'application/json'
          },
        });
        const result = await response.json();
        if (result.success) location.reload();
        else alert('❌ Delete failed: ' + (result.message || 'Unknown error'));
      } catch (err) {
        console.error(err);
        alert('❌ Error deleting session');
      }
      closeConfirmModal();
    }
  );
}

async function handleDeleteAll() {
  if (document.getElementById('deleteAllBtn').disabled) return;

  showConfirmModal(
    'Delete All Records?',
    'Are you sure you want to delete ALL auto records? This action cannot be undone.',
    async () => {
      try {
        const response = await fetch('/auto-sessions/delete-all', {
          method: 'DELETE',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
            'Content-Type': 'application/json'
          },
        });
        const result = await response.json();
        if (result.success) {
          location.reload();
        } else {
          alert('❌ Delete all failed: ' + (result.message || 'Unknown error'));
        }
      } catch (err) {
        console.error(err);
        alert('❌ Error deleting all sessions');
      }
      closeConfirmModal(true);
    },
    true
  );
}

function showSessionDetails(session) {
  const modal = document.getElementById('detailsModal');
  const modalBody = document.getElementById('detailsModalBody');
  let details = session.detail;
  if (!details || typeof details !== 'object' || Object.keys(details).length === 0) {
    modalBody.innerHTML = '<div style="text-align:center; padding:20px;">No detailed data available for this session.</div>';
    modal.classList.add('active');
    return;
  }
  let counts = details.counts;
  let pcuTotals = details.pcu_totals;
  if (typeof counts === 'string') {
    try { counts = JSON.parse(counts); } catch(e) { counts = []; }
  }
  if (typeof pcuTotals === 'string') {
    try { pcuTotals = JSON.parse(pcuTotals); } catch(e) { pcuTotals = []; }
  }
  const totalVehicles = (counts || []).reduce((a,b) => a + (b || 0), 0);
  const vcRatio = parseFloat(details.vc_ratio || 0);
  const vcFixed = vcRatio.toFixed(2);
  const los = details.los || '—';
  const getVcColor = (ratio) => {
    if (ratio >= 0.9) return 'var(--red)';
    if (ratio >= 0.7) return 'var(--amber)';
    return 'var(--green)';
  };
  const getLosColor = (los) => {
    if (los === 'A' || los === 'B') return 'var(--green)';
    if (los === 'C' || los === 'D') return 'var(--amber)';
    return 'var(--red)';
  };
  const vcColor = getVcColor(vcRatio);
  const losColor = getLosColor(los);

  // Build Session Info HTML
  const sessionInfoHtml = `
    <div class="session-info-section">
      <div class="session-info-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
        Session Information
      </div>
      <div class="session-info-grid">
        <div class="session-info-item">
          <div class="session-info-label">Session Code</div>
          <div class="session-info-value">${escapeHtml(session.session_code)}</div>
        </div>
        <div class="session-info-item">
          <div class="session-info-label">Created By</div>
          <div class="session-info-value">${escapeHtml(session.user?.name || session.created_by || 'System')}</div>
        </div>
        <div class="session-info-item">
          <div class="session-info-label">Source</div>
          <div class="session-info-value"><span class="status-badge" style="background:var(--blue-bg);color:var(--blue);">${escapeHtml(details.source || 'auto')}</span></div>
        </div>
        <div class="session-info-item">
          <div class="session-info-label">Created At</div>
          <div class="session-info-value">${new Date(session.created_at).toLocaleString()}</div>
        </div>
      </div>
    </div>
  `;

  const statsHtml = `
    <div class="details-stats-grid">
      <div class="details-stat-card" style="--card-accent: var(--maroon);">
        <div class="details-stat-label">Total PCU</div>
        <div class="details-stat-value" style="color: var(--maroon);">${details.total_pcu || 0}</div>
        <div class="details-stat-sub">Passenger Car Units</div>
      </div>
      <div class="details-stat-card" style="--card-accent: ${vcColor};">
        <div class="details-stat-label">V/C Ratio</div>
        <div class="details-stat-value" style="color: ${vcColor};">${vcFixed}</div>
        <div class="details-stat-sub">Volume / Capacity</div>
      </div>
      <div class="details-stat-card" style="--card-accent: ${losColor};">
        <div class="details-stat-label">LOS</div>
        <div class="details-stat-value" style="color: ${losColor};">${los}</div>
        <div class="details-stat-sub">Level of Service</div>
      </div>
      <div class="details-stat-card" style="--card-accent: var(--blue);">
        <div class="details-stat-label">Vehicles</div>
        <div class="details-stat-value" style="color: var(--blue);">${totalVehicles}</div>
        <div class="details-stat-sub">Total counted</div>
      </div>
    </div>
  `;

  let vehicleRowsHtml = '';
  if (Array.isArray(counts) && counts.length > 0) {
    vehicleRowsHtml = `
      <div style="margin-top: 16px;"><strong>Vehicle Breakdown</strong></div>
      <table class="vehicle-breakdown-table">
        <thead><tr><th>Vehicle Type</th><th>Count</th><th>PCU</th></tr></thead>
        <tbody>
          ${VEHICLE_NAMES.map((name, i) => {
            const count = counts[i] || 0;
            const pcu = parseFloat(pcuTotals[i] || 0).toFixed(1);
            return `<tr><td>${escapeHtml(name)}</td><td>${count}</td><td>${pcu}</td>`;
          }).join('')}
        </tbody>
      </table>
    `;
  } else {
    vehicleRowsHtml = '<div style="color:var(--text-muted); margin-top:16px;">No vehicle breakdown data.</div>';
  }
  modalBody.innerHTML = sessionInfoHtml + statsHtml + vehicleRowsHtml;
  modal.classList.add('active');
}

const searchInput = document.getElementById('searchInput');
const startDateFilter = document.getElementById('startDateFilter');
const endDateFilter = document.getElementById('endDateFilter');

startDateFilter.value = customStartDate;
endDateFilter.value = customEndDate;
searchInput.value = urlState.get('q') || '';

function syncFilterStateToUrl() {
  const params = new URLSearchParams();
  params.set('start_date', customStartDate);
  params.set('end_date', customEndDate);
  if (searchInput.value.trim()) params.set('q', searchInput.value.trim());
  window.history.replaceState(null, '', `${window.location.pathname}?${params.toString()}`);
}

function applyDateRange() {
  const startDate = startDateFilter.value;
  const endDate = endDateFilter.value;
  endDateFilter.setCustomValidity('');
  if (!startDate || !endDate) return;
  if (startDate > endDate) {
    endDateFilter.setCustomValidity('End date must be on or after the start date.');
    endDateFilter.reportValidity();
    return;
  }
  customStartDate = startDate;
  customEndDate = endDate;
  syncFilterStateToUrl();
  renderTable();
}

document.getElementById('applyDateFilterBtn').addEventListener('click', applyDateRange);
document.getElementById('todayFilterBtn').addEventListener('click', () => {
  customStartDate = pageData.today;
  customEndDate = pageData.today;
  startDateFilter.value = pageData.today;
  endDateFilter.value = pageData.today;
  endDateFilter.setCustomValidity('');
  syncFilterStateToUrl();
  renderTable();
});
searchInput.addEventListener('input', () => {
  syncFilterStateToUrl();
  renderTable();
});
document.getElementById('deleteAllBtn').addEventListener('click', handleDeleteAll);
renderTable();

// Edit modal
const editModal = document.getElementById('editModal');
const closeEditModal = () => editModal.classList.remove('active');
document.getElementById('closeEditModalBtn').addEventListener('click', closeEditModal);
document.getElementById('cancelEditBtn').addEventListener('click', closeEditModal);
editModal.addEventListener('click', e => { if (e.target === editModal) closeEditModal(); });

document.getElementById('editSessionForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const id = document.getElementById('edit_session_id').value;
  const data = {
    camera_name: document.getElementById('edit_camera_name').value,
    ip_address: document.getElementById('edit_ip_address').value,
    location: document.getElementById('edit_location').value,
    start_time: document.getElementById('edit_start_time').value,
    end_time: document.getElementById('edit_end_time').value || null,
    status: document.getElementById('edit_status').value,
  };
  const session = sessionsData.find(s => s.id == id);
  showConfirmModal(
    'Update Auto Session?',
    `Are you sure you want to update session <strong>${escapeHtml(session?.session_code)}</strong>?`,
    async () => {
      try {
        const response = await fetch(`/auto-sessions/${id}`, {
          method: 'PUT',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(data)
        });
        const result = await response.json();
        if (result.success) location.reload();
        else alert('❌ Update failed: ' + (result.message || 'Unknown error'));
      } catch (err) {
        console.error(err);
        alert('❌ Error updating session');
      }
      closeConfirmModal();
    }
  );
});

document.getElementById('confirmCancelBtn').addEventListener('click', () => closeConfirmModal());
document.getElementById('confirmOkBtn').addEventListener('click', () => {
  if (pendingAction) pendingAction();
});
document.getElementById('confirmModal').addEventListener('click', (e) => {
  if (e.target === document.getElementById('confirmModal')) closeConfirmModal();
});

// Delete All Modal handlers
document.getElementById('confirmDeleteAllCancelBtn').addEventListener('click', () => closeConfirmModal(true));
document.getElementById('confirmDeleteAllOkBtn').addEventListener('click', () => {
  if (pendingAction) pendingAction();
});
document.getElementById('confirmDeleteAllModal').addEventListener('click', (e) => {
  if (e.target === document.getElementById('confirmDeleteAllModal')) closeConfirmModal(true);
});

const detailsModal = document.getElementById('detailsModal');
const closeDetailsModal = () => detailsModal.classList.remove('active');
document.getElementById('closeDetailsModalBtn').addEventListener('click', closeDetailsModal);
detailsModal.addEventListener('click', (e) => { if (e.target === detailsModal) closeDetailsModal(); });
