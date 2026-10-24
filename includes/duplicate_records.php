<?php
/** Read-only possible-person matching. No workflow or record changes. */
function duplicateText($value): string {
    return mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string)$value)), 'UTF-8');
}

function duplicatePhone($value): string {
    $digits = preg_replace('/\D/', '', (string)$value);
    if (strlen($digits) === 12 && substr($digits, 0, 2) === '63') $digits = '0' . substr($digits, 2);
    if (strlen($digits) === 10 && substr($digits, 0, 1) === '9') $digits = '0' . $digits;
    return $digits;
}

function scoreDuplicateRecord(array $person, array $candidate): array {
    $score = 0;
    $matches = [];
    foreach (['senior_id_no' => ['Senior Citizen ID', 100], 'full_name' => ['Name', 45],
              'birth_date' => ['Birth date', 35], 'contact_number' => ['Contact number', 25],
              'barangay' => ['Barangay', 10]] as $key => [$label, $points]) {
        $a = $key === 'contact_number' ? duplicatePhone($person[$key] ?? '') : duplicateText($person[$key] ?? '');
        $b = $key === 'contact_number' ? duplicatePhone($candidate[$key] ?? '') : duplicateText($candidate[$key] ?? '');
        if ($a !== '' && $a === $b) { $score += $points; $matches[] = $label . ' (+' . $points . ')'; }
    }
    return ['score' => $score, 'percentage' => min(100, $score), 'matched_fields' => $matches];
}

function findPossibleDuplicateRecords(PDO $conn, array $person, ?string $barangay = null): array {
    // Compare base registrations, not the legitimate service requests linked to them.
    $sql = "SELECT id_number, parent_senior_id, full_name, birth_date, contact_number, barangay, senior_id_no,
                   is_archived FROM applications WHERE application_type = 'senior'
                   AND (parent_senior_id IS NULL OR parent_senior_id = '') AND id_number <> ?";
    $params = [(string)$person['id_number']];
    $root = trim((string)($person['parent_senior_id'] ?? ''));
    if ($root !== '') { $sql .= ' AND id_number <> ?'; $params[] = $root; }
    if ($barangay !== null) { $sql .= ' AND barangay = ?'; $params[] = $barangay; }
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $matches = [];
    while ($candidate = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $result = scoreDuplicateRecord($person, $candidate);
        if ($result['score'] < 60) continue;
        // Contact details are used for comparison but are not returned in this warning.
        $matches[] = array_merge($result, array_intersect_key($candidate,
            array_flip(['id_number', 'full_name', 'birth_date', 'barangay', 'is_archived'])));
    }
    usort($matches, static fn($a, $b) => ($b['score'] <=> $a['score']) ?: strcmp($a['id_number'], $b['id_number']));
    return ['threshold' => 60, 'count' => count($matches), 'matches' => array_slice($matches, 0, 20),
            'scope' => $barangay === null ? 'Citywide senior records, including archived records' : 'Your barangay senior records, including archived records'];
}
