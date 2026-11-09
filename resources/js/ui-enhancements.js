import '../css/ui-enhancements.css';

document.documentElement.classList.add('ui-enhanced');

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

const showNotice = ({ title = 'Something needs your attention', message = '', tone = 'warning' } = {}) => {
    let overlay = document.getElementById('uiNoticeModal');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'uiNoticeModal';
        overlay.className = 'ui-notice-overlay';
        overlay.innerHTML = `
            <section class="ui-notice-modal" role="alertdialog" aria-modal="true" aria-labelledby="uiNoticeTitle" aria-describedby="uiNoticeMessage">
                <button type="button" class="ui-notice-close" aria-label="Close">&times;</button>
                <div class="ui-notice-icon" aria-hidden="true">!</div>
                <h2 id="uiNoticeTitle"></h2>
                <p id="uiNoticeMessage"></p>
                <button type="button" class="ui-notice-ok">Okay</button>
            </section>`;
        document.body.appendChild(overlay);

        const close = () => {
            overlay.classList.remove('active');
            document.body.classList.remove('ui-modal-open');
        };
        overlay.querySelector('.ui-notice-close').addEventListener('click', close);
        overlay.querySelector('.ui-notice-ok').addEventListener('click', close);
        overlay.addEventListener('click', event => {
            if (event.target === overlay) close();
        });
    }

    overlay.dataset.tone = tone;
    overlay.querySelector('#uiNoticeTitle').textContent = title;
    overlay.querySelector('#uiNoticeMessage').textContent = message;
    overlay.classList.add('active');
    document.body.classList.add('ui-modal-open');
    window.setTimeout(() => overlay.querySelector('.ui-notice-ok').focus(), 20);
};

window.VehiSightsUI = { ...(window.VehiSightsUI || {}), showNotice };

const ensurePageSkeleton = () => {
    let skeleton = document.getElementById('uiPageSkeleton');
    if (skeleton) return skeleton;

    skeleton = document.createElement('div');
    skeleton.id = 'uiPageSkeleton';
    skeleton.className = 'ui-page-skeleton';
    skeleton.setAttribute('aria-hidden', 'true');
    skeleton.innerHTML = `
        <div class="ui-skeleton-sidebar">
            <div class="ui-skeleton-brand"><span></span><i></i></div>
            <div class="ui-skeleton-nav-heading"></div>
            <div class="ui-skeleton-nav active"></div>
            <div class="ui-skeleton-nav"></div>
            <div class="ui-skeleton-nav"></div>
            <div class="ui-skeleton-nav-heading short"></div>
            <div class="ui-skeleton-nav"></div>
            <div class="ui-skeleton-nav"></div>
            <div class="ui-skeleton-profile"><span></span><i></i></div>
        </div>
        <div class="ui-skeleton-workspace">
            <header class="ui-skeleton-topbar">
                <div><span></span><i></i></div>
                <div class="ui-skeleton-actions"><b></b><b></b><b></b></div>
            </header>
            <main class="ui-skeleton-content">
                <div class="ui-skeleton-toolbar"></div>
                <div class="ui-skeleton-kpis">
                    <section></section><section></section><section></section><section></section>
                </div>
                <div class="ui-skeleton-panels">
                    <section><span></span><i></i><i></i><i></i></section>
                    <section><span></span><i></i><i></i><i></i></section>
                </div>
            </main>
        </div>`;
    document.body.appendChild(skeleton);
    return skeleton;
};

let skeletonFallbackTimer = null;
const showPageSkeleton = () => {
    const skeleton = ensurePageSkeleton();
    window.clearTimeout(skeletonFallbackTimer);
    skeleton.classList.add('active');
    skeleton.setAttribute('aria-hidden', 'false');
    document.body.classList.add('ui-page-is-loading');
    document.body.setAttribute('aria-busy', 'true');
    skeletonFallbackTimer = window.setTimeout(hidePageSkeleton, 10000);
};

function hidePageSkeleton() {
    const skeleton = document.getElementById('uiPageSkeleton');
    skeleton?.classList.remove('active');
    skeleton?.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('ui-page-is-loading');
    document.body.removeAttribute('aria-busy');
    window.clearTimeout(skeletonFallbackTimer);
}

window.VehiSightsUI = { ...window.VehiSightsUI, showPageSkeleton, hidePageSkeleton };

