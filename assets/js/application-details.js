(function () {
    'use strict';

    const typeLabels = {
        senior: 'Senior Citizens ID Application',
        pension: 'Local Senior Pension Form',
        national_pension: 'National DSWD Pension Form',
        landbank: 'Land Bank Cash Card Enrollment',
        milestone_gift: 'Milestone Cash Gift Application',
        burial: 'Burial Assistance Form',
        home_visit: 'Home Visitation / Confirmation Form',
        pwd: 'PWD Support Application'
    };

    const escapeHtml = value => String(value ?? '')
        .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;').replaceAll("'", '&#039;');
    const hasValue = value => value !== null && value !== undefined && String(value).trim() !== '';
    const display = (value, fallback = 'Not provided') => hasValue(value) ? String(value).trim() : fallback;
    const yesNo = value => {
        if (value === true || value === 1 || value === '1' || String(value).toLowerCase() === 'yes') return 'Yes';
        if (value === false || value === 0 || value === '0' || String(value).toLowerCase() === 'no') return 'No';
        return 'Not provided';
    };
    const money = value => hasValue(value) && !Number.isNaN(Number(value))
        ? `PHP ${Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
        : 'Not provided';
    const date = value => {
        if (!hasValue(value)) return 'Not provided';
        const normalized = String(value).slice(0, 10);
        const parsed = new Date(`${normalized}T00:00:00`);
        return Number.isNaN(parsed.getTime()) ? String(value) : parsed.toLocaleDateString('en-PH', {
            year: 'numeric', month: 'long', day: 'numeric'
        });
    };
    const dateTime = value => {
        if (!hasValue(value)) return 'Not provided';
        const parsed = new Date(String(value).replace(' ', 'T'));
        return Number.isNaN(parsed.getTime()) ? String(value) : parsed.toLocaleString('en-PH');
    };
    const fullName = (app, prefix) => [
        app[`${prefix}_first_name`], app[`${prefix}_middle_name`],
        app[`${prefix}_last_name`], app[`${prefix}_suffix`]
    ].filter(hasValue).join(' ').trim();

    function field(label, value, options = {}) {
        const shown = options.raw ? value : display(value);
        const empty = shown === 'Not provided' || shown === 'Not yet issued';
        return `<div class="application-detail-field${options.wide ? ' application-detail-field--wide' : ''}">
            <span class="application-detail-label">${escapeHtml(label)}</span>
            <span class="application-detail-value${empty ? ' application-detail-value--empty' : ''}">${escapeHtml(shown)}</span>
        </div>`;
    }

    function section(icon, title, fields) {
        return `<section class="application-detail-section">
            <h4><i class="fas ${escapeHtml(icon)}"></i>${escapeHtml(title)}</h4>
            <div class="application-detail-grid">${fields.join('')}</div>
        </section>`;
    }

    function seniorId(app) {
        return display(app.senior_id_no, app.application_type === 'senior' ? 'Not yet issued' : 'Not provided');
    }

    function digitalSeniorId(app) {
        const status = app.workflow_state || app.status || '';
        const eligible = app.application_type === 'senior'
            && ['Verified', 'Approved', 'Released'].includes(status)
            && hasValue(app.senior_id_no)
            && !/^OSCA-[0-9]{4}-[0-9A-F]{6}$/i.test(String(app.senior_id_no).trim())
            && String(app.is_archived || '0') !== '1';
        if (!eligible) return '';
        const pageUrl = `digital_id.php?id=${encodeURIComponent(app.id_number)}`;
        return `<section class="staff-digital-id-section" aria-label="Temporary Digital Senior Citizen ID available">
            <div class="staff-digital-id-title">
                <div><h4><i class="fas fa-id-card"></i>Temporary Digital Senior Citizen ID</h4><p>This approved applicant's temporary digital ID is ready to view.</p></div>
                <a class="staff-digital-id-link" href="${escapeHtml(pageUrl)}" target="_blank" rel="noopener"><i class="fas fa-arrow-up-right-from-square"></i> View Digital ID</a>
            </div>
        </section>`;
    }

    function isWaitingForHomeVisit(app) {
        const isLocalPension = app.application_type === 'pension'
            || app.requested_benefit === 'Local Social Pension Assessment';
        return app.workflow_state !== 'Needs Correction' && isLocalPension && app.home_visit_status !== 'Completed';
    }

    function applicationIdentity(app, title) {
        return section('fa-clipboard-list', title, [
            field('Application ID', app.id_number),
            field('Application Type', typeLabels[app.application_type] || app.application_type),
            field('Requested Benefit / Service', app.requested_benefit),
            field('Senior Citizen ID No.', seniorId(app), { raw: true }),
            field('Processing Status', isWaitingForHomeVisit(app)
                ? 'Pending'
                : (app.workflow_state || app.status || 'Received')),
            field('Date Submitted', dateTime(app.date_submitted), { raw: true })
        ]);
    }

    function typeSpecificSections(app) {
        const sections = [];
        const benefitTypes = {
            'Senior Citizen ID Registration': 'senior',
            'Local Social Pension Assessment': 'pension',
            'Land Bank Cash Card Enrollment': 'landbank',
            'Milestone Cash Gift': 'milestone_gift'
        };
        const type = benefitTypes[app.requested_benefit] || app.application_type || '';

        if (type === 'senior') {
            sections.push(section('fa-id-card', app.requested_benefit === 'Senior Citizen ID Registration' ? 'Senior ID Registration' : 'Senior Pre-registration Profile', [
                field('Application Purpose', app.id_purpose), field('Control Number', app.control_no),
                field('Health Status', app.health_status), field('Emergency Contact', app.emergency_contact_name),
                field('Emergency Contact Number', app.emergency_contact)
            ]));
        }

        if (type === 'pension' || type === 'national_pension') {
            sections.push(section('fa-wallet', type === 'pension' ? 'Local Pension Assessment' : 'National Pension Assessment', [
                field('SSS Number', app.sss_number),
                field('Verified Monthly Pension', money(app.pension_amount), { raw: true }),
                field('Pensioner', yesNo(app.is_pensioner), { raw: true }), field('Pension Source', app.pension_source),
                ...(type === 'pension' ? [
                    field('Home Visit Schedule', dateTime(app.home_visit_scheduled_at), { raw: true }),
                    field('Home Visit Status', app.home_visit_status), field('SMS Notification', app.sms_notification_status)
                ] : []),
                field('Permanent Income', yesNo(app.is_permanent_income), { raw: true }), field('Income Source', app.income_source),
                field('Personal Income', yesNo(app.personal_income), { raw: true }),
                field('Personal Income Amount', money(app.personal_income_amount), { raw: true }),
                field('Family Support', yesNo(app.family_support), { raw: true }),
                field('Family Support Amount', money(app.family_support_amount), { raw: true }),
                field('Owns House', yesNo(app.owns_house), { raw: true }),
                field('Renter', yesNo(app.is_renter), { raw: true }),
                field('ATM / Temporary Stub Number', app.atm_card_no)
            ]));
        }

        if (type === 'landbank') {
            sections.push(section('fa-building-columns', 'Land Bank Enrollment', [
                field('Name on Card', app.name_on_card), field('Cash Card Number', app.landbank_card_no),
                field('ATM / Temporary Stub Number', app.atm_card_no), field('TIN', app.tin),
                field('ID Type Presented', app.id_type_presented), field("Mother's Maiden Name", app.mothers_maiden_name),
                field('Source of Funds', app.source_of_funds)
            ]));
        }

        if (type === 'milestone_gift') {
            sections.push(section('fa-cake-candles', 'Milestone Gift Claim', [
                field('Milestone Age', app.milestone_age), field('Applicant Name', app.applicant_name),
                field('Claimant Name', app.claimant_name), field('Claimant Relationship', app.claimant_relationship),
                field('Claimant Contact', app.claimant_contact)
            ]));
        }

        if (type === 'burial') {
            sections.push(section('fa-ribbon', 'Burial Assistance Claim', [
                field('Deceased Senior', fullName(app, 'deceased') || 'Not provided', { wide: true, raw: true }),
                field('Deceased Birth Date', date(app.deceased_birth_date || app.birth_date), { raw: true }),
                field('Date of Passing', date(app.date_of_death), { raw: true }),
                field('Applicant / Claimant', app.applicant_name || app.claimant_name),
                field('Relationship to Deceased', app.relationship_to_deceased || app.claimant_relationship),
                field('Claimant Contact', app.claimant_contact)
            ]));
        }

        if (type === 'home_visit') {
            sections.push(section('fa-house-medical', 'Home Visit Assessment', [
                field('Visit Purpose', app.visit_purpose, { wide: true }), field('Living Arrangement', app.living_arrangement),
                field('Health Condition', app.health_condition),
                field('Maintenance Medicine', yesNo(app.with_maintenance), { raw: true }),
                field('Medicine Details', app.maintenance_spec), field('Visit Summary', app.visit_summary, { wide: true })
            ]));
        }

        return sections;
    }

    function representativeSection(app) {
        if (String(app.is_proxy_application) !== '1' && ![app.proxy_name, app.proxy_relationship].some(hasValue)) return '';
        return section('fa-user-shield', 'Authorized Representative', [
            field('Representative Name', app.proxy_name), field('Relationship', app.proxy_relationship),
            field('Birth Date', date(app.proxy_birth_date), { raw: true }),
            field('Contact Number', app.proxy_contact_number), field('Email Address', app.proxy_email),
            field('Complete Address', app.proxy_address, { wide: true }),
            field('Government ID Type', app.proxy_id_type), field('Government ID Number', app.proxy_id_number),
            field('Linked Senior Application', app.parent_senior_id)
        ]);
    }

    function requestedBenefitSection(app) {
        const benefit = app.requested_benefit || '';
        if (!hasValue(benefit)) return '';

        if (benefit === 'Senior Citizen ID Registration') {
            return section('fa-id-card', 'Requested Senior ID Service', [
                field('ID Application Purpose', app.id_purpose)
            ]);
        }
        if (benefit === 'Home Visitation / Confirmation') {
            return section('fa-house-medical', 'Requested Home Visit', [
                field('Visit Purpose', app.visit_purpose, { wide: true }),
                field('Visit Instructions', app.visit_summary, { wide: true }),
                field('Living Arrangement', app.living_arrangement), field('Health Condition', app.health_condition)
            ]);
        }
        if (benefit.includes('Social Pension')) {
            return section('fa-wallet', 'Requested Pension Assessment', [
                field('Pensioner', yesNo(app.is_pensioner), { raw: true }), field('Pension Source', app.pension_source),
                field('SSS / GSIS Number', app.sss_number), field('Current Monthly Pension', money(app.pension_amount), { raw: true }),
                field('Family Support', yesNo(app.family_support), { raw: true }),
                field('Family Support Amount', money(app.family_support_amount), { raw: true }),
                field('Personal Income', yesNo(app.personal_income), { raw: true }),
                field('Personal Income Amount', money(app.personal_income_amount), { raw: true })
            ]);
        }
        if (benefit === 'Land Bank Cash Card Enrollment') {
            return section('fa-building-columns', 'Requested Land Bank Enrollment', [
                field('Name on Card', app.name_on_card), field('TIN', app.tin),
                field('Nationality', app.nationality), field('Senior ID Presented', app.id_type_presented),
                field('Source of Funds', app.source_of_funds, { wide: true })
            ]);
        }
        if (benefit === 'Milestone Cash Gift') {
            return section('fa-cake-candles', 'Requested Milestone Cash Gift', [
                field('Milestone Age', app.milestone_age), field('Claimant Name', app.claimant_name),
                field('Claimant Relationship', app.claimant_relationship), field('Claimant Contact', app.claimant_contact)
            ]);
        }
        if (benefit === 'Other OSCA Assistance') {
            return section('fa-hand-holding-heart', 'Requested OSCA Assistance', [
                field('Assistance Details', app.additional_notes, { wide: true })
            ]);
        }
        return '';
    }

    const wrap = sections => `<div class="application-detail-summary">${sections.filter(Boolean).join('')}</div>`;

    // The edit modal already contains the editable form, so its side panel does not repeat it.
    window.renderApplicationEditContext = app => wrap([
        section('fa-clipboard-list', 'Application Context', [
            field('Application ID', app.id_number),
            field('Application Type', typeLabels[app.application_type] || app.application_type),
            field('Processing Status', isWaitingForHomeVisit(app)
                ? 'Pending'
                : (app.workflow_state || app.status || 'Received')),
            field('Date Submitted', dateTime(app.date_submitted), { raw: true }),
            ...(app.application_type === 'pension' ? [
                field('Home Visit Schedule', dateTime(app.home_visit_scheduled_at), { raw: true }),
                field('SMS Notification', app.sms_notification_status)
            ] : [])
        ])
    ]);

    // Verification shows only the reference and facts needed to assess this application type.
    window.renderApplicationVerificationDetails = app => wrap([
        digitalSeniorId(app), applicationIdentity(app, 'Verification Reference'), ...typeSpecificSections(app),
        requestedBenefitSection(app), representativeSection(app),
        hasValue(app.return_reason || app.return_comments)
            ? section('fa-note-sticky', 'Correction Instructions', [field('Items and Reason', app.return_reason || app.return_comments, { wide: true })])
            : ''
    ]);

    // Record viewing adds profile facts that are not already in the modal's applicant summary.
    window.renderApplicationRecordDetails = app => wrap([
        digitalSeniorId(app), applicationIdentity(app, 'Record Reference'),
        String(app.is_archived) === '1'
            ? section('fa-box-archive', 'Archive Information', [
                field('Archive Status', 'Archived', { raw: true }),
                field('Archived Date', dateTime(app.archived_at), { raw: true }),
                field('Archived By', app.archived_by)
            ])
            : '',
        section('fa-address-card', 'Additional Profile Information', [
            field('Email Address', app.email_address), field('Place of Birth', app.place_of_birth),
            field('Gender', app.gender), field('Civil Status', app.civil_status),
            field('Emergency Contact', app.emergency_contact_name),
            field('Emergency Contact Number', app.emergency_contact), field('Landmark', app.landmark)
        ]),
        ...typeSpecificSections(app), requestedBenefitSection(app), representativeSection(app),
        hasValue(app.return_reason || app.return_comments)
            ? section('fa-note-sticky', 'Correction History', [field('Return / Correction Reason', app.return_reason || app.return_comments, { wide: true })])
            : ''
    ]);

    window.renderArchivedApplicationDetails = app => wrap([
        section('fa-user', 'Applicant Information', [
            field('Full Name', app.full_name), field('Birth Date', date(app.birth_date), { raw: true }),
            field('Contact Number', app.contact_number), field('Barangay', app.barangay),
            field('Complete Address', app.complete_address, { wide: true }),
            field('Additional Notes', app.additional_notes, { wide: true })
        ]),
        window.renderApplicationRecordDetails(app)
    ]);

    window.renderApplicationAuditHistory = (history, target = 'timelineList', options = {}) => {
        const container = typeof target === 'string' ? document.getElementById(target) : target;
        if (!container) return;

        const records = Array.isArray(history) ? [...history].reverse() : [];
        if (!records.length) {
            container.innerHTML = '<div class="audit-history-empty"><i class="fas fa-clock-rotate-left" aria-hidden="true"></i><span>No transition history found.</span></div>';
            return;
        }

        const requestedPageSize = Number(options.pageSize || 5);
        const pageSize = Number.isFinite(requestedPageSize) && requestedPageSize > 0 ? Math.floor(requestedPageSize) : 5;
        const pageCount = Math.ceil(records.length / pageSize);
        let currentPage = 1;

        const renderPage = () => {
            const start = (currentPage - 1) * pageSize;
            const visibleRecords = records.slice(start, start + pageSize);
            const firstRecord = start + 1;
            const lastRecord = Math.min(start + pageSize, records.length);
            const events = visibleRecords.map(log => {
                const parsedDate = new Date(String(log.changed_at || '').replace(' ', 'T'));
                const changedAt = Number.isNaN(parsedDate.getTime())
                    ? display(log.changed_at)
                    : parsedDate.toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' });
                return `<div class="timeline-event">
                    <div class="timeline-time">${escapeHtml(changedAt)}</div>
                    <div class="timeline-title">${escapeHtml(display(log.previous_state, 'None'))} <span aria-hidden="true">&rarr;</span><span class="sr-only"> to </span> ${escapeHtml(display(log.new_state))}</div>
                    <div class="timeline-by"><i class="fas fa-user-pen" aria-hidden="true"></i> ${escapeHtml(display(log.changed_by, 'System'))}</div>
                    ${hasValue(log.comments) ? `<div class="timeline-note">${escapeHtml(log.comments)}</div>` : ''}
                </div>`;
            }).join('');

            const pagination = pageCount > 1 ? `<nav class="audit-pagination" aria-label="Audit history pages">
                <button type="button" class="audit-pagination__button" data-audit-page="previous" ${currentPage === 1 ? 'disabled' : ''}><i class="fas fa-chevron-left" aria-hidden="true"></i> Previous</button>
                <span class="audit-pagination__page" aria-live="polite">Page ${currentPage} of ${pageCount}</span>
                <button type="button" class="audit-pagination__button" data-audit-page="next" ${currentPage === pageCount ? 'disabled' : ''}>Next <i class="fas fa-chevron-right" aria-hidden="true"></i></button>
            </nav>` : '';

            container.innerHTML = `<div class="audit-history-summary"><span>Showing ${firstRecord}&ndash;${lastRecord} of ${records.length} records</span><span>Most recent first</span></div>${events}${pagination}`;
            container.querySelector('[data-audit-page="previous"]')?.addEventListener('click', () => {
                currentPage = Math.max(1, currentPage - 1);
                renderPage();
                container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            });
            container.querySelector('[data-audit-page="next"]')?.addEventListener('click', () => {
                currentPage = Math.min(pageCount, currentPage + 1);
                renderPage();
                container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            });
        };

        renderPage();
    };

    function enhanceApplicationDialog() {
        const modal = document.getElementById('applicationModal');
        if (!modal) return;

        const dialog = modal.querySelector('.modal-box');
        const closeButton = modal.querySelector('.modal-close');
        let returnFocus = null;
        let wasOpen = false;

        if (dialog) dialog.setAttribute('tabindex', '-1');
        modal.setAttribute('aria-hidden', 'true');

        const isOpen = () => getComputedStyle(modal).display !== 'none';
        const syncState = () => {
            const open = isOpen();
            modal.setAttribute('aria-hidden', open ? 'false' : 'true');
            document.body.classList.toggle('application-modal-open', open);

            if (open && !wasOpen) {
                returnFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
                requestAnimationFrame(() => (closeButton || dialog)?.focus());
            } else if (!open && wasOpen && returnFocus?.isConnected) {
                returnFocus.focus();
            }
            wasOpen = open;
        };

        new MutationObserver(syncState).observe(modal, { attributes: true, attributeFilter: ['style', 'class'] });
        syncState();

        document.addEventListener('keydown', event => {
            if (!isOpen()) return;
            if (event.key === 'Escape') {
                event.preventDefault();
                closeButton?.click();
                return;
            }
            if (event.key !== 'Tab') return;

            const focusable = [...modal.querySelectorAll('button:not([disabled]), a[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')]
                .filter(element => element.offsetParent !== null);
            if (!focusable.length) return;
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', enhanceApplicationDialog, { once: true });
    } else {
        enhanceApplicationDialog();
    }
})();
