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
let dashboardChart = null;

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
