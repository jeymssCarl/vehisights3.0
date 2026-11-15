import { createClient } from '@supabase/supabase-js';
import { setupScheduleTimePickers } from '../components/schedule-time-picker';

const pageData = JSON.parse(document.getElementById('page-data').textContent);
const html = document.documentElement;
const toggle = document.getElementById('themeToggle');
const form = document.getElementById('scheduleForm');
const dateInput = document.getElementById('date');
const startTimeInput = document.getElementById('start_time');
const endTimeInput = document.getElementById('end_time');
const cameraSelect = document.getElementById('camera_id');
const startButton = document.getElementById('startSessionBtn');
const noticeModal = document.getElementById('scheduleNoticeModal');
const confirmModal = document.getElementById('scheduleConfirmModal');
let timePickers = null;
let cameraStatusRequest = null;
let scheduleCameras = [];
// Keep the complete list that Laravel rendered when this page opened. Status
// updates are allowed to change an entry, but an availability update must
// never make an offline camera disappear from the scheduling control.
const renderedCameraCatalog = new Map();
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

function normalizedCamera(camera) {
  return {
    id: Number(camera.id),
    name: camera.name || 'Unnamed camera',
    location: camera.location || 'No location',
    status: String(camera.status || 'offline').toLowerCase() === 'online' ? 'online' : 'offline',
  };
}

// Start from Laravel's complete server-rendered list. This means every saved
// camera remains schedulable even if an older availability endpoint returns a
// partial, online-only result during the first browser refresh.
function hydrateServerRenderedCameras() {
  if (!cameraSelect) return;

  const renderedCameras = [...cameraSelect.options]
    .filter(option => option.value && option.dataset.cameraId)
    .map(option => normalizedCamera({
      id: option.dataset.cameraId,
      name: option.dataset.cameraName,
      location: option.dataset.cameraLocation,
      status: option.dataset.cameraStatus,
    }));

  renderedCameras.forEach(camera => renderedCameraCatalog.set(camera.id, camera));
  scheduleCameras = [...renderedCameraCatalog.values()];
}

function mergeCamera(camera) {
  const normalized = normalizedCamera(camera);
  if (!Number.isFinite(normalized.id) || normalized.id <= 0) return;

  renderedCameraCatalog.set(normalized.id, {
    ...renderedCameraCatalog.get(normalized.id),
    ...normalized,
  });
  scheduleCameras = [...renderedCameraCatalog.values()];
}

function renderCameraOptions() {
  if (!cameraSelect) return;

  const selectedId = cameraSelect.value;
  const fragment = document.createDocumentFragment();
  const placeholder = document.createElement('option');
  placeholder.value = '';
  placeholder.textContent = 'Choose a camera...';
  fragment.appendChild(placeholder);

  scheduleCameras
    .slice()
    .sort((left, right) => left.name.localeCompare(right.name))
    .forEach(camera => {
      const option = document.createElement('option');
      option.value = String(camera.id);
      option.textContent = `${camera.name} (${camera.location}) — ${camera.status === 'online' ? 'reachable' : 'unreachable · scheduling allowed'}`;
      fragment.appendChild(option);
    });

  cameraSelect.replaceChildren(fragment);
  if ([...cameraSelect.options].some(option => option.value === selectedId)) {
    cameraSelect.value = selectedId;
  }
  updateCameraAvailability();
}

function updateCameraAvailability() {
  const notice = document.getElementById('cameraAvailability');
  if (!notice) return;

  const selected = scheduleCameras.find(camera => String(camera.id) === cameraSelect.value);
  if (!selected) {
    notice.hidden = true;
    return;
  }

  notice.hidden = false;
  const isOnline = selected.status === 'online';
  notice.textContent = isOnline
    ? 'Camera is reachable from its desktop station.'
    : 'Camera is unreachable. You may still schedule it, but counting starts only after its desktop station reconnects.';
  notice.classList.toggle('offline-camera-notice', !isOnline);
  notice.classList.toggle('online-camera-notice', isOnline);
}

async function refreshCameras() {
  if (!cameraSelect || cameraStatusRequest) return;

  const controller = new AbortController();
  cameraStatusRequest = controller;

  try {
    const response = await fetch('/cameras/data', {
      headers: { Accept: 'application/json' },
      cache: 'no-store',
      signal: controller.signal,
    });
    if (!response.ok) throw new Error(`Camera status refresh failed (${response.status}).`);

    const payload = await response.json();
    const cameras = Array.isArray(payload) ? payload : payload.data;
    // Merge rather than replace: availability endpoints from an older server
    // version may return only online cameras. An offline camera already shown
    // in the scheduling dropdown must remain schedulable and visible.
    const incomingCameras = Array.isArray(cameras) ? cameras : [];
    // Deliberately merge, never replace. This protects offline cameras when a
    // cached/proxied response contains only reachable cameras.
    incomingCameras.forEach(mergeCamera);
    renderCameraOptions();
  } catch (error) {
    if (error.name !== 'AbortError') console.warn(error.message);
  } finally {
    if (cameraStatusRequest === controller) cameraStatusRequest = null;
  }
}

function connectCameraRealtime() {
  const url = import.meta.env.VITE_SUPABASE_URL;
  const publishableKey = import.meta.env.VITE_SUPABASE_PUBLISHABLE_KEY;

  if (!url || !publishableKey) {
    console.warn('Supabase Realtime is not configured; camera availability will not update automatically.');
    return;
  }

  const supabase = createClient(url, publishableKey, {
    auth: { persistSession: false, autoRefreshToken: false },
  });

  supabase
    .channel('vehisights-schedule-cameras')
    .on(
      'postgres_changes',
      { event: '*', schema: 'public', table: 'cameras' },
      payload => {
        if (payload.eventType === 'DELETE') {
          // Do not remove a choice because of a transient/replayed Realtime
          // event. A normal page load is the authority for intentional camera
          // deletion; this page must keep every saved offline camera usable.
          const existing = renderedCameraCatalog.get(Number(payload.old.id));
          if (existing) mergeCamera({ ...existing, status: 'offline' });
        } else {
          mergeCamera(payload.new);
        }
        renderCameraOptions();
      }
    )
    .subscribe(status => {
      if (status === 'SUBSCRIBED') console.info('Supabase Realtime connected for scheduling camera status.');
    });
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
cameraSelect.addEventListener('change', updateCameraAvailability);
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
hydrateServerRenderedCameras();
refreshCameras();
connectCameraRealtime();