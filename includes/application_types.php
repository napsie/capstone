<?php
/**
 * OSCA application type definitions and shared form field helpers.
 */

function getApplicationTypeOptions(): array {
    return [
        'senior'           => 'Senior Citizens ID Application',
        'landbank'         => 'Land Bank Cash Card Enrollment',
        'pension'          => 'Local Senior Pension Form',
        'national_pension' => 'National DSWD Pension (RA 11916)',
        'milestone_gift'   => 'Octogenarian / Nonagenarian / Centenarian',
        'burial'           => 'Burial Assistance',
        'home_visit'       => 'Home Visitation / Confirmation',
    ];
}

function applicationTypeLabel(string $type): string {
    $options = getApplicationTypeOptions();
    return $options[$type] ?? ucwords(str_replace('_', ' ', $type));
}

/**
 * Benefit overview, eligibility, and document requirements per application type.
 */
function getApplicationBenefitDetails(): array {
    return [
        'senior' => [
            'summary'      => 'Official registration for the Senior Citizens Identification Card issued by OSCA.',
            'benefits'     => [
                'Valid government-recognized senior ID for discounts and privileges',
                'Access to local senior citizen programs and services',
                'Required baseline document for other OSCA benefit applications',
            ],
            'requirements' => [
                'Applicant must be at least 60 years old',
                'Must be a resident of the barangay where application is filed',
                'Valid proof of identity and proof of address',
                'Personal appearance or authorized representative with complete documents',
            ],
            'documents' => [
                'Birth certificate or valid government-issued ID',
                'Proof of address (utility bill, barangay certificate, etc.)',
                '1×1 or 2×2 ID photo (if required by local OSCA)',
            ],
        ],
        'landbank' => [
            'summary'      => 'Enrollment for Land Bank cash card disbursement of senior citizen benefits.',
            'benefits'     => [
                'Direct crediting of approved cash benefits to a Land Bank account',
                'Safer and faster release compared to over-the-counter claiming',
                'Linked to OSCA senior records for recurring disbursements',
            ],
            'requirements' => [
                'Must be a registered senior citizen (60+)',
                'Active senior ID or pending senior ID application',
                'Complete personal and address information',
                'TIN and valid ID type for bank KYC compliance',
            ],
            'documents' => [
                'Senior Citizens ID or proof of senior registration',
                'Valid government-issued ID presented for verification',
                'Proof of address',
                'Completed Land Bank enrollment details (name on card, TIN, etc.)',
            ],
        ],
        'pension' => [
            'summary'      => 'Local social pension for indigent seniors with limited or no SSS/GSIS pension.',
            'benefits'     => [
                'Monthly local social pension assistance (subject to LGU allocation)',
                'Support for seniors aged 65+ with verified low pension income',
                'Priority for seniors without adequate retirement benefits',
            ],
            'requirements' => [
                'Applicant must be at least 65 years old',
                'Verified SSS pension must not exceed ₱4,000 per month',
                'Must be a resident of the applying barangay',
                'Complete SSS verification during application',
            ],
            'documents' => [
                'Senior Citizens ID or valid government ID',
                'SSS number for pension verification',
                'Proof of address',
                'Supporting documents for indigency assessment (if required)',
            ],
        ],
        'national_pension' => [
            'summary'      => 'National DSWD social pension under Republic Act 11916 for qualified indigent seniors.',
            'benefits'     => [
                'National government social pension for eligible seniors',
                'Monthly assistance for seniors with no other pension benefits',
                'Coordinated through OSCA and DSWD validation',
            ],
            'requirements' => [
                'Applicant must be at least 65 years old',
                'Must have no existing SSS/GSIS or other monthly pension (verified amount must be ₱0)',
                'Must meet DSWD indigency and residency criteria',
                'Complete assessment fields and supporting declarations',
            ],
            'documents' => [
                'Senior Citizens ID or valid government ID',
                'SSS number for zero-pension verification',
                'Proof of address and residency',
                'Income and household support declarations',
            ],
        ],
        'milestone_gift' => [
            'summary'      => 'Cash gift for seniors reaching milestone ages (octogenarian, nonagenarian, centenarian).',
            'benefits'     => [
                'One-time cash gift at ages 80, 85, 90, 95, or 100+',
                'Recognition benefit for long-lived senior citizens',
                'Processed through OSCA milestone verification',
            ],
            'requirements' => [
                'Applicant age must match a milestone bracket (80, 85, 90, 95, or 100+)',
                'Must be a registered senior citizen',
                'Claimant details required if filed by a representative',
                'Milestone age must match birth date on record',
            ],
            'documents' => [
                'Senior Citizens ID',
                'Birth certificate or valid ID showing date of birth',
                'Proof of address',
                'Claimant authorization documents (if applicable)',
            ],
        ],
        'burial' => [
            'summary'      => 'Financial assistance for burial expenses of a deceased senior citizen.',
            'benefits'     => [
                'Burial assistance for families of deceased seniors (60+)',
                'Timely filing within the prescribed working-day window',
                'Processed as an OSCA burial assistance claim',
            ],
            'requirements' => [
                'Deceased must be at least 60 years old at time of passing',
                'Application must be filed within 30 working days from date of death',
                'Claimant must state relationship to the deceased',
                'Complete deceased senior and claimant information',
            ],
            'documents' => [
                'Death certificate of the deceased senior',
                'Senior Citizens ID or proof senior status of deceased',
                'Valid ID of claimant',
                'Proof of relationship to deceased',
                'Burial contract or funeral service documents (if available)',
            ],
        ],
        'home_visit' => [
            'summary'      => 'Home visitation and confirmation for bedridden, immobile, or hard-to-reach seniors.',
            'benefits'     => [
                'OSCA field validation without requiring personal appearance at office',
                'Assessment of living arrangement, health, and support needs',
                'Enables continued access to benefits for homebound seniors',
            ],
            'requirements' => [
                'Senior must be 60+ and unable to visit OSCA in person',
                'Complete visit purpose and living arrangement details',
                'Health condition and maintenance medication information',
                'Family or caregiver support details',
            ],
            'documents' => [
                'Senior Citizens ID or valid ID',
                'Medical certificate or doctor\'s recommendation (if available)',
                'Proof of address',
                'Caregiver or family contact information',
            ],
        ],
    ];
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
