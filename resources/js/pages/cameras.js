
/* ─ Data ─ */
let cameras = [
  { id:1, name:'Timog Ave North',  ip:'192.168.1.10', location:'Timog Avenue & National Highway', status:'online',  lastDetection:'2s ago',   streamUrl:'rtsp://admin:pass@192.168.1.10:554/stream1' },
  { id:2, name:'Timog Ave South',  ip:'192.168.1.11', location:'Timog Avenue Southbound',          status:'online',  lastDetection:'5s ago',   streamUrl:'rtsp://admin:pass@192.168.1.11:554/stream1' },
  { id:3, name:'Apokon East',      ip:'192.168.1.12', location:'Apokon–Bermudez Intersection',     status:'online',  lastDetection:'1s ago',   streamUrl:'rtsp://admin:pass@192.168.1.12:554/stream1' },
  { id:4, name:'Apokon West',      ip:'192.168.1.13', location:'Apokon–Bermudez West',             status:'offline', lastDetection:'2m ago',   streamUrl:'rtsp://admin:pass@192.168.1.13:554/stream1' },
  { id:5, name:'Pioneer South',    ip:'192.168.1.14', location:'Pioneer Street',                   status:'online',  lastDetection:'just now', streamUrl:'rtsp://admin:pass@192.168.1.14:554/stream1' },
  { id:6, name:'Green Coffee',     ip:'192.168.1.15', location:'Green Coffee Warehouse',           status:'offline', lastDetection:'15m ago',  streamUrl:'rtsp://admin:pass@192.168.1.15:554/stream1' },
];

let activeFilter = 'all';
let editId = null;

/* ─ Toast ─ */
function showToast(msg, type = 'success') {
  const t = document.getElementById('toast');
  t.className = `toast ${type}`;
  document.getElementById('toastMsg').textContent = msg;
  void t.offsetWidth;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2600);
}

/* ─ Stats ─ */
function renderStats() {
  const total   = cameras.length;
  const online  = cameras.filter(c => c.status === 'online').length;
  const offline = cameras.filter(c => c.status === 'offline').length;
  document.getElementById('statsRow').innerHTML = `
    <div class="stat-card c-blue" style="animation-delay:0.05s">
      <div class="stat-icon c-blue">
        <svg viewBox="0 0 24 24"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
      </div>
      <div class="stat-label">Total Cameras</div>
      <div class="stat-value">${total}</div>
      <div class="stat-meta">${online} active right now</div>
    </div>
    <div class="stat-card c-green" style="animation-delay:0.10s">
      <div class="stat-icon c-green">
        <svg viewBox="0 0 24 24"><path d="M22 12A10 10 0 1 1 12 2"/><polyline points="22 4 12 14 9 11"/></svg>
      </div>
      <div class="stat-label">Online</div>
      <div class="stat-value">${online}</div>
      <div class="stat-meta" style="color:var(--green)">● Streaming live</div>
    </div>
    <div class="stat-card c-red" style="animation-delay:0.15s">
      <div class="stat-icon c-red">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      </div>
      <div class="stat-label">Offline</div>
      <div class="stat-value">${offline}</div>
      <div class="stat-meta" style="color:var(--red)">● Needs attention</div>
    </div>
  `;
}

/* ─ Table ─ */
function renderCameras() {
  const search = document.getElementById('searchInput').value.toLowerCase();
  const filtered = cameras.filter(c => {
    if (activeFilter !== 'all' && c.status !== activeFilter) return false;
    if (search && !c.name.toLowerCase().includes(search) && !c.ip.includes(search) && !c.location.toLowerCase().includes(search)) return false;
    return true;
  });

  const tbody = document.getElementById('camerasTableBody');
  if (!filtered.length) {
    tbody.innerHTML = `<tr><td colspan="7"><div class="empty-state">
      <svg viewBox="0 0 24 24"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
      <h4>No cameras found</h4>
      <p>Try adjusting your search or filter</p>
    </div></td></tr>`;
    return;
  }

  tbody.innerHTML = filtered.map(c => `
    <tr>
      <td><span class="id-chip">#${String(c.id).padStart(2,'0')}</span></td>
      <td>
        <div class="cam-name-cell">
          <div class="cam-avatar">
            <svg viewBox="0 0 24 24"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
          </div>
          <div>
            <div class="cam-name">${c.name}</div>
            ${c.streamUrl ? `<div class="cam-stream">${c.streamUrl.replace(/:[^:@]+@/,':***@')}</div>` : ''}
          </div>
        </div>
      </td>
      <td><span class="ip-text">${c.ip}</span></td>
      <td>${c.location}</td>
      <td>
        <span class="status-badge ${c.status}">
          <span class="dot"></span>
          ${c.status.charAt(0).toUpperCase() + c.status.slice(1)}
        </span>
      </td>
      <td><span class="detection-time">${c.lastDetection}</span></td>
      <td>
        <div class="action-group">
          <button class="act-btn view" onclick="testConnection(${c.id})" title="Test Connection">
            <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
          <button class="act-btn edit" onclick="editCamera(${c.id})" title="Edit">
            <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
          </button>
          <button class="act-btn delete" onclick="deleteCamera(${c.id})" title="Delete">
            <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
          </button>
        </div>
      </td>
    </tr>
  `).join('');
}

