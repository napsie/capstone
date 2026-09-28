document.addEventListener('DOMContentLoaded', function() {
    if (document.documentElement.dataset.sidebarControllerReady === 'true') return;
    document.documentElement.dataset.sidebarControllerReady = 'true';
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content') || document.querySelector('.main');
    const toggleButton = sidebar?.querySelector('.sidebar-toggle-control');

    if (sidebar && mainContent) {
        const desktopQuery = window.matchMedia('(min-width: 769px)');
        const storageKey = 'seniorlinkSidebarCollapsed';

        function savedCollapsedState() {
            try { return window.localStorage.getItem(storageKey) === 'true'; }
            catch (error) { return false; }
        }

        function setCollapsed(collapsed, savePreference = false) {
            const useCollapsed = desktopQuery.matches && collapsed;
            sidebar.classList.toggle('collapsed', useCollapsed);
            mainContent.classList.toggle('collapsed', useCollapsed);
            sidebar.setAttribute('aria-expanded', useCollapsed ? 'false' : 'true');

            if (toggleButton) {
                toggleButton.setAttribute('aria-expanded', useCollapsed ? 'false' : 'true');
                toggleButton.setAttribute('aria-label', useCollapsed ? 'Expand sidebar' : 'Collapse sidebar');
                toggleButton.title = useCollapsed ? 'Expand sidebar' : 'Collapse sidebar';
                const label = toggleButton.querySelector('.toggle-label');
                if (label) label.textContent = useCollapsed ? 'Expand sidebar' : 'Collapse sidebar';
            }

            if (savePreference && desktopQuery.matches) {
                try { window.localStorage.setItem(storageKey, String(useCollapsed)); }
                catch (error) { /* The toggle still works when storage is unavailable. */ }
            }
        }

        setCollapsed(savedCollapsedState());

        toggleButton?.addEventListener('click', function() {
            setCollapsed(!sidebar.classList.contains('collapsed'), true);
        });

        desktopQuery.addEventListener?.('change', function() {
            setCollapsed(savedCollapsedState());
            if (desktopQuery.matches) closeMobileNavigation();
        });

        const mobileToggle = sidebar.querySelector('.mobile-nav-toggle');
        const mobileClose = sidebar.querySelector('.mobile-nav-close');
        const currentLabel = sidebar.querySelector('.mobile-nav-current');
        const mobileNavigation = sidebar.querySelector(':scope > .sidebar-menu, :scope > .nav-links');
        const activeLink = sidebar.querySelector('li.active a');
        if (currentLabel && activeLink) currentLabel.textContent = activeLink.querySelector('.link-text')?.textContent?.trim() || 'Navigation';

        let backdrop = document.querySelector('.mobile-nav-backdrop');
        if (!backdrop) {
            backdrop = document.createElement('button');
            backdrop.type = 'button';
            backdrop.className = 'mobile-nav-backdrop';
            backdrop.setAttribute('aria-label', 'Close navigation');
            document.body.appendChild(backdrop);
        }

        function openMobileNavigation() {
            if (desktopQuery.matches) return;
            document.body.classList.add('mobile-nav-open');
            sidebar.classList.add('mobile-nav-open');
            if (mobileNavigation) {
                mobileNavigation.inert = false;
                mobileNavigation.setAttribute('aria-hidden', 'false');
            }
            mobileToggle?.setAttribute('aria-expanded', 'true');
            window.setTimeout(() => mobileClose?.focus(), 0);
        }

        function closeMobileNavigation() {
            document.body.classList.remove('mobile-nav-open');
            sidebar.classList.remove('mobile-nav-open');
            if (mobileNavigation && !desktopQuery.matches) {
                mobileNavigation.inert = true;
                mobileNavigation.setAttribute('aria-hidden', 'true');
            } else if (mobileNavigation) {
                mobileNavigation.inert = false;
                mobileNavigation.removeAttribute('aria-hidden');
            }
            mobileToggle?.setAttribute('aria-expanded', 'false');
        }

        mobileToggle?.addEventListener('click', openMobileNavigation);
        mobileClose?.addEventListener('click', closeMobileNavigation);
        backdrop.addEventListener('click', closeMobileNavigation);
        sidebar.querySelectorAll(':is(.nav-links, .sidebar-menu) a').forEach(link => link.addEventListener('click', closeMobileNavigation));
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && sidebar.classList.contains('mobile-nav-open')) {
                closeMobileNavigation();
                mobileToggle?.focus();
            }
        });
        closeMobileNavigation();
    }
});
