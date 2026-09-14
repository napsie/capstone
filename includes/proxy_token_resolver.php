<?php
require_once __DIR__ . '/crypto.php';

/**
 * Resolve either a legacy encrypted representative token or a short PRX/PEN
 * reference. Short references contain no personal data; the current record is
 * read only after the caller has enforced authentication.
 */
function resolveProxyToken(PDO $conn, string $token): ?array
{
    $token = trim($token);
    if ($token === '') return null;

    if (preg_match('/^(?:PRX|PEN)-[A-Z0-9]+$/i', $token)) {
        $data = ['transactionId' => strtoupper($token)];
    } else {
        $data = ProxyCrypto::decrypt($token);
        if (!is_array($data) || empty($data['transactionId'])) return null;
    }

    $stmt = $conn->prepare(
        'SELECT id_number, lastName, firstName, middleName, suffix, birth_date,
                contact_number, complete_address, barangay, application_type,
                sss_number, date_of_death, death_registration_date, relationship_to_deceased,
                deceased_last_name, deceased_first_name, deceased_middle_name, deceased_suffix,
                deceased_birth_date, landbank_card_no, applicant_name, claimant_name,
                claimant_contact, id_type_presented, control_no, visit_summary,
                atm_card_no, is_pensioner, pension_source, is_permanent_income,
                income_source, family_support, family_support_type, family_support_amount,
                health_condition, owns_house, is_renter,
                proxy_name, proxy_relationship, proxy_contact_number, senior_id_no
         FROM applications WHERE id_number = ? AND is_proxy_application = 1 LIMIT 1'
    );
    $stmt->execute([$data['transactionId']]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$record) return null;

    $liveData = [
        'transactionId' => $record['id_number'],
        'lastName' => $record['lastName'] ?? $record['lastname'] ?? '',
        'firstName' => $record['firstName'] ?? $record['firstname'] ?? '',
        'middleName' => $record['middleName'] ?? $record['middlename'] ?? '',
        'suffix' => $record['suffix'] ?? '',
        'birthDate' => $record['birth_date'] ?? '',
        'contactNumber' => $record['contact_number'] ?? '',
        'completeAddress' => $record['complete_address'] ?? '',
        'barangay' => $record['barangay'] ?? '',
        'applicationType' => $record['application_type'] ?? 'senior',
        'sssNumber' => $record['sss_number'] ?? '',
        'dateOfDeath' => $record['date_of_death'] ?? '',
        'deathRegistrationDate' => $record['death_registration_date'] ?? '',
        'relationshipToDeceased' => $record['relationship_to_deceased'] ?? '',
        'deceasedLastName' => $record['deceased_last_name'] ?? '',
        'deceasedFirstName' => $record['deceased_first_name'] ?? '',
        'deceasedMiddleName' => $record['deceased_middle_name'] ?? '',
        'deceasedSuffix' => $record['deceased_suffix'] ?? '',
        'deceasedBirthDate' => $record['deceased_birth_date'] ?? '',
        'landbankCardNo' => $record['landbank_card_no'] ?? '',
        'applicantName' => $record['applicant_name'] ?? '',
        'claimantName' => $record['claimant_name'] ?? '',
        'claimantContact' => $record['claimant_contact'] ?? '',
        'idTypePresented' => $record['id_type_presented'] ?? '',
        'controlNo' => $record['control_no'] ?? '',
        'visitSummary' => $record['visit_summary'] ?? '',
        'atmCardNo' => $record['atm_card_no'] ?? '',
        'isPensioner' => $record['is_pensioner'] ?? '',
        'pensionSource' => $record['pension_source'] ?? '',
        'isPermanentIncome' => $record['is_permanent_income'] ?? '',
        'incomeSource' => $record['income_source'] ?? '',
        'familySupport' => $record['family_support'] ?? '',
        'familySupportType' => $record['family_support_type'] ?? '',
        'familySupportAmount' => $record['family_support_amount'] ?? '',
        'healthCondition' => $record['health_condition'] ?? '',
        'ownsHouse' => $record['owns_house'] ?? '',
        'isRenter' => $record['is_renter'] ?? '',
        'proxyName' => $record['proxy_name'] ?? '',
        'proxyRelationship' => $record['proxy_relationship'] ?? '',
        'proxyContactNumber' => $record['proxy_contact_number'] ?? '',
        'seniorIdNo' => $record['senior_id_no'] ?? '',
    ];

    return array_merge($data, $liveData);
}
