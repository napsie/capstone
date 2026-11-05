(() => {
    let opener = null;
    let pending = false;

    function openDialog(dialog) {
        if (!dialog || dialog.open) return;
        dialog.addEventListener('close', () => {
            document.body.classList.remove('classification-dialog-open');
            const url = new URL(location.href);
            if (url.searchParams.has('profile')) {
                url.searchParams.delete('profile');
                if (url.hash === '#classification-profile') url.hash = '';
                history.replaceState(null, '', url);
            }
            opener?.focus();
        }, { once: true });
        dialog.querySelector('[data-classification-close]').addEventListener('click', () => dialog.close());
        dialog.addEventListener('click', event => {
            const bounds = dialog.getBoundingClientRect();
            if (event.target === dialog && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)) dialog.close();
        });
        dialog.showModal();
        document.body.classList.add('classification-dialog-open');
    }

    document.addEventListener('click', async event => {
        const link = event.target.closest('[data-classification-manage]');
        if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        if (pending) return;
        pending = true;
        opener = link;
        link.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(link.href, { credentials: 'same-origin', cache: 'no-store' });
            if (!response.ok) throw new Error('Unable to load classification');
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const content = page.querySelector('#classification-profile');
            if (!content) throw new Error('Classification form unavailable');
            document.querySelector('#classification-profile')?.remove();
            const dialog = document.importNode(content, true);
            document.body.append(dialog);
            openDialog(dialog);
        } catch {
            location.assign(link.href);
        } finally {
            pending = false;
            link.removeAttribute('aria-busy');
        }
    });

    openDialog(document.querySelector('#classification-profile'));
})();