function render() { renderStats(); renderCameras(); }

/* ─ Actions ─ */
function testConnection(id) {
  const c = cameras.find(x => x.id === id);
  showToast(`${c.name}: ${c.status === 'online' ? 'Reachable ✓' : 'Unreachable — camera offline'}`,
    c.status === 'online' ? 'success' : 'error');
}
function editCamera(id) {
  const c = cameras.find(x => x.id === id);
  if (!c) return;
  editId = id;
  document.getElementById('modalTitle').textContent = 'Edit Camera';
  document.getElementById('camName').value = c.name;
  document.getElementById('camIp').value = c.ip;
  document.getElementById('camLocation').value = c.location;
  document.getElementById('camStreamUrl').value = c.streamUrl || '';
  document.getElementById('cameraModal').classList.add('active');
}
function deleteCamera(id) {
  const c = cameras.find(x => x.id === id);
  if (c && confirm(`Remove "${c.name}"? This cannot be undone.`)) {
    cameras = cameras.filter(x => x.id !== id);
    render();
    showToast(`${c.name} removed`, 'error');
  }
}

/* ─ Pills ─ */
document.querySelectorAll('.pill').forEach(pill => {
  pill.addEventListener('click', () => {
    document.querySelectorAll('.pill').forEach(p => p.classList.remove('active'));
    pill.classList.add('active');
    activeFilter = pill.dataset.filter;
    renderCameras();
  });
});

/* ─ Search & Refresh ─ */
document.getElementById('searchInput').addEventListener('input', renderCameras);
document.getElementById('refreshBtn').addEventListener('click', () => {
  render();
  showToast('Camera list refreshed', 'info');
});

/* ─ Modal ─ */
const modal = document.getElementById('cameraModal');
document.getElementById('newCameraBtn').addEventListener('click', () => {
  editId = null;
  document.getElementById('modalTitle').textContent = 'Add Camera';
  document.getElementById('cameraForm').reset();
  modal.classList.add('active');
});
function closeModal() { modal.classList.remove('active'); }
document.getElementById('closeModalBtn').addEventListener('click', closeModal);
document.getElementById('cancelModalBtn').addEventListener('click', closeModal);
modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });

document.getElementById('cameraForm').addEventListener('submit', e => {
  e.preventDefault();
  const name      = document.getElementById('camName').value.trim();
  const ip        = document.getElementById('camIp').value.trim();
  const location  = document.getElementById('camLocation').value.trim();
  const streamUrl = document.getElementById('camStreamUrl').value.trim();
  if (!name || !ip || !location) return;
  if (editId) {
    const idx = cameras.findIndex(c => c.id === editId);
    if (idx !== -1) Object.assign(cameras[idx], { name, ip, location, streamUrl });
    showToast(`${name} updated`, 'success');
  } else {
    cameras.push({ id: Math.max(...cameras.map(c => c.id), 0) + 1, name, ip, location, streamUrl, status: 'offline', lastDetection: 'never' });
    showToast(`${name} added`, 'success');
  }
  render();
  closeModal();
});

/* ─ Persistent Dark Mode with localStorage ─ */
const htmlEl = document.documentElement;
const toggle = document.getElementById('themeToggle');

const savedTheme = localStorage.getItem('vehisights-theme');
if (savedTheme) {
  htmlEl.setAttribute('data-theme', savedTheme);
} else {
  htmlEl.setAttribute('data-theme', 'light');
}

toggle.addEventListener('click', () => {
  const isDark = htmlEl.getAttribute('data-theme') === 'dark';
  const newTheme = isDark ? 'light' : 'dark';
  htmlEl.setAttribute('data-theme', newTheme);
  localStorage.setItem('vehisights-theme', newTheme);
});

render();

// Functions used by action controls rendered in the cameras table.
window.testConnection = testConnection;
window.editCamera = editCamera;
window.deleteCamera = deleteCamera;
