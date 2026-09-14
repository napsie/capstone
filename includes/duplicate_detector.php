<?php

function duplicateComparableText(?string $value): string
{
    $value = mb_strtolower(trim((string)$value), 'UTF-8');
    return preg_replace('/[^\p{L}\p{N}]+/u', '', $value) ?? '';
}

function duplicateComparablePhone(?string $value): string
{
    $digits = preg_replace('/\D+/', '', (string)$value) ?? '';
    if (strlen($digits) === 12 && str_starts_with($digits, '63')) return '0' . substr($digits, 2);
    return $digits;
}

/** Find likely records for the same person. Scores of 60+ require review. */
function findLikelyBeneficiaryDuplicates(PDO $conn, array $input, int $limit = 8): array
{
    $applicationId = trim((string)($input['id_number'] ?? ''));
    $seniorId = duplicateComparableText($input['senior_id_no'] ?? '');
    $firstName = duplicateComparableText($input['first_name'] ?? '');
    $lastName = duplicateComparableText($input['last_name'] ?? '');
    $fullName = duplicateComparableText($input['full_name'] ?? '');
    $birthDate = trim((string)($input['birth_date'] ?? ''));
    $contact = duplicateComparablePhone($input['contact_number'] ?? '');
    $barangay = duplicateComparableText($input['barangay'] ?? '');

    if ($seniorId === '' && $birthDate === '' && $contact === '' && $firstName === '' && $lastName === '') return [];

    $stmt = $conn->prepare(
        "SELECT id_number, senior_id_no, full_name, firstName, lastName, birth_date,
                contact_number, barangay, application_type, workflow_state, is_archived
         FROM applications
         WHERE id_number <> ? AND COALESCE(is_archived, 0) = 0
           AND COALESCE(workflow_state, '') <> 'Rejected'
           AND (birth_date = ?
                OR REPLACE(REPLACE(REPLACE(REPLACE(contact_number, ' ', ''), '-', ''), '(', ''), ')', '') = ?
                OR LOWER(TRIM(firstName)) = LOWER(TRIM(?))
                OR LOWER(TRIM(lastName)) = LOWER(TRIM(?))
                OR (? <> '' AND LOWER(REPLACE(REPLACE(REPLACE(senior_id_no, '-', ''), '/', ''), ' ', '')) = ?))
         ORDER BY date_submitted DESC LIMIT 40"
    );
    $stmt->execute([$applicationId, $birthDate, $contact, $input['first_name'] ?? '', $input['last_name'] ?? '', $seniorId, $seniorId]);

    $matches = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $record) {
        $reasons = [];
        $score = 0;
        $recordSeniorId = duplicateComparableText($record['senior_id_no'] ?? '');
        $recordFirst = duplicateComparableText($record['firstName'] ?? '');
        $recordLast = duplicateComparableText($record['lastName'] ?? '');
        $recordFull = duplicateComparableText($record['full_name'] ?? '');
        $nameMatches = ($firstName !== '' && $lastName !== '' && $firstName === $recordFirst && $lastName === $recordLast)
            || ($fullName !== '' && $fullName === $recordFull);

        if ($seniorId !== '' && $recordSeniorId !== '' && hash_equals($seniorId, $recordSeniorId)) { $score += 100; $reasons[] = 'same Senior ID'; }
        if ($nameMatches) { $score += 45; $reasons[] = 'same name'; }
        if ($birthDate !== '' && $birthDate === (string)$record['birth_date']) { $score += 35; $reasons[] = 'same birth date'; }
        if ($contact !== '' && $contact === duplicateComparablePhone($record['contact_number'] ?? '')) { $score += 25; $reasons[] = 'same contact number'; }
        if ($barangay !== '' && $barangay === duplicateComparableText($record['barangay'] ?? '')) { $score += 10; $reasons[] = 'same barangay'; }
        if ($score < 60) continue;

        $recordPhone = duplicateComparablePhone($record['contact_number'] ?? '');
        $matches[] = [
            'id_number' => (string)$record['id_number'],
            'full_name' => (string)$record['full_name'],
            'birth_date' => (string)$record['birth_date'],
            'barangay' => (string)$record['barangay'],
            'contact_number' => strlen($recordPhone) >= 4 ? str_repeat('•', max(0, strlen($recordPhone) - 4)) . substr($recordPhone, -4) : '',
            'application_type' => (string)$record['application_type'],
            'workflow_state' => (string)$record['workflow_state'],
            'score' => $score,
            'reasons' => $reasons,
        ];
    }

    usort($matches, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
    return array_slice($matches, 0, max(1, $limit));
}
