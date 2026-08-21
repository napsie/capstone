document.addEventListener('DOMContentLoaded', function() {
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
        });
    }
});
