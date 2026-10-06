<?php
require_once __DIR__ . '/../includes/announcements.php';
$announcementSurface = $announcementSurface ?? 'public';
$announcementVariant = $announcementVariant ?? 'standard';
$announcementItems = activeAnnouncements($conn, $announcementSurface, 5);
?>
<?php if ($announcementItems): ?>
<?php if ($announcementVariant === 'compact'): ?>
<details class="central-announcements central-announcements--compact">
    <summary>
        <span class="central-announcements__summary-icon"><i class="fas fa-bullhorn" aria-hidden="true"></i></span>
        <span><strong>Latest updates</strong><small><?php echo count($announcementItems); ?> active <?php echo count($announcementItems) === 1 ? 'notice' : 'notices'; ?></small></span>
        <span class="central-announcements__summary-action">View <i class="fas fa-chevron-down" aria-hidden="true"></i></span>
    </summary>
    <div class="central-announcements__list">
        <?php foreach ($announcementItems as $item): ?>
        <article class="central-announcement central-announcement--<?php echo htmlspecialchars($item['category']); ?>">
            <span class="central-announcement__badge"><?php echo $item['category'] === 'benefit' ? 'Benefit' : 'Notice'; ?></span>
            <div><h3><?php echo htmlspecialchars($item['title']); ?></h3><p><?php echo nl2br(htmlspecialchars($item['message'])); ?></p></div>
        </article>
        <?php endforeach; ?>
    </div>
</details>
<?php else: ?>
<section class="central-announcements" aria-labelledby="centralAnnouncementsTitle">
    <div class="central-announcements__heading">
        <span><i class="fas fa-bullhorn" aria-hidden="true"></i></span>
        <div><h2 id="centralAnnouncementsTitle">Announcements and Benefit Updates</h2><p>Official updates published through SENIORLINK.</p></div>
    </div>
    <div class="central-announcements__list">
        <?php foreach ($announcementItems as $item): ?>
        <article class="central-announcement central-announcement--<?php echo htmlspecialchars($item['category']); ?>">
            <span class="central-announcement__badge"><?php echo $item['category'] === 'benefit' ? 'Benefit' : 'Announcement'; ?></span>
            <div><h3><?php echo htmlspecialchars($item['title']); ?></h3><p><?php echo nl2br(htmlspecialchars($item['message'])); ?></p></div>
        </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
<style>
.central-announcements{width:100%;margin:0 0 24px;padding:20px;border:1px solid #dbe5f1;border-radius:16px;background:#fff;box-shadow:0 8px 24px rgba(15,23,42,.07);box-sizing:border-box}.central-announcements__heading{display:flex;align-items:center;gap:12px;margin-bottom:14px}.central-announcements__heading>span{display:grid;place-items:center;width:42px;height:42px;border-radius:11px;background:#dbeafe;color:#1d4ed8}.central-announcements h2{margin:0;color:#0f172a;font-size:1.05rem}.central-announcements__heading p{margin:2px 0 0;color:#64748b;font-size:.78rem}.central-announcements__list{display:grid;gap:10px}.central-announcement{display:grid;grid-template-columns:auto minmax(0,1fr);align-items:start;gap:12px;padding:13px 14px;border:1px solid #dbe5f1;border-left:4px solid #2563eb;border-radius:10px;background:#f8fafc}.central-announcement--benefit{border-left-color:#16a34a;background:#f0fdf4}.central-announcement__badge{width:max-content;padding:4px 8px;border-radius:999px;background:#dbeafe;color:#1d4ed8;font-size:.65rem;font-weight:900;text-transform:uppercase}.central-announcement--benefit .central-announcement__badge{background:#dcfce7;color:#166534}.central-announcement h3{margin:0 0 3px;color:#172033;font-size:.9rem}.central-announcement p{margin:0;color:#526178;font-size:.8rem;line-height:1.5}.central-announcements--compact{margin:-5px 0 16px;padding:0;border-color:#cfe0f2;border-radius:12px;background:#f8fbff;box-shadow:none;overflow:hidden}.central-announcements--compact summary{display:grid;grid-template-columns:34px minmax(0,1fr) auto;align-items:center;gap:10px;min-height:54px;padding:9px 12px;cursor:pointer;list-style:none}.central-announcements--compact summary::-webkit-details-marker{display:none}.central-announcements__summary-icon{display:grid;place-items:center;width:34px;height:34px;border-radius:9px;background:#dbeafe;color:#2563eb}.central-announcements--compact summary strong,.central-announcements--compact summary small{display:block}.central-announcements--compact summary strong{color:#172033;font-size:.82rem}.central-announcements--compact summary small{margin-top:2px;color:#64748b;font-size:.68rem}.central-announcements__summary-action{color:#2563eb;font-size:.7rem;font-weight:800}.central-announcements__summary-action i{margin-left:4px;transition:transform .2s}.central-announcements--compact[open] .central-announcements__summary-action i{transform:rotate(180deg)}.central-announcements--compact .central-announcements__list{max-height:210px;padding:0 10px 10px;overflow:auto}.central-announcements--compact .central-announcement{grid-template-columns:auto minmax(0,1fr);padding:10px;gap:9px}.central-announcements--compact .central-announcement h3{font-size:.8rem}.central-announcements--compact .central-announcement p{font-size:.72rem}@media(max-width:600px){.central-announcements{padding:15px}.central-announcement{grid-template-columns:1fr}.central-announcements--compact{padding:0}.central-announcements--compact .central-announcement{grid-template-columns:1fr}.central-announcements__summary-action{font-size:0}.central-announcements__summary-action i{font-size:.72rem}}
</style>
<?php endif; ?>
