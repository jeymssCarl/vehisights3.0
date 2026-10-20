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

  // Tabs
  document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
      document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
      tab.classList.add('active');
      document.getElementById(`tab-${tab.dataset.tab}`).classList.add('active');
    });
  });

  // Demo users data
  let users = [
    { id: 1, name: 'Juan Dela Cruz', email: 'juan.delacruz@santotomas.gov.ph', role: 'CPD Admin', status: 'active' },
    { id: 2, name: 'Maria Santos', email: 'maria.santos@santotomas.gov.ph', role: 'IT Admin', status: 'active' },
    { id: 3, name: 'Jose Rizal', email: 'jose.rizal@santotomas.gov.ph', role: 'CPD Admin', status: 'inactive' },
  ];

  function renderUsers() {
    const tbody = document.getElementById('usersTableBody');
    tbody.innerHTML = users.map(u => `
      <tr>
        <td style="font-family:'DM Mono',monospace;">${u.id}</td>
        <td>${u.name}</td>
        <td>${u.email}</td>
        <td><span class="role-badge ${u.role === 'IT Admin' ? 'role-admin' : 'role-super'}">${u.role}</span></td>
        <td><span class="status-badge ${u.status === 'active' ? 'online' : 'offline'}">${u.status === 'active' ? 'Active' : 'Inactive'}</span></td>
        <td class="action-icons">
          <svg viewBox="0 0 24 24" onclick="editUser(${u.id})"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
          <svg viewBox="0 0 24 24" onclick="deleteUser(${u.id})"><path d="M3 6h18M8 6V4h8v2M10 11v6M14 11v6"/></svg>
        </td>
      </tr>
    `).join('');
  }

  function editUser(id) {
    const user = users.find(u => u.id === id);
    if (user) {
      document.getElementById('modalTitle').innerText = 'Edit User';
      document.getElementById('userFullname').value = user.name;
      document.getElementById('userEmail').value = user.email;
      document.getElementById('userRole').value = user.role;
      document.getElementById('userPassword').value = '';
      window.editId = id;
      document.getElementById('userModal').classList.add('active');
    }
  }

  function deleteUser(id) {
    if (confirm('Delete this user? This action cannot be undone.')) {
      users = users.filter(u => u.id !== id);
      renderUsers();
    }
  }

  // Modal logic
  const modal = document.getElementById('userModal');
  const newBtn = document.getElementById('newUserBtn');
  const closeBtn = document.getElementById('closeModalBtn');
  const form = document.getElementById('userForm');

  newBtn.addEventListener('click', () => {
    document.getElementById('modalTitle').innerText = 'Add User';
    form.reset();
    window.editId = null;
    modal.classList.add('active');
  });
  closeBtn.addEventListener('click', () => modal.classList.remove('active'));
  window.addEventListener('click', (e) => { if (e.target === modal) modal.classList.remove('active'); });

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const name = document.getElementById('userFullname').value.trim();
    const email = document.getElementById('userEmail').value.trim();
    const role = document.getElementById('userRole').value;
    const password = document.getElementById('userPassword').value;
    if (!name || !email) return;

    if (window.editId) {
      const idx = users.findIndex(u => u.id === window.editId);
      if (idx !== -1) {
        users[idx].name = name;
        users[idx].email = email;
        users[idx].role = role;
        if (password) console.log(`Password updated for ${email} (simulated)`);
      }
    } else {
      const newId = Math.max(...users.map(u=>u.id), 0) + 1;
      users.push({
        id: newId,
        name: name,
        email: email,
        role: role,
        status: 'active'
      });
      if (password) console.log(`New user password set (simulated)`);
    }
    renderUsers();
    modal.classList.remove('active');
  });

  // Save settings (demo)
  document.getElementById('saveSettingsBtn').addEventListener('click', () => {
    const sysName = document.getElementById('sysName').value;
    const interval = document.getElementById('pollInterval').value;
    const capacity = document.getElementById('defaultCapacity').value;
    const maintenance = document.getElementById('maintenanceMode').value;
    alert(`Settings saved (demo):\nSystem Name: ${sysName}\nPoll Interval: ${interval}s\nDefault Capacity: ${capacity} PCU/lane/hr\nMaintenance Mode: ${maintenance === '1' ? 'Enabled' : 'Disabled'}`);
  });

  renderUsers();

  // Functions used by action controls rendered in the users table.
  window.editUser = editUser;
  window.deleteUser = deleteUser;
