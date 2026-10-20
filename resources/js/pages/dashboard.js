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

const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
let dashboardChart = null;

function getSavedDateRange() {
  const savedStart = localStorage.getItem('dashboard_start_date');
  const savedEnd = localStorage.getItem('dashboard_end_date');
  if (savedStart && savedEnd) return { start: savedStart, end: savedEnd };
  return null;
}

function saveDateRange(startDate, endDate) {
  localStorage.setItem('dashboard_start_date', startDate);
  localStorage.setItem('dashboard_end_date', endDate);
}

function clearSavedDateRange() {
  localStorage.removeItem('dashboard_start_date');
  localStorage.removeItem('dashboard_end_date');
}

function showLoading() {
  document.getElementById('loadingOverlay').classList.add('active');
}

function hideLoading() {
  document.getElementById('loadingOverlay').classList.remove('active');
}

function animateNumber(element, target, decimals, duration) {
  if (!element) return;
  const startValue = 0;
  const startTime = performance.now();
  function step(now) {
    const elapsed = now - startTime;
    const progress = Math.min(elapsed / duration, 1);
    const easeOut = 1 - Math.pow(1 - progress, 3);
    const currentValue = startValue + (target * easeOut);
    if (decimals > 0) {
      element.textContent = currentValue.toFixed(decimals);
    } else {
      element.textContent = Math.round(currentValue).toLocaleString();
    }
    if (progress < 1) {
      requestAnimationFrame(step);
    } else {
      element.textContent = decimals > 0 ? target.toFixed(decimals) : Math.round(target).toLocaleString();
    }
  }
  requestAnimationFrame(step);
}

function showStatCards() {
  const cards = document.querySelectorAll('.stat-card');
  cards.forEach((card, idx) => {
    setTimeout(() => {
      card.classList.add('visible');
    }, idx * 50);
  });
}

