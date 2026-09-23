(() => {
    // A page can include more than one shared layout or an embedded view.
    // Keep exactly one idle timer running so an older duplicate cannot expire
    // an actively used session.
    if (window.__seniorlinkSessionTimeoutLoaded) return;
    window.__seniorlinkSessionTimeoutLoaded = true;

    const idleLimitMs = 5 * 60 * 1000;
    let deadline = Date.now() + idleLimitMs;
    let timer = null;
    let lastServerTouch = 0;
    let lastClientActivity = 0;

    const expire = () => {
        if (window.__seniorlinkSessionExpiring) return;
        window.__seniorlinkSessionExpiring = true;
        const dialog = document.createElement('div');
        dialog.id = 'sessionExpiredDialog';
        dialog.setAttribute('role', 'alertdialog');
        dialog.setAttribute('aria-modal', 'true');
        dialog.setAttribute('aria-labelledby', 'sessionExpiredTitle');
        dialog.style.cssText = 'position:fixed;inset:0;z-index:10000;display:grid;place-items:center;padding:20px;background:rgba(15,23,42,.62);';
        dialog.innerHTML = '<section style="width:min(410px,100%);padding:26px;border-radius:16px;background:#fff;box-shadow:0 24px 60px rgba(15,23,42,.32);font-family:Inter,Segoe UI,Arial,sans-serif;text-align:center"><span style="display:grid;place-items:center;width:48px;height:48px;margin:0 auto 13px;border-radius:50%;background:#fef3c7;color:#a16207;font-size:1.35rem">⏱</span><h2 id="sessionExpiredTitle" style="margin:0;color:#172033;font-size:1.15rem">Session expired</h2><p style="margin:10px 0 20px;color:#475569;line-height:1.5">You were signed out after 5 minutes of inactivity. Please sign in again to continue.</p><button type="button" data-session-signin style="width:100%;min-height:44px;border:0;border-radius:9px;background:#178b4b;color:#fff;font-weight:800;cursor:pointer">Sign in again</button></section>';
        document.body.appendChild(dialog);
        const signIn = () => window.location.replace('../index.php?session=expired');
        dialog.querySelector('[data-session-signin]').addEventListener('click', signIn);
        dialog.querySelector('[data-session-signin]').focus();
    };
    const schedule = () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(expire, Math.max(0, deadline - Date.now()));
    };
    const recordActivity = () => {
        const now = Date.now();
        // Pointer movement and typing can occur many times per second. One
        // activity update per second is enough to keep the deadline accurate
        // while avoiding unnecessary timer resets and server requests.
        if (now - lastClientActivity < 1000) return;
        lastClientActivity = now;
        deadline = now + idleLimitMs;
        schedule();
        // Keep the server timestamp in step with real user activity, without
        // sending one request for every keystroke or pointer movement.
        if (now - lastServerTouch >= 15 * 1000) {
            lastServerTouch = now;
            fetch('../api/session_touch.php', { method: 'POST', credentials: 'same-origin' })
                .then(response => { if (response.status === 401) expire(); })
                .catch(() => {});
        }
    };

    // Only genuine user interaction extends the visible session. Background
    // dashboard fetches must never keep an inactive account signed in.
    [
        'pointerdown', 'pointermove', 'keydown', 'input', 'change',
        'focusin', 'touchstart', 'touchmove', 'scroll'
    ].forEach(eventName => {
        window.addEventListener(eventName, recordActivity, { passive: true });
    });
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            if (Date.now() >= deadline) expire();
            else recordActivity();
        }
    });
    window.addEventListener('focus', recordActivity, { passive: true });

    let logoutTrigger = null;
    const closeLogoutDialog = () => {
        const dialog = document.getElementById('sessionLogoutDialog');
        if (!dialog) return;
        dialog.remove();
        logoutTrigger?.focus();
        logoutTrigger = null;
    };
    const showLogoutDialog = link => {
        if (document.getElementById('sessionLogoutDialog')) return;
        logoutTrigger = link;
        const dialog = document.createElement('div');
        dialog.id = 'sessionLogoutDialog';
        dialog.setAttribute('role', 'dialog');
        dialog.setAttribute('aria-modal', 'true');
        dialog.setAttribute('aria-labelledby', 'sessionLogoutTitle');
        dialog.style.cssText = 'position:fixed;inset:0;z-index:9999;display:grid;place-items:center;padding:20px;background:rgba(15,23,42,.58);';
        dialog.innerHTML = '<section style="width:min(420px,100%);padding:24px;border-radius:16px;background:#fff;box-shadow:0 24px 60px rgba(15,23,42,.3);font-family:Inter,Segoe UI,Arial,sans-serif"><div style="display:flex;align-items:center;gap:11px;color:#a16207"><span style="display:grid;place-items:center;width:40px;height:40px;border-radius:50%;background:#fef3c7">↪</span><h2 id="sessionLogoutTitle" style="margin:0;color:#172033;font-size:1.1rem">Log out?</h2></div><p style="margin:14px 0 20px;color:#475569;line-height:1.5">Are you sure you want to end your session? You will need to sign in again to continue.</p><div style="display:flex;justify-content:flex-end;gap:10px"><button type="button" data-logout-cancel style="min-height:40px;padding:8px 14px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#334155;font-weight:700;cursor:pointer">Cancel</button><button type="button" data-logout-confirm style="min-height:40px;padding:8px 14px;border:0;border-radius:8px;background:#b91c1c;color:#fff;font-weight:700;cursor:pointer">Yes, log out</button></div></section>';
        document.body.appendChild(dialog);
        dialog.querySelector('[data-logout-cancel]').addEventListener('click', closeLogoutDialog);
        dialog.querySelector('[data-logout-confirm]').addEventListener('click', () => { window.location.assign(link.href); });
        dialog.addEventListener('click', event => { if (event.target === dialog) closeLogoutDialog(); });
        dialog.addEventListener('keydown', event => { if (event.key === 'Escape') closeLogoutDialog(); });
        dialog.querySelector('[data-logout-cancel]').focus();
    };
    document.addEventListener('click', event => {
        const logoutLink = event.target.closest('a[data-logout-confirm]');
        if (!logoutLink) return;
        event.preventDefault();
        showLogoutDialog(logoutLink);
    });
    // The interval is a safeguard for browser timer throttling: if the page
    // was sleeping in the background, it expires as soon as it becomes active.
    window.setInterval(() => {
        if (Date.now() >= deadline) expire();
    }, 1000);
    schedule();
})();
