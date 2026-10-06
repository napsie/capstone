<?php

function activeAnnouncements(PDO $conn, string $surface = 'public', int $limit = 5): array
{
    if (!in_array($surface, ['public', 'staff'], true)) $surface = 'public';
    try {
        $stmt = $conn->prepare(
            "SELECT id, title, message, category, audience, starts_at, ends_at, created_at
             FROM announcements
             WHERE is_active = 1
               AND audience IN ('all', ?)
               AND (starts_at IS NULL OR starts_at <= NOW())
               AND (ends_at IS NULL OR ends_at >= NOW())
             ORDER BY category = 'benefit' DESC, created_at DESC
             LIMIT " . max(1, min(20, $limit))
        );
        $stmt->execute([$surface]);
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
