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

let currentDateFilter = 'today';
let customStartDate = null;
let customEndDate = null;

// Vehicle types with PCU factors
const vehicleTypes = [
  { name: "Private Car / Taxi / Pick-up", pcu: 1.0 },
  { name: "Jeepney", pcu: 1.5 },
  { name: "Van for Hire (AUV, GT Express)", pcu: 1.5 },
  { name: "Bus & Mini Bus", pcu: 2.5 },
  { name: "Light Truck", pcu: 2.0 },
  { name: "Heavy Truck", pcu: 3.0 },
  { name: "Motorcycle", pcu: 0.5 },
  { name: "Tricycle", pcu: 0.7 }
];

function getDateRange(range) {
  const today = new Date();
  let startDate, endDate;
  
  switch(range) {
    case 'today':
      startDate = new Date(today);
      endDate = new Date(today);
      break;
    case 'this_month':
      startDate = new Date(today.getFullYear(), today.getMonth(), 1);
      endDate = new Date(today);
      break;
    default:
      return null;
  }
  
  const formatDate = (d) => {
    let year = d.getFullYear();
    let month = String(d.getMonth() + 1).padStart(2, '0');
    let day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  };
  
  return { start: formatDate(startDate), end: formatDate(endDate) };
}

function filterByDate(session) {
  if (!currentDateFilter && !customStartDate) return true;
  const sessionDate = session.start_time ? session.start_time.split('T')[0] : null;
  if (!sessionDate) return false;
  if (customStartDate && customEndDate) {
    return sessionDate >= customStartDate && sessionDate <= customEndDate;
  }
  if (currentDateFilter) {
    const range = getDateRange(currentDateFilter);
    if (range) {
      return sessionDate >= range.start && sessionDate <= range.end;
    }
  }
  return true;
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

function renderTable() {
  document.querySelectorAll('.date-pill').forEach(btn => {
    btn.classList.remove('date-pill-active');
    if (btn.dataset.date === currentDateFilter && !customStartDate) {
      btn.classList.add('date-pill-active');
    }
  });
  
  const searchTerm = document.getElementById('searchInput').value.toLowerCase();
  let filtered = sessionsData.filter(s => {
    if (searchTerm && !s.session_code.toLowerCase().includes(searchTerm) && !s.road_name.toLowerCase().includes(searchTerm)) return false;
    if (!filterByDate(s)) return false;
    return true;
  });
  
  const tbody = document.getElementById('sessionsTableBody');
  if (filtered.length === 0) {
    tbody.innerHTML = '<tr><td colspan="7" class="empty-message">No sessions found</td><tr';
    return;
  }
  
  tbody.innerHTML = filtered.map(s => {
    const dateTime = formatDateTime(s.start_time);
    return `
      <tr onclick="rowClickHandler(event)" data-session-code="${escapeHtml(s.session_code)}">
        <td style="font-family:'DM Mono',monospace;">${escapeHtml(s.session_code)}</td>
        <td>${escapeHtml(s.road_name)}</td>
        <td>${escapeHtml(s.direction)}</td>
        <td style="font-family:'DM Mono',monospace; font-size:0.75rem;">${dateTime.date}</td>
        <td style="font-family:'DM Mono',monospace; font-size:0.75rem;">${dateTime.time}</td>
        <td><span class="status-badge ${s.status}">${s.status === 'active' ? 'Active' : 'Ended'}</span></td>
        <td class="action-icons" onclick="event.stopPropagation()">
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
  document.querySelectorAll('#sessionsTableBody tr').forEach(row => {
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
  document.getElementById('edit_road_name').value = session.road_name || '';
  document.getElementById('edit_barangay').value = session.barangay || '';
  document.getElementById('edit_city').value = session.city || '';
  document.getElementById('edit_direction').value = session.direction || '';
  document.getElementById('edit_lanes').value = session.lanes || 2;
  document.getElementById('edit_has_median').value = session.has_median ? '1' : '0';
  if (session.start_time) {
    const start = new Date(session.start_time);
    document.getElementById('edit_start_time').value = start.toISOString().slice(0, 16);
  }
  if (session.end_time) {
    const end = new Date(session.end_time);
    document.getElementById('edit_end_time').value = end.toISOString().slice(0, 16);
  }
  document.getElementById('edit_status').value = session.status;
  
  // Load vehicle counts from session detail
  const details = session.detail;
  let counts = details?.counts || [0,0,0,0,0,0,0,0];
  let pcuTotals = details?.pcu_totals || [0,0,0,0,0,0,0,0];
  
  if (typeof counts === 'string') {
    try { counts = JSON.parse(counts); } catch(e) { counts = [0,0,0,0,0,0,0,0]; }
  }
  if (typeof pcuTotals === 'string') {
    try { pcuTotals = JSON.parse(pcuTotals); } catch(e) { pcuTotals = [0,0,0,0,0,0,0,0]; }
  }
  
  renderVehicleEditTable(counts, pcuTotals);
  updateEditSummary();
  
  document.getElementById('editModal').classList.add('active');
}

function renderVehicleEditTable(counts, pcuTotals) {
  const tbody = document.getElementById('vehicleEditTableBody');
  tbody.innerHTML = vehicleTypes.map((vt, idx) => `
    <tr>
      <td style="text-align:left;">${vt.name}</td>
      <td>${vt.pcu}</td>
      <td><input type="number" class="edit-count" data-idx="${idx}" value="${counts[idx] || 0}" min="0" step="1" style="width:80px; text-align:center;"></td>
      <td id="edit_pcu_${idx}" style="font-family:'DM Mono',monospace;">${(counts[idx] || 0) * vt.pcu}</td>
    </tr>
  `).join('');
  
  document.querySelectorAll('.edit-count').forEach(input => {
    input.addEventListener('input', function() {
      const idx = parseInt(this.dataset.idx);
      const count = parseInt(this.value) || 0;
      const pcu = count * vehicleTypes[idx].pcu;
      document.getElementById(`edit_pcu_${idx}`).innerText = pcu;
      updateEditSummary();
    });
  });
}

function updateEditSummary() {
  let totalPcu = 0;
  document.querySelectorAll('.edit-count').forEach(input => {
    const idx = parseInt(input.dataset.idx);
    const count = parseInt(input.value) || 0;
    totalPcu += count * vehicleTypes[idx].pcu;
  });
  
  const lanes = parseInt(document.getElementById('edit_lanes').value) || 1;
  const hasMedian = document.getElementById('edit_has_median').value === '1';
  const baseCap = hasMedian ? 900 : 720;
  const totalCapacity = lanes * baseCap;
  const vc = totalCapacity > 0 ? (totalPcu / totalCapacity).toFixed(2) : 0;
  
  let los = '';
  if (vc < 0.20) los = 'A';
  else if (vc < 0.45) los = 'B';
  else if (vc < 0.70) los = 'C';
  else if (vc < 0.85) los = 'D';
  else if (vc <= 1.0) los = 'E';
  else los = 'F';
  
  document.getElementById('edit_total_pcu').innerText = totalPcu;
  document.getElementById('edit_vc_ratio').innerText = vc;
  document.getElementById('edit_los').innerText = los;
}

document.getElementById('edit_lanes').addEventListener('input', updateEditSummary);
document.getElementById('edit_has_median').addEventListener('change', updateEditSummary);

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
    'Delete Session?',
    `Are you sure you want to delete session <strong>${escapeHtml(session.session_code)}</strong>?<br>This action cannot be undone.`,
    async () => {
      try {
        const response = await fetch(`/sessions/${id}`, {
          method: 'DELETE',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
            'Content-Type': 'application/json'
          },
        });
        const result = await response.json();
        if (result.success) {
          window.location.reload();
        } else {
          alert('❌ Delete failed: ' + (result.message || 'Unknown error'));
        }
      } catch (err) {
        console.error(err);
        alert('❌ Error deleting session');
      }
      closeConfirmModal();
    }
  );
}

async function handleDeleteAll() {
  showConfirmModal(
    'Delete All Records?',
    'Are you sure you want to delete ALL manual records? This action cannot be undone.',
    async () => {
      try {
        const response = await fetch('/sessions/delete-all', {
          method: 'DELETE',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
            'Content-Type': 'application/json'
          },
        });
        const result = await response.json();
        if (result.success) {
          window.location.reload();
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
            return `<tr><td style="text-align:left;">${escapeHtml(name)}</td><td>${count}</td><td>${pcu}</td></tr>`;
          }).join('')}
        </tbody>
      </table>
    `;
  } else {
    vehicleRowsHtml = '<div style="color:var(--text-muted); margin-top:16px;">No vehicle breakdown data.</div>';
  }

  modalBody.innerHTML = statsHtml + vehicleRowsHtml;
  modal.classList.add('active');
}

// Date filter handlers
document.querySelectorAll('.date-pill').forEach(btn => {
  btn.addEventListener('click', () => {
    const dateValue = btn.dataset.date;
    if (dateValue) {
      document.querySelectorAll('.date-pill').forEach(b => b.classList.remove('date-pill-active'));
      btn.classList.add('date-pill-active');
      currentDateFilter = dateValue;
      customStartDate = null;
      customEndDate = null;
      renderTable();
    }
  });
});

document.getElementById('searchInput').addEventListener('input', renderTable);
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
  
  // Get vehicle counts from edit table
  const counts = [];
  document.querySelectorAll('.edit-count').forEach(input => {
    counts.push(parseInt(input.value) || 0);
  });
  
  // Calculate PCU totals
  const pcuTotals = counts.map((count, idx) => count * vehicleTypes[idx].pcu);
  const totalPcu = pcuTotals.reduce((a,b) => a + b, 0);
  const lanes = parseInt(document.getElementById('edit_lanes').value);
  const hasMedian = document.getElementById('edit_has_median').value === '1';
  const baseCap = hasMedian ? 900 : 720;
  const totalCapacity = lanes * baseCap;
  const vc = totalCapacity > 0 ? (totalPcu / totalCapacity).toFixed(2) : 0;
  
  let los = '';
  if (vc < 0.20) los = 'A';
  else if (vc < 0.45) los = 'B';
  else if (vc < 0.70) los = 'C';
  else if (vc < 0.85) los = 'D';
  else if (vc <= 1.0) los = 'E';
  else los = 'F';
  
  const data = {
    road_name: document.getElementById('edit_road_name').value,
    barangay: document.getElementById('edit_barangay').value,
    city: document.getElementById('edit_city').value,
    direction: document.getElementById('edit_direction').value,
    lanes: lanes,
    has_median: hasMedian,
    start_time: document.getElementById('edit_start_time').value,
    end_time: document.getElementById('edit_end_time').value || null,
    status: document.getElementById('edit_status').value,
    counts: counts,
    pcu_totals: pcuTotals,
    total_pcu: totalPcu,
    vc: parseFloat(vc),
    los: los
  };
  
  const session = sessionsData.find(s => s.id == id);
  showConfirmModal(
    'Update Session?',
    `Are you sure you want to update session <strong>${escapeHtml(session?.session_code)}</strong>? This will update dashboard and reports.`,
    async () => {
      try {
        const response = await fetch(`/sessions/${id}/full-update`, {
          method: 'PUT',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(data)
        });
        const result = await response.json();
        if (result.success) {
          window.location.reload();
        } else {
          alert('❌ Update failed: ' + (result.message || 'Unknown error'));
        }
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

// Custom Date Modal
const customDateBtn = document.getElementById('customDateBtn');
const customDateModal = document.getElementById('customDateModal');
const closeDateModalBtn = document.getElementById('closeDateModalBtn');
const applyDateModalBtn = document.getElementById('applyDateModalBtn');
const modalStartDate = document.getElementById('modal_start_date');
const modalEndDate = document.getElementById('modal_end_date');

customDateBtn.addEventListener('click', () => {
  modalStartDate.value = pageData.today;
  modalEndDate.value = pageData.today;
  customDateModal.classList.add('active');
});

function closeDateModal() {
  customDateModal.classList.remove('active');
}

closeDateModalBtn.addEventListener('click', closeDateModal);
customDateModal.addEventListener('click', (e) => {
  if (e.target === customDateModal) closeDateModal();
});

applyDateModalBtn.addEventListener('click', () => {
  const startDate = modalStartDate.value;
  const endDate = modalEndDate.value;
  if (startDate && endDate) {
    document.querySelectorAll('.date-pill').forEach(b => b.classList.remove('date-pill-active'));
    currentDateFilter = null;
    customStartDate = startDate;
    customEndDate = endDate;
    renderTable();
  }
  closeDateModal();
});

// Function used by table rows rendered from the server-provided session data.
window.rowClickHandler = rowClickHandler;
