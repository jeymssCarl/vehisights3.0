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

function selectedFilters() {
  const startDate = document.getElementById('start_date').value;
  const endDate = document.getElementById('end_date').value;
  const station = document.getElementById('station').value;

  return { startDate, endDate, station };
}

function filtersAreValid() {
  const { startDate, endDate } = selectedFilters();
  const endInput = document.getElementById('end_date');
  endInput.setCustomValidity('');
  if (!startDate || !endDate) return false;
  if (startDate > endDate) {
    endInput.setCustomValidity('End date must be on or after the start date.');
    endInput.reportValidity();
    return false;
  }
  return true;
}

document.getElementById('applyFilters').addEventListener('click', () => {
  if (!filtersAreValid()) return;
  const { startDate, endDate, station } = selectedFilters();
  const params = new URLSearchParams({ start_date: startDate, end_date: endDate });
  if (station !== 'all') params.set('station', station);
  window.location.href = `/reports?${params.toString()}`;
});

async function downloadReport(endpoint, extension, trigger) {
  if (!filtersAreValid()) return;

  const { startDate, endDate, station } = selectedFilters();
  const formData = new FormData();
  formData.append('start_date', startDate);
  formData.append('end_date', endDate);
  formData.append('station', station);

  const originalText = trigger.textContent;
  trigger.disabled = true;
  trigger.textContent = 'Preparing...';

  try {
    const response = await fetch(endpoint, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        'Accept': extension === 'pdf' ? 'application/pdf' : 'text/csv'
      },
      body: formData
    });
    if (!response.ok) throw new Error('The report could not be generated.');

    const blob = await response.blob();
    const objectUrl = window.URL.createObjectURL(blob);
    const downloadLink = document.createElement('a');
    downloadLink.href = objectUrl;
    downloadLink.download = `vehisights_vehicle_count_${startDate}_to_${endDate}.${extension}`;
    document.body.appendChild(downloadLink);
    downloadLink.click();
    downloadLink.remove();
    window.URL.revokeObjectURL(objectUrl);
  } catch (error) {
    console.error(error);
    window.alert('Unable to export the report. Please try again.');
  } finally {
    trigger.disabled = false;
    trigger.textContent = originalText;
  }
}

document.getElementById('exportCsvBtn').addEventListener('click', event => {
  downloadReport('/reports/export-csv', 'csv', event.currentTarget);
});

document.getElementById('exportPdfBtn').addEventListener('click', event => {
  downloadReport('/reports/generate-pdf', 'pdf', event.currentTarget);
});

document.getElementById('generateFormBtn').addEventListener('click', () => {
  document.getElementById('surveyModal').classList.add('active');
});

document.getElementById('printSurveyBtn').addEventListener('click', () => {
  const printWindow = window.open('', '_blank');
  if (!printWindow) {
    window.alert('Please allow pop-ups to print the survey form.');
    return;
  }

  const printStyles = document.getElementById('reportDocumentStyles').textContent;
  const printContent = document.getElementById('surveyContent').innerHTML;
  printWindow.document.write(`<!DOCTYPE html><html><head><meta charset="UTF-8"><title>VehiSights Vehicle Count Survey</title><style>${printStyles}</style></head><body>${printContent}</body></html>`);
  printWindow.document.close();
  printWindow.focus();
  window.setTimeout(() => printWindow.print(), 250);
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