const initializeNavigationSkeleton = () => {
    // The dashboard skeleton belongs only to the authenticated application shell.
    // Guest pages and OAuth redirects must navigate immediately without it.
    if (!document.querySelector('.layout .sidebar')) return;

    ensurePageSkeleton();

    document.addEventListener('click', event => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        const link = event.target.closest('a[href]');
        if (!link || link.target === '_blank' || link.hasAttribute('download')) return;

        const destination = new URL(link.href, window.location.href);
        if (destination.origin !== window.location.origin) return;
        if (/^\/(?:auth|login|register|logout)(?:\/|$)/.test(destination.pathname)) return;
        if (destination.href === window.location.href || (destination.pathname === window.location.pathname && destination.search === window.location.search)) return;

        event.preventDefault();
        showPageSkeleton();
        window.requestAnimationFrame(() => {
            window.requestAnimationFrame(() => window.location.assign(destination.href));
        });
    });

    window.addEventListener('pageshow', hidePageSkeleton);
};

const initializeMobileNavigation = () => {
    const sidebar = document.querySelector('.sidebar');
    const topbar = document.querySelector('.topbar');
    if (!sidebar || !topbar || document.querySelector('.mobile-menu-button')) return;

    document.querySelectorAll('table').forEach(table => {
        if (table.closest('.table-wrapper, .table-container, .table-scroll, .survey-form, .mobile-table-scroll')) return;
        const scrollRegion = document.createElement('div');
        scrollRegion.className = 'mobile-table-scroll';
        scrollRegion.setAttribute('role', 'region');
        scrollRegion.setAttribute('aria-label', 'Scrollable table');
        scrollRegion.setAttribute('tabindex', '0');
        table.before(scrollRegion);
        scrollRegion.appendChild(table);
    });

    sidebar.id = sidebar.id || 'primarySidebar';
    const menuButton = document.createElement('button');
    menuButton.type = 'button';
    menuButton.className = 'mobile-menu-button';
    menuButton.setAttribute('aria-label', 'Open navigation menu');
    menuButton.setAttribute('aria-controls', sidebar.id);
    menuButton.setAttribute('aria-expanded', 'false');
    menuButton.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>';
    topbar.prepend(menuButton);

    const closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.className = 'mobile-sidebar-close';
    closeButton.setAttribute('aria-label', 'Close navigation menu');
    closeButton.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>';
    document.body.appendChild(closeButton);

    const backdrop = document.createElement('div');
    backdrop.className = 'mobile-sidebar-backdrop';
    backdrop.setAttribute('aria-hidden', 'true');
    document.body.appendChild(backdrop);

    const closeMenu = ({ restoreFocus = false } = {}) => {
        sidebar.classList.remove('mobile-open');
        backdrop.classList.remove('active');
        document.body.classList.remove('mobile-nav-open');
        menuButton.setAttribute('aria-expanded', 'false');
        sidebar.setAttribute('aria-hidden', window.matchMedia('(max-width: 1100px)').matches ? 'true' : 'false');
        if (restoreFocus) menuButton.focus();
    };

    const openMenu = () => {
        sidebar.classList.add('mobile-open');
        backdrop.classList.add('active');
        document.body.classList.add('mobile-nav-open');
        menuButton.setAttribute('aria-expanded', 'true');
        sidebar.setAttribute('aria-hidden', 'false');
        window.setTimeout(() => closeButton.focus(), 180);
    };

    menuButton.addEventListener('click', openMenu);
    const handleCloseControl = event => {
        event.preventDefault();
        event.stopPropagation();
        closeMenu({ restoreFocus: true });
    };
    closeButton.addEventListener('pointerdown', handleCloseControl);
    closeButton.addEventListener('click', handleCloseControl);
    document.addEventListener('pointerdown', event => {
        if (event.target.closest('.mobile-sidebar-close')) handleCloseControl(event);
    }, true);
    backdrop.addEventListener('click', () => closeMenu({ restoreFocus: true }));
    sidebar.querySelector('.nav')?.addEventListener('click', event => {
        if (event.target.closest('a[href]')) closeMenu();
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && sidebar.classList.contains('mobile-open')) {
            closeMenu({ restoreFocus: true });
        }
    });

    const mobileQuery = window.matchMedia('(max-width: 1100px)');
    const synchronizeMenu = event => {
        if (!event.matches) closeMenu();
        sidebar.setAttribute('aria-hidden', event.matches && !sidebar.classList.contains('mobile-open') ? 'true' : 'false');
    };
    mobileQuery.addEventListener('change', synchronizeMenu);
    synchronizeMenu(mobileQuery);
};

const relativeTime = value => {
    const timestamp = new Date(value).getTime();
    if (!Number.isFinite(timestamp)) return '';
    const seconds = Math.max(0, Math.round((Date.now() - timestamp) / 1000));
    if (seconds < 60) return 'Just now';
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.floor(hours / 24);
    return days < 7 ? `${days}d ago` : new Date(timestamp).toLocaleDateString();
};

