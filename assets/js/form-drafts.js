(function () {
    'use strict';

    const DB_NAME = 'seniorlink-form-drafts';
    const STORE_NAME = 'drafts';
    const DB_VERSION = 1;
    const FORM_IDS = ['newSeniorForm', 'existingPensionForm'];
    let saveTimer = null;

    function openDatabase() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(DB_NAME, DB_VERSION);
            request.onupgradeneeded = () => {
                if (!request.result.objectStoreNames.contains(STORE_NAME)) {
                    request.result.createObjectStore(STORE_NAME, { keyPath: 'formId' });
                }
            };
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async function useStore(mode, operation) {
        const database = await openDatabase();
        return new Promise((resolve, reject) => {
            const transaction = database.transaction(STORE_NAME, mode);
            const request = operation(transaction.objectStore(STORE_NAME));
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
            transaction.oncomplete = () => database.close();
        });
    }

    const getDraft = formId => useStore('readonly', store => store.get(formId));
    const putDraft = draft => useStore('readwrite', store => store.put(draft));
    const deleteDraft = formId => useStore('readwrite', store => store.delete(formId));

    function draftStatus(form) {
        return form.parentElement?.querySelector(`[data-draft-status="${form.id}"]`);
    }

    function setStatus(form, message, isError) {
        const status = draftStatus(form);
        if (!status) return;
        status.textContent = message;
        status.style.color = isError ? '#b91c1c' : '#475569';
    }

    function addToolbar(form) {
        const toolbar = document.createElement('div');
        toolbar.className = 'form-draft-toolbar';
        toolbar.style.cssText = 'display:flex;align-items:center;justify-content:space-between;gap:12px;margin:0 0 18px;padding:10px 12px;border:1px solid #bfdbfe;border-radius:9px;background:#eff6ff;font-size:.78rem;';
        const status = document.createElement('span');
        status.dataset.draftStatus = form.id;
        status.style.color = '#475569';
        status.textContent = 'Your progress is saved only in this browser.';
        const clearButton = document.createElement('button');
        clearButton.type = 'button';
        clearButton.textContent = 'Clear saved draft';
        clearButton.style.cssText = 'padding:6px 9px;border:1px solid #93c5fd;border-radius:7px;background:#fff;color:#1d4ed8;font-weight:700;cursor:pointer;white-space:nowrap;';
        clearButton.addEventListener('click', async () => {
            await deleteDraft(form.id);
            form.reset();
            form.querySelectorAll('input[type="file"]').forEach(input => input.dispatchEvent(new Event('change', { bubbles: true })));
            if (form.id === 'newSeniorForm' && typeof window.changePublicBenefit === 'function') window.changePublicBenefit();
            setStatus(form, 'Saved draft cleared.', false);
        });
        toolbar.append(status, clearButton);
        form.prepend(toolbar);
    }

    async function serializeForm(form) {
        const fields = {};
        const files = {};
        for (const control of form.elements) {
            if (!control.name || control.type === 'submit' || control.name === 'proxy_submit') continue;
            if (control.type === 'file') {
                if (control.files?.[0]) {
                    const file = control.files[0];
                    files[control.name] = { name: file.name, type: file.type, lastModified: file.lastModified, blob: file };
                }
                continue;
            }
            if (control.type === 'radio') {
                if (control.checked) fields[control.name] = control.value;
            } else if (control.type === 'checkbox') {
                fields[control.name] = control.checked;
            } else {
                fields[control.name] = control.value;
            }
        }
        return { formId: form.id, fields, files, savedAt: Date.now() };
    }

    async function saveForm(form) {
        try {
            await putDraft(await serializeForm(form));
            setStatus(form, `Progress saved at ${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}.`, false);
        } catch (error) {
            console.warn('Form draft could not be saved.', error);
            setStatus(form, 'This browser could not save the draft. Keep this page open.', true);
        }
    }

    function scheduleSave(form) {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(() => saveForm(form), 250);
    }

    async function restoreForm(form, draft) {
        if (!draft) return;
        for (const [name, value] of Object.entries(draft.fields || {})) {
            const controls = form.querySelectorAll(`[name="${CSS.escape(name)}"]`);
            controls.forEach(control => {
                if (control.type === 'radio') control.checked = control.value === value;
                else if (control.type === 'checkbox') control.checked = Boolean(value);
                else control.value = value;
            });
        }

        const requestedBenefit = form.querySelector('[name="requestedBenefit"]')?.value;
        if (form.id === 'newSeniorForm' && requestedBenefit && typeof window.selectRequestedBenefit === 'function') {
            const card = document.querySelector(`[data-benefit-value="${CSS.escape(requestedBenefit)}"]`);
            window.selectRequestedBenefit(requestedBenefit, card);
        }

        for (const [name, stored] of Object.entries(draft.files || {})) {
            const input = form.querySelector(`input[type="file"][name="${CSS.escape(name)}"]`);
            if (!input || input.disabled || !stored?.blob) continue;
            try {
                const transfer = new DataTransfer();
                transfer.items.add(new File([stored.blob], stored.name, { type: stored.type, lastModified: stored.lastModified }));
                input.files = transfer.files;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            } catch (error) {
                console.warn(`Could not restore ${stored.name}.`, error);
            }
        }

        form.querySelectorAll('input:not([type="file"]), select, textarea').forEach(control => {
            control.dispatchEvent(new Event('change', { bubbles: true }));
        });
        setStatus(form, `Draft restored from ${new Date(draft.savedAt).toLocaleString()}.`, false);
    }

    async function initialize(options) {
        if (!('indexedDB' in window)) return;
        if (options.clearOnSuccess) {
            await Promise.all(FORM_IDS.map(deleteDraft));
            return;
        }

        const forms = FORM_IDS.map(id => document.getElementById(id)).filter(Boolean);
        const drafts = await Promise.all(forms.map(form => getDraft(form.id)));
        const newestDraft = drafts.filter(Boolean).sort((a, b) => b.savedAt - a.savedAt)[0];
        if (newestDraft && typeof window.selectPortalPath === 'function') {
            window.selectPortalPath(newestDraft.formId === 'existingPensionForm' ? 'existing_benefits' : 'new_senior');
        }

        forms.forEach((form, index) => {
            addToolbar(form);
            restoreForm(form, drafts[index]);
            form.addEventListener('input', () => scheduleSave(form));
            form.addEventListener('change', () => scheduleSave(form));
        });
    }

    window.SeniorlinkFormDrafts = { initialize };
})();
