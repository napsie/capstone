document.addEventListener('DOMContentLoaded', () => {
    const button = document.getElementById('legalQuickButton');
    const drawer = document.getElementById('legalQuickDrawer');
    const backdrop = document.getElementById('legalQuickBackdrop');
    const closeButton = document.getElementById('legalQuickClose');
    const search = document.getElementById('legalQuickSearch');
    const topics = Array.from(document.querySelectorAll('.legal-quick-topic'));
    const empty = document.getElementById('legalQuickEmpty');
    const notificationButton = document.getElementById('notificationQuickButton');
    const notificationPanel = document.getElementById('notificationQuickPanel');
    const notificationClose = document.getElementById('notificationQuickClose');
    const notificationList = document.getElementById('notificationQuickList');
    const notificationBadge = document.getElementById('notificationQuickBadge');
    const quickActions = document.querySelector('.legal-quick-actions');
    const quickActionsToggle = document.getElementById('quickActionsToggle');
    const quickActionChoices = Array.from(document.querySelectorAll('[data-quick-action-choice]'));
    if (!button || !drawer || !backdrop || !closeButton) return;

    let previouslyFocused = null;
    let currentNotificationSignatures = [];
    const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), summary, [tabindex]:not([tabindex="-1"])';

    const openDrawer = () => {
        closeNotificationPanel();
        setQuickActionsOpen(false);
        previouslyFocused = document.activeElement;
        backdrop.hidden = false;
        drawer.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
        button.setAttribute('aria-expanded', 'true');
        document.body.classList.add('legal-quick-open');
        window.requestAnimationFrame(() => search?.focus());
    };

    const closeDrawer = () => {
        drawer.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
        button.setAttribute('aria-expanded', 'false');
        backdrop.hidden = true;
        document.body.classList.remove('legal-quick-open');
        if (previouslyFocused instanceof HTMLElement) previouslyFocused.focus();
    };

    button.addEventListener('click', openDrawer);
    closeButton.addEventListener('click', closeDrawer);
    backdrop.addEventListener('click', closeDrawer);

    const closeNotificationPanel = () => {
        notificationPanel?.classList.remove('is-open');
        notificationPanel?.setAttribute('aria-hidden', 'true');
        notificationButton?.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('notification-quick-open');
    };

    const setQuickActionsOpen = open => {
        quickActions?.classList.toggle('is-open', open);
        quickActionsToggle?.setAttribute('aria-expanded', String(open));
        quickActionsToggle?.setAttribute('aria-label', open ? 'Close quick actions' : 'Open quick actions');
        if (quickActionsToggle) quickActionsToggle.title = open ? 'Close quick actions' : 'Quick actions';
        quickActionChoices.forEach(choice => choice.setAttribute('tabindex', open ? '0' : '-1'));
    };

    quickActionsToggle?.addEventListener('click', () => {
        setQuickActionsOpen(!quickActions?.classList.contains('is-open'));
    });

    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, character => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
    })[character]);

    const formatNotificationDate = value => {
        const date = new Date(String(value || '').replace(' ', 'T'));
        return Number.isNaN(date.getTime()) ? '' : date.toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' });
    };

    const getRecentNotifications = notifications => {
        if (!Array.isArray(notifications)) return [];
        return notifications.slice(0, 8);
    };

    const notificationStorageKey = `seniorlink-quick-notifications-${notificationPanel?.dataset.role || 'user'}`;

    const markNotificationsSeen = () => {
        if (!currentNotificationSignatures.length) return;
        sessionStorage.setItem(notificationStorageKey, JSON.stringify(currentNotificationSignatures));
        if (notificationBadge) notificationBadge.hidden = true;
    };

    const readSeenNotifications = () => {
        const stored = sessionStorage.getItem(notificationStorageKey) || '';
        if (!stored) return [];
        try {
            const parsed = JSON.parse(stored);
            return Array.isArray(parsed) ? parsed : [];
        } catch {
            // Migrate the original pipe-delimited signature format.
            return stored.split('|').filter(Boolean);
        }
    };

    const renderQuickNotifications = notifications => {
        if (!notificationList || !notificationBadge) return;
        const recent = getRecentNotifications(notifications);
        currentNotificationSignatures = recent.map(item => `${item.id || item.id_number || ''}:${item.workflow_state || item.status || ''}:${item.date_submitted || ''}`);
        const seenNotifications = new Set(readSeenNotifications());
        const unreadCount = currentNotificationSignatures.filter(signature => !seenNotifications.has(signature)).length;
        notificationBadge.textContent = String(unreadCount);
        notificationBadge.setAttribute('aria-label', `${unreadCount} new update${unreadCount === 1 ? '' : 's'}`);
        notificationBadge.hidden = unreadCount === 0 || notificationPanel?.classList.contains('is-open');
        if (!recent.length) {
            notificationList.innerHTML = '<div class="notification-quick-state"><i class="fas fa-circle-check" aria-hidden="true"></i><strong>No recent updates</strong><br>There are no recent applications to display.</div>';
            return;
        }
        const typeLabels = { senior: 'Senior ID', pension: 'Local Pension', national_pension: 'DSWD Pension', milestone_gift: 'Milestone Gift', landbank: 'Land Bank', home_visit: 'Home Visit', burial: 'Burial Assistance' };
        notificationList.innerHTML = recent.map(item => {
            const rawStatus = item.workflow_state || item.status || 'Received';
            const status = ['Approved', 'Released'].includes(rawStatus) ? 'Verified' : rawStatus;
            const type = typeLabels[item.application_type] || item.application_type || 'Application';
            const priority = item.priority_level === 'high' ? '<span class="notification-quick-status notification-quick-priority">Priority</span>' : '';
            const applicantName = item.full_name || 'Application update';
            const isFinalized = ['Verified', 'Approved', 'Released'].includes(rawStatus);
            const isBarangay = notificationPanel?.dataset.role === 'barangay_staff';
            const destination = isFinalized
                ? (isBarangay ? 'barangay_records.php' : 'department_records.php')
                : (isBarangay ? 'submit_application.php' : 'verify_document.php');
            const applicantUrl = `${destination}?search=${encodeURIComponent(applicantName)}`;
            return `<article class="notification-quick-item">
                <span class="notification-quick-icon" aria-hidden="true"><i class="fas fa-file-circle-check"></i></span>
                <div class="notification-quick-copy">
                    <div class="notification-quick-name"><a href="${applicantUrl}" title="Show ${escapeHtml(applicantName)}">${escapeHtml(applicantName)}</a></div>
                    <div class="notification-quick-meta">${escapeHtml(type)}${item.barangay ? ` · ${escapeHtml(item.barangay)}` : ''}<br>${escapeHtml(formatNotificationDate(item.date_submitted))}</div>
                    <span class="notification-quick-status">${escapeHtml(status)}</span>${priority}
                </div>
            </article>`;
        }).join('');
    };

    const loadQuickNotifications = async () => {
        if (!notificationPanel?.dataset.endpoint) return;
        try {
            const response = await fetch(notificationPanel.dataset.endpoint, { cache: 'no-store', headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error(`Request failed (${response.status})`);
            const result = await response.json();
            renderQuickNotifications(result?.data?.notifications || []);
        } catch (error) {
            currentNotificationSignatures = [];
            if (notificationBadge) notificationBadge.hidden = true;
            if (notificationList) notificationList.innerHTML = '<div class="notification-quick-state"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i>Notifications could not be loaded.</div>';
        }
    };

    notificationButton?.addEventListener('click', async () => {
        const willOpen = !notificationPanel?.classList.contains('is-open');
        closeNotificationPanel();
        if (!willOpen || !notificationPanel) return;
        if (notificationList) notificationList.innerHTML = '<div class="notification-quick-state"><i class="fas fa-spinner fa-spin" aria-hidden="true"></i>Loading active updates…</div>';
        notificationPanel.classList.add('is-open');
        notificationPanel.setAttribute('aria-hidden', 'false');
        notificationButton.setAttribute('aria-expanded', 'true');
        document.body.classList.add('notification-quick-open');
        setQuickActionsOpen(false);
        await loadQuickNotifications();
        markNotificationsSeen();
    });
    notificationClose?.addEventListener('click', () => {
        closeNotificationPanel();
        notificationButton?.focus();
    });
    document.addEventListener('click', event => {
        if (notificationPanel?.classList.contains('is-open') &&
            !notificationPanel.contains(event.target) && !notificationButton?.contains(event.target)) {
            closeNotificationPanel();
        }
        if (quickActions?.classList.contains('is-open') && !quickActions.contains(event.target)) setQuickActionsOpen(false);
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && notificationPanel?.classList.contains('is-open')) {
            closeNotificationPanel();
            notificationButton?.focus();
        }
        if (event.key === 'Escape' && quickActions?.classList.contains('is-open')) {
            setQuickActionsOpen(false);
            quickActionsToggle?.focus();
        }
    });
    loadQuickNotifications();
    window.setInterval(loadQuickNotifications, 15000);

    topics.forEach(topic => {
        topic.addEventListener('toggle', () => {
            if (!topic.open) return;
            topics.forEach(otherTopic => {
                if (otherTopic !== topic) otherTopic.open = false;
            });
        });
    });

    search?.addEventListener('input', () => {
        const query = search.value.trim().toLocaleLowerCase();
        let visibleCount = 0;
        topics.forEach(topic => {
            const haystack = `${topic.dataset.search || ''} ${topic.textContent || ''}`.toLocaleLowerCase();
            const matches = query === '' || haystack.includes(query);
            topic.hidden = !matches;
            if (matches) visibleCount += 1;
        });
        if (empty) empty.hidden = visibleCount !== 0;
    });

    drawer.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            closeDrawer();
            return;
        }
        if (event.key !== 'Tab') return;
        const focusable = Array.from(drawer.querySelectorAll(focusableSelector)).filter(element => !element.hidden && element.offsetParent !== null);
        if (!focusable.length) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });
});
