const pageData = JSON.parse(document.getElementById('page-data').textContent);
// ─── Dark Mode ────────────────────────────────────────────────
const html   = document.documentElement;
const toggle = document.getElementById('themeToggle');

const savedTheme = localStorage.getItem('vehisights-theme') || 'light';
html.setAttribute('data-theme', savedTheme);

toggle.addEventListener('click', () => {
  const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
  html.setAttribute('data-theme', next);
  localStorage.setItem('vehisights-theme', next);
});

// ─── Cancel ───────────────────────────────────────────────────
document.getElementById('cancelBtn').addEventListener('click', () => {
  if (confirm('Clear all form fields?')) {
    document.getElementById('scheduleForm').reset();
    document.getElementById('date').value       = pageData.today;
    document.getElementById('start_time').value = '06:00';
    document.getElementById('end_time').value   = '07:00';
    document.getElementById('lanes').value      = '2';
    document.getElementById('city').value       = 'Sto. Tomas';
  }
});

// ─── Submit ───────────────────────────────────────────────────
document.getElementById('scheduleForm').addEventListener('submit', async (e) => {
  e.preventDefault();

  const btn = document.getElementById('startSessionBtn');
  btn.disabled = true;
  btn.innerHTML = `
    <svg viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2;animation:spin 0.8s linear infinite">
      <circle cx="12" cy="12" r="10" stroke-dasharray="31.4" stroke-dashoffset="10"/>
    </svg>
    Starting…`;

  const roadName = document.getElementById('road_name').value.trim();
  const city     = document.getElementById('city').value.trim();
  const cameraId = document.getElementById('camera_id').value;

  if (!roadName || !city || !cameraId) {
    alert('Please fill in all required fields: Road Name, City, and Camera.');
    btn.disabled = false;
    btn.innerHTML = `<svg viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2"><polygon points="5 3 19 12 5 21 5 3"/></svg> Start Session`;
    return;
  }

  // Combine direction_from and direction_to
  const directionFrom = document.getElementById('direction_from').value.trim();
  const directionTo = document.getElementById('direction_to').value.trim();
  const direction = directionFrom + (directionTo ? ' → ' + directionTo : '');

  const payload = {
    road_name:     roadName,
    barangay:      document.getElementById('barangay').value.trim(),
    city:          city,
    direction:     direction,
    direction_from: directionFrom,
    direction_to: directionTo,
    date:          document.getElementById('date').value,
    start_time:    document.getElementById('start_time').value,
    end_time:      document.getElementById('end_time').value,
    has_median:    document.getElementById('has_median').value === '1',
    lanes:         parseInt(document.getElementById('lanes').value),
    camera_id:     cameraId,
    schedule_name: `Auto Session — ${roadName} — ${document.getElementById('date').value}`,
    recurrence:    'once',
    is_active:     true,
  };

  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const response  = await fetch('/schedules', {
      method:  'POST',
      headers: {
        'Content-Type':  'application/json',
        'X-CSRF-TOKEN':  csrfToken,
        'Accept':        'application/json',
      },
      body: JSON.stringify(payload),
    });

    const result = await response.json();

    if (result.success) {
      alert('✅ Auto session started successfully! The system will now count vehicles (Private Car/Taxi/Pick-up, Jeepney, Van, Bus, Light Truck, Heavy Truck, Motorcycle, Tricycle) using the selected camera.');
      window.location.href = '/auto-records';
    } else {
      alert('❌ Error: ' + (result.message || 'Something went wrong. Please try again.'));
    }
  } catch (err) {
    console.error(err);
    alert('❌ Connection error: ' + err.message);
  } finally {
    btn.disabled = false;
    btn.innerHTML = `<svg viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2"><polygon points="5 3 19 12 5 21 5 3"/></svg> Start Session`;
  }
});
