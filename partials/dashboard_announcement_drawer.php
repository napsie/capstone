<?php
require_once __DIR__ . '/../includes/announcements.php';
$dashboardAnnouncementBarangay = trim((string)($_SESSION['barangay'] ?? ''));
$dashboardAnnouncementItems = activeAnnouncements($conn, 'staff', 10, $dashboardAnnouncementBarangay);
$dashboardAnnouncementIsDepartment = in_array((string)($_SESSION['role'] ?? ''), ['department_admin', 'super_admin'], true);
?>
<link rel="stylesheet" href="../assets/css/dashboard-announcement-drawer.css?v=1">
<div class="dashboard-announcement-backdrop" id="dashboardAnnouncementBackdrop" hidden></div>
<aside class="dashboard-announcement-drawer" id="dashboardAnnouncementDrawer" role="dialog" aria-modal="true" aria-labelledby="dashboardAnnouncementTitle" aria-hidden="true">
    <header class="dashboard-announcement-drawer__header">
        <div><span>Official updates</span><h2 id="dashboardAnnouncementTitle"><i class="fas fa-bullhorn" aria-hidden="true"></i> Announcements</h2><p><?= $dashboardAnnouncementIsDepartment ? 'Citywide announcements and benefit updates.' : 'Announcements and benefit updates for your barangay.' ?></p></div>
        <button id="dashboardAnnouncementClose" type="button" aria-label="Close announcements"><i class="fas fa-xmark" aria-hidden="true"></i></button>
    </header>
    <div class="dashboard-announcement-drawer__body">
        <?php if (!$dashboardAnnouncementItems): ?>
            <div class="announcement-drawer-empty"><i class="fas fa-bell-slash" aria-hidden="true"></i><strong>No active announcements</strong><p>New official updates will appear here.</p></div>
        <?php else: ?>
            <div class="announcement-drawer-list">
            <?php foreach ($dashboardAnnouncementItems as $item): $announcementDialogId = 'announcementDetail' . (int)$item['id']; ?>
                <button type="button" class="announcement-drawer-item announcement-drawer-item--<?= htmlspecialchars($item['category']) ?>" data-announcement-detail="<?= $announcementDialogId ?>" aria-haspopup="dialog">
                    <span class="announcement-drawer-badge"><?= $item['category'] === 'benefit' ? 'Benefit' : ($item['category'] === 'other' ? 'Other' : 'Announcement') ?></span>
                    <h3><?= htmlspecialchars($item['title']) ?></h3>
                    <p><?= htmlspecialchars($item['message']) ?></p>
                    <small><i class="far fa-clock" aria-hidden="true"></i> Published <?= htmlspecialchars(date('M j, Y g:i A', strtotime($item['created_at']))) ?></small>
                    <span class="announcement-drawer-view"><i class="fas fa-eye" aria-hidden="true"></i> View information</span>
                </button>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</aside>
<?php foreach ($dashboardAnnouncementItems as $item): $announcementDialogId = 'announcementDetail' . (int)$item['id']; ?>
<dialog class="announcement-detail-dialog" id="<?= $announcementDialogId ?>">
    <div class="announcement-detail-header"><div><span><?= $item['category'] === 'benefit' ? 'Benefit update' : ($item['category'] === 'other' ? 'Official update' : 'Official announcement') ?></span><h2><?= htmlspecialchars($item['title']) ?></h2></div><button type="button" data-announcement-detail-close aria-label="Close announcement"><i class="fas fa-xmark" aria-hidden="true"></i></button></div>
    <div class="announcement-detail-body"><p><?= nl2br(htmlspecialchars($item['message'])) ?></p><small><i class="far fa-clock" aria-hidden="true"></i> Published <?= htmlspecialchars(date('F j, Y g:i A', strtotime($item['created_at']))) ?></small></div>
    <div class="announcement-detail-footer"><button type="button" data-announcement-detail-close>Close</button></div>
</dialog>
<?php endforeach; ?>
<script src="../assets/js/dashboard-announcement-drawer.js?v=2" defer></script>
