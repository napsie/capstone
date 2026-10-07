<?php

function activeAnnouncements(PDO $conn, string $surface = 'public', int $limit = 5, string $barangay = ''): array
{
    if (!in_array($surface, ['public', 'staff'], true)) $surface = 'public';
    try {
        $audienceSql = "audience IN ('all', ?)";
        $params = [$surface];
        if ($surface === 'staff' && $barangay !== '') {
            $audienceSql = "(audience IN ('all', 'staff') OR (audience = 'barangay' AND target_barangay = ?))";
            $params = [$barangay];
        }
        $stmt = $conn->prepare(
            "SELECT id, title, message, category, audience, target_barangay, starts_at, ends_at, created_at
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
        if ((string)$e->getCode() !== '42S02') error_log('Announcement lookup failed: ' . $e->getMessage());
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
