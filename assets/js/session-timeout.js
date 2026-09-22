(() => {
    const idleLimitMs = 5 * 60 * 1000;
    let deadline = Date.now() + idleLimitMs;
    let timer = null;
    let lastServerTouch = 0;

    const expire = () => {
        if (window.__seniorlinkSessionExpiring) return;
        window.__seniorlinkSessionExpiring = true;
        window.location.replace('../index.php?session=expired');
    };
    const schedule = () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(expire, Math.max(0, deadline - Date.now()));
    };
    const recordActivity = () => {
        deadline = Date.now() + idleLimitMs;
        schedule();
        // Keep the server timestamp in step with real user activity, without
        // sending one request for every keystroke or pointer movement.
        if (Date.now() - lastServerTouch >= 30 * 1000) {
            lastServerTouch = Date.now();
            fetch('../api/session_touch.php', { method: 'POST', credentials: 'same-origin' })
                .then(response => { if (response.status === 401) expire(); })
                .catch(() => {});
        }
    };

    // Only genuine user interaction extends the visible session. Background
    // dashboard fetches must never keep an inactive account signed in.
    ['pointerdown', 'keydown', 'touchstart', 'scroll'].forEach(eventName => {
        window.addEventListener(eventName, recordActivity, { passive: true });
    });
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && Date.now() >= deadline) expire();
    });
    // The interval is a safeguard for browser timer throttling: if the page
    // was sleeping in the background, it expires as soon as it becomes active.
    window.setInterval(() => {
        if (Date.now() >= deadline) expire();
    }, 1000);
    schedule();
})();
