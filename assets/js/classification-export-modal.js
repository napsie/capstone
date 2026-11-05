(() => {
    const dialog = document.querySelector('#classification-export');
    const trigger = document.querySelector('#classification-export-open');
    const form = document.querySelector('#classification-export-form');
    if (!dialog || !trigger || !form) return;
    const message = document.querySelector('#classification-export-message');
    const barangay = document.querySelector('#classification-export-barangay');
    const scope = () => {
        if (!barangay) return;
        const all = form.querySelector('[name="coverage"]:checked')?.value === 'all';
        barangay.disabled = all;
        document.querySelector('#classification-export-barangay-label').hidden = all;
    };
    form.querySelectorAll('[name="coverage"]').forEach(input => input.addEventListener('change', scope));
    scope();
    trigger.addEventListener('click', () => {
        message.hidden = true;
        dialog.showModal();
        document.body.classList.add('classification-dialog-open');
    });
    const close = () => dialog.close();
    document.querySelector('#classification-export-close').addEventListener('click', close);
    document.querySelector('#classification-export-cancel').addEventListener('click', close);
    dialog.addEventListener('close', () => {
        document.body.classList.remove('classification-dialog-open');
        trigger.focus();
    });
    dialog.addEventListener('click', event => {
        const bounds = dialog.getBoundingClientRect();
        if (event.target === dialog && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)) close();
    });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        const button = form.querySelector('[type="submit"]');
        if (button.disabled) return;
        const params = new URLSearchParams(new FormData(form));
        if (params.get('coverage') === 'all') params.set('barangay', 'all');
        params.delete('coverage');
        button.disabled = true;
        form.setAttribute('aria-busy', 'true');
        message.hidden = false;
        message.textContent = 'Preparing your report…';
        try {
            const response = await fetch(form.action + '?' + params, { credentials: 'same-origin', cache: 'no-store' });
            if (!response.ok) throw new Error(response.status === 400 ? await response.text() : 'Unable to generate the report. Reload the page and try again.');
            const type = response.headers.get('Content-Type') || '';
            if (!type.includes('application/pdf') && !type.includes('spreadsheetml.sheet')) throw new Error('Please sign in again before exporting the report.');
            const blob = await response.blob();
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'senior_classifications.' + params.get('format');
            document.body.append(link);
            link.click();
            link.remove();
            setTimeout(() => URL.revokeObjectURL(url), 30000);
            message.textContent = 'Your report is ready. Check your downloads.';
        } catch (error) {
            message.textContent = error.message;
        } finally {
            button.disabled = false;
            form.removeAttribute('aria-busy');
        }
    });
})();
