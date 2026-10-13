import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    const menu = document.querySelector('[data-menu]');
    const sidebar = document.querySelector('#sidebar');
    const setMenu = open => {
        sidebar?.classList.toggle('open', open);
        menu?.setAttribute('aria-expanded', String(open));
    };

    menu?.addEventListener('click', () => setMenu(!sidebar?.classList.contains('open')));
    document.addEventListener('click', event => {
        if (window.innerWidth <= 900 && sidebar?.classList.contains('open') && !sidebar.contains(event.target) && !menu?.contains(event.target)) {
            setMenu(false);
        }
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') setMenu(false);
    });
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
