<?php
require_once __DIR__ . '/../includes/announcements.php';
$announcementSurface = $announcementSurface ?? 'public';
$announcementVariant = $announcementVariant ?? 'standard';
$announcementBarangay = $announcementBarangay ?? (($announcementSurface === 'staff') ? trim((string)($_SESSION['barangay'] ?? '')) : '');
$announcementItems = activeAnnouncements($conn, $announcementSurface, 5, $announcementBarangay);
$showAnnouncementControl = !empty($announcementItems) || in_array($announcementVariant, ['compact', 'header'], true);
?>
<?php if ($showAnnouncementControl): ?>
<?php if (in_array($announcementVariant, ['compact', 'header'], true)): ?>
<details class="central-announcements central-announcements--compact<?php echo $announcementVariant === 'header' ? ' central-announcements--header' : ''; ?>">
    <summary>
        <span class="central-announcements__summary-icon"><i class="fas fa-bullhorn" aria-hidden="true"></i></span>
        <span><strong>Announcement</strong><small><?php echo count($announcementItems); ?> active <?php echo count($announcementItems) === 1 ? 'notice' : 'notices'; ?></small></span>
        <span class="central-announcements__summary-action">View <i class="fas fa-chevron-down" aria-hidden="true"></i></span>
    </summary>
    <div class="central-announcements__list">
        <div class="central-announcements__popover-header">
            <div>
                <span>Official updates</span>
                <h2><i class="fas fa-bullhorn" aria-hidden="true"></i> Announcements</h2>
                <p>Official announcements and benefit updates.</p>
            </div>
            <button type="button" data-announcement-menu-close aria-label="Close announcements"><i class="fas fa-xmark" aria-hidden="true"></i></button>
        </div>
        <?php if (!$announcementItems): ?>
        <div class="central-announcements__empty" role="status">
            <i class="fas fa-bell-slash" aria-hidden="true"></i>
            <span><strong>No active announcements</strong><small>New official updates will appear here.</small></span>
        </div>
        <?php else: ?>
        <?php foreach ($announcementItems as $item): $announcementTypeLabel = $item['category'] === 'other' && !empty($item['custom_type']) ? $item['custom_type'] : ($item['category'] === 'benefit' ? 'Benefit' : ($item['category'] === 'other' ? 'Other' : 'Notice')); ?>
        <?php $publicAnnouncementDialogId = 'publicAnnouncementDetail' . (int)$item['id']; ?>
        <button type="button" class="central-announcement central-announcement--<?php echo htmlspecialchars($item['category']); ?> central-announcement__trigger" data-public-announcement="<?php echo $publicAnnouncementDialogId; ?>" aria-haspopup="dialog">
            <span class="central-announcement__badge"><?php echo htmlspecialchars($announcementTypeLabel); ?></span><span class="central-announcement__title"><strong><?php echo htmlspecialchars($item['title']); ?></strong><small><i class="fas fa-eye" aria-hidden="true"></i> View full details</small></span><i class="fas fa-chevron-right" aria-hidden="true"></i>
        </button>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</details>
<?php if (in_array($announcementVariant, ['compact', 'header'], true)): ?>
<?php foreach ($announcementItems as $item): $publicAnnouncementDialogId = 'publicAnnouncementDetail' . (int)$item['id']; $announcementTypeLabel = $item['category'] === 'other' && !empty($item['custom_type']) ? $item['custom_type'] : ($item['category'] === 'benefit' ? 'Benefit update' : ($item['category'] === 'other' ? 'Official update' : 'Official announcement')); ?>
<dialog class="public-announcement-dialog" id="<?php echo $publicAnnouncementDialogId; ?>">
    <header class="public-announcement-dialog__header"><div><span><?php echo htmlspecialchars($announcementTypeLabel); ?></span><h2><?php echo htmlspecialchars($item['title']); ?></h2></div><button type="button" data-public-announcement-close aria-label="Close announcement"><i class="fas fa-xmark" aria-hidden="true"></i></button></header>
    <div class="public-announcement-dialog__body"><p><?php echo nl2br(htmlspecialchars($item['message'])); ?></p><small><i class="far fa-clock" aria-hidden="true"></i> Published <?php echo htmlspecialchars(date('F j, Y g:i A', strtotime($item['created_at']))); ?></small></div>
