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

    function applicationIdentity(app, title) {
        return section('fa-clipboard-list', title, [
            field('Application ID', app.id_number),
            field('Application Type', typeLabels[app.application_type] || app.application_type),
            field('Senior Citizen ID No.', seniorId(app), { raw: true }),
            field('Processing Status', app.workflow_state || app.status || 'Received'),
            field('Date Submitted', dateTime(app.date_submitted), { raw: true }),
            field('Priority Level', app.priority_level || 'Normal')
        ]);
    }

    function typeSpecificSections(app) {
        const sections = [];
        const type = app.application_type || '';

        if (type === 'senior') {
            sections.push(section('fa-id-card', 'Senior ID Registration', [
                field('Application Purpose', app.id_purpose), field('Control Number', app.control_no),
                field('ID Type Presented', app.id_type_presented), field('TIN', app.tin),
                field('Health Status', app.health_status)
            ]));
        }

        if (type === 'pension' || type === 'national_pension') {
            sections.push(section('fa-wallet', type === 'pension' ? 'Local Pension Assessment' : 'National Pension Assessment', [
                field('SSS Number', app.sss_number),
                field('Verified Monthly Pension', money(app.pension_amount), { raw: true }),
                field('Pensioner', yesNo(app.is_pensioner), { raw: true }), field('Pension Source', app.pension_source),
                field('Permanent Income', yesNo(app.is_permanent_income), { raw: true }), field('Income Source', app.income_source),
                field('Personal Income', yesNo(app.personal_income), { raw: true }),
                field('Personal Income Amount', money(app.personal_income_amount), { raw: true }),
                field('Family Support', yesNo(app.family_support), { raw: true }),
                field('Family Support Amount', money(app.family_support_amount), { raw: true }),
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
            field('Contact Number', app.proxy_contact_number), field('Linked Senior Application', app.parent_senior_id)
        ]);
    }

    const wrap = sections => `<div class="application-detail-summary">${sections.filter(Boolean).join('')}</div>`;

    // The edit modal already contains the editable form, so its side panel does not repeat it.
    window.renderApplicationEditContext = app => wrap([
        section('fa-clipboard-list', 'Application Context', [
            field('Application ID', app.id_number),
            field('Application Type', typeLabels[app.application_type] || app.application_type),
            field('Processing Status', app.workflow_state || app.status || 'Received'),
            field('Date Submitted', dateTime(app.date_submitted), { raw: true }),
            field('Priority Level', app.priority_level || 'Normal')
        ])
    ]);

    // Verification shows only the reference and facts needed to assess this application type.
    window.renderApplicationVerificationDetails = app => wrap([
        applicationIdentity(app, 'Verification Reference'), ...typeSpecificSections(app), representativeSection(app)
    ]);

    // Record viewing adds profile facts that are not already in the modal's applicant summary.
    window.renderApplicationRecordDetails = app => wrap([
        applicationIdentity(app, 'Record Reference'),
        section('fa-address-card', 'Additional Profile Information', [
            field('Email Address', app.email_address), field('Place of Birth', app.place_of_birth),
            field('Gender', app.gender), field('Civil Status', app.civil_status), field('Nationality', app.nationality),
            field('Emergency Contact', app.emergency_contact_name),
            field('Emergency Contact Number', app.emergency_contact), field('Landmark', app.landmark)
        ]),
        ...typeSpecificSections(app), representativeSection(app),
        hasValue(app.return_reason || app.return_comments)
            ? section('fa-note-sticky', 'Correction History', [field('Return / Correction Reason', app.return_reason || app.return_comments, { wide: true })])
            : ''
    ]);
})();
