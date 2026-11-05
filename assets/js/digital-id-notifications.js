(() => {
    const panel = document.querySelector('[data-digital-id-notification]');
    if (!panel) return;
    const token = panel.dataset.trackingToken;
    panel.querySelector('a').addEventListener('click', event => {
        // A same-page fragment alone cannot load a card issued after page load.
        if (document.getElementById('digital-id')) return;
        event.preventDefault();
        const target = new URL(event.currentTarget.href);
        target.searchParams.set('notice_updated', String(Date.now()));
        window.location.assign(target.href);
    });
    let busy = false;
    async function refresh() {
        if (busy || document.hidden) return;
        busy = true;
        try {
            const response = await fetch('../api/tracker_notifications.php?' + new URLSearchParams({ token }), {
                cache: 'no-store', credentials: 'same-origin', referrerPolicy: 'no-referrer'
            });
            if (!response.ok) return;
            const data = await response.json();
            if (!data.success) return;
            panel.hidden = !data.notification;
            const card = document.getElementById('digital-id');
            if (card) card.hidden = !data.notification;
            if (!data.notification) return;
            panel.querySelector('[data-notice-title]').textContent = data.notification.title;
            panel.querySelector('[data-notice-message]').textContent = data.notification.message;
            panel.querySelector('[data-notice-date]').textContent = 'Notification recorded: ' + data.notification.created_at;
        } catch (_) { /* Keep the tracker usable if notification polling fails. */ }
        finally { busy = false; }
    }
    refresh();
    setInterval(refresh, 20000);
    document.addEventListener('visibilitychange', refresh);
})();
