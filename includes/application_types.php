<?php
/**
 * OSCA application type definitions and shared form field helpers.
 */

function getApplicationTypeOptions(): array {
    $options = [];
    foreach (getApplicationBenefitDetails() as $type => $definition) {
        // National DSWD Pension is retained below only for historical-record
        // compatibility; it is no longer an available application type.
        if ($type === 'national_pension') continue;
        $options[$type] = $definition['label'];
    }
    return $options;
}

function applicationTypeLabel(string $type): string {
    $definitions = getApplicationBenefitDetails();
    return $definitions[$type]['label'] ?? ucwords(str_replace('_', ' ', $type));
}

function getDeceasedRelationshipOptions(): array {
    return ['Spouse', 'Child', 'Parent', 'Sibling', 'Grandchild', 'Other Relative', 'Legal Representative'];
}

function getEmergencyContactRelationshipOptions(): array {
    return ['Spouse', 'Child', 'Parent', 'Sibling', 'Grandchild', 'Other Relative', 'Caregiver', 'Friend', 'Legal Guardian'];
}

function milestoneAgeForCurrentAge(int $age): ?int {
    if ($age >= 100) return 100;
    return in_array($age, [80, 85, 90, 95], true) ? $age : null;
}

/**
 * Benefit overview, eligibility, and document requirements per application type.
 */
