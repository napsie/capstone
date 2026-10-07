document.addEventListener('DOMContentLoaded', () => {
    const panels = document.querySelector('.dashboard-panels');
    const graphColumn = panels?.querySelector('.left-panel');
    const notifications = panels?.querySelector('.right-panel .recent-apps-card');
    if (!panels || !graphColumn || !notifications) return;

    const desktop = window.matchMedia('(min-width: 992px)');
    const alignNotificationPanel = () => {
        if (!desktop.matches) {
            panels.style.removeProperty('--notification-panel-height');
            return;
        }
        const graphColumnHeight = Math.round(graphColumn.getBoundingClientRect().height);
        if (graphColumnHeight > 0) panels.style.setProperty('--notification-panel-height', `${graphColumnHeight}px`);
    };

    if ('ResizeObserver' in window) {
        const observer = new ResizeObserver(alignNotificationPanel);
        observer.observe(graphColumn);
    }
    desktop.addEventListener?.('change', alignNotificationPanel);
    window.addEventListener('resize', alignNotificationPanel, { passive: true });
    alignNotificationPanel();
});
