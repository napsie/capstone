<?php
declare(strict_types=1);

require_once __DIR__ . '/barangays_list.php';

function classificationActor(array $session): array {
    $role = (string)($session['role'] ?? '');
    if (empty($session['user_id']) || !in_array($role, ['department_admin', 'super_admin', 'barangay_staff'], true)) {
        throw new RuntimeException('Authorized staff access is required.', 403);
    }
    $barangay = trim((string)($session['barangay'] ?? ''));
    if ($role === 'barangay_staff' && $barangay === '') {
        throw new RuntimeException('A barangay assignment is required.', 403);
    }
    return ['id' => (int)$session['user_id'], 'name' => (string)($session['username'] ?? 'Staff'), 'role' => $role,
        'can_edit' => $role !== 'barangay_staff', 'barangay' => $role === 'barangay_staff' ? $barangay : null];
}

function classificationFilters(array $input, array $actor): array {
    global $barangays_list;
    foreach (['search','activity','residence','barangay'] as $key) {
        if (isset($input[$key]) && !is_scalar($input[$key])) throw new InvalidArgumentException('Select valid classification report filters.');
    }
    $filters = [
        'search' => trim((string)($input['search'] ?? '')),
        'activity' => (string)($input['activity'] ?? 'all'),
        'residence' => (string)($input['residence'] ?? 'all'),
        'barangay' => $actor['barangay'] ?? (string)($input['barangay'] ?? 'all'),
    ];
    if (mb_strlen($filters['search']) > 100
        || !in_array($filters['activity'], ['all', 'Active', 'Inactive', 'Deceased'], true)
        || !in_array($filters['residence'], ['all', 'Within Pasig', 'Outside Pasig', 'Unclassified'], true)
        || ($filters['barangay'] !== 'all' && !in_array($filters['barangay'], $barangays_list, true))) {
        throw new InvalidArgumentException('Select valid classification report filters.');
    }
    return $filters;
}

/** One row per primary senior profile. Linked benefits and ID changes never add people. */
function classificationSourceSql(): string {
    return "SELECT s.id_number AS profile_id, s.full_name, s.senior_id_no,
        COALESCE(NULLIF(TRIM(s.proxy_token), ''), s.id_number) AS tracking_no,
        s.birth_date, s.barangay,
        CASE WHEN s.workflow_state = 'Deceased' OR LOWER(s.status) = 'deceased' OR s.deceased_at IS NOT NULL
            THEN 'Deceased' ELSE COALESCE(NULLIF(c.activity_status, 'Unclassified'), 'Active') END AS activity_status,
        COALESCE(NULLIF(c.residence_status, 'Unclassified'), 'Within Pasig') AS residence_status,
        c.effective_date, c.reason, c.updated_by_name, c.updated_at, COALESCE(c.version, 0) AS version
        FROM applications s LEFT JOIN senior_classifications c ON c.profile_id = s.id_number
        WHERE s.application_type = 'senior' AND NULLIF(TRIM(s.parent_senior_id), '') IS NULL
          AND COALESCE(s.is_archived, 0) = 0
          AND (s.workflow_state IN ('Verified', 'Approved', 'Released', 'Deceased')
            OR LOWER(s.status) IN ('verified', 'approved', 'deceased') OR s.deceased_at IS NOT NULL)";
}

function classificationWhere(array $filters): array {
    $clauses = []; $params = [];
    if ($filters['barangay'] !== 'all') { $clauses[] = 'r.barangay = ?'; $params[] = $filters['barangay']; }
    if ($filters['activity'] !== 'all') { $clauses[] = 'r.activity_status = ?'; $params[] = $filters['activity']; }
    if ($filters['residence'] !== 'all') { $clauses[] = 'r.residence_status = ?'; $params[] = $filters['residence']; }
    if ($filters['search'] !== '') {
        $clauses[] = '(r.full_name LIKE ? OR r.senior_id_no LIKE ? OR r.tracking_no LIKE ?)';
        $term = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['search']) . '%';
        $clauses[count($clauses) - 1] = "(r.full_name LIKE ? ESCAPE '!' OR r.senior_id_no LIKE ? ESCAPE '!' OR r.tracking_no LIKE ? ESCAPE '!')";
        array_push($params, $term, $term, $term);
    }
    return [$clauses ? ' WHERE ' . implode(' AND ', $clauses) : '', $params];
}