function getApplicationBenefitDetails(): array {
    return [
        'senior' => [
            'label'        => 'Senior Citizens ID Application',
            'public_request' => 'Senior Citizen ID Registration',
            'minimum_age'  => 60,
            'icon'         => 'fas fa-id-card',
            'accent'       => '#60a5fa',
            'required_fields' => ['idPurpose', 'healthStatus', 'emergencyContactName', 'emergencyContact', 'emergencyContactRelationship'],
            'support_assessment' => false,
            'summary'      => 'Official registration for the Senior Citizens Identification Card issued by OSCA.',
            'benefits'     => [
                'Valid government-recognized senior ID for discounts and privileges',
                'Access to local senior citizen programs and services',
                'Required baseline document for other OSCA benefit applications',
            ],
            'requirements' => [
                'Applicant must be at least 60 years old',
                'Must be a resident of the barangay where application is filed',
                'Purpose-specific documents for New, Lost, Change, or Transfer',
                'Personal appearance with physical original documents for verification',
            ],
            'documents' => [
                'Two recent 1×1 ID photos with white background',
                'Birth certificate and original barangay residency certificate for new applicants',
                'Original ID, affidavit, or transfer certificates according to application purpose',
            ],
            'form_documents' => [
                ['field' => 'psa_birth_cert_file', 'label' => 'PSA Birth Certificate', 'description' => "Clear copy of the senior citizen's PSA birth certificate or accepted proof of age."],
                ['field' => 'barangay_residency_file', 'label' => 'Barangay Residency Certificate', 'description' => 'Current barangay certificate confirming Pasig residency.'],
                ['field' => 'comelec_cert_file', 'label' => '2-Year COMELEC Certification', 'description' => 'Official voter residency certification of the senior citizen applicant.'],
                ['field' => 'id_photo_file', 'label' => 'Two Recent 1×1 ID Photos', 'description' => 'Upload a clear recent 1×1 portrait with a white background; bring two printed copies for verification.', 'extra' => true, 'image_only' => true],
            ],
        ],
        'landbank' => [
            'label'        => 'Land Bank Cash Card Enrollment',
            'public_request' => 'Land Bank Cash Card Enrollment',
            'minimum_age'  => 60,
            'icon'         => 'fas fa-credit-card',
            'accent'       => '#34d399',
            'required_fields' => ['nameOnCard', 'tin', 'seniorIdTypePresented', 'nationality', 'sourceOfFunds', 'mothersMaidenName'],
            'support_assessment' => false,
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
            'form_documents' => [
                ['field' => 'psa_birth_cert_file', 'label' => 'Senior Citizen ID or Proof of Registration', 'description' => 'Senior Citizen ID or proof of an active senior registration.'],
                ['field' => 'barangay_residency_file', 'label' => 'Valid Government-Issued ID', 'description' => 'Government ID presented for Land Bank identity verification.'],
                ['field' => 'comelec_cert_file', 'label' => 'Proof of Address', 'description' => 'Current barangay certificate, utility bill, or equivalent address document.'],
            ],
        ],
        'pension' => [
            'label'        => 'Local Senior Pension Form',
            'public_request' => 'Local Social Pension Assessment',
            'minimum_age'  => 65,
            'icon'         => 'fas fa-wallet',
            'accent'       => '#fbbf24',
            'required_fields' => ['atmCardNo', 'mothersMaidenName', 'isPensioner', 'isPermanentIncome', 'familySupport', 'healthCondition', 'ownsHouse', 'isRenter'],
            'support_assessment' => false,
            'summary'      => 'Local social pension for indigent seniors with limited or no SSS/GSIS pension.',
            'benefits'     => [
                'Monthly local social pension assistance (subject to LGU allocation)',
                'Support for seniors aged 65+ with verified low pension income',
                'Priority for seniors without adequate retirement benefits',
            ],
            'requirements' => [
                'Applicant must be at least 65 years old',
                'Must be a resident of the applying barangay',
                'Complete the economic-status declaration on the official Local Senior Pension Form',
                'Present the Senior Citizens ID and ATM or temporary cash-card stub for verification',
            ],
            'documents' => [
                'Latest senior citizen ID photo',
                'Senior Citizens ID or valid government ID',
                'Proof of address',
                'Pension or income supporting record, when applicable',
            ],
            'form_documents' => [
                ['field' => 'psa_birth_cert_file', 'label' => 'Senior Citizen ID or Valid Government ID', 'description' => 'Identification document of the senior citizen.'],
                ['field' => 'barangay_residency_file', 'label' => 'Barangay Certificate of Indigency', 'description' => 'Current indigency and residency certification issued by the barangay.'],
                ['field' => 'comelec_cert_file', 'label' => 'SSS / GSIS Pension Record or Certification', 'description' => 'Document showing the pension source and monthly amount, or proof that no pension is received.'],
                ['field' => 'id_photo_file', 'label' => 'Latest Senior Citizen ID Photo', 'description' => 'Upload a clear, recent portrait for the photo box on the Local Senior Pension Form.', 'extra' => true, 'image_only' => true],
            ],
        ],
        'national_pension' => [
            'label'        => 'National DSWD Pension (RA 11916)',
            'public_request' => null,
            'minimum_age'  => 65,
            'icon'         => 'fas fa-landmark',
            'accent'       => '#6366f1',
            'required_fields' => ['sssNumber'],
            'support_assessment' => true,
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
            'label'        => 'Octogenarian / Nonagenarian / Centenarian',
            'public_request' => 'Milestone Cash Gift',
            'minimum_age'  => 80,
            'milestone_ages' => [80, 85, 90, 95, 100],
            'icon'         => 'fas fa-gift',
            'accent'       => '#f472b6',
            'required_fields' => ['milestoneAge', 'claimantName', 'claimantRelationship', 'claimantContact'],
            'support_assessment' => false,
            'summary'      => 'Cash gift for seniors reaching milestone ages (octogenarian, nonagenarian, centenarian).',
            'benefits'     => [
                'One-time cash gift at ages 80, 85, 90, 95, or 100+',
                'Recognition benefit for long-lived senior citizens',
                'Processed through OSCA milestone verification',
            ],
            'requirements' => [
                'Must have a Pasig City Senior Citizen ID',
                'Must have at least two years actual residency in Pasig City',
                'Must have reached an eligible milestone age: 80, 85, 90, 95, or 100',
                'If primary birth documents are unavailable, submit any two accepted secondary age documents',
            ],
            'documents' => [
                'PSA-issued or authenticated Certificate of Live Birth',
                'Senior Citizen identification card (OSCA ID), front and back',
                'Latest A4-size whole-body picture',
                'Alternative documents when needed: PSA late registration, government ID, eldest child birth certificate, passport, baptismal/church record, NCIP certification, or NCMF certification',
            ],
            'form_documents' => [
                ['field' => 'psa_birth_cert_file', 'label' => 'PSA Certificate of Live Birth', 'description' => 'Certificate of live birth duly issued or authenticated by the Philippine Statistics Authority.'],
                ['field' => 'barangay_residency_file', 'label' => 'Senior Citizen OSCA ID (Front and Back)', 'description' => 'Clear front-and-back copy of the Pasig City Senior Citizen identification card.'],
                ['field' => 'comelec_cert_file', 'label' => 'Latest A4-Size Whole-Body Picture', 'description' => 'Recent whole-body photograph in A4 portrait format.', 'image_only' => true],
            ],
        ],
        'burial' => [
            'label'        => 'Burial Assistance',
            'public_request' => 'Burial Assistance',
            'minimum_age'  => 60,
            'filing_working_days' => 30,
            'icon'         => 'fas fa-ribbon',
            'accent'       => '#94a3b8',
            'required_fields' => ['dateOfDeath', 'relationshipToDeceased', 'claimantName', 'claimantContact', 'seniorIdNo', 'landbankCardNo', 'idTypePresented'],
            'support_assessment' => false,
            'summary'      => 'Financial assistance for burial expenses of a deceased senior citizen.',
            'benefits'     => [
                'Burial assistance for families of deceased seniors (60+)',
                'Timely filing within the prescribed working-day window',
                'Processed as an OSCA burial assistance claim',
            ],
            'requirements' => [
                'Deceased must be at least 60 years old at time of passing',
                'Application must be submitted within the required 30-working-day filing period',
                'Claimant must state relationship to the deceased',
                'Complete deceased senior and claimant information',
            ],
            'documents' => [
                'Certified True Copy of Death Certificate with registry number (original and one photocopy)',
                'Two valid claimant IDs, front and back, with three signatures (original and two photocopies)',
                'Senior Citizen ID of deceased, front and back (original and two photocopies)',
                'Landbank cash card of deceased, front and back (original and two photocopies)',
                'Proof of relationship: marriage contract, birth certificate, or other accepted proof',
                'Original copy of affidavit, if applicable: kinship, discrepancy, died single without a child, cohabitation, or other',
            ],
            'form_documents' => [
                ['field' => 'psa_birth_cert_file', 'label' => 'Certified True Copy of Death Certificate', 'description' => 'Upload the certificate showing its Local Civil Registry number.'],
                ['field' => 'barangay_residency_file', 'label' => 'Two Valid IDs of Claimant', 'description' => 'Upload front and back copies showing three specimen signatures.'],
                ['field' => 'comelec_cert_file', 'label' => 'Deceased Senior Citizen ID', 'description' => 'Upload the front and back of the deceased senior citizen ID.'],
                ['field' => 'deceased_landbank_card_file', 'label' => 'Deceased Landbank Cash Card', 'description' => 'Upload the front and back of the deceased Landbank cash card.', 'extra' => true],
            ],
        ],
        'home_visit' => [
            'label'        => 'Home Visitation / Confirmation',
            'public_request' => null,
            'minimum_age'  => 60,
            'icon'         => 'fas fa-house-user',
            'accent'       => '#14b8a6',
            'required_fields' => ['visitPurpose', 'livingArrangement', 'isPensioner', 'familySupport', 'personalIncome', 'healthCondition', 'withMaintenance', 'visitSummary'],
            'support_assessment' => true,
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

/** Public request-name keyed definitions used by the public form and handler. */
function getPublicBenefitDefinitions(): array {
    $public = [];
    foreach (getApplicationBenefitDetails() as $type => $definition) {
        $request = $definition['public_request'] ?? null;
        if (!$request) continue;
        $definition['type'] = $type;
        $public[$request] = $definition;
    }
    return $public;
}

function getPublicBenefitDefinition(string $request): ?array {
    $definitions = getPublicBenefitDefinitions();
    return $definitions[$request] ?? null;
}

function getApplicationAgeRule(string $type): array {
    $definition = getApplicationBenefitDetails()[$type] ?? [];
    return [
        'minimum_age' => (int)($definition['minimum_age'] ?? 0),
        'milestone_ages' => array_map('intval', $definition['milestone_ages'] ?? []),
    ];
}

function isApplicationAgeEligible(string $type, int $age, ?int $claimedMilestone = null): bool {
    $rule = getApplicationAgeRule($type);
    if ($age < $rule['minimum_age']) return false;
    if (!$rule['milestone_ages']) return true;
    if ($claimedMilestone === null || !in_array($claimedMilestone, $rule['milestone_ages'], true)) return false;
    return $claimedMilestone === 100 ? $age >= 100 : $age === $claimedMilestone;
}

function getOscaExtraColumns(): array {
    return [
        'place_of_birth', 'gender', 'civil_status', 'mothers_maiden_name',
        'house_no', 'street', 'city', 'province', 'zip_code', 'landmark',
        'health_status', 'senior_id_no', 'id_purpose', 'milestone_age',
        'claimant_name', 'claimant_relationship', 'claimant_contact',
        'deceased_last_name', 'deceased_first_name', 'deceased_middle_name',
        'deceased_suffix', 'deceased_birth_date', 'death_registration_date', 'landbank_card_no', 'applicant_name',
        'visit_purpose', 'living_arrangement', 'is_pensioner', 'pension_source',
        'family_support', 'family_support_type', 'family_support_amount', 'personal_income', 'personal_income_amount',
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
        'claimant_relationship'  => $str('claimantRelationship') ?? $str('emergencyContactRelationship'),
        'claimant_contact'       => $str('claimantContact'),
        'deceased_last_name'     => $str('deceasedLastName'),
        'deceased_first_name'    => $str('deceasedFirstName'),
        'deceased_middle_name'   => $str('deceasedMiddleName'),
        'deceased_suffix'        => $str('deceasedSuffix'),
        'deceased_birth_date'    => $str('deceasedBirthDate'),
        'death_registration_date'=> $str('deathRegistrationDate'),
        'landbank_card_no'       => $str('landbankCardNo'),
        'applicant_name'         => $str('applicantName'),
        'visit_purpose'          => $visitPurpose,
        'living_arrangement'     => $str('livingArrangement'),
        'is_pensioner'           => $bool('isPensioner'),
        'pension_source'         => $str('pensionSource'),
        'family_support'         => $bool('familySupport'),
        'family_support_type'    => $str('familySupportType'),
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
        'nationality'            => $str('nationality'),
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
