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
let sessionsData = pageData.sessions;
let activeDetailsSessionId = null;
const urlState = new URLSearchParams(window.location.search);
const isValidDate = value => /^\d{4}-\d{2}-\d{2}$/.test(value || '');

let customStartDate = isValidDate(urlState.get('start_date')) ? urlState.get('start_date') : pageData.today;
let customEndDate = isValidDate(urlState.get('end_date')) ? urlState.get('end_date') : pageData.today;
if (customStartDate > customEndDate) customEndDate = customStartDate;

function filterByDate(session) {
  const sessionDate = session.start_time ? formatDateTime(session.start_time).date : null;
  if (!sessionDate) return false;
  return sessionDate >= customStartDate && sessionDate <= customEndDate;
}

// Database timestamps are UTC. Auto Records displays Philippine time using
// a 12-hour clock, while time24 remains available for datetime-local inputs.
function formatDateTime(timestamp) {
  if (!timestamp) return { date: '—', time: '—', time24: '—' };

  const value = new Date(timestamp);
  if (Number.isNaN(value.getTime())) return { date: '—', time: '—', time24: '—' };

  const dateParts = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Manila',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(value);
  const time24Parts = new Intl.DateTimeFormat('en-GB', {
    timeZone: 'Asia/Manila',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
  }).formatToParts(value);
  const part = (parts, type) => parts.find(item => item.type === type)?.value || '';
  const displayTime = new Intl.DateTimeFormat('en-US', {
    timeZone: 'Asia/Manila',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  }).format(value);

  return {
    date: `${part(dateParts, 'year')}-${part(dateParts, 'month')}-${part(dateParts, 'day')}`,
    time: displayTime,
    time24: `${part(time24Parts, 'hour')}:${part(time24Parts, 'minute')}`,
  };
}

function formatDateTimeLocal(timestamp) {
  const formatted = formatDateTime(timestamp);
  return formatted.date === '—' ? '' : `${formatted.date}T${formatted.time24}`;
}

