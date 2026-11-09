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

const periodTypeInput = document.getElementById('period_type');
const singleDateInput = document.getElementById('single_date');
const monthInput = document.getElementById('report_month');
const startDateInput = document.getElementById('start_date');
const endDateInput = document.getElementById('end_date');
const stationInput = document.getElementById('station');

function monthDateRange(month) {
  const match = /^(\d{4})-(\d{2})$/.exec(month);
  if (!match) return null;

  const year = Number(match[1]);
  const monthNumber = Number(match[2]);
  const lastDay = new Date(year, monthNumber, 0).getDate();
  return {
    startDate: `${month}-01`,
    endDate: `${month}-${String(lastDay).padStart(2, '0')}`,
  };
}

function updatePeriodControls() {
  const periodType = periodTypeInput.value;
  document.getElementById('singleDateGroup').hidden = periodType !== 'date';
  document.getElementById('monthGroup').hidden = periodType !== 'month';
  document.getElementById('startDateGroup').hidden = periodType !== 'range';
  document.getElementById('endDateGroup').hidden = periodType !== 'range';
}

function selectedFilters() {
  const periodType = periodTypeInput.value;
  let startDate = '';
  let endDate = '';

  if (periodType === 'date') {
    startDate = singleDateInput.value;
    endDate = singleDateInput.value;
  } else if (periodType === 'month') {
    const range = monthDateRange(monthInput.value);
    startDate = range?.startDate || '';
    endDate = range?.endDate || '';
  } else {
    startDate = startDateInput.value;
    endDate = endDateInput.value;
  }

  return { periodType, startDate, endDate, station: stationInput.value };
}

function filtersAreValid() {
  const { periodType, startDate, endDate, station } = selectedFilters();
  const endInput = document.getElementById('end_date');
  endInput.setCustomValidity('');

  const activeInput = periodType === 'date'
    ? singleDateInput
    : (periodType === 'month' ? monthInput : startDateInput);
  activeInput.setCustomValidity('');

  if (!startDate || !endDate) {
    activeInput.setCustomValidity('Select the report period before generating or exporting.');
    activeInput.reportValidity();
    return false;
  }
  if (startDate > endDate) {
    endInput.setCustomValidity('End date must be on or after the start date.');
    endInput.reportValidity();
    return false;
  }
  if (!station) {
    window.VehiSightsUI.showNotice({
      title: 'No station or road available',
      message: 'Add a completed manual or automatic session before generating a report.'
    });
    return false;
  }
  return true;
}

function reportUrl(openSurvey = false) {
  const { periodType, startDate, endDate, station } = selectedFilters();
  const params = new URLSearchParams({
    period_type: periodType,
    start_date: startDate,
    end_date: endDate,
    station,
  });
  if (openSurvey) params.set('open_survey', '1');
  return `/reports?${params.toString()}`;
}

function filtersMatchDisplayedReport() {
  const selected = selectedFilters();
  return selected.startDate === pageData.startDate
    && selected.endDate === pageData.endDate
    && selected.station === pageData.station;
}

periodTypeInput.addEventListener('change', updatePeriodControls);
updatePeriodControls();

document.getElementById('applyFilters').addEventListener('click', () => {
  if (!filtersAreValid()) return;
  window.location.href = reportUrl();
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
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': extension === 'pdf'
          ? 'application/pdf, application/json'
          : 'text/csv, application/json'
      },
      body: formData
    });
    if (!response.ok) {
      let message = 'The report could not be generated.';
      if (response.headers.get('content-type')?.includes('application/json')) {
        const errorData = await response.json();
        message = Object.values(errorData.errors || {}).flat()[0] || errorData.message || message;
      }
      throw new Error(message);
    }

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
    window.VehiSightsUI.showNotice({ title: 'Report export failed', message: error.message || 'Unable to export the report. Please try again.' });
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
  if (!filtersAreValid()) return;

  if (!filtersMatchDisplayedReport()) {
    window.location.href = reportUrl(true);
    return;
  }

  if (Number(pageData.totalRecords) === 0) {
    window.VehiSightsUI.showNotice({
      title: 'No completed records found',
      message: 'There are no completed records for the selected report period and station. Adjust the filters and try again.'
    });
    return;
  }

  document.getElementById('surveyModal').classList.add('active');
});

if (pageData.openSurvey && Number(pageData.totalRecords) > 0) {
  document.getElementById('surveyModal').classList.add('active');
  const cleanUrl = reportUrl();
  window.history.replaceState(null, '', cleanUrl);
}

document.getElementById('printSurveyBtn').addEventListener('click', () => {
  const printWindow = window.open('', '_blank');
  if (!printWindow) {
    window.VehiSightsUI.showNotice({ title: 'Pop-up blocked', message: 'Allow pop-ups for VehiSights, then try printing the survey form again.' });
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
