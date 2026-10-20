    function checkStrength(pw) {
        const fill = document.getElementById('strength-fill');
        const label = document.getElementById('strength-label');
        let score = 0;
        if (pw.length >= 8) score++;
        if (pw.length >= 12) score++;
        if (/[A-Z]/.test(pw)) score++;
        if (/[0-9]/.test(pw)) score++;
        if (/[^A-Za-z0-9]/.test(pw)) score++;
        const levels = [
            { w: '0%', color: 'transparent', text: 'Enter a password' },
            { w: '25%', color: '#e74c3c', text: 'Weak' },
            { w: '50%', color: '#e67e22', text: 'Fair' },
            { w: '75%', color: '#f1c40f', text: 'Good' },
            { w: '100%', color: '#27ae60', text: 'Strong' }
        ];
        const level = pw.length === 0 ? levels[0] : levels[Math.min(score, 4)];
        fill.style.width = level.w;
        fill.style.background = level.color;
        label.textContent = pw.length === 0 ? 'Enter a password' : level.text;
        label.style.color = pw.length === 0 ? 'var(--gray-400)' : level.color;
        updateProgress();

    }

    function togglePw(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        icon.innerHTML = show
            ? '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>'
            : '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
    }

    function updateProgress() {
        const firstName = document.getElementById('first_name')?.value.trim() || '';
        const lastName = document.getElementById('last_name')?.value.trim() || '';
        const email = document.getElementById('email')?.value.trim() || '';
        const department = document.getElementById('department')?.value || '';
        const role = document.getElementById('role')?.value || '';
        const password = document.getElementById('password')?.value || '';
        const confirm = document.getElementById('password_confirmation')?.value || '';
        const terms = document.getElementById('termsCheckbox')?.checked || false;

        let completed = 0;
        const total = 8;
        if (firstName.length > 0) completed++;
        if (lastName.length > 0) completed++;
        if (email.includes('@') && email.includes('.')) completed++;
        if (department !== '' && department !== 'Select…') completed++;
        if (role !== '' && role !== 'Select…') completed++;
        if (password.length >= 8) completed++;
        if (confirm.length >= 8 && password === confirm) completed++;
        if (terms) completed++;

        let percent = Math.round((completed / total) * 100);
        if (percent > 100) percent = 100;
        const fillBar = document.getElementById('progressFill');
        const percentSpan = document.getElementById('progressPercent');
        percentSpan.textContent = percent + '%';
        fillBar.style.width = percent + '%';
        const r = Math.min(255, Math.floor(255 * (1 - percent/100)));
        const g = Math.min(255, Math.floor(255 * (percent/100)));
        fillBar.style.background = `rgb(${r}, ${g}, 0)`;
    }

    document.addEventListener('DOMContentLoaded', function() {
        const fields = ['first_name', 'last_name', 'email', 'role', 'password', 'password_confirmation'];
        fields.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('input', updateProgress);
        });
        const roleSelect = document.getElementById('role');
        if (roleSelect) roleSelect.addEventListener('change', updateProgress);
        updateProgress();
    });

    window.checkStrength = checkStrength;
    window.togglePw = togglePw;
    window.updateProgress = updateProgress;
