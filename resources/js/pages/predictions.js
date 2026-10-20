// Dark mode
const html = document.documentElement;
const toggle = document.getElementById('themeToggle');
const saved = localStorage.getItem('vehisights-theme') || 'light';
html.setAttribute('data-theme', saved);
toggle.addEventListener('click', () => {
  const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
  html.setAttribute('data-theme', next);
  localStorage.setItem('vehisights-theme', next);
  rebuildCharts();
});

function isDark() { return html.getAttribute('data-theme') === 'dark'; }
function getGridColor() { return isDark() ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.07)'; }
function getTextColor() { return isDark() ? '#9b9590' : '#737069'; }

// Data
const histHours = ['6AM','7AM','8AM','9AM','10AM','11AM','12PM','1PM','2PM','3PM','4PM','5PM','6PM','7PM','8PM','9PM'];
const histPcu   = [125,245,412,589,678,745,812,789,856,924,1102,1245,1089,845,612,345];
const predHoursAll = ['1PM','2PM','3PM','4PM','5PM','6PM','7PM','8PM'];
const predActual   = [789, 856, 924, 1102, null, null, null, null];
const predForecast = [null, null, null, 1102, 1390, 1210, 980, 720];

let histChartObj = null, predChartObj = null;

function makeHistChart() {
  const ctx = document.getElementById('histChart').getContext('2d');
  if (histChartObj) histChartObj.destroy();
  histChartObj = new Chart(ctx, {
    type: 'bar',
    data: { labels: histHours, datasets: [{ label: 'Avg PCU', data: histPcu, backgroundColor: 'rgba(123,29,29,0.75)', borderColor: '#7B1D1D', borderWidth: 1, borderRadius: 3, hoverBackgroundColor: '#7B1D1D' }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => `${c.parsed.y.toLocaleString()} PCU` } } }, scales: { y: { beginAtZero: true, grid: { color: getGridColor() }, ticks: { color: getTextColor(), font: { size: 10 } } }, x: { grid: { display: false }, ticks: { color: getTextColor(), font: { size: 9 }, maxRotation: 0 } } } }
  });
}

function makePredChart() {
  const ctx = document.getElementById('predChart').getContext('2d');
  if (predChartObj) predChartObj.destroy();
  predChartObj = new Chart(ctx, {
    type: 'line',
    data: { labels: predHoursAll, datasets: [
      { label: 'Actual', data: predActual, borderColor: '#7B1D1D', backgroundColor: 'rgba(123,29,29,0.08)', borderWidth: 2, fill: true, tension: 0.3, pointBackgroundColor: '#7B1D1D', pointRadius: 3, spanGaps: false },
      { label: 'Forecast', data: predForecast, borderColor: '#b45309', backgroundColor: 'rgba(180,83,9,0.06)', borderWidth: 2, borderDash: [5, 4], fill: true, tension: 0.3, pointBackgroundColor: '#b45309', pointRadius: 3, pointStyle: 'triangle', spanGaps: false }
    ] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => c.parsed.y ? `${c.parsed.y.toLocaleString()} PCU` : null } } }, scales: { y: { beginAtZero: false, min: 600, grid: { color: getGridColor() }, ticks: { color: getTextColor(), font: { size: 10 } } }, x: { grid: { display: false }, ticks: { color: getTextColor(), font: { size: 9 }, maxRotation: 0 } } } }
  });
}

// Updated forecast data - removed Risk field
const forecastData = [
  { h: '1:00 PM', pcu: 789, vc: 0.55, los: 'C', status: 'Moderate', pred: false },
  { h: '2:00 PM', pcu: 856, vc: 0.59, los: 'C', status: 'Moderate', pred: false },
  { h: '3:00 PM', pcu: 924, vc: 0.64, los: 'D', status: 'Heavy', pred: false },
  { h: '4:00 PM', pcu: 1102, vc: 0.77, los: 'D', status: 'Heavy', pred: false },
  { h: '5:00 PM', pcu: 1390, vc: 0.97, los: 'E', status: 'Severe', pred: true },
  { h: '6:00 PM', pcu: 1210, vc: 0.84, los: 'E', status: 'Severe', pred: true },
  { h: '7:00 PM', pcu: 980, vc: 0.68, los: 'D', status: 'Heavy', pred: true },
  { h: '8:00 PM', pcu: 720, vc: 0.50, los: 'C', status: 'Moderate', pred: true },
  { h: '9:00 PM', pcu: 450, vc: 0.31, los: 'B', status: 'Light', pred: true },
  { h: '10:00 PM', pcu: 280, vc: 0.19, los: 'A', status: 'Free Flow', pred: true },
];

