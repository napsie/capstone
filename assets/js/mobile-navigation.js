(() => {
    if (window.__seniorlinkMobileNavigationReady) return;
    window.__seniorlinkMobileNavigationReady = true;

    const sidebar = document.querySelector('.system-sidebar');
    if (!sidebar) return;

    const toggle = sidebar.querySelector('.mobile-nav-toggle');
    const closeButton = sidebar.querySelector('.mobile-nav-close');
    const navigation = sidebar.querySelector(':scope > .sidebar-menu, :scope > .nav-links');
    const mobileQuery = window.matchMedia('(max-width: 768px)');
    const currentLabel = sidebar.querySelector('.mobile-nav-current');
    const activeLabel = sidebar.querySelector('li.active .link-text')?.textContent?.trim();
    if (currentLabel && activeLabel) currentLabel.textContent = activeLabel;

    let backdrop = document.querySelector('.mobile-nav-backdrop');
    if (!backdrop) {
        backdrop = document.createElement('button');
        backdrop.type = 'button';
        backdrop.className = 'mobile-nav-backdrop';
        backdrop.setAttribute('aria-label', 'Close navigation');
        document.body.appendChild(backdrop);
    }

    const setOpen = open => {
        open = mobileQuery.matches && open;
        sidebar.classList.toggle('mobile-nav-open', open);
        document.body.classList.toggle('mobile-nav-open', open);
        toggle?.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (navigation) {
            navigation.inert = mobileQuery.matches && !open;
            if (mobileQuery.matches) navigation.setAttribute('aria-hidden', open ? 'false' : 'true');
            else navigation.removeAttribute('aria-hidden');
        }
        if (open) window.setTimeout(() => closeButton?.focus(), 0);
    };

    toggle?.addEventListener('click', event => {
        event.preventDefault();
        setOpen(true);
    });
    closeButton?.addEventListener('click', event => {
        event.preventDefault();
        setOpen(false);
        toggle?.focus();
    });
    backdrop.addEventListener('click', () => setOpen(false));
    navigation?.querySelectorAll('a').forEach(link => link.addEventListener('click', () => setOpen(false)));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && sidebar.classList.contains('mobile-nav-open')) {
            setOpen(false);
            toggle?.focus();
        }
    });
    mobileQuery.addEventListener?.('change', () => setOpen(false));

    setOpen(false);
})();
