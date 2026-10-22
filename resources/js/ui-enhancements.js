import '../css/ui-enhancements.css';

document.documentElement.classList.add('ui-enhanced');

const initializeEnhancements = () => {
    requestAnimationFrame(() => document.body.classList.add('ui-ready'));

    const topbar = document.querySelector('.topbar');
    const main = document.querySelector('.main');

    if (topbar) {
        const updateTopbarDepth = () => {
            const scrollTop = Math.max(window.scrollY, main?.scrollTop ?? 0);
            topbar.classList.toggle('ui-is-scrolled', scrollTop > 6);
        };

        window.addEventListener('scroll', updateTopbarDepth, { passive: true });
        main?.addEventListener('scroll', updateTopbarDepth, { passive: true });
        updateTopbarDepth();
    }

    document.addEventListener('pointerdown', (event) => {
        const control = event.target.closest(
            'button, .btn-primary, .btn-secondary, .btn-submit, .icon-btn, .date-pill, .pill, .tab, .action-icon, .act-btn'
        );

        if (!control || control.matches(':disabled')) return;

        control.classList.remove('ui-pressed');
        void control.offsetWidth;
        control.classList.add('ui-pressed');
        window.setTimeout(() => control.classList.remove('ui-pressed'), 260);
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeEnhancements, { once: true });
} else {
    initializeEnhancements();
}
