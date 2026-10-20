// Persistent dark mode with localStorage
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
  if (window.trendChart) updateChartTheme();
});

function updateChartTheme() {
  if (!window.trendChart) return;
  const dark = htmlEl.getAttribute('data-theme') === 'dark';
  window.trendChart.options.scales.y.grid.color = dark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';
  window.trendChart.update('none');
}

// Chart initialization
const pageData = JSON.parse(document.getElementById('page-data').textContent);
const chartLabels = pageData.chartLabels;
const chartData = pageData.chartData;
let trendChart = null;
const hasChartData = chartLabels && chartLabels.length > 0 && chartData && chartData.length > 0;

function initChart(labels, data) {
  const ctx = document.getElementById('trendChart').getContext('2d');
  if (trendChart) trendChart.destroy();
  const dark = htmlEl.getAttribute('data-theme') === 'dark';
  const grad = ctx.createLinearGradient(0, 0, 0, 300);
  grad.addColorStop(0, dark ? 'rgba(249,168,168,0.3)' : 'rgba(123,29,29,0.2)');
  grad.addColorStop(1, 'rgba(123,29,29,0)');
  
  trendChart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [{
        label: 'PCU / hour',
        data: data,
        borderColor: '#7B1D1D',
        backgroundColor: grad,
        borderWidth: 2.5,
        fill: true,
        tension: 0.3,
        pointBackgroundColor: '#7B1D1D',
        pointBorderColor: dark ? '#1c1917' : '#fff',
        pointBorderWidth: 2,
        pointRadius: 4,
        pointHoverRadius: 7
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: c => `${c.parsed.y.toLocaleString()} PCU` } }
      },
      scales: {
        y: { 
          beginAtZero: true, 
          grid: { color: dark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.04)' },
          ticks: { color: dark ? '#9b9590' : '#b0aa9e', font: { size: 11 } }
        },
        x: { 
          grid: { display: false },
          ticks: { 
            color: dark ? '#9b9590' : '#b0aa9e', 
            font: { size: 11 },
            maxRotation: 0,
            minRotation: 0
          }
        }
      }
    }
  });
}

// Initialize chart if data exists
if (hasChartData) {
  document.getElementById('trendChart').style.display = 'block';
  document.getElementById('chartEmptyState').style.display = 'none';
  initChart(chartLabels, chartData);
} else {
  document.getElementById('trendChart').style.display = 'none';
  document.getElementById('chartEmptyState').style.display = 'flex';
}

// Apply Filters (reload page with new parameters)
document.getElementById('applyFilters').addEventListener('click', () => {
  const startDate = document.getElementById('start_date').value;
  const endDate = document.getElementById('end_date').value;
  const station = document.getElementById('station').value;
  
  let url = '/reports?';
  if (startDate) url += `start_date=${startDate}&`;
  if (endDate) url += `end_date=${endDate}&`;
  if (station && station !== 'all') url += `station=${encodeURIComponent(station)}`;
  
  window.location.href = url;
});

// Export CSV (use the server endpoint)
document.getElementById('exportCsvBtn').addEventListener('click', async () => {
  const startDate = document.getElementById('start_date').value;
  const endDate = document.getElementById('end_date').value;
  const station = document.getElementById('station').value;
  
  const formData = new FormData();
  formData.append('start_date', startDate);
  formData.append('end_date', endDate);
  formData.append('station', station);
  
  const response = await fetch('/reports/export-csv', {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
    body: formData
  });
  
  const blob = await response.blob();
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `traffic_report_${startDate}_to_${endDate}.csv`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  window.URL.revokeObjectURL(url);
});

// Export PDF (download HTML version)
document.getElementById('exportPdfBtn').addEventListener('click', async () => {
  const startDate = document.getElementById('start_date').value;
  const endDate = document.getElementById('end_date').value;
  const station = document.getElementById('station').value;
  
  const formData = new FormData();
  formData.append('start_date', startDate);
  formData.append('end_date', endDate);
  formData.append('station', station);
  
  const response = await fetch('/reports/generate-pdf', {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
    body: formData
  });
  
  const blob = await response.blob();
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `traffic_report_${startDate}.html`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  window.URL.revokeObjectURL(url);
});

