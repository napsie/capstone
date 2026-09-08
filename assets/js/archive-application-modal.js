(function () {
    'use strict';

    const modal = document.getElementById('archiveApplicationModal');
    const title = document.getElementById('archiveApplicationModalTitle');
    const body = document.getElementById('archiveApplicationModalBody');
    const closeButton = document.getElementById('closeArchiveApplicationModal');
    if (!modal || !title || !body || !closeButton) return;

    let previouslyFocused = null;

    function closeModal() {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('archive-modal-open');
        if (previouslyFocused) previouslyFocused.focus();
    }

    function openModal(applicationId, applicationName) {
        previouslyFocused = document.activeElement;
        title.textContent = applicationName || 'Archived Application Details';
        body.innerHTML = '<div class="archive-detail-loading"><i class="fas fa-spinner fa-spin"></i> Loading application details...</div>';
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('archive-modal-open');
        closeButton.focus();

        window.loadApplicationModalData(applicationId, 'archiveApplicationModal', () => openModal(applicationId, applicationName))
            .then(application => {
                if (!application) return;
                title.textContent = `${application.full_name} - Archived Application`;
                body.innerHTML = typeof window.renderApplicationRecordDetails === 'function'
                    ? window.renderArchivedApplicationDetails(application)
                    : '<div class="archive-detail-error">Application details renderer is unavailable.</div>';
                const documents = document.createElement('div');
                documents.innerHTML = window.renderApplicationDocuments(application, applicationId);
                body.append(documents);
                const heading = document.createElement('h3');
                heading.textContent = 'Application History';
                const history = document.createElement('div');
                body.append(heading, history);
                window.renderApplicationAuditHistory(application.history, history, { pageSize: 5 });
            })
            .catch(error => {
                window.showApplicationModalError('archiveApplicationModal', error);
            });
    }

    document.addEventListener('click', event => {
        const row = event.target.closest('.archive-application-row[data-id]');
        if (row && !event.target.closest('button, a, input, select, textarea, form')) {
            openModal(row.dataset.id, row.dataset.name);
        }
    });

    document.addEventListener('keydown', event => {
        const row = event.target.closest('.archive-application-row[data-id]');
        if (row && event.target === row && (event.key === 'Enter' || event.key === ' ')) {
            event.preventDefault();
            openModal(row.dataset.id, row.dataset.name);
        }
    });

    closeButton.addEventListener('click', closeModal);
    modal.addEventListener('click', event => {
        if (event.target === modal) closeModal();
    });
    document.addEventListener('keydown', event => {
        if (!modal.classList.contains('open')) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            closeModal();
            return;
        }
        if (event.key !== 'Tab') return;

        const focusable = [...modal.querySelectorAll('button:not([disabled]), a[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')]
            .filter(element => element.offsetParent !== null);
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
})();
