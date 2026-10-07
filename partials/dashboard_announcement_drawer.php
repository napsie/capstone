<?php
require_once __DIR__ . '/../includes/announcements.php';
$dashboardAnnouncementBarangay = trim((string)($_SESSION['barangay'] ?? ''));
$dashboardAnnouncementItems = activeAnnouncements($conn, 'staff', 10, $dashboardAnnouncementBarangay);
?>
<link rel="stylesheet" href="../assets/css/dashboard-announcement-drawer.css?v=1">
<div class="dashboard-announcement-backdrop" id="dashboardAnnouncementBackdrop" hidden></div>
<aside class="dashboard-announcement-drawer" id="dashboardAnnouncementDrawer" role="dialog" aria-modal="true" aria-labelledby="dashboardAnnouncementTitle" aria-hidden="true">
    <header class="dashboard-announcement-drawer__header">
        <div><span>Official updates</span><h2 id="dashboardAnnouncementTitle"><i class="fas fa-bullhorn" aria-hidden="true"></i> Announcements</h2><p>Announcements and benefit updates for your barangay.</p></div>
        <button id="dashboardAnnouncementClose" type="button" aria-label="Close announcements"><i class="fas fa-xmark" aria-hidden="true"></i></button>
    </header>
    <div class="dashboard-announcement-drawer__body">
        <?php if (!$dashboardAnnouncementItems): ?>
            <div class="announcement-drawer-empty"><i class="fas fa-bell-slash" aria-hidden="true"></i><strong>No active announcements</strong><p>New official updates will appear here.</p></div>
        <?php else: ?>
            <div class="announcement-drawer-list">
            <?php foreach ($dashboardAnnouncementItems as $item): ?>
                <article class="announcement-drawer-item announcement-drawer-item--<?= htmlspecialchars($item['category']) ?>">
                    <span class="announcement-drawer-badge"><?= $item['category'] === 'benefit' ? 'Benefit' : ($item['category'] === 'other' ? 'Other' : 'Announcement') ?></span>
                    <h3><?= htmlspecialchars($item['title']) ?></h3>
                    <p><?= nl2br(htmlspecialchars($item['message'])) ?></p>
                    <small><i class="far fa-clock" aria-hidden="true"></i> Published <?= htmlspecialchars(date('M j, Y g:i A', strtotime($item['created_at']))) ?></small>
                </article>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</aside>
<script src="../assets/js/dashboard-announcement-drawer.js?v=1" defer></script>
