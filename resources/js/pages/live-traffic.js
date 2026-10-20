  // Number animation helper (count-up)
  function animateNumber(element, target, isDecimal = false, duration = 1000) {
    const start = performance.now();
    const startValue = isDecimal ? 0 : 0; // always start from 0
    if (target === startValue) return;
    const step = (now) => {
      const elapsed = now - start;
      const progress = Math.min(elapsed / duration, 1);
      const ease = progress < 0.5 ? 2 * progress * progress : 1 - Math.pow(-2 * progress + 2, 2) / 2;
      let current = startValue + target * ease;
      if (isDecimal) {
        element.innerText = current.toFixed(2);
      } else {
        element.innerText = Math.round(current).toLocaleString();
      }
      if (progress < 1) {
        requestAnimationFrame(step);
      } else {
        element.innerText = isDecimal ? target.toFixed(2) : target.toLocaleString();
      }
    };
    requestAnimationFrame(step);
  }

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
  });

  // Data
  let cameras = [
    { id: 1, name: "Timog Ave North", status: "online", pcuLastHour: 728, lastDetection: "2s ago", location: "N-bound" },
    { id: 2, name: "Timog Ave South", status: "online", pcuLastHour: 663, lastDetection: "5s ago", location: "S-bound" },
    { id: 3, name: "Apokon–Bermudez East", status: "online", pcuLastHour: 841, lastDetection: "1s ago", location: "East" },
    { id: 4, name: "Apokon–Bermudez West", status: "online", pcuLastHour: 812, lastDetection: "4s ago", location: "West" },
    { id: 5, name: "Pioneer Street", status: "online", pcuLastHour: 1157, lastDetection: "just now", location: "South" },
    { id: 6, name: "Green Coffee Warehouse", status: "offline", pcuLastHour: 0, lastDetection: "2m ago", location: "North" },
    { id: 7, name: "Magdum Crossing", status: "online", pcuLastHour: 1080, lastDetection: "3s ago", location: "East" },
    { id: 8, name: "Rina–Curvada", status: "online", pcuLastHour: 485, lastDetection: "7s ago", location: "West" },
  ];

  const vehicleTypes = [
    { type: "Private Car", pcu: 1.0, losEffect: "A" }, { type: "Jeepney", pcu: 1.5, losEffect: "B" },
    { type: "Van", pcu: 1.5, losEffect: "B" }, { type: "Bus", pcu: 2.5, losEffect: "C" },
    { type: "2‑Axle Truck", pcu: 2.0, losEffect: "C" }, { type: "3‑Axle Truck", pcu: 3.0, losEffect: "D" },
    { type: "Motorcycle", pcu: 0.5, losEffect: "A" }, { type: "Tricycle", pcu: 0.7, losEffect: "A" },
  ];

  function randomItem(arr) { return arr[Math.floor(Math.random() * arr.length)]; }

  function generateDetection(camera) {
    const vehicle = randomItem(vehicleTypes);
    const now = new Date();
    const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute:'2-digit', second:'2-digit' });
    return { timestamp: timeStr, camera: camera.name, vehicle: vehicle.type, pcu: vehicle.pcu, lane: camera.location, los: vehicle.losEffect };
  }

  function renderCameras() {
    const grid = document.getElementById('camerasGrid');
    grid.innerHTML = cameras.map(cam => `
      <div class="camera-card" data-cam-id="${cam.id}">
        <div class="camera-header"><span class="camera-name">${cam.name}</span><span class="camera-status ${cam.status}">${cam.status === 'online' ? '● Live' : '● Offline'}</span></div>
        <div class="camera-stats"><span>Last hour PCU</span><span class="camera-pcu" id="camera-pcu-${cam.id}">0</span></div>
        <div class="last-detection">Last detection: ${cam.lastDetection}</div>
        <div class="last-detection" style="font-size:0.65rem; margin-top:6px;">Dir: ${cam.location}</div>
      </div>
    `).join('');
    // Animate each camera PCU from 0 to its target
    cameras.forEach(cam => {
      const el = document.getElementById(`camera-pcu-${cam.id}`);
      if (el && cam.pcuLastHour > 0) {
        animateNumber(el, cam.pcuLastHour, false, 800);
      }
    });
  }

  function updateCameraPCU(camId, newValue) {
    const element = document.getElementById(`camera-pcu-${camId}`);
    if (element) {
      animateNumber(element, newValue, false, 500);
    }
  }

  function addDetection() {
    const onlineCams = cameras.filter(c => c.status === 'online');
    if (onlineCams.length === 0) return;
    const detection = generateDetection(randomItem(onlineCams));
    const tbody = document.getElementById('detectionsBody');
    const newRow = document.createElement('tr');
    newRow.innerHTML = `<td style="font-family:'DM Mono',monospace; font-size:0.73rem;">${detection.timestamp}</td><td>${detection.camera}</td><td><div class="vehicle-badge">${detection.vehicle}</div></td><td style="font-family:'DM Mono',monospace;">${detection.pcu}</td><td>${detection.lane}</td><td><span class="los-mini los-${detection.los.toLowerCase()}">${detection.los}</span></td>`;
    tbody.prepend(newRow);
    while (tbody.children.length > 20) tbody.removeChild(tbody.lastChild);
  }

  // Initial render
  renderCameras();
  for (let i = 0; i < 8; i++) addDetection();

  // Animate main stats from 0 to target
  const pcuElement = document.getElementById('livePcu');
  const vcElement = document.getElementById('liveVc');
  const pcuTarget = parseInt(pcuElement.getAttribute('data-target')) || 1847;
  const vcTarget = parseFloat(vcElement.getAttribute('data-target')) || 0.68;
  animateNumber(pcuElement, pcuTarget, false, 1400);
  animateNumber(vcElement, vcTarget, true, 1400);

  setInterval(addDetection, 4000);
  setInterval(() => {
    cameras.forEach(cam => {
      if (cam.status === 'online') {
        const oldValue = cam.pcuLastHour;
        cam.pcuLastHour += Math.floor(Math.random() * 30) - 10;
        if (cam.pcuLastHour < 0) cam.pcuLastHour = 0;
        if (oldValue !== cam.pcuLastHour) {
          updateCameraPCU(cam.id, cam.pcuLastHour);
        }
        // Update last detection text randomly (but we don't need to re-render)
        const detectionSpan = document.querySelector(`.camera-card[data-cam-id="${cam.id}"] .last-detection:first-of-type`);
        if (detectionSpan) {
          const seconds = Math.floor(Math.random() * 30);
          cam.lastDetection = `${seconds}s ago`;
          detectionSpan.innerText = `Last detection: ${cam.lastDetection}`;
        }
      }
    });
    const newPcu = Math.floor(1500 + Math.random() * 500);
    const newVc = (0.55 + Math.random() * 0.3).toFixed(2);
    animateNumber(document.getElementById('livePcu'), newPcu, false, 800);
    animateNumber(document.getElementById('liveVc'), parseFloat(newVc), true, 800);
  }, 10000);
