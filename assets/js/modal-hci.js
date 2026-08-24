(function () {
    'use strict';

    const modalSelector = [
        '.modal-overlay', '.modal', '.cl-modal', '.about-modal', '.archive-detail-overlay',
        '#carelinkResultModal', '#carelinkConfirmModal', '#appResultModal'
    ].join(',');
    const closeSelector = [
        '.modal-close', '.close-modal', '.close-btn', '.archive-detail-close',
        '.close', '.modal-cancel', '.btn-benefit-cancel', '.carelink-result-ok', '.carelink-confirm-cancel'
    ].join(',');
    const focusableSelector = [
        'a[href]', 'button:not([disabled])', 'input:not([disabled]):not([type="hidden"])',
        'select:not([disabled])', 'textarea:not([disabled])', '[tabindex]:not([tabindex="-1"])'
    ].join(',');

    function isVisible(element) {
        if (!element || element.getAttribute('aria-hidden') === 'true') return false;
        const style = window.getComputedStyle(element);
        return style.display !== 'none' && style.visibility !== 'hidden';
    }

    function activeModal() {
        return [...document.querySelectorAll(modalSelector)].reverse().find(isVisible) || null;
    }

    function focusableElements(modal) {
        return [...modal.querySelectorAll(focusableSelector)].filter(element => {
            const style = window.getComputedStyle(element);
            return style.display !== 'none' && style.visibility !== 'hidden';
        });
    }

    function ensureDialogSemantics(modal, index) {
        if (!modal.hasAttribute('role')) modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');

        if (!modal.hasAttribute('aria-labelledby') && !modal.hasAttribute('aria-label')) {
            const heading = modal.querySelector('h1, h2, h3');
            if (heading) {
                if (!heading.id) heading.id = `modal-title-${index + 1}`;
                modal.setAttribute('aria-labelledby', heading.id);
            }
        }
    }

    function syncBodyState() {
        document.body.classList.toggle('modal-hci-open', Boolean(activeModal()));
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll(modalSelector).forEach(ensureDialogSemantics);
        new MutationObserver(syncBodyState).observe(document.body, {
            attributes: true,
            attributeFilter: ['class', 'style', 'aria-hidden'],
            childList: true,
            subtree: true
        });
        syncBodyState();
    });

    document.addEventListener('keydown', function (event) {
        const modal = activeModal();
        if (!modal) return;

        if (event.key === 'Escape') {
            const closeControl = modal.querySelector(closeSelector);
            if (closeControl) {
                event.preventDefault();
                event.stopImmediatePropagation();
                closeControl.click();
            }
            return;
        }

        if (event.key !== 'Tab') return;
        const focusables = focusableElements(modal);
        if (!focusables.length) {
            event.preventDefault();
            modal.setAttribute('tabindex', '-1');
            modal.focus();
            return;
        }

        const first = focusables[0];
        const last = focusables[focusables.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }, true);
})();
