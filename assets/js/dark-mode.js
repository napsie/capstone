
document.addEventListener('DOMContentLoaded', function() {
    const themeToggle = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeIcon');

    function applyTheme(theme) {
        if (theme === 'dark') {
            document.body.classList.add('dark-mode');
            if (themeIcon) {
                themeIcon.classList.remove('fa-sun');
                themeIcon.classList.add('fa-moon');
            }
            if (themeToggle) {
                themeToggle.setAttribute('aria-pressed', 'true');
            }
        } else {
            document.body.classList.remove('dark-mode');
            if (themeIcon) {
                themeIcon.classList.remove('fa-moon');
                themeIcon.classList.add('fa-sun');
            }
            if (themeToggle) {
                themeToggle.setAttribute('aria-pressed', 'false');
            }
        }
        localStorage.setItem('carelink_theme', theme);
    }

    // Initialize theme
    const storedTheme = localStorage.getItem('carelink_theme');
    const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    
    let initialTheme = 'light';
    if (storedTheme) {
        initialTheme = storedTheme;
    } else if (prefersDark) {
        initialTheme = 'dark';
    }

    applyTheme(initialTheme);

    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const isDark = document.body.classList.contains('dark-mode');
            applyTheme(isDark ? 'light' : 'dark');
        });
    }
});
