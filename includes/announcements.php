<?php

function activeAnnouncements(PDO $conn, string $surface = 'public', int $limit = 5, string $barangay = ''): array
{
    if (!in_array($surface, ['public', 'staff'], true)) $surface = 'public';
    try {
        // Some long-lived Railway databases predate barangay targeting. Keep the
        // general feed visible while the deployment migration repairs that drift.
        $hasTargetBarangay = false;
        $hasCustomType = false;
        if ($conn->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $hasTargetBarangay = (bool)$conn->query(
                "SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'announcements'
                   AND COLUMN_NAME = 'target_barangay' LIMIT 1"
            )->fetchColumn();
            $hasCustomType = (bool)$conn->query(
                "SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'announcements'
                   AND COLUMN_NAME = 'custom_type' LIMIT 1"
            )->fetchColumn();
        }
        $audienceSql = "audience IN ('all', ?)";
        $params = [$surface];
        if ($surface === 'staff' && $barangay !== '' && $hasTargetBarangay) {
            $audienceSql = "(audience IN ('all', 'staff') OR (audience = 'barangay' AND target_barangay = ?))";
            $params = [$barangay];
        }
        $targetSelect = $hasTargetBarangay ? 'target_barangay' : 'NULL AS target_barangay';
        $customTypeSelect = $hasCustomType ? 'custom_type' : 'NULL AS custom_type';
        $stmt = $conn->prepare(
            "SELECT id, title, message, category, {$customTypeSelect}, audience, {$targetSelect}, starts_at, ends_at, created_at
             FROM announcements
             WHERE is_active = 1
               AND {$audienceSql}
               AND (starts_at IS NULL OR starts_at <= NOW())
               AND (ends_at IS NULL OR ends_at >= NOW())
             ORDER BY category = 'benefit' DESC, created_at DESC
             LIMIT " . max(1, min(20, $limit))
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Keep public pages usable before the migration is applied.
        error_log('Announcement lookup failed: ' . $e->getMessage());
        return [];
    }
}

function activeAnnouncementCount(PDO $conn): int
{
    try {
        return (int)$conn->query(
            "SELECT COUNT(*) FROM announcements WHERE is_active = 1
             AND (starts_at IS NULL OR starts_at <= NOW())
             AND (ends_at IS NULL OR ends_at >= NOW())"
        )->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}
