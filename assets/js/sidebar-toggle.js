document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content') || document.querySelector('.main');

    if (sidebar && mainContent) {
        // Check sidebar state from sessionStorage
        if (sessionStorage.getItem('sidebarState') === 'expanded') {
            sidebar.classList.remove('collapsed');
            mainContent.classList.remove('collapsed');
        } else {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('collapsed');
        }

        sidebar.addEventListener('mouseenter', function() {
            sidebar.classList.remove('collapsed');
            mainContent.classList.remove('collapsed');
            sessionStorage.setItem('sidebarState', 'expanded');
        });

        sidebar.addEventListener('mouseleave', function() {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('collapsed');
            sessionStorage.setItem('sidebarState', 'collapsed');
        });
    }
});