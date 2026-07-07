/**
 * Toggle OSCA type-specific form sections based on application type.
 * @param {string} type - application type value
 * @param {string} prefix - optional ID prefix for modal fields (e.g. 'modal-')
 */
function toggleOscaFormFields(type, prefix = '') {
    const sections = {
        senior: [`${prefix}senior-fields`],
        landbank: [`${prefix}landbank-fields`],
        pension: [`${prefix}pension-extra-fields`],
        national_pension: [`${prefix}pension-extra-fields`],
        milestone_gift: [`${prefix}milestone-fields`],
        burial: [`${prefix}burial-extra-fields`],
        home_visit: [`${prefix}homevisit-fields`],
    };

    document.querySelectorAll('.osca-type-fields').forEach(el => {
        if (!prefix || el.id.startsWith(prefix)) {
            el.style.display = 'none';
        }
    });

    (sections[type] || []).forEach(id => {
        const el = document.getElementById(id);
        if (el) el.style.display = 'block';
    });

    // Document label updates
    const labelProof = document.getElementById(prefix ? 'labelProofOfAddress' : 'labelProofOfAddress');
    const labelId = document.getElementById(prefix ? 'labelIdImage' : 'labelIdImage');
    if (!labelProof || !labelId) return;

    const docLabels = {
        senior: ['Proof of Address (Barangay Residency)', 'PSA Birth Certificate / 1x1 ID Picture'],
        landbank: ['Proof of Address', 'Senior ID / Valid ID (front & back)'],
        pension: ['Certificate of Barangay Indigency', 'Landbank ATM Card/Stub or Senior ID'],
        national_pension: ['Certificate of Indigency (CSWD)', 'DSWD Social Pension Form or Senior ID'],
        milestone_gift: ['PSA Birth Certificate (Certified True Copy)', 'Latest Whole-Body Picture (A4 bond paper)'],
        burial: ['Certified True Copy of Death Certificate', 'Proof of Relationship & Deceased IDs'],
        home_visit: ['Supporting Document (if any)', 'Senior ID / Valid ID'],
    };
    const labels = docLabels[type] || docLabels.senior;
    labelProof.textContent = labels[0];
    labelId.textContent = labels[1];
}

/**
 * Populate OSCA extra fields from application API response.
 */
function populateOscaFields(app, prefix = '') {
    const set = (name, val) => {
        if (val == null || val === '') return;
        const el = document.getElementById(prefix + name);
        if (el) el.value = val;
    };
    const setCheckboxes = (name, val) => {
        if (!val) return;
        const values = val.split(',').map(s => s.trim());
        document.querySelectorAll(`input[name="${name}[]"]`).forEach(cb => {
            cb.checked = values.includes(cb.value);
        });
    };

    set('placeOfBirth', app.place_of_birth);
    set('gender', app.gender);
    set('civilStatus', app.civil_status);
    set('mothersMaidenName', app.mothers_maiden_name);
    set('houseNo', app.house_no);
    set('street', app.street);
    set('city', app.city);
    set('province', app.province);
    set('zipCode', app.zip_code);
    set('landmark', app.landmark);
    set('healthStatus', app.health_status);
    set('seniorIdNo', app.senior_id_no);
    set('idPurpose', app.id_purpose);
    set('milestoneAge', app.milestone_age);
    set('claimantName', app.claimant_name);
    set('claimantRelationship', app.claimant_relationship);
    set('claimantContact', app.claimant_contact);
    set('deceasedLastName', app.deceased_last_name);
    set('deceasedFirstName', app.deceased_first_name);
    set('deceasedMiddleName', app.deceased_middle_name);
    set('deceasedSuffix', app.deceased_suffix);
    set('deceasedBirthDate', app.deceased_birth_date);
    set('landbankCardNo', app.landbank_card_no);
    set('applicantName', app.applicant_name);
    set('livingArrangement', app.living_arrangement);
    set('isPensioner', app.is_pensioner != null ? String(app.is_pensioner) : '');
    set('familySupport', app.family_support != null ? String(app.family_support) : '');
    set('personalIncome', app.personal_income != null ? String(app.personal_income) : '');
    set('withMaintenance', app.with_maintenance != null ? String(app.with_maintenance) : '');
    set('ownsHouse', app.owns_house != null ? String(app.owns_house) : '');
    set('isRenter', app.is_renter != null ? String(app.is_renter) : '');
    set('isPermanentIncome', app.is_permanent_income != null ? String(app.is_permanent_income) : '');
    set('pensionSource', app.pension_source);
    set('healthCondition', app.health_condition);
    set('maintenanceSpec', app.maintenance_spec);
    set('visitSummary', app.visit_summary);
    set('nameOnCard', app.name_on_card);
    set('tin', app.tin);
    set('idTypePresented', app.id_type_presented);
    set('nationality', app.nationality);
    set('sourceOfFunds', app.source_of_funds);
    set('atmCardNo', app.atm_card_no);
    set('controlNo', app.control_no);
    set('incomeSource', app.income_source);
    setCheckboxes('visit_purpose', app.visit_purpose);
}
