<?php
declare(strict_types=1);

/** Keep the readiness condition shared by event creation and tracker delivery. */
function digitalIdIsReady(array $record): bool {
    $id = trim((string)($record['senior_id_no'] ?? ''));
    return ($record['application_type'] ?? '') === 'senior'
        && in_array($record['workflow_state'] ?? '', ['Verified', 'Approved', 'Released'], true)
        && empty($record['is_archived']) && empty($record['parent_senior_id']) && $id !== ''
        && !preg_match('/^OSCA-[0-9]{4}-[0-9A-F]{6}$/i', $id);
}

/** Called inside the transaction that makes the credential available. */
function recordDigitalIdReady(PDO $conn, string $applicationId): void {
    $stmt = $conn->prepare('SELECT application_type, workflow_state, senior_id_no, is_archived, parent_senior_id FROM applications WHERE id_number = ?');
    $stmt->execute([$applicationId]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$record || !digitalIdIsReady($record)) return;
    $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
    $suffix = $driver === 'mysql'
        ? ' ON DUPLICATE KEY UPDATE application_id = VALUES(application_id)'
        : ' ON CONFLICT (application_id) DO NOTHING';
    $insert = $conn->prepare('INSERT INTO digital_id_notifications (application_id) VALUES (?)' . $suffix);
    $insert->execute([$applicationId]);
}

/** Read only notices belonging to this permanent tracking reference. */
function trackerDigitalIdNotice(PDO $conn, string $token): ?array {
    if (!preg_match('/^PRX-[A-Z0-9]{4,12}$/', $token)) return null;
    $stmt = $conn->prepare("SELECT a.application_type, a.workflow_state, a.senior_id_no, a.is_archived, a.parent_senior_id, n.created_at
        FROM applications a JOIN digital_id_notifications n ON n.application_id = a.id_number
        WHERE a.application_type = 'senior' AND (a.parent_senior_id IS NULL OR a.parent_senior_id = '')
          AND (a.proxy_token = ? OR a.id_number = ?)
        ORDER BY CASE WHEN a.id_number = ? THEN 0 ELSE 1 END LIMIT 1");
    $stmt->execute([$token, $token, $token]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$record || !digitalIdIsReady($record)) return null;
    return ['title' => 'Your digital ID is ready',
        'message' => 'Your temporary digital Senior Citizen ID is now available. Select Senior Citizen ID in your tracker to view it.',
        'created_at' => (string)$record['created_at']];
}
