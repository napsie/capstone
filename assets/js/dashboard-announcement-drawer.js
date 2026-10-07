document.addEventListener('DOMContentLoaded', () => {
    const drawer = document.getElementById('dashboardAnnouncementDrawer');
    const backdrop = document.getElementById('dashboardAnnouncementBackdrop');
    const closeButton = document.getElementById('dashboardAnnouncementClose');
    const launchers = Array.from(document.querySelectorAll('[data-announcement-open]'));
    if (!drawer || !backdrop || !closeButton || !launchers.length) return;

    let returnFocus = null;
    const open = launcher => {
        returnFocus = launcher;
        drawer.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
        backdrop.hidden = false;
        document.body.classList.add('dashboard-announcement-open');
        launcher.setAttribute('aria-expanded', 'true');
        closeButton.focus();
    };
    const close = () => {
        drawer.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
        backdrop.hidden = true;
        document.body.classList.remove('dashboard-announcement-open');
        launchers.forEach(button => button.setAttribute('aria-expanded', 'false'));
        returnFocus?.focus();
    };

    launchers.forEach(button => button.addEventListener('click', () => open(button)));
    closeButton.addEventListener('click', close);
    backdrop.addEventListener('click', close);
    document.querySelectorAll('[data-announcement-detail]').forEach(button => {
        const dialog = document.getElementById(button.dataset.announcementDetail);
        if (!dialog) return;
        button.addEventListener('click', () => dialog.showModal());
        dialog.querySelectorAll('[data-announcement-detail-close]').forEach(control => control.addEventListener('click', () => dialog.close()));
        dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
    });
    document.addEventListener('keydown', event => {
        if (!drawer.classList.contains('is-open')) return;
        if (event.key === 'Escape') { event.preventDefault(); close(); return; }
        if (event.key !== 'Tab') return;
        const controls = Array.from(drawer.querySelectorAll('button:not([disabled]),[href],[tabindex]:not([tabindex="-1"])'));
        if (!controls.length) return;
        const first = controls[0], last = controls[controls.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
});
