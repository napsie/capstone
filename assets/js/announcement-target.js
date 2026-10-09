document.addEventListener('DOMContentLoaded', function () {
    const audience = document.getElementById('audience');
    const category = document.getElementById('category');
    const customTypeField = document.getElementById('customTypeField');
    const customTypeInput = document.getElementById('custom_type');
    const config = window.announcementTargetConfig || {};
    if (!audience || !Array.isArray(config.barangays)) return;

    if (category && !Array.from(category.options).some(function (item) { return item.value === 'other'; })) {
        category.appendChild(new Option('Other', 'other'));
        category.value = config.category || 'announcement';
    }

    const conciseAudienceLabels = {
        all: 'Everyone',
        public: 'Senior Portal Public',
        staff: 'All staff dashboards'
    };
    Array.from(audience.options).forEach(function (audienceOption) {
        if (conciseAudienceLabels[audienceOption.value]) {
            audienceOption.textContent = conciseAudienceLabels[audienceOption.value];
        }
    });

    const option = document.createElement('option');
    option.value = 'barangay';
    option.textContent = 'Specific barangay';
    audience.appendChild(option);

    const field = document.createElement('div');
    field.className = 'field';
    field.id = 'barangayTargetField';

    const label = document.createElement('label');
    label.htmlFor = 'target_barangay';
    label.textContent = 'Barangay';

    const select = document.createElement('select');
    select.id = 'target_barangay';
    select.name = 'target_barangay';
    select.appendChild(new Option('Select a barangay', ''));
    config.barangays.forEach(function (name) {
        select.appendChild(new Option(name, name));
    });

    const help = document.createElement('small');
    help.className = 'field-help';
    help.textContent = 'This update will appear only on the selected barangay dashboard.';
    field.append(label, select, help);
    audience.closest('.two').insertAdjacentElement('afterend', field);

    audience.value = config.audience || 'all';
    select.value = config.barangay || '';

    function syncTargetField() {
        const targeted = audience.value === 'barangay';
        field.hidden = !targeted;
        select.required = targeted;
    }

    function syncCustomTypeField() {
        if (!category || !customTypeField || !customTypeInput) return;
        const isOther = category.value === 'other';
        customTypeField.hidden = !isOther;
        customTypeInput.required = isOther;
        if (!isOther) customTypeInput.value = '';
    }

    audience.addEventListener('change', syncTargetField);
    category?.addEventListener('change', syncCustomTypeField);
    syncTargetField();
    syncCustomTypeField();

    setupPublishedUpdateModal();
});

function setupPublishedUpdateModal() {
    const updates = Array.from(document.querySelectorAll('.items .item'));
    const auditConfig = window.announcementAuditConfig || {};
    if (!updates.length) return;

    const modal = document.createElement('div');
    modal.className = 'announcement-modal';
    modal.hidden = true;
    modal.innerHTML =
        '<section class="announcement-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="announcementModalTitle">' +
            '<header class="announcement-modal__header">' +
                '<span class="badge announcement-modal__badge"></span>' +
                '<h2 id="announcementModalTitle"></h2>' +
                '<div class="announcement-modal__meta"></div>' +
                '<button class="announcement-modal__close" type="button" aria-label="Close announcement"><i class="fas fa-xmark" aria-hidden="true"></i></button>' +
            '</header>' +
            '<div class="announcement-modal__body"></div>' +
        '</section>';
    document.body.appendChild(modal);

    const title = modal.querySelector('h2');
    const badge = modal.querySelector('.announcement-modal__badge');
    const meta = modal.querySelector('.announcement-modal__meta');
    const body = modal.querySelector('.announcement-modal__body');
    const closeButton = modal.querySelector('.announcement-modal__close');
    let trigger = null;

    function closeModal() {
        modal.hidden = true;
        document.body.style.overflow = '';
        if (trigger) trigger.focus();
    }

    function openModal(update) {
        const sourceTitle = update.querySelector('h3');
        const sourceBadge = update.querySelector('.badge');
        const sourceBody = update.querySelector(':scope > p');
        const sourceMeta = update.querySelector(':scope > .meta');
        trigger = update;
        title.textContent = sourceTitle ? sourceTitle.textContent.trim() : 'Published update';
        badge.textContent = sourceBadge ? sourceBadge.textContent.trim() : 'Announcement';
        badge.className = 'badge announcement-modal__badge' + (sourceBadge && sourceBadge.classList.contains('benefit') ? ' benefit' : '');
        meta.textContent = sourceMeta ? sourceMeta.textContent.trim() : '';
        body.innerHTML = sourceBody ? sourceBody.innerHTML : '';
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        closeButton.focus();
    }

    updates.forEach(function (update) {
        const idInput = update.querySelector('input[name="id"]');
        const audit = idInput ? auditConfig[idInput.value] : null;
        const rowMeta = update.querySelector(':scope > .meta');
        if (audit && audit.author && rowMeta) {
            rowMeta.textContent += ' · Published by ' + audit.author;
        }
        update.tabIndex = 0;
        update.setAttribute('role', 'button');
        const updateTitle = update.querySelector('h3');
        update.setAttribute('aria-label', 'Open ' + (updateTitle ? updateTitle.textContent.trim() : 'published update'));
        update.addEventListener('click', function (event) {
            if (event.target.closest('button, form, a')) return;
            openModal(update);
        });
        update.addEventListener('keydown', function (event) {
            if (event.target.closest('button, form, a')) return;
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openModal(update);
            }
        });
    });

    closeButton.addEventListener('click', closeModal);
    modal.addEventListener('click', function (event) {
        if (event.target === modal) closeModal();
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !modal.hidden) closeModal();
    });
}