function losClass(l) {
  const m = { A: 'los-a', B: 'los-b', C: 'los-c', D: 'los-d', E: 'los-e', F: 'los-f' };
  return m[l] || 'los-a';
}

function buildTable() {
  const tbody = document.getElementById('forecastBody');
  tbody.innerHTML = forecastData.map(r => `
    <tr class="${r.pred ? 'pred-row' : ''}">
      <td style="font-family:'DM Mono',monospace;font-size:0.73rem;">${r.h}</td>
      <td style="font-family:'DM Mono',monospace;font-weight:700;">${r.pcu.toLocaleString()}</td>
      <td style="font-family:'DM Mono',monospace;">${r.vc.toFixed(2)}</td>
      <td><span class="los-badge ${losClass(r.los)}">${r.los}</span></td>
      <td>${r.status}</td>
      <td style="font-size:0.68rem;color:${r.pred ? 'var(--amber)' : 'var(--text-muted)'};">${r.pred ? 'Forecast' : 'Actual'}</td>
    </tr>
  `).join('');
}

// Auto-refresh function (no button needed)
function refreshForecast() {
  const station = document.getElementById('stationSel').value;
  document.getElementById('forecastStation').innerText = station;
  
  // Update KPIs based on station selection
  const kpiCurrent = document.getElementById('kpiCurrent');
  const kpiNext = document.getElementById('kpiNext');
  const kpiPeak = document.getElementById('kpiPeak');
  const kpiConf = document.getElementById('kpiConf');
  
  if (station === 'Timog Avenue') {
    kpiCurrent.innerText = '845';
    kpiNext.innerText = '1,020';
    kpiPeak.innerText = '5:00 PM';
    kpiConf.innerText = '89';
  } else if (station === 'Apokon Intersection') {
    kpiCurrent.innerText = '632';
    kpiNext.innerText = '780';
    kpiPeak.innerText = '4:30 PM';
    kpiConf.innerText = '86';
  } else {
    kpiCurrent.innerText = '412';
    kpiNext.innerText = '510';
    kpiPeak.innerText = '5:30 PM';
    kpiConf.innerText = '84';
  }
  
  // Update insights text based on station
  const insightItems = document.querySelectorAll('.insight-item');
  if (station === 'Timog Avenue') {
    insightItems[0].textContent = 'Traffic expected to surge 22% between 4PM–6PM today.';
    insightItems[1].textContent = 'LOS at Timog Avenue likely to degrade to E by 5PM.';
  } else if (station === 'Apokon Intersection') {
    insightItems[0].textContent = 'Moderate traffic expected with peak at 4:30PM.';
    insightItems[1].textContent = 'LOS at Apokon Intersection likely to stay at D throughout rush hour.';
  } else {
    insightItems[0].textContent = 'Light to moderate traffic expected throughout the day.';
    insightItems[1].textContent = 'LOS at Pioneer Street expected to remain at C or better.';
  }
}

function rebuildCharts() {
  makeHistChart();
  makePredChart();
}

// Auto-refresh on page load and every 15 minutes
document.addEventListener('DOMContentLoaded', function() {
  // Initial load - automatic
  refreshForecast();
  
  // Auto-refresh every 15 minutes (900,000 ms)
  setInterval(refreshForecast, 900000);
});

// init charts
buildTable();
makeHistChart();
makePredChart();

// Expose for debugging
window.refreshForecast = refreshForecast;
