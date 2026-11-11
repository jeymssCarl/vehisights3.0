// Dark mode
const html = document.documentElement;
const toggle = document.getElementById('themeToggle');
const savedTheme = localStorage.getItem('vehisights-theme');
if (savedTheme) html.setAttribute('data-theme', savedTheme);
else html.setAttribute('data-theme', 'light');

toggle.addEventListener('click', () => {
  const isDark = html.getAttribute('data-theme') === 'dark';
  const newTheme = isDark ? 'light' : 'dark';
  html.setAttribute('data-theme', newTheme);
  localStorage.setItem('vehisights-theme', newTheme);
});

const initialDashboardData = JSON.parse(document.getElementById('dashboard-data').textContent);
const dashboardContent = document.querySelector('.content[data-live-sessions-url]');
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
let dashboardChart = null;
let liveSessionsRequest = null;

function escapeHtml(value) {
  const element = document.createElement('div');
  element.textContent = String(value ?? '');
  return element.innerHTML;
}

function activeSessionsEmptyState() {
  return `<div class="dashboard-empty-state"><div class="dashboard-empty-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></div><strong>No active sessions</strong><span>Sessions currently in progress will appear here.</span></div>`;
}

function renderActiveSessions(sessions = []) {
  const container = document.getElementById('activeSessionsContainer');
  const state = document.getElementById('activeSessionsState');
  if (!container || !state) return;

  if (!sessions.length) {
    state.innerHTML = '<div class="panel-state-label">No active session</div>';
    container.innerHTML = activeSessionsEmptyState();
    return;
  }

  state.innerHTML = '<div class="status-pill" style="font-size:0.7rem;padding:5px 11px;"><div class="pulse"></div>Live</div>';
  container.innerHTML = `<table class="vtable"><thead><tr><th>Session</th><th>Road</th><th>V/C</th><th>LOS</th><th>Status</th></tr></thead><tbody>${sessions.map(session => {
    const vc = Number(session.vc || 0);
    const los = String(session.los || 'N/A').toUpperCase();
    const losClass = /^[A-F]$/.test(los) ? los.toLowerCase() : 'a';
    const width = Math.min(Math.max(vc * 100, 0), 100);
    const color = vc >= 0.85 ? '#dc2626' : '#16a34a';
    return `<tr><td class="mono-cell">${escapeHtml(session.code)}</td><td>${escapeHtml(session.road)}</td><td><div class="vc-wrap"><span class="vc-num">${vc.toFixed(2)}</span><div class="vc-track"><div class="vc-fill" style="background:${color};width:${width}%"></div></div></div></td><td><span class="los-badge los-${losClass}">${escapeHtml(los)}</span></td><td><span class="status-tag ok"><span class="dot"></span>Active</span></td></tr>`;
  }).join('')}</tbody></table>`;
}

async function refreshLiveSessions() {
  if (!dashboardContent?.dataset.liveSessionsUrl || document.hidden || liveSessionsRequest) return;

  liveSessionsRequest = new AbortController();
  try {
    const response = await fetch(dashboardContent.dataset.liveSessionsUrl, {
      method: 'POST',
      headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
      cache: 'no-store',
      signal: liveSessionsRequest.signal,
    });
    if (!response.ok) throw new Error(`Live dashboard refresh failed (${response.status}).`);
    const payload = await response.json();
    renderActiveSessions(payload.active_sessions || []);

    const activeCameras = Number(payload.active_cameras || 0);
    const headerCount = document.getElementById('activeCamerasCount');
    const cardCount = document.getElementById('activeCameras');
    if (headerCount) headerCount.textContent = activeCameras;
    if (cardCount) cardCount.textContent = activeCameras;
  } catch (error) {
    if (error.name !== 'AbortError') console.warn(error.message);
  } finally {
    liveSessionsRequest = null;
  }
}

function initializeDashboardChart() {
  try {
    const data = initialDashboardData;
    
    // The metrics and tables are already rendered by Laravel. JavaScript only
    // initializes Chart.js from the same server-provided payload.
    if (dashboardChart) dashboardChart.destroy();
    if (data.chart_labels && data.chart_labels.length > 0) {
      const ctx = document.getElementById('trafficChart').getContext('2d');
      const isDark = html.getAttribute('data-theme') === 'dark';
      const grad = ctx.createLinearGradient(0, 0, 0, 260);
      grad.addColorStop(0, isDark ? 'rgba(123,29,29,0.30)' : 'rgba(123,29,29,0.18)');
      grad.addColorStop(1, 'rgba(123,29,29,0)');
      dashboardChart = new Chart(ctx, {
        type: 'line',
        data: { labels: data.chart_labels, datasets: [{ label: 'PCU / hour', data: data.chart_data, borderColor: '#7B1D1D', backgroundColor: grad, borderWidth: 2, fill: true, tension: 0.4, pointBackgroundColor: '#7B1D1D', pointBorderColor: isDark ? '#1c1917' : '#fff', pointBorderWidth: 2, pointRadius: 4 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => `${c.parsed.y.toLocaleString()} PCU` } } }, scales: { y: { beginAtZero: true, grid: { color: isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.04)' }, ticks: { color: isDark ? '#5c5852' : '#b0aa9e' } }, x: { ticks: { color: isDark ? '#5c5852' : '#b0aa9e', maxRotation: 0 } } } }
      });
    }
  } catch (error) {
    console.error('Error initializing dashboard chart:', error);
  }
}

function applyDateRange() {
  const startDate = document.getElementById('start_date').value;
  const endDate = document.getElementById('end_date').value;
  if (startDate && endDate) {
    window.location.href = `/dashboard?start_date=${startDate}&end_date=${endDate}`;
  }
}

function resetToToday() {
  window.location.href = `/dashboard`;
}

document.getElementById('applyFilterBtn').addEventListener('click', applyDateRange);
document.getElementById('resetTodayBtn').addEventListener('click', resetToToday);

initializeDashboardChart();

let chartResizeFrame = null;
window.addEventListener('resize', () => {
  window.cancelAnimationFrame(chartResizeFrame);
  chartResizeFrame = window.requestAnimationFrame(() => {
    dashboardChart?.resize();
  });
}, { passive: true });

document.addEventListener('visibilitychange', () => {
  if (!document.hidden) {
    dashboardChart?.resize();
    refreshLiveSessions();
  }
});

refreshLiveSessions();
window.setInterval(refreshLiveSessions, 1000);