// Generate Survey Form Modal (uses current table data)
document.getElementById('generateFormBtn').addEventListener('click', () => {
  const tableRows = document.querySelectorAll('#hourlyTable tbody tr');
  const stationSelect = document.getElementById('station');
  const station = stationSelect.options[stationSelect.selectedIndex]?.text || 'All Stations';
  const startDate = document.getElementById('start_date').value;
  const endDate = document.getElementById('end_date').value;
  
  let tableHtml = '<table class="survey-table"><thead><tr><th>Hour</th><th>Station</th><th>Private Car/Taxi/Pick-up</th><th>Jeepney</th><th>Van</th><th>Bus</th><th>Light Truck</th><th>Heavy Truck</th><th>Motorcycle</th><th>Tricycle</th><th>Total PCU</th><th>V/C</th><th>LOS</th><th>Source</th></tr></thead><tbody>';
  
  tableRows.forEach(row => {
    if (row.classList && row.classList.contains('empty-state')) return;
    const cells = row.querySelectorAll('td');
    if (cells.length >= 14) {
      tableHtml += `<tr>
        <td>${cells[0]?.innerText || ''}</td>
        <td>${cells[1]?.innerText || ''}</td>
        <td>${cells[2]?.innerText || ''}</td>
        <td>${cells[3]?.innerText || ''}</td>
        <td>${cells[4]?.innerText || ''}</td>
        <td>${cells[5]?.innerText || ''}</td>
        <td>${cells[6]?.innerText || ''}</td>
        <td>${cells[7]?.innerText || ''}</td>
        <td>${cells[8]?.innerText || ''}</td>
        <td>${cells[9]?.innerText || ''}</td>
        <td>${cells[10]?.innerText || ''}</td>
        <td>${cells[11]?.innerText || ''}</td>
        <td>${cells[12]?.innerText || ''}</td>
        <td>${cells[13]?.innerText || ''}</td>
      </tr>`;
    }
  });
  
  tableHtml += '</tbody></tr>';
  
  const totalPcu = document.getElementById('totalPcu').innerText;
  const avgVc = document.getElementById('avgVc').innerText;
  const maxLos = document.getElementById('maxLos').innerText;
  const totalRecords = tableRows.length;
  
  const surveyHtml = `
    <div style="text-align:center; margin-bottom:20px;">
      <h4>Republic of the Philippines</h4>
      <h4>Department of Transportation (DOTr)</h4>
      <h4>Land Transportation Franchising and Regulatory Board (LTFRB)</h4>
      <h3 style="margin-top:15px;">VEHICLE COUNT SURVEY FORM</h3>
    </div>
    <div style="margin-bottom:20px;">
      <p><strong>Station:</strong> ${station}</p>
      <p><strong>Date Range:</strong> ${startDate} to ${endDate}</p>
      <p><strong>Generated:</strong> ${new Date().toLocaleString()}</p>
    </div>
    ${tableHtml}
    <div style="margin-top:20px;">
      <p><strong>Total PCU:</strong> ${totalPcu}</p>
      <p><strong>Average V/C Ratio:</strong> ${avgVc}</p>
      <p><strong>Peak LOS:</strong> ${maxLos}</p>
      <p><strong>Total Records:</strong> ${totalRecords}</p>
    </div>
    <div style="margin-top:30px; text-align:center; font-size:0.7rem;">
      <p>Prepared by: VehiSights Traffic Monitoring System</p>
      <p>${new Date().toLocaleString()}</p>
    </div>
  `;
  
  document.getElementById('surveyContent').innerHTML = surveyHtml;
  document.getElementById('surveyModal').classList.add('active');
});

// Print Survey Form
document.getElementById('printSurveyBtn').addEventListener('click', () => {
  const printContent = document.getElementById('surveyContent').innerHTML;
  const printWindow = window.open('', '_blank');
  printWindow.document.write(`
    <html>
      <head>
        <title>LTFRB Vehicle Count Survey Form</title>
        <style>
          body { font-family: Arial, sans-serif; margin: 20px; }
          .survey-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
          .survey-table th, .survey-table td { border: 1px solid #000; padding: 6px; text-align: center; font-size: 9px; }
          .survey-table th { background: #f0f0f0; }
          @media print { body { margin: 0; } }
        </style>
      </head>
      <body>${printContent}</body>
    </html>
  `);
  printWindow.document.close();
  printWindow.print();
});

// Close modal
document.getElementById('closeModalBtn').addEventListener('click', () => {
  document.getElementById('surveyModal').classList.remove('active');
});
document.getElementById('surveyModal').addEventListener('click', (e) => {
  if (e.target === document.getElementById('surveyModal')) {
    document.getElementById('surveyModal').classList.remove('active');
  }
});

// Copy table to clipboard
document.getElementById('copyTableBtn').addEventListener('click', () => {
  const table = document.getElementById('hourlyTable');
  const range = document.createRange();
  range.selectNode(table);
  window.getSelection().removeAllRanges();
  window.getSelection().addRange(range);
  document.execCommand('copy');
  window.getSelection().removeAllRanges();
  
  const btn = document.getElementById('copyTableBtn');
  const originalText = btn.innerText;
  btn.innerText = '✓ Copied!';
  setTimeout(() => { btn.innerText = originalText; }, 2000);
});
