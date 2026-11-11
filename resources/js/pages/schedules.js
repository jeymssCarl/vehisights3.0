import { setupScheduleTimePickers } from '../components/schedule-time-picker';

const pageData = JSON.parse(document.getElementById('page-data').textContent);
const html = document.documentElement;
const toggle = document.getElementById('themeToggle');
const form = document.getElementById('scheduleForm');
const dateInput = document.getElementById('date');
const startTimeInput = document.getElementById('start_time');
const endTimeInput = document.getElementById('end_time');
const startButton = document.getElementById('startSessionBtn');
const noticeModal = document.getElementById('scheduleNoticeModal');
const confirmModal = document.getElementById('scheduleConfirmModal');
let timePickers = null;
localStorage.removeItem('vehisights-scheduling-daily-draft');

const startButtonContent = `
  <svg viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2">
    <polygon points="5 3 19 12 5 21 5 3"/>
  </svg>
  Start Session`;

html.setAttribute('data-theme', localStorage.getItem('vehisights-theme') || 'light');

function toManilaIsoDateTime(date, time) {
  return `${date}T${time}:00+08:00`;
}

function manilaDateTime(offsetMs = 0) {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Manila',
    year: 'numeric', month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
  }).formatToParts(new Date(Date.now() + offsetMs));
  const value = type => parts.find(part => part.type === type)?.value || '';

  return {
    date: `${value('year')}-${value('month')}-${value('day')}`,
    time: `${value('hour')}:${value('minute')}`,
  };
}

function updateTimeConstraints(setSafeDefault = false) {
  const now = manilaDateTime();
  dateInput.min = now.date;

  if (dateInput.value === now.date) {
    startTimeInput.min = now.time;
    if (setSafeDefault && (!startTimeInput.value || startTimeInput.value < now.time)) {
      const next = manilaDateTime(60000);
      dateInput.value = next.date;
      startTimeInput.value = next.time;
    }
  } else {
    startTimeInput.removeAttribute('min');
  }

  endTimeInput.min = startTimeInput.value || '';
}

function isPastSchedule(date, time) {
  const now = manilaDateTime();
  return date < now.date || (date === now.date && time < now.time);
}

let pendingPastTimeNotice = false;
let pendingEndTimeNotice = false;

function showPendingTimeNotice(type) {
  window.setTimeout(() => {
    if (type === 'start' && pendingPastTimeNotice) {
      pendingPastTimeNotice = false;
      showNotice('Past time not available', 'The start time was moved to the next available minute in Philippine time.');
    }

    if (type === 'end' && pendingEndTimeNotice) {
      pendingEndTimeNotice = false;
      showNotice('Invalid end time', 'Choose an end time later than the session start time.');
    }
  }, 120);
}

function enforceStartTime(showFeedback = false) {
  const now = manilaDateTime();
  if (!dateInput.value || !startTimeInput.value) return true;

  if (dateInput.value < now.date) {
    dateInput.value = now.date;
  }

  if (dateInput.value === now.date && startTimeInput.value < now.time) {
    const next = manilaDateTime(60000);
    dateInput.value = next.date;
    startTimeInput.value = next.time;
    updateTimeConstraints();
    if (showFeedback) {
      showNotice('Past time not available', 'The start time was moved to the next available minute in Philippine time.');
    } else {
      pendingPastTimeNotice = true;
    }
    return false;
  }

  endTimeInput.min = startTimeInput.value;
  return true;
}

function enforceEndTime(showFeedback = false) {
  if (!startTimeInput.value || !endTimeInput.value) return true;
  if (endTimeInput.value <= startTimeInput.value) {
    endTimeInput.value = '';
    if (showFeedback) {
      showNotice('Invalid end time', 'Choose an end time later than the session start time.');
    } else {
      pendingEndTimeNotice = true;
    }
    return false;
  }
  return true;
}


function firstErrorMessage(result, fallback) {
  const first = Object.values(result?.errors || {}).flat().find(Boolean);
  return first || result?.message || fallback;
}

let noticeCallback = null;
function showNotice(title, message, tone = 'error', afterClose = null) {
  document.getElementById('scheduleNoticeIcon').classList.toggle('success', tone === 'success');
  document.getElementById('scheduleNoticeTitle').textContent = title;
  document.getElementById('scheduleNoticeMessage').textContent = message;
  noticeCallback = afterClose;
  noticeModal.classList.add('active');
  document.getElementById('scheduleNoticeOkBtn').focus();
}

function closeNotice() {
  noticeModal.classList.remove('active');
  const callback = noticeCallback;
  noticeCallback = null;
  if (callback) callback();
}

function resetForm() {
  form.reset();
  const now = manilaDateTime();
  const next = now.time === '23:59' ? manilaDateTime(60000) : now;
  dateInput.value = next.date;
  startTimeInput.value = next.time;
  endTimeInput.value = '23:59';
  document.getElementById('lanes').value = '2';
  document.getElementById('city').value = 'Sto. Tomas';
  updateTimeConstraints(true);
  timePickers?.sync();
}

function setStarting(isStarting) {
  startButton.disabled = isStarting;
  startButton.innerHTML = isStarting
    ? `<svg viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2;animation:spin 0.8s linear infinite"><circle cx="12" cy="12" r="10" stroke-dasharray="31.4" stroke-dashoffset="10"/></svg> Starting…`
    : startButtonContent;
}

toggle.addEventListener('click', () => {
  const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
  html.setAttribute('data-theme', next);
  localStorage.setItem('vehisights-theme', next);
});