const initializeNotificationCenter = () => {
    const bell = [...document.querySelectorAll('.topbar-right .icon-btn')].find(button =>
        [...button.querySelectorAll('path')].some(path => path.getAttribute('d')?.startsWith('M18 8'))
    );
    if (!bell) return;

    bell.setAttribute('role', 'button');
    bell.setAttribute('tabindex', '0');
    bell.setAttribute('aria-label', 'Notifications');
    bell.setAttribute('aria-expanded', 'false');
    bell.classList.add('notification-trigger');

    const oldDot = bell.querySelector('.notif-dot');
    oldDot?.remove();
    const badge = document.createElement('span');
    badge.className = 'notification-badge';
    badge.hidden = true;
    bell.appendChild(badge);

    const panel = document.createElement('section');
    panel.className = 'notification-panel';
    panel.setAttribute('aria-label', 'Notifications');
    panel.innerHTML = `
        <div class="notification-panel-header">
            <div><strong>Notifications</strong><span>Team activity</span></div>
            <button type="button" class="notification-read-all">Mark all read</button>
        </div>
        <div class="notification-list"><div class="notification-state">Loading notifications…</div></div>`;
    bell.parentElement.appendChild(panel);

    const list = panel.querySelector('.notification-list');
    const readAll = panel.querySelector('.notification-read-all');
    let loading = false;

    const updateBadge = count => {
        const total = Number(count) || 0;
        badge.hidden = total === 0;
        badge.textContent = total > 99 ? '99+' : String(total);
        bell.setAttribute('aria-label', total ? `Notifications, ${total} unread` : 'Notifications');
    };

    const render = payload => {
        list.replaceChildren();
        const notifications = payload.notifications || [];
        updateBadge(payload.unread_count);
        readAll.disabled = !payload.unread_count;

        if (!notifications.length) {
            const empty = document.createElement('div');
            empty.className = 'notification-state notification-empty';
            empty.innerHTML = '<span aria-hidden="true">✓</span><strong>You’re all caught up</strong><small>Team activity will appear here.</small>';
            list.appendChild(empty);
            return;
        }

        notifications.forEach(notification => {
            const data = notification.data || {};
            const item = document.createElement('button');
            item.type = 'button';
            item.className = `notification-item${notification.read_at ? '' : ' unread'}`;
            item.innerHTML = '<span class="notification-item-icon" aria-hidden="true"></span><span class="notification-item-copy"><strong></strong><span></span><small></small></span>';
            item.querySelector('strong').textContent = data.title || 'Team activity';
            item.querySelector('.notification-item-copy > span').textContent = data.message || '';
            item.querySelector('small').textContent = relativeTime(notification.created_at);
            item.addEventListener('click', async () => {
                if (!notification.read_at) {
                    await fetch(`/notifications/${notification.id}/read`, {
                        method: 'PATCH',
                        headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' }
                    }).catch(() => null);
                }
                if (data.url) window.location.href = data.url;
            });
            list.appendChild(item);
        });
    };

    const load = async () => {
        if (loading || document.hidden) return;
        loading = true;
        try {
            const response = await fetch('/notifications', { headers: { 'Accept': 'application/json' } });
            if (!response.ok) throw new Error('Unable to load notifications');
            render(await response.json());
        } catch (error) {
            if (panel.classList.contains('active')) {
                list.innerHTML = '<div class="notification-state">Notifications could not be loaded. Please try again.</div>';
            }
        } finally {
            loading = false;
        }
    };

    const toggle = event => {
        event?.stopPropagation();
        const open = !panel.classList.contains('active');
        panel.classList.toggle('active', open);
        bell.setAttribute('aria-expanded', String(open));
        if (open) load();
    };

    bell.addEventListener('click', toggle);
    bell.addEventListener('keydown', event => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            toggle(event);
        }
    });
    panel.addEventListener('click', event => event.stopPropagation());
    document.addEventListener('click', () => {
        panel.classList.remove('active');
        bell.setAttribute('aria-expanded', 'false');
    });
    readAll.addEventListener('click', async () => {
        readAll.disabled = true;
        readAll.textContent = 'Marking…';
        try {
            await fetch('/notifications/read-all', {
                method: 'PATCH',
                headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' }
            });
            await load();
        } finally {
            readAll.textContent = 'Mark all read';
        }
    });

    load();
    window.setInterval(load, 15000);
};

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

    initializeNotificationCenter();
    initializeNavigationSkeleton();
    initializeMobileNavigation();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeEnhancements, { once: true });
} else {
    initializeEnhancements();
}
