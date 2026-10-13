import './bootstrap';

try {
    if (localStorage.getItem('cfo-sidebar') === 'compact') {
        document.querySelector('[data-workspace]')?.classList.add('nav-collapsed');
    }
} catch {
    // The navigation still works when browser storage is unavailable.
}

document.addEventListener('DOMContentLoaded', () => {
    const workspace = document.querySelector('[data-workspace]');
    const menu = document.querySelector('[data-menu]');
    const sidebar = document.querySelector('#sidebar');
    const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
    const menuClosers = document.querySelectorAll('[data-menu-close]');
    const mainContent = document.querySelector('#main-content');
    const mobileQuery = window.matchMedia('(max-width: 900px)');
    let menuWasOpen = false;

    const setMenu = open => {
        open = Boolean(open && mobileQuery.matches);
        sidebar?.classList.toggle('open', open);
        workspace?.classList.toggle('menu-open', open);
        document.body.classList.toggle('nav-open', open);
        menu?.setAttribute('aria-expanded', String(open));
        menu?.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
        if (sidebar) {
            if (open) {
                sidebar.setAttribute('role', 'dialog');
                sidebar.setAttribute('aria-modal', 'true');
            } else {
                sidebar.removeAttribute('role');
                sidebar.removeAttribute('aria-modal');
            }
        }
        if (mainContent) mainContent.inert = open;
        if (open) {
            menuWasOpen = true;
            requestAnimationFrame(() => sidebar?.querySelector('[data-menu-close]')?.focus());
        } else if (menuWasOpen) {
            menuWasOpen = false;
            menu?.focus();
        }
    };

    menu?.addEventListener('click', () => setMenu(!sidebar?.classList.contains('open')));
    menuClosers.forEach(button => button.addEventListener('click', () => setMenu(false)));
    sidebar?.querySelectorAll('nav a').forEach(link => link.addEventListener('click', () => setMenu(false)));
    mobileQuery.addEventListener('change', () => setMenu(false));

    const focusableElements = () => [...(sidebar?.querySelectorAll('a[href], button:not(:disabled)') ?? [])]
        .filter(element => element.getClientRects().length > 0);
    document.addEventListener('keydown', event => {
        if (!sidebar?.classList.contains('open')) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            setMenu(false);
        }
        if (event.key === 'Tab') {
            const items = focusableElements();
            const first = items[0];
            const last = items[items.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last?.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first?.focus();
            }
        }
    });

    const updateToggle = collapsed => {
        workspace?.classList.toggle('nav-collapsed', collapsed);
        sidebarToggle?.setAttribute('aria-expanded', String(!collapsed));
        sidebarToggle?.setAttribute('aria-label', collapsed ? 'Expand navigation' : 'Collapse navigation');
        if (sidebarToggle) sidebarToggle.title = collapsed ? 'Expand navigation' : 'Collapse navigation';
    };
    updateToggle(Boolean(workspace?.classList.contains('nav-collapsed')));
    sidebarToggle?.addEventListener('click', () => {
        const collapsed = !workspace?.classList.contains('nav-collapsed');
        updateToggle(collapsed);
        try {
            localStorage.setItem('cfo-sidebar', collapsed ? 'compact' : 'expanded');
        } catch {
            // Keep the current session usable when browser storage is unavailable.
        }
    });

    const tooltip = document.createElement('div');
    tooltip.className = 'sidebar-tooltip';
    tooltip.setAttribute('role', 'tooltip');
    document.body.append(tooltip);
    let tooltipTimer;
    const hideTooltip = () => {
        window.clearTimeout(tooltipTimer);
        tooltip.classList.remove('visible');
    };
    const showTooltip = (element, immediate = false) => {
        hideTooltip();
        if (!workspace?.classList.contains('nav-collapsed') || mobileQuery.matches) return;
        const reveal = () => {
            const rect = element.getBoundingClientRect();
            tooltip.textContent = element.dataset.label || element.getAttribute('aria-label');
            tooltip.style.top = `${rect.top + rect.height / 2}px`;
            tooltip.classList.add('visible');
        };
        tooltipTimer = window.setTimeout(reveal, immediate ? 0 : 180);
    };
    sidebar?.querySelectorAll('[data-label]').forEach(element => {
        element.addEventListener('mouseenter', () => showTooltip(element));
        element.addEventListener('mouseleave', hideTooltip);
        element.addEventListener('focus', () => showTooltip(element, true));
        element.addEventListener('blur', hideTooltip);
    });
    sidebar?.addEventListener('scroll', hideTooltip, true);
    sidebarToggle?.addEventListener('click', hideTooltip);

    document.querySelectorAll('form').forEach(form => form.addEventListener('submit', () => {
        const button = form.querySelector('button[type="submit"],button:not([type])');
        if (button) {
            button.disabled = true;
            button.dataset.label = button.textContent;
            button.textContent = 'Working…';
        }
    }));

    const reveals = document.querySelectorAll('[data-reveal]');
    if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        const observer = new IntersectionObserver(entries => entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        }), { threshold: .14 });
        reveals.forEach(element => observer.observe(element));
    } else {
        reveals.forEach(element => element.classList.add('is-visible'));
    }
});
