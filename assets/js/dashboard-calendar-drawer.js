document.addEventListener('DOMContentLoaded', () => {
    const drawer = document.getElementById('dashboardCalendarDrawer');
    const backdrop = document.getElementById('dashboardCalendarBackdrop');
    const closeButton = document.getElementById('dashboardCalendarClose');
    const launchers = Array.from(document.querySelectorAll('[data-calendar-open]'));
    if (!drawer || !backdrop || !closeButton || !launchers.length) return;

    let returnFocus = null;
    const focusable = () => Array.from(drawer.querySelectorAll('button:not([disabled]), [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'));
    const open = launcher => {
        returnFocus = launcher;
        drawer.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
        backdrop.hidden = false;
        document.body.classList.add('dashboard-calendar-open');
        launcher.setAttribute('aria-expanded', 'true');
        closeButton.focus();
    };
    const close = () => {
        drawer.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
        backdrop.hidden = true;
        document.body.classList.remove('dashboard-calendar-open');
        launchers.forEach(button => button.setAttribute('aria-expanded', 'false'));
        returnFocus?.focus();
    };

    launchers.forEach(button => button.addEventListener('click', () => open(button)));
    closeButton.addEventListener('click', close);
    backdrop.addEventListener('click', close);
    document.addEventListener('keydown', event => {
        if (!drawer.classList.contains('is-open')) return;
        if (event.key === 'Escape') { event.preventDefault(); close(); return; }
        if (event.key !== 'Tab') return;
        const controls = focusable();
        if (!controls.length) return;
        const first = controls[0], last = controls[controls.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
});
