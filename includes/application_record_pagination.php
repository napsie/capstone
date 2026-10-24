<?php

/**
 * Fetch one page of applicants and only the application rows belonging to
 * those applicants. This keeps related benefit records together without
 * loading the complete result set into PHP.
 */
function fetchApplicationRecordPage(
    PDO $conn,
    string $fromWhereSql,
    array $params,
    string $selectColumns,
    int $requestedPage,
    int $perPage,
    bool $includeBarangay = false
): array {
    $perPage = max(1, min(100, $perPage));
    // Only explicit links establish identity. Matching names/birth dates are
    // possible duplicates for manual review, never permission to merge rows.
    $identityExpression = "COALESCE(NULLIF(TRIM(parent_senior_id), ''), id_number)";

    $bind = static function (PDOStatement $statement, array $values): void {
        foreach ($values as $key => $value) {
            $statement->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
    };

    $recordCountStatement = $conn->prepare('SELECT COUNT(*) ' . $fromWhereSql);
    $bind($recordCountStatement, $params);
    $recordCountStatement->execute();
    $totalRecords = (int)$recordCountStatement->fetchColumn();

    $applicantCountStatement = $conn->prepare(
        'SELECT COUNT(*) FROM (SELECT 1 ' . $fromWhereSql .
        ' GROUP BY ' . $identityExpression . ') applicant_count'
    );
    $bind($applicantCountStatement, $params);
    $applicantCountStatement->execute();
    $totalApplicants = (int)$applicantCountStatement->fetchColumn();

    $totalPages = max(1, (int)ceil($totalApplicants / $perPage));
    $page = min(max(1, $requestedPage), $totalPages);
    $offset = ($page - 1) * $perPage;

    $groupStatement = $conn->prepare(
        'SELECT ' . $identityExpression . ' AS identity_key, MAX(date_submitted) AS latest_date ' .
        $fromWhereSql . ' GROUP BY ' . $identityExpression . ' ' .
        'ORDER BY latest_date DESC, identity_key ASC LIMIT :record_limit OFFSET :record_offset'
    );
    $bind($groupStatement, $params);
    $groupStatement->bindValue(':record_limit', $perPage, PDO::PARAM_INT);
    $groupStatement->bindValue(':record_offset', $offset, PDO::PARAM_INT);
    $groupStatement->execute();
    $pageApplicants = $groupStatement->fetchAll(PDO::FETCH_ASSOC);

    if (!$pageApplicants) {
        return compact('totalRecords', 'totalApplicants', 'totalPages', 'page') + ['groups' => []];
    }

    $groupClauses = [];
    $rowParams = $params;
    foreach ($pageApplicants as $index => $applicant) {
        $identityKey = ':applicant_identity_' . $index;
        $rowParams[$identityKey] = (string)$applicant['identity_key'];
        $groupClauses[] = "{$identityExpression} = {$identityKey}";
    }

    $rowsStatement = $conn->prepare(
        'SELECT ' . $selectColumns . ', ' . $identityExpression . ' AS record_identity_key ' . $fromWhereSql . ' AND (' . implode(' OR ', $groupClauses) . ') ' .
        'ORDER BY date_submitted DESC, id_number DESC'
    );
    $bind($rowsStatement, $rowParams);
    $rowsStatement->execute();

    $groupsByKey = [];
    foreach ($rowsStatement->fetchAll(PDO::FETCH_ASSOC) as $application) {
        $groupKey = hash('sha256', (string)$application['record_identity_key']);
        if (!isset($groupsByKey[$groupKey])) {
            $groupsByKey[$groupKey] = [
                'key' => substr($groupKey, 0, 12),
                'full_name' => $application['full_name'],
                'birth_date' => $application['birth_date'],
                'latest_date' => $application['date_submitted'],
                'applications' => [],
                'benefit_count' => 0,
            ];
            if ($includeBarangay) $groupsByKey[$groupKey]['barangay'] = $application['barangay'];
        }
        $groupsByKey[$groupKey]['applications'][] = $application;
        if ($application['application_type'] !== 'senior') $groupsByKey[$groupKey]['benefit_count']++;
    }

    $orderedGroups = [];
    foreach ($pageApplicants as $applicant) {
        $groupKey = hash('sha256', (string)$applicant['identity_key']);
        if (isset($groupsByKey[$groupKey])) $orderedGroups[] = $groupsByKey[$groupKey];
    }

    return compact('totalRecords', 'totalApplicants', 'totalPages', 'page') + ['groups' => $orderedGroups];
}