function localInputToManilaIso(value) {
  if (!value) return null;
  return `${value.length === 16 ? `${value}:00` : value}+08:00`;
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
  updateDeleteAllButton(filtered.some(session => session.status === 'ended'));

  if (filtered.length === 0) {
    tbody.innerHTML = '<tr><td colspan="8" class="empty-message">No auto records found</td></tr>';
    return;
  }
  
  tbody.innerHTML = filtered.map(s => {
    const dateTime = formatDateTime(s.start_time);
    const statusLabel = s.status === 'scheduled' ? 'Scheduled' : (s.status === 'active' ? 'Active' : 'Ended');
    const actions = s.status === 'scheduled'
      ? `<span class="scheduled-record-label">Queued</span>
         <div class="action-icon cancel-scheduled" data-id="${s.id}" title="Cancel Scheduled Session">
           <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
         </div>`
      : (s.status === 'active'
        ? '<span class="live-record-label">Live</span>'
        : `<div class="action-icon edit" data-id="${s.id}" title="Edit Session">
             <svg viewBox="0 0 24 24"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
           </div>
           <div class="action-icon delete" data-id="${s.id}" title="Delete Session">
             <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
           </div>`);
    return `
      <tr class="record-row" data-session-id="${s.id}" title="View session details">
        <td style="font-family:'DM Mono',monospace;">${escapeHtml(s.session_code)}</td>
        <td style="font-weight:500;">${escapeHtml(s.camera_name)}</td>
        <td style="font-family:'DM Mono',monospace;">${escapeHtml(s.ip_address)}</td>
        <td>${escapeHtml(s.location)}</td>
        <td style="font-family:'DM Mono',monospace; font-size:0.75rem;">${dateTime.date}</td>
        <td style="font-family:'DM Mono',monospace; font-size:0.75rem;">${dateTime.time}</td>
        <td><span class="status-badge ${s.status}">${statusLabel}</span></td>
        <td class="action-icons">
          ${actions}
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
  const session = sessionsData.find(s => Number(s.id) === Number(e.currentTarget.dataset.sessionId));
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
  document.querySelectorAll('.action-icon.cancel-scheduled').forEach(btn => {
    btn.removeEventListener('click', handleCancelScheduled);
    btn.addEventListener('click', handleCancelScheduled);
  });
}

function handleCancelScheduled(e) {
  e.stopPropagation();
  const id = parseInt(e.currentTarget.dataset.id);
  const session = sessionsData.find(item => item.id === id);
  if (!session) return;

  showConfirmModal(
    'Cancel Scheduled Session?',
    `Cancel session <strong>${escapeHtml(session.session_code)}</strong>? It will not start at its scheduled time.`,
    async () => {
      try {
        const response = await fetch(`/auto-sessions/${id}`, {
          method: 'DELETE',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
            'Content-Type': 'application/json',
          },
        });
        const result = await response.json();

        if (!response.ok || !result.success) {
          throw new Error(result.message || 'The scheduled session could not be cancelled.');
        }

        closeConfirmModal();
        await refreshAutoRecords();
      } catch (error) {
        console.error(error);
        alert(`❌ ${error.message}`);
        closeConfirmModal();
      }
    }
  );
}

function handleEdit(e) {
  e.stopPropagation();
  const id = parseInt(e.currentTarget.dataset.id);
  const session = sessionsData.find(s => s.id === id);
  if (!session) return;
  if (session.status !== 'ended') return;
  document.getElementById('edit_session_id').value = session.id;
  document.getElementById('edit_camera_name').value = session.camera_name || '';
  document.getElementById('edit_ip_address').value = session.ip_address || '';
  document.getElementById('edit_location').value = session.location || '';
  document.getElementById('edit_day_classification').value = session.day_classification || '';
  if (session.start_time) {
    document.getElementById('edit_start_time').value = formatDateTimeLocal(session.start_time);
  } else document.getElementById('edit_start_time').value = '';
  if (session.end_time) {
    document.getElementById('edit_end_time').value = formatDateTimeLocal(session.end_time);
  } else document.getElementById('edit_end_time').value = '';
  document.getElementById('edit_status').value = session.status;
  document.getElementById('editModal').classList.add('active');
}

let pendingAction = null;
let pendingActionRunning = false;
function setConfirmLoading(isDeleteAll, loading, text = 'Deleting…') {
  const button = document.getElementById(isDeleteAll ? 'confirmDeleteAllOkBtn' : 'confirmOkBtn');
  const cancel = document.getElementById(isDeleteAll ? 'confirmDeleteAllCancelBtn' : 'confirmCancelBtn');
  if (!button.dataset.defaultLabel) button.dataset.defaultLabel = button.textContent.trim();
  button.disabled = loading;
  cancel.disabled = loading;
  button.classList.toggle('ui-action-loading', loading);
  button.textContent = loading ? text : button.dataset.defaultLabel;
  pendingActionRunning = loading;
}
function showConfirmModal(title, message, onConfirm, isDeleteAll = false) {
  const modalId = isDeleteAll ? 'confirmDeleteAllModal' : 'confirmModal';
  document.getElementById('confirmTitle').innerText = title;
  document.getElementById('confirmMessage').innerHTML = message;
  pendingAction = onConfirm;
  document.getElementById(modalId).classList.add('active');
}
function closeConfirmModal(isDeleteAll = false) {
  if (pendingActionRunning) return;
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
      setConfirmLoading(false, true);
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
        else {
          setConfirmLoading(false, false);
          window.VehiSightsUI.showNotice({ title: 'Delete failed', message: result.message || 'The automatic session could not be deleted.' });
        }
      } catch (err) {
        console.error(err);
        setConfirmLoading(false, false);
        window.VehiSightsUI.showNotice({ title: 'Delete failed', message: 'The automatic session could not be deleted. Check your connection and try again.' });
      }
      if (!pendingActionRunning) closeConfirmModal();
    }
  );
}

async function handleDeleteAll() {
  if (document.getElementById('deleteAllBtn').disabled) return;

  showConfirmModal(
    'Delete All Records?',
    'Are you sure you want to delete ALL auto records? This action cannot be undone.',
    async () => {
      setConfirmLoading(true, true, 'Deleting all…');
      document.getElementById('deleteAllBtn').disabled = true;
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
          setConfirmLoading(true, false);
          document.getElementById('deleteAllBtn').disabled = false;
          window.VehiSightsUI.showNotice({ title: 'Delete all failed', message: result.message || 'The automatic records could not be deleted.' });
        }
      } catch (err) {
        console.error(err);
        setConfirmLoading(true, false);
        document.getElementById('deleteAllBtn').disabled = false;
        window.VehiSightsUI.showNotice({ title: 'Delete all failed', message: 'The automatic records could not be deleted. Check your connection and try again.' });
      }
      if (!pendingActionRunning) closeConfirmModal(true);
    },
    true
  );
}

function showSessionDetails(session) {
  activeDetailsSessionId = session.id;
  const modal = document.getElementById('detailsModal');
  const modalBody = document.getElementById('detailsModalBody');
  document.getElementById('detailsModalTitle').textContent = session.status === 'active'
    ? 'Live Session Details'
    : (session.status === 'scheduled' ? 'Scheduled Session Details' : 'Session Details');
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

  const liveBannerHtml = session.status === 'active' ? `
    <div class="live-details-banner">
      <span class="live-details-dot"></span>
      <strong>Live counting</strong>
      <span>Vehicle totals refresh automatically every 2 seconds.</span>
    </div>
  ` : '';

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
          <div class="session-info-label">Day Classification</div>
          <div class="session-info-value">${session.day_classification === 'market_day' ? 'Market Day' : (session.day_classification === 'sunday' ? 'Sunday' : 'Not specified')}</div>
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
  modalBody.innerHTML = liveBannerHtml + sessionInfoHtml + statsHtml + vehicleRowsHtml;
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
    location: document.getElementById('edit_location').value,
    day_classification: document.getElementById('edit_day_classification').value || null,
    start_time: localInputToManilaIso(document.getElementById('edit_start_time').value),
    end_time: localInputToManilaIso(document.getElementById('edit_end_time').value),
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
  if (pendingAction && !pendingActionRunning) pendingAction();
});
document.getElementById('confirmModal').addEventListener('click', (e) => {
  if (e.target === document.getElementById('confirmModal')) closeConfirmModal();
});

// Delete All Modal handlers
document.getElementById('confirmDeleteAllCancelBtn').addEventListener('click', () => closeConfirmModal(true));
document.getElementById('confirmDeleteAllOkBtn').addEventListener('click', () => {
  if (pendingAction && !pendingActionRunning) pendingAction();
});
document.getElementById('confirmDeleteAllModal').addEventListener('click', (e) => {
  if (e.target === document.getElementById('confirmDeleteAllModal')) closeConfirmModal(true);
});

const detailsModal = document.getElementById('detailsModal');
const closeDetailsModal = () => {
  activeDetailsSessionId = null;
  detailsModal.classList.remove('active');
};
document.getElementById('closeDetailsModalBtn').addEventListener('click', closeDetailsModal);
detailsModal.addEventListener('click', (e) => { if (e.target === detailsModal) closeDetailsModal(); });

let recordsRefreshInProgress = false;

async function refreshAutoRecords() {
  if (recordsRefreshInProgress) return;

  recordsRefreshInProgress = true;

  try {
    const response = await fetch('/auto-records/data', {
      headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
      throw new Error('Unable to refresh automatic records.');
    }

    const result = await response.json();

    if (result.success) {
      sessionsData = result.sessions;
      renderTable();

      if (activeDetailsSessionId !== null) {
        const updatedSession = sessionsData.find(
          session => Number(session.id) === Number(activeDetailsSessionId)
        );

        if (updatedSession) {
          showSessionDetails(updatedSession);
        } else {
          closeDetailsModal();
        }
      }
    }
  } catch (error) {
    console.error('Auto Records refresh error:', error);
  } finally {
    recordsRefreshInProgress = false;
  }
}

setInterval(refreshAutoRecords, 2000);
