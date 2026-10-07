document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.querySelector('.system-sidebar');
    if (!sidebar) return;

    const groups = Array.from(sidebar.querySelectorAll('.sidebar-nav-group'));
    groups.forEach(group => {
        const toggle = group.querySelector(':scope > .sidebar-group-toggle');
        const submenu = group.querySelector(':scope > .sidebar-submenu');
        if (!toggle || !submenu) return;

        const setOpen = open => {
            group.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            submenu.hidden = !open;
        };

        setOpen(group.classList.contains('is-open'));
        toggle.addEventListener('click', () => {
            const shouldOpen = !group.classList.contains('is-open');

            if (sidebar.classList.contains('collapsed')) {
                sidebar.querySelector('.sidebar-toggle-control')?.click();
            }

            groups.forEach(other => {
                if (other === group) return;
                const otherToggle = other.querySelector(':scope > .sidebar-group-toggle');
                const otherSubmenu = other.querySelector(':scope > .sidebar-submenu');
                other.classList.remove('is-open');
                otherToggle?.setAttribute('aria-expanded', 'false');
                if (otherSubmenu) otherSubmenu.hidden = true;
            });
            setOpen(shouldOpen);
        });
    });
});
