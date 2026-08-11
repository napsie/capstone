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
                sss_number, date_of_death, relationship_to_deceased,
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
        'relationshipToDeceased' => $record['relationship_to_deceased'] ?? '',
        'proxyName' => $record['proxy_name'] ?? '',
        'proxyRelationship' => $record['proxy_relationship'] ?? '',
        'proxyContactNumber' => $record['proxy_contact_number'] ?? '',
        'seniorIdNo' => $record['senior_id_no'] ?? '',
    ];

    return array_merge($data, $liveData);
}

