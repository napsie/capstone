document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.getElementById('deleteAnnouncementDialog');
    const idInput = document.getElementById('deleteAnnouncementId');
    const nameOutput = document.getElementById('deleteAnnouncementName');
    if (!dialog || !idInput || !nameOutput) return;

    let returnFocus = null;
    document.querySelectorAll('.delete-announcement').forEach(button => {
        const sourceForm = button.closest('form');
        if (!sourceForm) return;
        sourceForm.onsubmit = null;
        sourceForm.addEventListener('submit', event => {
            event.preventDefault();
            returnFocus = button;
            idInput.value = sourceForm.querySelector('input[name="id"]')?.value || '';
            nameOutput.textContent = button.closest('.item')?.querySelector('h3')?.textContent?.trim() || 'Selected announcement';
            dialog.showModal();
            dialog.querySelector('[data-delete-cancel]')?.focus();
        });
    });

    const close = () => dialog.close();
    dialog.querySelectorAll('[data-delete-cancel]').forEach(button => button.addEventListener('click', close));
    dialog.addEventListener('click', event => { if (event.target === dialog) close(); });
    dialog.addEventListener('close', () => returnFocus?.focus());
});
