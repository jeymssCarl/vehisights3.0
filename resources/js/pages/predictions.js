const html = document.documentElement;
const toggle = document.getElementById('themeToggle');
const root = document.querySelector('.wrap[data-forecast-url]');
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
const stationSelect = document.getElementById('stationSel');
const dateSelect = document.getElementById('dateSel');
const windowSelect = document.getElementById('windowSel');
const trainButton = document.getElementById('trainModelBtn');

let histChartObj = null;
let predChartObj = null;
let modelReady = root?.dataset.modelReady === '1';
let forecastRequest = null;

const savedTheme = localStorage.getItem('vehisights-theme') || 'light';
html.setAttribute('data-theme', savedTheme);

toggle?.addEventListener('click', () => {
  const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
  html.setAttribute('data-theme', next);
  localStorage.setItem('vehisights-theme', next);
  rebuildCharts();
});

function isDark() {
  return html.getAttribute('data-theme') === 'dark';
}

function getGridColor() {
  return isDark() ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.07)';
}

function getTextColor() {
  return isDark() ? '#9b9590' : '#737069';
}

function formatHour(value) {
  return new Intl.DateTimeFormat('en-PH', {
    hour: 'numeric',
    minute: '2-digit',
  }).format(new Date(value));
}

function formatNumber(value) {
  return Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: 0 });
}

function escapeHtml(value) {
  const element = document.createElement('div');
  element.textContent = String(value ?? '');
  return element.innerHTML;
}

function losClass(los) {
  return `los-${String(los || 'a').toLowerCase()}`;
}

function setMessage(message = '', tone = 'info') {
  const element = document.getElementById('predictionMessage');
  if (!element) return;
  element.textContent = message;
  element.dataset.tone = tone;
  element.hidden = !message;
}

function updateModelStatus(status) {
  if (!status) return;
  modelReady = Boolean(status.ready);
  root.dataset.modelReady = modelReady ? '1' : '0';
  const pill = document.getElementById('modelStatusPill');
  if (!pill) return;
  if (modelReady) {
    const accuracy = Number(status.metrics?.classifier_accuracy || 0) * 100;
    pill.innerHTML = `<div class="dot"></div>Classifier ready · ${accuracy.toFixed(0)}% accuracy`;
  } else {
    pill.innerHTML = `<div class="dot"></div>${status.available_rows}/${status.minimum_rows} training rows`;
  }
}

function makeHistoricalChart(records = []) {
  const canvas = document.getElementById('histChart');
  if (!canvas) return;
  histChartObj?.destroy();
  histChartObj = new Chart(canvas.getContext('2d'), {
    type: 'bar',
    data: {
      labels: records.map(row => formatHour(row.timestamp)),
      datasets: [{
        label: 'Recorded PCU',
        data: records.map(row => Number(row.total_pcu)),
        backgroundColor: 'rgba(123,29,29,0.75)',
        borderColor: '#7B1D1D',
        borderWidth: 1,
        borderRadius: 3,
      }],
    },
    options: chartOptions(true),
  });
}

function makePredictionChart(actual = [], predicted = []) {
  const canvas = document.getElementById('predChart');
  if (!canvas) return;
  predChartObj?.destroy();
  const labels = [...actual.map(row => formatHour(row.timestamp)), ...predicted.map(row => formatHour(row.timestamp))];
  predChartObj = new Chart(canvas.getContext('2d'), {
    type: 'line',
    data: {
      labels,
      datasets: [
        {
          label: 'Actual',
          data: [...actual.map(row => Number(row.total_pcu)), ...predicted.map(() => null)],
          borderColor: '#7B1D1D',
          backgroundColor: 'rgba(123,29,29,0.08)',
          borderWidth: 2,
          fill: true,
          tension: 0.3,
          pointRadius: 3,
        },
        {
          label: 'Estimated PCU',
          data: [...actual.map(() => null), ...predicted.map(row => Number(row.predicted_pcu))],
          borderColor: '#b45309',
          backgroundColor: 'rgba(180,83,9,0.06)',
          borderWidth: 2,
          borderDash: [5, 4],
          fill: true,
          tension: 0.3,
          pointRadius: 3,
          pointStyle: 'triangle',
        },
      ],
    },
    options: chartOptions(false),
  });
}

function chartOptions(beginAtZero) {
  return {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: { callbacks: { label: context => `${formatNumber(context.parsed.y)} PCU` } },
    },
    scales: {
      y: { beginAtZero, grid: { color: getGridColor() }, ticks: { color: getTextColor(), font: { size: 10 } } },
      x: { grid: { display: false }, ticks: { color: getTextColor(), font: { size: 9 }, maxRotation: 0 } },
    },
  };
}

function buildForecastTable(predictions = []) {
  const tbody = document.getElementById('forecastBody');
  if (!tbody) return;
  if (!predictions.length) {
    tbody.innerHTML = '<tr><td colspan="6" class="prediction-empty">No RFC classification is available.</td></tr>';
    return;
  }
  tbody.innerHTML = predictions.map(row => {
    const predictedLos = row.predicted_los || '—';
    const status = trafficStatus(predictedLos);
    return `
    <tr class="pred-row">
      <td class="mono-value">${formatHour(row.timestamp)}</td>
      <td class="mono-value strong">${formatNumber(row.predicted_pcu)}</td>
      <td class="mono-value">${Number(row.predicted_vc_ratio).toFixed(2)}</td>
      <td><span class="los-badge ${losClass(predictedLos)}">${escapeHtml(predictedLos)}</span></td>
      <td>${escapeHtml(status)}</td>
      <td class="forecast-type">${Number(row.confidence_score || 0).toFixed(0)}%</td>
    </tr>
  `;
  }).join('');
}

