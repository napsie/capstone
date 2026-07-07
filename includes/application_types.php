<?php
/**
 * OSCA application type definitions and shared form field helpers.
 */

function getApplicationTypeOptions(): array {
    return [
        'senior'           => 'F1 — Senior Citizens ID Application',
        'landbank'         => 'F2 — Land Bank Cash Card Enrollment',
        'pension'          => 'Local Senior Pension Form',
        'national_pension' => 'National DSWD Pension (RA 11916)',
        'milestone_gift'   => 'F5 — Octogenarian / Nonagenarian / Centenarian',
        'burial'           => 'F7 — Burial Assistance',
        'home_visit'       => 'F8 — Home Visitation / Confirmation',
    ];
}

function applicationTypeLabel(string $type): string {
    $options = getApplicationTypeOptions();
    return $options[$type] ?? ucwords(str_replace('_', ' ', $type));
}

function getOscaExtraColumns(): array {
    return [
        'place_of_birth', 'gender', 'civil_status', 'mothers_maiden_name',
        'house_no', 'street', 'city', 'province', 'zip_code', 'landmark',
        'health_status', 'senior_id_no', 'id_purpose', 'milestone_age',
        'claimant_name', 'claimant_relationship', 'claimant_contact',
        'deceased_last_name', 'deceased_first_name', 'deceased_middle_name',
        'deceased_suffix', 'deceased_birth_date', 'landbank_card_no', 'applicant_name',
        'visit_purpose', 'living_arrangement', 'is_pensioner', 'pension_source',
        'family_support', 'family_support_amount', 'personal_income', 'personal_income_amount',
        'health_condition', 'with_maintenance', 'maintenance_spec', 'visit_summary',
        'name_on_card', 'tin', 'id_type_presented', 'nationality', 'source_of_funds',
        'atm_card_no', 'control_no', 'is_permanent_income', 'income_source',
        'owns_house', 'is_renter',
    ];
}

function parseOscaFormPost(array $post): array {
    $str = fn($k) => isset($post[$k]) ? trim(strip_tags($post[$k])) : null;
    $bool = fn($k) => isset($post[$k]) && $post[$k] !== '' ? (int)(bool)$post[$k] : null;
    $float = fn($k) => isset($post[$k]) && $post[$k] !== '' ? floatval($post[$k]) : null;

    $visitPurpose = null;
    if (!empty($post['visit_purpose']) && is_array($post['visit_purpose'])) {
        $visitPurpose = implode(', ', array_map('strip_tags', $post['visit_purpose']));
    } elseif (!empty($post['visit_purpose'])) {
        $visitPurpose = trim(strip_tags($post['visit_purpose']));
    }

    return [
        'place_of_birth'         => $str('placeOfBirth'),
        'gender'                 => $str('gender'),
        'civil_status'           => $str('civilStatus'),
        'mothers_maiden_name'    => $str('mothersMaidenName'),
        'house_no'               => $str('houseNo'),
        'street'                 => $str('street'),
        'city'                   => $str('city') ?: 'Pasig City',
        'province'               => $str('province') ?: 'Metro Manila',
        'zip_code'               => $str('zipCode'),
        'landmark'               => $str('landmark'),
        'health_status'          => $str('healthStatus'),
        'senior_id_no'           => $str('seniorIdNo'),
        'id_purpose'             => $str('idPurpose'),
        'milestone_age'          => $str('milestoneAge'),
        'claimant_name'          => $str('claimantName'),
        'claimant_relationship'  => $str('claimantRelationship'),
        'claimant_contact'       => $str('claimantContact'),
        'deceased_last_name'     => $str('deceasedLastName'),
        'deceased_first_name'    => $str('deceasedFirstName'),
        'deceased_middle_name'   => $str('deceasedMiddleName'),
        'deceased_suffix'        => $str('deceasedSuffix'),
        'deceased_birth_date'    => $str('deceasedBirthDate'),
        'landbank_card_no'       => $str('landbankCardNo'),
        'applicant_name'         => $str('applicantName'),
        'visit_purpose'          => $visitPurpose,
        'living_arrangement'     => $str('livingArrangement'),
        'is_pensioner'           => $bool('isPensioner'),
        'pension_source'         => $str('pensionSource'),
        'family_support'         => $bool('familySupport'),
        'family_support_amount'  => $float('familySupportAmount'),
        'personal_income'        => $bool('personalIncome'),
        'personal_income_amount' => $float('personalIncomeAmount'),
        'health_condition'       => $str('healthCondition'),
        'with_maintenance'       => $bool('withMaintenance'),
        'maintenance_spec'       => $str('maintenanceSpec'),
        'visit_summary'          => $str('visitSummary'),
        'name_on_card'           => $str('nameOnCard'),
        'tin'                    => $str('tin'),
        'id_type_presented'      => $str('idTypePresented'),
        'nationality'            => $str('nationality') ?: 'Filipino',
        'source_of_funds'        => $str('sourceOfFunds'),
        'atm_card_no'            => $str('atmCardNo'),
        'control_no'             => $str('controlNo'),
        'is_permanent_income'    => $bool('isPermanentIncome'),
        'income_source'          => $str('incomeSource'),
        'owns_house'             => $bool('ownsHouse'),
        'is_renter'              => $bool('isRenter'),
    ];
}

function buildStructuredAddress(array $fields): string {
    $parts = array_filter([
        $fields['house_no'] ?? '',
        $fields['street'] ?? '',
        $_SESSION['barangay'] ?? '',
        $fields['city'] ?? 'Pasig City',
        $fields['province'] ?? 'Metro Manila',
        $fields['zip_code'] ?? '',
    ]);
    return implode(', ', $parts);
}
