(function () {
    'use strict';
    const requests = new Map();
    const style = document.createElement('style');
    style.textContent = '.application-data-pending .modal-scroller > :not(.application-data-status),.application-data-pending .archive-detail-body > :not(.application-data-status){display:none!important}.application-data-status{padding:28px 16px;line-height:1.6;color:#475569}.application-data-status button{display:block;margin-top:12px;padding:9px 16px;border:0;border-radius:8px;background:#1d4ed8;color:white;font:inherit;cursor:pointer}';
    document.head.append(style);

    function statusElement(modal) {
        const container = modal.querySelector('.modal-scroller, .archive-detail-body');
        let status = container.querySelector('.application-data-status');
        if (!status) {
            status = document.createElement('div');
            status.className = 'application-data-status';
            container.prepend(status);
        }
        return status;
    }

    window.showApplicationModalError = function (modalId, error) {
        const modal = document.getElementById(modalId);
        const request = requests.get(modalId);
        if (!modal || !request || request.controller.signal.aborted) return;
        modal.classList.add('application-data-pending');
        modal.setAttribute('aria-busy', 'false');
        const title = modal.querySelector('#modalAppTitle, #archiveApplicationModalTitle');
        if (title) title.textContent = 'Unable to load application';
        const status = statusElement(modal);
        status.hidden = false;
        status.setAttribute('role', 'alert');
        status.replaceChildren(document.createTextNode(error.message || 'Unable to load application details. Please try again.'));
        const retry = document.createElement('button');
        retry.type = 'button';
        retry.textContent = 'Try again';
        retry.addEventListener('click', request.retry);
        status.append(retry);
    };

    window.loadApplicationModalData = async function (applicationId, modalId = 'applicationModal', retry) {
        const modal = document.getElementById(modalId);
        const previous = requests.get(modalId);
        previous?.controller.abort();
        previous?.observer.disconnect();
        const controller = new AbortController();
        const request = { controller, retry: retry || (() => window.openApplicationModal(applicationId)) };
        requests.set(modalId, request);
        request.observer = new MutationObserver(() => {
            if (getComputedStyle(modal).display === 'none' || modal.getAttribute('aria-hidden') === 'true') controller.abort();
        });
        request.observer.observe(modal, { attributes: true, attributeFilter: ['style', 'class', 'aria-hidden'] });
        modal.classList.add('application-data-pending');
        modal.setAttribute('aria-busy', 'true');
        const title = modal.querySelector('#modalAppTitle, #archiveApplicationModalTitle');
        if (title) title.textContent = 'Loading application…';
        const status = statusElement(modal);
        status.hidden = false;
        status.setAttribute('role', 'status');
        status.textContent = 'Loading applicant information, documents, and history…';
        let timedOut = false;
        const timeout = setTimeout(() => { timedOut = true; controller.abort(); }, 15000);
        try {
            const response = await fetch(`../api/get_application_details.php?id=${encodeURIComponent(applicationId)}`, {
                signal: controller.signal, cache: 'no-store', headers: { Accept: 'application/json' }
            });
            let application;
            try { application = await response.json(); }
            catch { throw new Error('The server returned an unreadable response. Please try again.'); }
            if (controller.signal.aborted || requests.get(modalId) !== request) return null;
            if (!response.ok || application?.error) {
                throw new Error(response.status === 401 ? 'Your session has expired. Please sign in again.'
                    : response.status === 403 || response.status === 404 ? 'Application not found or access denied.'
                    : 'Unable to load application details. Please try again.');
            }
            if (!application || application.id_number !== applicationId) throw new Error('The requested application could not be verified. Please try again.');
            modal.classList.remove('application-data-pending');
            modal.setAttribute('aria-busy', 'false');
            status.hidden = true;
            return application;
        } catch (error) {
            if (requests.get(modalId) !== request || (controller.signal.aborted && !timedOut)) return null;
            // The timeout abort has ended; allow its retry message to be displayed.
            if (timedOut) request.controller = new AbortController();
            window.showApplicationModalError(modalId, timedOut
                ? new Error('Loading took too long. Check your connection and try again.') : error);
            return null;
        } finally {
            clearTimeout(timeout);
        }
    };
})();
