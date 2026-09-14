(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('mainAppForm');
        if (!form) return;

        const dialog = document.createElement('dialog');
        dialog.className = 'duplicate-review-dialog';
        dialog.innerHTML = `<section class="duplicate-review" aria-labelledby="duplicateReviewTitle">
            <header class="duplicate-review__header"><span class="duplicate-review__icon"><i class="fas fa-users-viewfinder" aria-hidden="true"></i></span><div><h2 id="duplicateReviewTitle">Possible beneficiary already exists</h2><p>Review these matches before creating another application. A valid repeat benefit application may continue with a reason.</p></div></header>
            <div class="duplicate-review__body"><div id="duplicateMatches"></div><label for="duplicateReason">Reason for creating another application</label><textarea id="duplicateReason" maxlength="500" placeholder="Example: Applicant is applying for a different benefit program."></textarea><p class="duplicate-review__error" id="duplicateReasonError" role="alert"></p></div>
            <footer class="duplicate-review__actions"><button class="duplicate-review__cancel" type="button">Review form</button><button class="duplicate-review__continue" type="button">Continue and record reason</button></footer>
        </section>`;
        document.body.appendChild(dialog);

        const checking = document.createElement('div');
        checking.className = 'duplicate-review__checking';
        checking.innerHTML = '<span><i class="fas fa-circle-notch fa-spin" aria-hidden="true"></i> Checking for existing beneficiaries…</span>';
        document.body.appendChild(checking);

        const matchesContainer = dialog.querySelector('#duplicateMatches');
        const reason = dialog.querySelector('#duplicateReason');
        const reasonError = dialog.querySelector('#duplicateReasonError');
        const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));

        dialog.querySelector('.duplicate-review__cancel').addEventListener('click', () => dialog.close());
        dialog.querySelector('.duplicate-review__continue').addEventListener('click', () => {
            const explanation = reason.value.trim();
            if (explanation.length < 10) {
                reasonError.textContent = 'Provide a clear reason using at least 10 characters.';
                reason.focus();
                return;
            }
            document.getElementById('duplicateOverride').value = '1';
            document.getElementById('duplicateOverrideReason').value = explanation;
            form.dataset.duplicateChecked = 'true';
            dialog.close();
            form.requestSubmit();
        });

        form.addEventListener('submit', async event => {
            if (event.defaultPrevented) return;
            if (form.dataset.duplicateChecked === 'true') {
                delete form.dataset.duplicateChecked;
                return;
            }
            event.preventDefault();
            checking.classList.add('is-visible');
            const read = id => document.getElementById(id)?.value?.trim() || '';
            const payload = {
                id_number: read('idNumber'), senior_id_no: read('seniorIdNo'),
                first_name: read('firstName'), last_name: read('lastName'),
                full_name: [read('firstName'), read('middleName'), read('lastName'), read('suffix')].filter(Boolean).join(' '),
                birth_date: read('birthDate'), contact_number: read('contactNumber')
            };
            try {
                const response = await fetch('../api/check_beneficiary_duplicates.php', {
                    method: 'POST', headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
                    credentials: 'same-origin', body: JSON.stringify(payload)
                });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Duplicate check failed.');
                if (!result.matches.length) {
                    form.dataset.duplicateChecked = 'true';
                    form.requestSubmit();
                    return;
                }
                matchesContainer.innerHTML = result.matches.map(match => `<article class="duplicate-match">
                    <div class="duplicate-match__top"><span class="duplicate-match__name">${escapeHtml(match.full_name)}</span><span class="duplicate-match__id">${escapeHtml(match.id_number)}</span></div>
                    <div class="duplicate-match__meta"><span>${escapeHtml(match.birth_date)}</span><span>${escapeHtml(match.barangay)}</span><span>${escapeHtml(match.contact_number)}</span><span>${escapeHtml(match.workflow_state || match.application_type)}</span></div>
                    <div class="duplicate-match__reasons">Matched: ${match.reasons.map(escapeHtml).join(', ')}</div>
                </article>`).join('');
                reason.value = '';
                reasonError.textContent = '';
                dialog.showModal();
            } catch (error) {
                window.showCarelinkResult?.(error.message || 'The duplicate check could not be completed. Please try again.', false);
            } finally {
                checking.classList.remove('is-visible');
            }
        });
    });
}());
