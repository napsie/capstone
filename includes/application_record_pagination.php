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
    $nameExpression = "LOWER(TRIM(REPLACE(REPLACE(full_name, '  ', ' '), '  ', ' ')))";

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
        ' GROUP BY ' . $nameExpression . ', birth_date) applicant_count'
    );
    $bind($applicantCountStatement, $params);
    $applicantCountStatement->execute();
    $totalApplicants = (int)$applicantCountStatement->fetchColumn();

    $totalPages = max(1, (int)ceil($totalApplicants / $perPage));
    $page = min(max(1, $requestedPage), $totalPages);
    $offset = ($page - 1) * $perPage;

    $groupStatement = $conn->prepare(
        'SELECT ' . $nameExpression . ' AS normalized_name, birth_date, MAX(date_submitted) AS latest_date ' .
        $fromWhereSql . ' GROUP BY ' . $nameExpression . ', birth_date ' .
        'ORDER BY latest_date DESC, normalized_name ASC LIMIT :record_limit OFFSET :record_offset'
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
        $nameKey = ':applicant_name_' . $index;
        $birthKey = ':applicant_birth_' . $index;
        $rowParams[$nameKey] = (string)$applicant['normalized_name'];
        if ($applicant['birth_date'] === null || $applicant['birth_date'] === '') {
            $groupClauses[] = "({$nameExpression} = {$nameKey} AND (birth_date IS NULL OR birth_date = ''))";
        } else {
            $rowParams[$birthKey] = (string)$applicant['birth_date'];
            $groupClauses[] = "({$nameExpression} = {$nameKey} AND birth_date = {$birthKey})";
        }
    }

    $rowsStatement = $conn->prepare(
        'SELECT ' . $selectColumns . ' ' . $fromWhereSql . ' AND (' . implode(' OR ', $groupClauses) . ') ' .
        'ORDER BY date_submitted DESC, id_number DESC'
    );
    $bind($rowsStatement, $rowParams);
    $rowsStatement->execute();

    $groupsByKey = [];
    foreach ($rowsStatement->fetchAll(PDO::FETCH_ASSOC) as $application) {
        $normalizedName = mb_strtolower(preg_replace('/\s+/', ' ', trim((string)$application['full_name'])));
        $groupKey = hash('sha256', $normalizedName . '|' . (string)$application['birth_date']);
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
        $groupKey = hash('sha256', (string)$applicant['normalized_name'] . '|' . (string)$applicant['birth_date']);
        if (isset($groupsByKey[$groupKey])) $orderedGroups[] = $groupsByKey[$groupKey];
    }

    return compact('totalRecords', 'totalApplicants', 'totalPages', 'page') + ['groups' => $orderedGroups];
}

