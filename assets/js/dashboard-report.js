document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.querySelector('#dashboardReportDialog');
    const trigger = document.querySelector('[data-dashboard-report-open]');
    const form = dialog?.querySelector('form');
    if (!dialog || !trigger || !form) return;
    const message = dialog.querySelector('#dashboardReportMessage');
    trigger.addEventListener('click', () => {
        message.hidden = true;
        dialog.showModal();
        document.body.classList.add('dashboard-report-open');
    });
    dialog.querySelectorAll('[data-dashboard-report-close]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('close', () => { document.body.classList.remove('dashboard-report-open'); trigger.focus(); });
    dialog.addEventListener('click', event => {
        const bounds = dialog.getBoundingClientRect();
        if (event.target === dialog && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)) dialog.close();
    });
    form.addEventListener('submit', async event => {
        if (event.defaultPrevented) return;
        event.preventDefault();
        const button = form.querySelector('[type="submit"]');
        if (button.disabled || !form.reportValidity()) return;
        button.disabled = true;
        form.setAttribute('aria-busy', 'true');
        message.hidden = false;
        message.textContent = 'Preparing your report…';
        const params = new URLSearchParams(new FormData(form));
        try {
            const response = await fetch(form.action + '?' + params, { credentials: 'same-origin', cache: 'no-store' });
            const type = response.headers.get('Content-Type') || '';
            if (type.includes('application/json')) { const result = await response.json(); throw new Error(result.message || 'Unable to generate this report.'); }
            if (!response.ok) throw new Error(response.status === 400 ? await response.text() : 'Unable to generate the report. Reload the page and try again.');
            if (!type.includes('application/pdf') && !type.includes('spreadsheetml.sheet')) throw new Error('Please sign in again before exporting the report.');
            const url = URL.createObjectURL(await response.blob());
            const link = document.createElement('a');
            link.href = url;
            link.download = response.headers.get('Content-Disposition')?.match(/filename="([^"]+)"/)?.[1] || ('dashboard_summary.' + (params.get('format') === 'pdf' ? 'pdf' : 'xlsx'));
            document.body.append(link); link.click(); link.remove();
            setTimeout(() => URL.revokeObjectURL(url), 30000);
            message.textContent = 'Your report is ready. Check your downloads.';
        } catch (error) { message.textContent = error.message; }
        finally { button.disabled = false; form.removeAttribute('aria-busy'); }
    });
});
