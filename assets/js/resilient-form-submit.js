(function () {
    'use strict';

    function attach(form) {
        const submit = form.querySelector('button[type="submit"]');
        if (!submit) return;
        let busy = false;

        function errorBeside(control, message) {
            const target = control || submit;
            const previous = form.querySelector('[data-submission-error]');
            previous?.remove();
            const error = document.createElement('p');
            error.dataset.submissionError = 'true';
            error.id = `${form.id}-submission-error`;
            error.setAttribute('role', 'alert');
            error.textContent = message;
            error.style.cssText = 'margin:8px 0;color:#b91c1c;font-size:.9rem;font-weight:600;line-height:1.45;';
            target.insertAdjacentElement('afterend', error);
            if (control) {
                control.setAttribute('aria-invalid', 'true');
                control.setAttribute('aria-describedby', error.id);
            }
            error.scrollIntoView({ block: 'nearest' });
        }

        function clearError() {
            form.querySelector('[data-submission-error]')?.remove();
            form.querySelectorAll('[aria-describedby$="-submission-error"]').forEach(control => {
                control.removeAttribute('aria-describedby');
                control.removeAttribute('aria-invalid');
            });
        }

        form.addEventListener('input', clearError);
        form.addEventListener('change', clearError);
        form.addEventListener('submit', event => {
            if (busy) { event.preventDefault(); return; }
            // The form-specific validators were registered before this handler.
            // Cancel navigation synchronously so a lost connection keeps inputs.
            if (event.defaultPrevented) return;
            event.preventDefault();
            (async () => {
                if (!form.checkValidity()) {
                    const invalid = form.querySelector(':invalid');
                    if (invalid) errorBeside(invalid, invalid.validationMessage);
                    return;
                }
                clearError();
                // Capture successful controls before disabling the clicked button.
                const payload = new FormData(form);
                const submitter = event.submitter || submit;
                if (submitter.name) payload.append(submitter.name, submitter.value);
                busy = true;
                submit.disabled = true;
                const originalText = submit.innerHTML;
                submit.textContent = 'Submitting… please wait';
                const files = [...form.querySelectorAll('input[type="file"]')].filter(input => input.files?.length);
                try {
                    const response = await fetch(form.action, {
                        method: 'POST', body: payload, credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'SeniorlinkForm' },
                    });
                    if ((response.headers.get('content-type') || '').includes('application/json')) {
                        const result = await response.json();
                        errorBeside(submit, result.message || 'Submission could not be processed. Please try again.');
                        return;
                    }
                    const html = await response.text();
                    const page = new DOMParser().parseFromString(html, 'text/html');
                    if (!response.ok) {
                        errorBeside(files[0] || submit, response.status === 413
                            ? 'The upload is too large. Choose smaller files and try again.'
                            : `Submission failed (HTTP ${response.status}). Your entries are still here; please try again.`);
                        return;
                    }
                    const expired = /(?:^|\/)index\.php(?:[?#]|$)/.test(new URL(response.url).pathname)
                        || (form.id === 'mainAppForm' && !page.querySelector('#mainAppForm') && !response.url.includes('submit_application.php'));
                    if (expired) {
                        errorBeside(submit, 'Your session expired. Your entries are still here. Sign in again in a new tab, then submit once more.');
                        return;
                    }
                    if (response.url.includes('submit_application.php?success=1')
                        || (form.id === 'newSeniorForm' && !page.querySelector('#newSeniorForm') && !page.querySelector('.alert-banner-error'))) {
                        if (form.id === 'newSeniorForm') {
                            try { await window.SeniorlinkFormDrafts?.clear?.(form.id); } catch (error) { /* The server accepted the form even if local storage is unavailable. */ }
                            document.open(); document.write(html); document.close();
                        } else {
                            window.location.assign(response.url);
                        }
                        return;
                    }
                    const serverError = [...page.querySelectorAll('.alert-banner-error, .alert-error')]
                        .filter(alert => !alert.closest('[hidden]'))
                        .map(alert => alert.textContent.trim()).find(Boolean);
                    const message = serverError || 'The application was not accepted. Review the required information and try again.';
                    // A general server error does not identify a particular file input.
                    errorBeside(submit, message);
                } catch (error) {
                    errorBeside(files[0] || submit, files.length
                        ? 'The upload was interrupted. Your entries and selected files are still here. Check your connection and try again.'
                        : 'The connection was interrupted. Your entries are still here. Check your connection and try again.');
                } finally {
                    busy = false;
                    submit.disabled = false;
                    submit.innerHTML = originalText;
                }
            })();
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        ['newSeniorForm', 'mainAppForm'].forEach(id => {
            const form = document.getElementById(id);
            if (form) attach(form);
        });
    });
})();