</dialog>
<?php endforeach; ?>
<?php endif; ?>
<?php else: ?>
<section class="central-announcements" aria-labelledby="centralAnnouncementsTitle">
    <div class="central-announcements__heading">
        <span><i class="fas fa-bullhorn" aria-hidden="true"></i></span>
        <div><h2 id="centralAnnouncementsTitle">Announcements and Benefit Updates</h2><p>Official updates published through SENIORLINK.</p></div>
    </div>
    <div class="central-announcements__list">
        <?php foreach ($announcementItems as $item): $announcementTypeLabel = $item['category'] === 'other' && !empty($item['custom_type']) ? $item['custom_type'] : ($item['category'] === 'benefit' ? 'Benefit' : ($item['category'] === 'other' ? 'Other' : 'Announcement')); ?>
        <article class="central-announcement central-announcement--<?php echo htmlspecialchars($item['category']); ?>">
            <span class="central-announcement__badge"><?php echo htmlspecialchars($announcementTypeLabel); ?></span>
            <div><h3><?php echo htmlspecialchars($item['title']); ?></h3><p><?php echo nl2br(htmlspecialchars($item['message'])); ?></p></div>
        </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
<style>
.central-announcements{width:100%;margin:0 0 24px;padding:20px;border:1px solid #dbe5f1;border-radius:16px;background:#fff;box-shadow:0 8px 24px rgba(15,23,42,.07);box-sizing:border-box}.central-announcements__heading{display:flex;align-items:center;gap:12px;margin-bottom:14px}.central-announcements__heading>span{display:grid;place-items:center;width:42px;height:42px;border-radius:11px;background:#dbeafe;color:#1d4ed8}.central-announcements h2{margin:0;color:#0f172a;font-size:1.05rem}.central-announcements__heading p{margin:2px 0 0;color:#64748b;font-size:.78rem}.central-announcements__list{display:grid;gap:10px}.central-announcement{display:grid;grid-template-columns:auto minmax(0,1fr);align-items:start;gap:12px;padding:13px 14px;border:1px solid #dbe5f1;border-left:4px solid #2563eb;border-radius:10px;background:#f8fafc}.central-announcement--benefit{border-left-color:#16a34a;background:#f0fdf4}.central-announcement__badge{width:max-content;padding:4px 8px;border-radius:999px;background:#dbeafe;color:#1d4ed8;font-size:.65rem;font-weight:900;text-transform:uppercase}.central-announcement--benefit .central-announcement__badge{background:#dcfce7;color:#166534}.central-announcement h3{margin:0 0 3px;color:#172033;font-size:.9rem}.central-announcement p{margin:0;color:#526178;font-size:.8rem;line-height:1.5}.central-announcements--compact{margin:-5px 0 16px;padding:0;border-color:#cfe0f2;border-radius:12px;background:#f8fbff;box-shadow:none;overflow:hidden}.central-announcements--compact summary{display:grid;grid-template-columns:34px minmax(0,1fr) auto;align-items:center;gap:10px;min-height:54px;padding:9px 12px;cursor:pointer;list-style:none}.central-announcements--compact summary::-webkit-details-marker{display:none}.central-announcements__summary-icon{display:grid;place-items:center;width:34px;height:34px;border-radius:9px;background:#dbeafe;color:#2563eb}.central-announcements--compact summary strong,.central-announcements--compact summary small{display:block}.central-announcements--compact summary strong{color:#172033;font-size:.82rem}.central-announcements--compact summary small{margin-top:2px;color:#64748b;font-size:.68rem}.central-announcements__summary-action{color:#2563eb;font-size:.7rem;font-weight:800}.central-announcements__summary-action i{margin-left:4px;transition:transform .2s}.central-announcements--compact[open] .central-announcements__summary-action i{transform:rotate(180deg)}.central-announcements--compact .central-announcements__list{max-height:210px;padding:0 10px 10px;overflow:auto}.central-announcements--compact .central-announcement{grid-template-columns:auto minmax(0,1fr);padding:10px;gap:9px}.central-announcements--compact .central-announcement h3{font-size:.8rem}.central-announcements--compact .central-announcement p{font-size:.72rem}@media(max-width:600px){.central-announcements{padding:15px}.central-announcement{grid-template-columns:1fr}.central-announcements--compact{padding:0}.central-announcements--compact .central-announcement{grid-template-columns:1fr}.central-announcements__summary-action{font-size:0}.central-announcements__summary-action i{font-size:.72rem}}
</style>
<style>
.central-announcements--compact .central-announcement{display:block}.central-announcements--compact .central-announcement>summary{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:9px;cursor:pointer;list-style:none}.central-announcements--compact .central-announcement>summary::-webkit-details-marker{display:none}.central-announcement__title{min-width:0}.central-announcement__title strong,.central-announcement__title small{display:block}.central-announcement__title strong{overflow:hidden;color:#172033!important;font-size:.8rem;text-overflow:ellipsis;white-space:nowrap}.central-announcement__title small{margin-top:2px;color:#64748b!important;font-size:.66rem}.central-announcement>summary>i{color:#2563eb;font-size:.68rem;transition:transform .2s}.central-announcement[open]>summary>i{transform:rotate(180deg)}.central-announcement__details{margin-top:10px;padding-top:10px;border-top:1px solid #dbe5f1}.central-announcement__details p{margin:0;white-space:normal}@media(max-width:600px){.central-announcements--compact .central-announcement>summary{grid-template-columns:auto minmax(0,1fr) auto}.central-announcement__title strong{white-space:normal}}
.central-announcements--compact .central-announcement__trigger{display:grid;width:100%;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;color:#172033;font:inherit;text-align:left;cursor:pointer}.central-announcements--compact .central-announcement__trigger:hover,.central-announcements--compact .central-announcement__trigger:focus-visible{border-color:#93c5fd;background:#eff6ff;outline:none}.central-announcements--compact .central-announcement__trigger:focus-visible{box-shadow:0 0 0 3px rgba(37,99,235,.22)}.central-announcements--compact .central-announcement__trigger>i{color:#2563eb;font-size:.7rem}
.central-announcements__popover-header{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin:-10px -10px 2px;padding:20px 20px 18px;border-radius:13px 13px 0 0;color:#fff;background:linear-gradient(135deg,#0f4c81,#2563eb)}.central-announcements__popover-header>div>span{display:block;color:#bfdbfe;font-size:.66rem;font-weight:900;letter-spacing:.09em;text-transform:uppercase}.central-announcements__popover-header h2{display:flex;align-items:center;gap:9px;margin:6px 0 3px!important;color:#fff!important;font-size:1.18rem!important;line-height:1.25}.central-announcements__popover-header p{margin:0!important;color:rgba(255,255,255,.82)!important;font-size:.72rem!important;line-height:1.4!important}.central-announcements__popover-header button{display:grid;place-items:center;width:40px;height:40px;flex:0 0 40px;border:0;border-radius:10px;background:rgba(255,255,255,.14);color:#fff;cursor:pointer}.central-announcements__popover-header button:hover{background:rgba(255,255,255,.24)}.central-announcements__popover-header button:focus-visible{outline:3px solid rgba(191,219,254,.75);outline-offset:2px}
.central-announcements--compact:not(.central-announcements--header){position:relative;overflow:visible;z-index:30}.central-announcements--compact:not(.central-announcements--header)>.central-announcements__list{position:absolute;top:calc(100% + 8px);right:0;left:0;z-index:100;width:auto;max-height:min(340px,calc(100dvh - 160px));padding:10px;overflow-y:auto;border:1px solid #dbe5f1;border-radius:13px;background:#fff;box-shadow:0 18px 45px rgba(15,23,42,.24)}.central-announcements--compact:not(.central-announcements--header):not([open])>.central-announcements__list{display:none}@media(max-width:600px){.central-announcements--compact:not(.central-announcements--header)>.central-announcements__list{right:-4px;left:-4px;max-height:calc(100dvh - 140px)}}
.central-announcements__empty{display:flex;align-items:center;gap:11px;padding:16px;border:1px dashed #cbd5e1;border-radius:10px;background:#f8fafc;color:#64748b}.central-announcements__empty>i{font-size:1.1rem}.central-announcements__empty span,.central-announcements__empty strong,.central-announcements__empty small{display:block}.central-announcements__empty strong{color:#334155;font-size:.8rem}.central-announcements__empty small{margin-top:3px;color:#64748b;font-size:.7rem}
</style>
<?php if (in_array($announcementVariant, ['compact', 'header'], true)): ?>
<style>
.site-header .central-announcements--header{position:relative;width:auto;min-width:190px;margin:0 0 0 auto;padding:0;overflow:visible;border:1px solid rgba(255,255,255,.18);border-radius:11px;background:rgba(255,255,255,.08);box-shadow:none}.site-header .central-announcements--header summary{grid-template-columns:34px minmax(0,1fr) auto;min-height:48px;padding:6px 10px;color:#fff}.site-header .central-announcements--header summary:hover{background:rgba(255,255,255,.08)}.site-header .central-announcements--header summary:focus-visible{outline:3px solid rgba(140,225,175,.45);outline-offset:2px}.site-header .central-announcements--header .central-announcements__summary-icon{background:rgba(71,207,130,.14);color:#8ce1af}.site-header .central-announcements--header summary strong{color:#fff}.site-header .central-announcements--header summary small{color:rgba(255,255,255,.68)}.site-header .central-announcements--header .central-announcements__summary-action{color:#a7f3d0}.site-header .central-announcements--header .central-announcements__list{position:absolute;top:calc(100% + 10px);right:0;width:min(430px,calc(100vw - 30px));max-height:min(360px,calc(100dvh - 105px));padding:10px;overflow:auto;border:1px solid #dbe5f1;border-radius:13px;background:#fff;box-shadow:0 18px 45px rgba(3,17,36,.26)}.site-header .central-announcements--header .central-announcement{text-align:left}.site-header .central-announcements--header .central-announcement h3{color:#172033}.site-header .central-announcements--header .central-announcement p{color:#526178}@media(max-width:600px){.site-header .central-announcements--header{min-width:0}.site-header .central-announcements--header summary{grid-template-columns:32px auto;gap:7px;min-height:44px;padding:5px 9px}.site-header .central-announcements--header summary>span:nth-child(2) small,.site-header .central-announcements--header .central-announcements__summary-action{display:none}.site-header .central-announcements--header summary strong{font-size:.74rem}.site-header .central-announcements--header .central-announcements__list{position:fixed;top:78px;right:15px;left:15px;width:auto;max-height:calc(100dvh - 96px)}.site-header .central-announcements--header .central-announcement{grid-template-columns:1fr}}@media(max-width:390px){.site-header .central-announcements--header summary{grid-template-columns:30px auto}.site-header .central-announcements--header .central-announcements__summary-icon{width:30px;height:30px}.site-header .central-announcements--header summary strong{font-size:.7rem}}
.site-header .central-announcements--header .central-announcement>summary{grid-template-columns:auto minmax(0,1fr) auto;min-height:0;padding:0;color:#172033;background:transparent}.site-header .central-announcements--header .central-announcement>summary:hover{background:transparent}.site-header .central-announcements--header .central-announcement>summary:focus-visible{outline:3px solid rgba(37,99,235,.25);outline-offset:3px}.site-header .central-announcements--header .central-announcement__title{display:grid;align-content:center;gap:3px}.site-header .central-announcements--header .central-announcement__title strong,.site-header .central-announcements--header .central-announcement__title small{margin:0;line-height:1.25}.site-header .central-announcements--header .central-announcement__details{grid-column:1/-1}
.site-header .central-announcements--header .central-announcement__trigger{width:100%;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;color:#172033;font:inherit;text-align:left;cursor:pointer}.site-header .central-announcements--header .central-announcement__trigger:hover,.site-header .central-announcements--header .central-announcement__trigger:focus-visible{border-color:#93c5fd;background:#eff6ff;outline:none}.site-header .central-announcements--header .central-announcement__trigger:focus-visible{box-shadow:0 0 0 3px rgba(37,99,235,.22)}.site-header .central-announcements--header .central-announcement__trigger>i{color:#2563eb;font-size:.7rem}
.public-announcement-dialog{position:fixed;inset:0;width:min(620px,calc(100% - 24px));max-height:calc(100dvh - 30px);margin:auto;padding:0;overflow:hidden;border:0;border-radius:18px;background:#fff;box-shadow:0 24px 70px rgba(15,23,42,.34)}.public-announcement-dialog[open]{display:flex;flex-direction:column}.public-announcement-dialog::backdrop{background:rgba(15,23,42,.72)}.public-announcement-dialog__header{display:flex;flex:0 0 auto;align-items:flex-start;justify-content:space-between;gap:16px;padding:21px 22px;color:#fff;background:linear-gradient(135deg,#0f4c81,#2563eb)}.public-announcement-dialog__header span{color:#bfdbfe;font-size:.68rem;font-weight:900;letter-spacing:.08em;text-transform:uppercase}.public-announcement-dialog__header h2{margin:5px 0 0;color:#fff;font-size:1.25rem;line-height:1.3}.public-announcement-dialog__header button{width:40px;height:40px;flex:0 0 40px;border:0;border-radius:10px;background:rgba(255,255,255,.14);color:#fff;cursor:pointer}.public-announcement-dialog__header button:hover{background:rgba(255,255,255,.24)}.public-announcement-dialog__body{min-height:0;flex:1 1 auto;padding:24px;overflow-y:auto;overscroll-behavior:contain}.public-announcement-dialog__body p{margin:0;color:#334155;font-size:.92rem;line-height:1.7;overflow-wrap:anywhere}.public-announcement-dialog__body small{display:block;margin-top:20px;color:#64748b}.public-announcement-dialog button:focus-visible{outline:3px solid rgba(147,197,253,.7);outline-offset:2px}@media(max-width:520px){.public-announcement-dialog{width:calc(100% - 20px);max-height:calc(100dvh - 20px)}.public-announcement-dialog__header,.public-announcement-dialog__body{padding:18px}}
</style>
<script>
document.addEventListener('DOMContentLoaded',function(){
    document.querySelectorAll('[data-announcement-menu-close]').forEach(function(button){
        button.addEventListener('click',function(){button.closest('details.central-announcements')?.removeAttribute('open');});
    });
    document.querySelectorAll('[data-public-announcement]').forEach(function(trigger){
        var dialog=document.getElementById(trigger.dataset.publicAnnouncement);
        if(!dialog)return;
        trigger.addEventListener('click',function(){var menu=trigger.closest('details.central-announcements');if(menu)menu.removeAttribute('open');dialog._returnFocus=trigger;dialog.showModal();});
        dialog.querySelectorAll('[data-public-announcement-close]').forEach(function(button){button.addEventListener('click',function(){dialog.close();});});
        dialog.addEventListener('click',function(event){if(event.target===dialog)dialog.close();});
        dialog.addEventListener('close',function(){dialog._returnFocus?.focus();});
    });
    document.querySelectorAll('details.central-announcements--compact:not(.central-announcements--header)').forEach(function(menu){
        document.addEventListener('click',function(event){if(menu.open&&!menu.contains(event.target))menu.removeAttribute('open');});
        menu.addEventListener('keydown',function(event){if(event.key==='Escape'){menu.removeAttribute('open');menu.querySelector(':scope > summary')?.focus();}});
    });
});
</script>
<?php endif; ?>
<?php endif; ?>