function classificationSummary(PDO $conn, array $filters): array {
    [$where, $params] = classificationWhere($filters);
    $stmt = $conn->prepare('SELECT COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN activity_status = \'Active\' THEN 1 ELSE 0 END),0) AS active,
        COALESCE(SUM(CASE WHEN activity_status = \'Inactive\' THEN 1 ELSE 0 END),0) AS inactive,
        COALESCE(SUM(CASE WHEN activity_status = \'Unclassified\' THEN 1 ELSE 0 END),0) AS unclassified,
        COALESCE(SUM(CASE WHEN residence_status = \'Unclassified\' THEN 1 ELSE 0 END),0) AS residence_unclassified,
        COALESCE(SUM(CASE WHEN activity_status = \'Deceased\' THEN 1 ELSE 0 END),0) AS deceased
        FROM (' . classificationSourceSql() . ') r' . $where);
    $stmt->execute($params);
    return array_map('intval', $stmt->fetch(PDO::FETCH_ASSOC));
}

function classificationRows(PDO $conn, array $filters, int $limit = 25, int $offset = 0): array {
    [$where, $params] = classificationWhere($filters);
    $limit = max(1, min(5001, $limit)); $offset = max(0, $offset);
    $stmt = $conn->prepare('SELECT * FROM (' . classificationSourceSql() . ') r' . $where
        . " ORDER BY r.full_name, r.profile_id LIMIT {$limit} OFFSET {$offset}");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function classificationProfile(PDO $conn, string $id, array $actor): ?array {
    $sql = 'SELECT * FROM (' . classificationSourceSql() . ') r WHERE r.profile_id = ?';
    $params = [$id];
    if ($actor['barangay'] !== null) { $sql .= ' AND r.barangay = ?'; $params[] = $actor['barangay']; }
    $stmt = $conn->prepare($sql); $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function saveSeniorClassification(PDO $conn, array $actor, array $input): void {
    if (!$actor['can_edit']) throw new RuntimeException('Only Main OSCA administrators can update classifications.', 403);
    foreach (['profile_id','activity_status','residence_status','effective_date','reason','version'] as $key) {
        if (isset($input[$key]) && !is_scalar($input[$key])) throw new InvalidArgumentException('Enter valid classification details.');
    }
    $id = trim((string)($input['profile_id'] ?? ''));
    $activity = (string)($input['activity_status'] ?? '');
    $residence = (string)($input['residence_status'] ?? '');
    $effective = (string)($input['effective_date'] ?? '');
    $reason = trim((string)($input['reason'] ?? ''));
    $version = filter_var($input['version'] ?? null, FILTER_VALIDATE_INT);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $effective, new DateTimeZone('Asia/Manila'));
    if ($id === '' || mb_strlen($id) > 255 || $version === false || $version === null || $version < 0
        || !in_array($activity, ['Active', 'Inactive'], true)
        || !in_array($residence, ['Within Pasig', 'Outside Pasig'], true)
        || !$date || $date->format('Y-m-d') !== $effective
        || $date > new DateTimeImmutable('today', new DateTimeZone('Asia/Manila'))
        || mb_strlen($reason) < 5 || mb_strlen($reason) > 1000) {
        throw new InvalidArgumentException('Choose valid classifications, a non-future effective date, and a reason of 5 to 1,000 characters.');
    }
    $conn->beginTransaction();
    try {
        // Lock the profile as well as its classification, including the first update.
        $lock = $conn->prepare('SELECT id_number FROM applications WHERE id_number = ? FOR UPDATE');
        $lock->execute([$id]);
        $profile = classificationProfile($conn, $id, $actor);
        if (!$profile) throw new RuntimeException('Senior profile is unavailable.', 404);
        if ($profile['activity_status'] === 'Deceased') throw new RuntimeException('Deceased status comes from the deceased registry and cannot be overwritten here.', 409);
        if ((int)$profile['version'] !== $version) throw new RuntimeException('This classification changed since you opened it. Reload the profile before saving.', 409);
        $values = [$activity, $residence, $effective, $reason, $actor['id'], $actor['name']];
        if ($version === 0) {
            $stmt = $conn->prepare('INSERT INTO senior_classifications
                (activity_status,residence_status,effective_date,reason,updated_by,updated_by_name,profile_id)
                VALUES (?,?,?,?,?,?,?)');
        } else {
            $stmt = $conn->prepare('UPDATE senior_classifications SET activity_status=?,residence_status=?,
                effective_date=?,reason=?,updated_by=?,updated_by_name=?,version=version+1,updated_at=CURRENT_TIMESTAMP WHERE profile_id=?');
        }
        $stmt->execute([...$values, $id]);
        $history = $conn->prepare('INSERT INTO senior_classification_history
            (profile_id,previous_activity_status,activity_status,previous_residence_status,residence_status,effective_date,reason,changed_by,changed_by_name)
            VALUES (?,?,?,?,?,?,?,?,?)');
        $history->execute([$id,$profile['activity_status'],$activity,$profile['residence_status'],$residence,$effective,$reason,$actor['id'],$actor['name']]);
        require_once __DIR__ . '/audit_logger.php';
        if (!logAudit($conn, 'UPDATE_SENIOR_CLASSIFICATION', "{$id}: {$activity}; {$residence}; effective {$effective}. {$reason}", $actor['id'], ['username'=>$actor['name'], 'role'=>$actor['role']])) {
            throw new RuntimeException('The classification could not be recorded in the audit history.');
        }
        $conn->commit();
    } catch (Throwable $e) { if ($conn->inTransaction()) $conn->rollBack(); throw $e; }
}