async function loadDashboardData() {
  showLoading();
  
  const startDate = document.getElementById('start_date').value;
  const endDate = document.getElementById('end_date').value;
  const url = `/dashboard-data?start_date=${startDate}&end_date=${endDate}`;
  
  try {
    const response = await fetch(url, {
      headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
    });
    const data = await response.json();
    
    // Update stat cards with animation
    animateNumber(document.getElementById('totalPcu'), data.total_pcu, 0, 1200);
    animateNumber(document.getElementById('avgVc'), data.avg_vc, 2, 1200);
    document.getElementById('peakLos').textContent = data.peak_los;
    document.getElementById('activeCameras').textContent = data.active_cameras;
    
    // Update meta text
    const dateRangeText = startDate === endDate ? `For ${new Date(startDate).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}` : `${new Date(startDate).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} - ${new Date(endDate).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}`;
    document.getElementById('totalPcuMeta').textContent = dateRangeText;
    document.getElementById('avgVcMeta').textContent = data.avg_vc >= 0.7 ? 'Level D/E — heavy' : (data.avg_vc >= 0.45 ? 'Level C — moderate' : 'Level A/B — light');
    document.getElementById('peakLosMeta').textContent = data.peak_los === 'A' || data.peak_los === 'B' ? 'Free flow' : (data.peak_los === 'C' ? 'Stable near capacity' : (data.peak_los === 'D' ? 'Approaching unstable' : 'Near capacity/Breakdown'));
    
    // Update chart
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
    
    // Update recent manual counts
    if (data.recent_manual && data.recent_manual.length > 0) {
      document.getElementById('recentManualContainer').innerHTML = `<table class="vtable"><thead><tr><th>Time</th><th>Station</th><th>PCU</th><th>LOS</th></tr></thead><tbody>${data.recent_manual.map(m => `<tr><td style="font-family:'DM Mono',monospace;">${m.time}</td><td>${m.station}</td><td style="font-family:'DM Mono',monospace;">${m.pcu.toLocaleString()}</td><td><span class="los-badge los-${m.los.toLowerCase()}">${m.los}</span></td></tr>`).join('')}</tbody></table><div style="margin-top:12px;"><a href="/manual-records" class="view-all">View all →</a></div>`;
    } else {
      document.getElementById('recentManualContainer').innerHTML = '<div style="text-align: center; padding: 48px 20px;"><p style="color: var(--text-muted);">No manual counts found</p></div>';
    }
    
    // Update active sessions
    if (data.active_sessions && data.active_sessions.length > 0) {
      document.getElementById('activeSessionsContainer').innerHTML = `<table class="vtable"><thead><tr><th>Session</th><th>Road</th><th>V/C</th><th>LOS</th><th>Status</th></tr></thead><tbody>${data.active_sessions.map(s => `<tr><td style="font-family:'DM Mono',monospace;">${s.code}</td><td>${s.road}</td><td><div class="vc-wrap"><span class="vc-num">${s.vc}</span><div class="vc-track"><div class="vc-fill" style="background:${parseFloat(s.vc) >= 0.85 ? '#dc2626' : '#16a34a'}; width:${Math.min(parseFloat(s.vc) * 100, 100)}%"></div></div></div></td><td><span class="los-badge los-${s.los?.toLowerCase() || 'a'}">${s.los || 'N/A'}</span></td><td><span class="status-tag ok"><span class="dot"></span>Active</span></td></tr>`).join('')}</tbody></table>`;
    } else {
      document.getElementById('activeSessionsContainer').innerHTML = '<div style="text-align: center; padding: 48px 20px;"><p style="color: var(--text-muted);">No active sessions</p></div>';
    }
    
    // Update recent auto counts
    if (data.recent_auto && data.recent_auto.length > 0) {
      document.getElementById('recentAutoContainer').innerHTML = `<table class="vtable"><thead><tr><th>Time</th><th>Camera</th><th>PCU</th><th>LOS</th></tr></thead><tbody>${data.recent_auto.map(a => `<tr><td style="font-family:'DM Mono',monospace;">${a.time}</td><td>${a.camera}</td><td style="font-family:'DM Mono',monospace;">${a.pcu}</td><td><span class="los-badge los-${a.los?.toLowerCase() || 'a'}">${a.los || '?'}</span></td></tr>`).join('')}</tbody></table>`;
    } else {
      document.getElementById('recentAutoContainer').innerHTML = '<div style="text-align: center; padding: 48px 20px;"><p style="color: var(--text-muted);">No auto counts yet</p></div>';
    }
    
    showStatCards();
  } catch (error) {
    console.error('Error loading dashboard:', error);
  } finally {
    hideLoading();
  }
}

function setDateInputs() {
  const urlParams = new URLSearchParams(window.location.search);
  const urlStart = urlParams.get('start_date');
  const urlEnd = urlParams.get('end_date');
  const startInput = document.getElementById('start_date');
  const endInput = document.getElementById('end_date');
  
  if (urlStart && urlEnd) {
    startInput.value = urlStart;
    endInput.value = urlEnd;
    saveDateRange(urlStart, urlEnd);
  } else {
    const saved = getSavedDateRange();
    if (saved) {
      startInput.value = saved.start;
      endInput.value = saved.end;
    } else {
      const today = new Date().toISOString().split('T')[0];
      startInput.value = today;
      endInput.value = today;
    }
  }
}

function applyDateRange() {
  const startDate = document.getElementById('start_date').value;
  const endDate = document.getElementById('end_date').value;
  if (startDate && endDate) {
    saveDateRange(startDate, endDate);
    window.location.href = `/dashboard?start_date=${startDate}&end_date=${endDate}`;
  }
}

function resetToToday() {
  clearSavedDateRange();
  window.location.href = `/dashboard`;
}

document.getElementById('applyFilterBtn').addEventListener('click', applyDateRange);
document.getElementById('resetTodayBtn').addEventListener('click', resetToToday);

setDateInputs();
loadDashboardData();