dateInput.addEventListener('change', () => {
  updateTimeConstraints(true);
  enforceStartTime(true);
  timePickers?.sync();
});
startTimeInput.addEventListener('focus', () => updateTimeConstraints());
startTimeInput.addEventListener('pointerdown', () => updateTimeConstraints());
startTimeInput.addEventListener('input', () => enforceStartTime(false));
startTimeInput.addEventListener('change', () => {
  startTimeInput.setCustomValidity('');
  enforceStartTime(false);
  enforceEndTime(false);
});
startTimeInput.addEventListener('blur', () => {
  enforceStartTime(false);
  showPendingTimeNotice('start');
});
endTimeInput.addEventListener('focus', () => {
  endTimeInput.min = startTimeInput.value || '';
});
endTimeInput.addEventListener('pointerdown', () => {
  updateTimeConstraints();
  endTimeInput.min = startTimeInput.value || '';
});
endTimeInput.addEventListener('input', () => enforceEndTime(false));
endTimeInput.addEventListener('change', () => enforceEndTime(false));
endTimeInput.addEventListener('blur', () => {
  enforceEndTime(false);
  showPendingTimeNotice('end');
});

document.getElementById('cancelBtn').addEventListener('click', () => {
  confirmModal.classList.add('active');
  document.getElementById('scheduleConfirmCancelBtn').focus();
});
document.getElementById('scheduleConfirmCancelBtn').addEventListener('click', () => confirmModal.classList.remove('active'));
document.getElementById('scheduleConfirmOkBtn').addEventListener('click', () => {
  confirmModal.classList.remove('active');
  resetForm();
});
confirmModal.addEventListener('click', event => {
  if (event.target === confirmModal) confirmModal.classList.remove('active');
});

document.getElementById('scheduleNoticeCloseBtn').addEventListener('click', closeNotice);
document.getElementById('scheduleNoticeOkBtn').addEventListener('click', closeNotice);
noticeModal.addEventListener('click', event => {
  if (event.target === noticeModal) closeNotice();
});

document.addEventListener('keydown', event => {
  if (event.key !== 'Escape') return;
  if (noticeModal.classList.contains('active')) closeNotice();
  if (confirmModal.classList.contains('active')) confirmModal.classList.remove('active');
});

window.addEventListener('pageshow', event => {
  if (event.persisted) resetForm();
});

form.addEventListener('submit', async event => {
  event.preventDefault();

  const roadName = document.getElementById('road_name').value.trim();
  const city = document.getElementById('city').value.trim();
  const cameraId = document.getElementById('camera_id').value;
  const directionFrom = document.getElementById('direction_from').value.trim();
  const directionTo = document.getElementById('direction_to').value.trim();
  const scheduleDate = dateInput.value;
  const scheduleTime = startTimeInput.value;

  if (!form.checkValidity()) {
    form.reportValidity();
    showNotice('Complete the required fields', 'Please review the highlighted fields before starting the session.');
    return;
  }

  if (!roadName || !city || !cameraId) {
    showNotice('Missing session information', 'Road name, city, and an online camera are required.');
    return;
  }

  if (isPastSchedule(scheduleDate, scheduleTime)) {
    updateTimeConstraints(true);
    showNotice('Past schedule not allowed', 'Choose a start date and time that is current or in the future (Philippine time).');
    return;
  }

  if (!enforceEndTime(true)) return;

  setStarting(true);
  const payload = {
    road_name: roadName,
    barangay: document.getElementById('barangay').value.trim(),
    city,
    direction: directionFrom + (directionTo ? ` → ${directionTo}` : ''),
    direction_from: directionFrom,
    direction_to: directionTo,
    day_classification: document.getElementById('day_classification').value || null,
    start_date: scheduleDate,
    start_time: scheduleTime,
    end_time: endTimeInput.value,
    has_median: document.getElementById('has_median').value === '1',
    lanes: parseInt(document.getElementById('lanes').value, 10),
    camera_id: cameraId,
    schedule_name: `Auto Session — ${roadName} — ${scheduleDate}`,
    recurrence: 'once',
    is_active: true,
  };

  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const response = await fetch('/schedules', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
      body: JSON.stringify(payload),
    });
    const result = await response.json();

    if (!response.ok || !result.success) {
      throw new Error(firstErrorMessage(result, 'The schedule could not be created.'));
    }

    let startResult = { success: true, auto_session: result.auto_session };

    // Backward-compatible fallback for a server that has not yet deployed the
    // atomic schedule + automatic-session response.
    if (!startResult.auto_session) {
      const startResponse = await fetch('/auto-sessions/start', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
        body: JSON.stringify({
          schedule_id: result.schedule.id,
          start_time: toManilaIsoDateTime(payload.start_date, payload.start_time),
        }),
      });
      startResult = await startResponse.json();

      if (!startResponse.ok || !startResult.success) {
        throw new Error(firstErrorMessage(startResult, 'The schedule was saved, but its automatic session could not be started.'));
      }
    }

    const isScheduled = startResult.auto_session?.status === 'scheduled';
    showNotice(
      isScheduled ? 'Session scheduled' : 'Session started',
      isScheduled
        ? 'The session is queued and will become active automatically at the selected start time.'
        : 'The camera session is active and its live counts are now available in Auto Records.',
      'success',
      () => { window.location.href = '/auto-records'; }
    );
  } catch (error) {
    console.error(error);
    showNotice('Unable to start session', error.message || 'A connection error occurred. Please try again.');
  } finally {
    setStarting(false);
  }
});

timePickers = setupScheduleTimePickers({
  dateInput,
  startInput: startTimeInput,
  endInput: endTimeInput,
  getManilaNow: manilaDateTime,
  showNotice,
});
updateTimeConstraints(true);
timePickers.sync();