function trafficStatus(los) {
  return {
    A: 'Free Flow',
    B: 'Light',
    C: 'Moderate',
    D: 'Heavy',
    E: 'Severe',
    F: 'Breakdown',
  }[String(los || '').toUpperCase()] || 'Unclassified';
}

function updateSummary(payload) {
  const predictions = payload.forecast?.predictions || [];
  const historical = payload.historical_data || [];
  const current = historical.at(-1);
  const next = predictions[0];
  const severity = { A: 1, B: 2, C: 3, D: 4, E: 5, F: 6 };
  const highestClass = predictions.reduce((best, row) => {
    const rowLos = String(row.predicted_los || '').toUpperCase();
    const bestLos = String(best?.predicted_los || '').toUpperCase();
    return !best || (severity[rowLos] || 0) > (severity[bestLos] || 0) ? row : best;
  }, null);
  const classifierAccuracy = Number(payload.forecast?.metrics?.classifier_accuracy || 0) * 100;
  const nextLos = next?.predicted_los;
  const highestLos = highestClass?.predicted_los;

  document.getElementById('kpiCurrent').textContent = current ? formatNumber(current.total_pcu) : '—';
  document.getElementById('kpiCurrentSub').textContent = current ? `Recorded ${formatHour(current.timestamp)}` : 'No historical record';
  document.getElementById('kpiNext').textContent = nextLos ? `LOS ${nextLos}` : '—';
  document.getElementById('kpiNextSub').textContent = next ? `${trafficStatus(nextLos)} · ${Number(next.confidence_score || 0).toFixed(0)}% confidence` : 'Awaiting RFC result';
  document.getElementById('kpiPeak').textContent = highestLos ? `LOS ${highestLos}` : '—';
  document.getElementById('kpiPeakSub').textContent = highestClass ? `${trafficStatus(highestLos)} at ${formatHour(highestClass.timestamp)}` : 'Awaiting RFC result';
  document.getElementById('kpiConf').textContent = `${classifierAccuracy.toFixed(0)}%`;
  document.getElementById('kpiConfSub').textContent = 'Time-series classifier accuracy';
  document.getElementById('forecastStation').textContent = stationSelect.value;
  document.getElementById('forecastTableTitle').textContent = `RFC Traffic Classification — Next ${predictions.length} Hours`;
  document.getElementById('predictionChartRange').textContent = `Next ${predictions.length} classified hours`;
  document.getElementById('historicalChartRange').textContent = `${historical.length} latest recorded hours`;
  document.getElementById('insightConfidence').innerHTML = `<div class="dot"></div>RFC validation accuracy: ${classifierAccuracy.toFixed(0)}%`;

  document.getElementById('insightItems').innerHTML = [
    `${predictions.length} hourly RFC classifications generated for ${stationSelect.value}.`,
    highestClass ? `Highest predicted condition is LOS ${highestLos} (${trafficStatus(highestLos)}) at ${formatHour(highestClass.timestamp)}.` : 'No classification result is available.',
    `PCU and V/C values are displayed as estimates supporting the predicted traffic class.`,
    `Road capacity used for the estimates: ${formatNumber(payload.forecast?.capacity)} PCU/hour.`,
  ].map(text => `<div class="insight-item">${escapeHtml(text)}</div>`).join('');
}

async function refreshForecast() {
  if (!root || !stationSelect?.value) {
    setMessage('Register a road through Manual Sessions or Scheduling before requesting a forecast.', 'warning');
    return;
  }
  if (!modelReady) {
    setMessage('The model is not trained yet. Collect traffic records, then select Train Model.', 'warning');
    return;
  }

  forecastRequest?.abort();
  forecastRequest = new AbortController();
    setMessage('Generating RFC traffic classifications…');
  try {
    const response = await fetch(root.dataset.forecastUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
      body: JSON.stringify({
        road_name: stationSelect.value,
        prediction_date: dateSelect.value,
        forecast_window: Number(windowSelect.value),
      }),
      signal: forecastRequest.signal,
    });
    const payload = await response.json();
    updateModelStatus(payload.model_status);
    if (!response.ok || !payload.success) throw new Error(payload.message || 'Forecast request failed.');
    const predictions = payload.forecast?.predictions || [];
    buildForecastTable(predictions);
    makeHistoricalChart(payload.historical_data || []);
    makePredictionChart(payload.actual_data || [], predictions);
    updateSummary(payload);
    setMessage('');
  } catch (error) {
    if (error.name === 'AbortError') return;
    buildForecastTable([]);
    setMessage(error.message, 'error');
  }
}

async function trainModel() {
  if (!root || !trainButton) return;
  trainButton.disabled = true;
  trainButton.textContent = 'Training…';
  setMessage('Training the Random Forest model…');
  try {
    const response = await fetch(root.dataset.trainUrl, {
      method: 'POST',
      headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
    });
    const payload = await response.json();
    updateModelStatus(payload.model_status);
    if (!response.ok || !payload.success) throw new Error(payload.message || 'Training failed.');
    setMessage(payload.message, 'success');
    await refreshForecast();
  } catch (error) {
    setMessage(error.message, 'error');
  } finally {
    trainButton.disabled = false;
    trainButton.textContent = 'Train Model';
  }
}

function rebuildCharts() {
  makeHistoricalChart([]);
  makePredictionChart([], []);
  if (modelReady) refreshForecast();
}

[stationSelect, dateSelect, windowSelect].forEach(element => element?.addEventListener('change', refreshForecast));
trainButton?.addEventListener('click', trainModel);

document.addEventListener('DOMContentLoaded', () => {
  makeHistoricalChart([]);
  makePredictionChart([], []);
  refreshForecast();
  setInterval(() => modelReady && refreshForecast(), 900000);
});

window.refreshForecast = refreshForecast;
