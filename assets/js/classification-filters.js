(() => {
    const form = document.querySelector('#classification-filters');
    if (!form) return;
    const search = form.querySelector('[name="search"]');
    let composing = false;
    let submitting = false;
    form.addEventListener('submit', event => {
        if (submitting || composing) {
            event.preventDefault();
            return;
        }
        submitting = true;
        form.setAttribute('aria-busy', 'true');
    });
    // Search applies only on Enter or Search, so typing never triggers navigation.
    form.querySelectorAll('select').forEach(select => select.addEventListener('change', () => {
        if (!submitting && !composing) form.requestSubmit();
    }));
    search?.addEventListener('compositionstart', () => { composing = true; });
    search?.addEventListener('compositionend', () => { composing = false; });
})();
