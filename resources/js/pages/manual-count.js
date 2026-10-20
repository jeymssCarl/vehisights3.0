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

  // Vehicle types with PCU factors (UPDATED NAMES)
  const vehicleTypes = [
    { name: "Private Car / Taxi / Pick-up", pcu: 1.0 },
    { name: "Jeepney", pcu: 1.5 },
    { name: "Van for Hire (AUV, GT Express)", pcu: 1.5 },
    { name: "Bus & Mini Bus", pcu: 2.5 },
    { name: "Light Truck", pcu: 2.0 },
    { name: "Heavy Truck", pcu: 3.0 },
    { name: "Motorcycle", pcu: 0.5 },
    { name: "Tricycle", pcu: 0.7 }
  ];

  let counts = new Array(vehicleTypes.length).fill(0);
  let pcuTotals = new Array(vehicleTypes.length).fill(0);

  function getBaseCapacity() {
    const hasMedian = document.getElementById('hasMedian').value === '1';
    return hasMedian ? 900 : 720;
  }

  function renderTable() {
    const tbody = document.getElementById('vehicleRows');
    tbody.innerHTML = vehicleTypes.map((vt, idx) => `
      <tr>
        <td>${vt.name}</td>
        <td style="font-family:'DM Mono',monospace;">${vt.pcu}</td>
        <td>
          <div class="counter">
            <button class="counter-btn" data-idx="${idx}" data-delta="-1">-</button>
            <input type="number" class="counter-input" id="count_${idx}" value="${counts[idx]}" min="0" step="1">
            <button class="counter-btn" data-idx="${idx}" data-delta="1">+</button>
          </div>
        </td>
        <td style="font-family:'DM Mono',monospace;" id="pcu_${idx}">${pcuTotals[idx]}</td>
      </tr>
    `).join('');

    document.querySelectorAll('.counter-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        const idx = parseInt(btn.dataset.idx);
        const delta = parseInt(btn.dataset.delta);
        let newVal = counts[idx] + delta;
        if (newVal < 0) newVal = 0;
        counts[idx] = newVal;
        document.getElementById(`count_${idx}`).value = newVal;
        updatePcu(idx);
        updateSummary();
      });
    });
    for (let i = 0; i < vehicleTypes.length; i++) {
      const input = document.getElementById(`count_${i}`);
      if (input) {
        input.addEventListener('change', (e) => {
          let val = parseInt(e.target.value);
          if (isNaN(val)) val = 0;
          if (val < 0) val = 0;
          counts[i] = val;
          updatePcu(i);
          updateSummary();
        });
      }
    }
  }

  function updatePcu(idx) {
    const pcuVal = counts[idx] * vehicleTypes[idx].pcu;
    pcuTotals[idx] = pcuVal;
    const pcuCell = document.getElementById(`pcu_${idx}`);
    if (pcuCell) pcuCell.innerText = pcuVal;
  }

  function updateSummary() {
    const totalPcu = pcuTotals.reduce((a,b) => a + b, 0);
    const lanes = parseInt(document.getElementById('lanes').value) || 1;
    const baseCap = getBaseCapacity();
    const totalCapacity = lanes * baseCap;
    const vc = totalCapacity > 0 ? (totalPcu / totalCapacity).toFixed(2) : 0;
    let los = '';
    if (vc < 0.20) los = 'A';
    else if (vc < 0.45) los = 'B';
    else if (vc < 0.70) los = 'C';
    else if (vc < 0.85) los = 'D';
    else if (vc <= 1.0) los = 'E';
    else los = 'F';

    document.getElementById('baseCapDisplay').innerText = baseCap;
    document.getElementById('totalPcuDisplay').innerText = totalPcu;
    document.getElementById('totalCapacityDisplay').innerText = totalCapacity;
    document.getElementById('vcRatioDisplay').innerText = vc;
    const losSpan = document.getElementById('losDisplay');
    losSpan.innerText = los;
    losSpan.style.backgroundColor = 
      los === 'A' || los === 'B' ? 'var(--green-bg)' : 
      los === 'C' ? 'var(--amber-bg)' : 'var(--red-bg)';
    losSpan.style.color = 
      los === 'A' || los === 'B' ? 'var(--green)' : 
      los === 'C' ? 'var(--amber)' : 'var(--red)';
    losSpan.style.border = 
      los === 'A' || los === 'B' ? '1px solid var(--green-border)' : 
      los === 'C' ? '1px solid var(--amber-border)' : '1px solid var(--red-border)';
  }

  document.getElementById('hasMedian').addEventListener('change', updateSummary);
  document.getElementById('lanes').addEventListener('input', updateSummary);

  // Show custom success modal
  function showSuccessModal(message) {
    const modal = document.getElementById('successModal');
    document.getElementById('successMessage').innerHTML = message;
    modal.classList.add('active');
  }

  function closeSuccessModal() {
    document.getElementById('successModal').classList.remove('active');
  }

  // Submit button with fetch and custom modal
  document.getElementById('submitManualCount').addEventListener('click', async () => {
    const hasMedian = document.getElementById('hasMedian').value === '1';
    const lanes = parseInt(document.getElementById('lanes').value) || 1;
    const data = {
      road: document.getElementById('roadName').value,
      barangay: document.getElementById('barangay').value,
      city: document.getElementById('city').value,
      dir_from: document.getElementById('dirFrom').value,
      dir_to: document.getElementById('dirTo').value,
      date: document.getElementById('date').value,
      hour_start: document.getElementById('hourStart').value,
      hour_end: document.getElementById('hourEnd').value,
      has_median: hasMedian,
      lanes: lanes,
      counts: counts,
      pcu_totals: pcuTotals,
      total_pcu: parseInt(document.getElementById('totalPcuDisplay').innerText),
      vc: parseFloat(document.getElementById('vcRatioDisplay').innerText),
      los: document.getElementById('losDisplay').innerText
    };

    // Disable button to prevent double submission
    const btn = document.getElementById('submitManualCount');
    btn.disabled = true;
    btn.textContent = 'Saving...';

    try {
      const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
      const pageData = JSON.parse(document.getElementById('page-data').textContent);
      const response = await fetch(pageData.storeUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'Accept': 'application/json'
        },
        body: JSON.stringify(data)
      });

      if (!response.ok) {
        const text = await response.text();
        console.error('Server response:', text);
        throw new Error(`HTTP ${response.status}: ${text.substring(0, 100)}`);
      }

      const result = await response.json();
      if (result.success) {
        showSuccessModal('Manual count saved successfully!');
        // Optional: reset counts after save
        // counts.fill(0); pcuTotals.fill(0); renderTable(); updateSummary();
      } else {
        alert('❌ Error: ' + (result.message || 'Something went wrong'));
      }
    } catch (error) {
      console.error('Fetch error:', error);
      alert('❌ Connection error: ' + error.message);
    } finally {
      btn.disabled = false;
      btn.textContent = 'Save Manual Count';
    }
  });

  // Close success modal
  document.getElementById('closeSuccessModalBtn').addEventListener('click', closeSuccessModal);
  document.getElementById('successModal').addEventListener('click', (e) => {
    if (e.target === document.getElementById('successModal')) closeSuccessModal();
  });

  renderTable();
  updateSummary();
