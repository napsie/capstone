<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/request_security.php';
require_once '../includes/audit_logger.php';
require_once '../includes/barangays_list.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['department_admin', 'super_admin'], true)) {
    header('Location: ../index.php');
    exit;
}
if (empty($_SESSION['announcement_csrf'])) $_SESSION['announcement_csrf'] = bin2hex(random_bytes(32));
$message = (string)($_SESSION['announcement_notice']['message'] ?? '');
$error = (string)($_SESSION['announcement_notice']['error'] ?? '');
unset($_SESSION['announcement_notice']);

$announcementTargetConfig = json_encode([
    'barangays' => $barangays_list,
    'audience' => (string)($_POST['audience'] ?? 'all'),
    'barangay' => (string)($_POST['target_barangay'] ?? ''),
    'category' => (string)($_POST['category'] ?? 'announcement'),
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$announcementAuditConfig = '{}';
$announcementProfilePicture = basename((string)($_SESSION['profile_picture'] ?? 'default.jpg'));
$announcementProfilePath = '../images/profile_pictures/' . $announcementProfilePicture;
if (!file_exists($announcementProfilePath) || is_dir($announcementProfilePath)) {
    $announcementProfilePath = '../images/profile_pictures/default.jpg';
}
$announcementAdminName = trim((string)(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')));
if ($announcementAdminName === '') $announcementAdminName = (string)($_SESSION['username'] ?? 'Administrator');
$announcementAdminRole = ($_SESSION['role'] ?? '') === 'super_admin'
    ? 'System Admin - Pasig City'
    : 'Department Admin - Pasig City';
$announcementHeader = '<header class="page-header">'
    . '<div class="page-header-left"><div class="greeting">Official centralized updates and benefit information</div>'
    . '<h1>Announcements <span>&amp; Benefits</span></h1></div>'
    . '<div class="header-actions"><div class="header-user">'
    . '<img src="' . htmlspecialchars($announcementProfilePath, ENT_QUOTES) . '" alt="Profile picture">'
    . '<div class="header-user-info"><h3>' . htmlspecialchars($announcementAdminName) . '</h3>'
    . '<p>' . htmlspecialchars($announcementAdminRole) . '</p></div></div></div></header>';
ob_start(static function (string $html) use ($announcementTargetConfig, $announcementHeader, &$announcementAuditConfig): string {
    $scripts = '<script>window.announcementTargetConfig=' . $announcementTargetConfig
        . ';window.announcementAuditConfig=' . $announcementAuditConfig . ';</script>'
        . '<script src="../assets/js/announcement-target.js?v=5"></script>';
    $html = str_replace('announcements.css?v=1', 'announcements.css?v=8', $html);
    $html = str_replace(
        'Choose where it appears and optionally schedule its visibility.',
        'Choose where this update will appear.',
        $html
    );
    $html = str_replace('</head>', '<link rel="stylesheet" href="../assets/css/system-header.css?v=1"></head>', $html);
    $html = preg_replace(
        '/<main class="main-content"><div class="shell"><header class="page-head">.*?<\/header>/s',
        '<main class="main-content">' . $announcementHeader . '<div class="shell">',
        $html,
        1
    );
    $html = preg_replace(
        '/<div class="two"><div class="field"><label for="starts_at">.*?<\/div><\/div><button class="publish"/s',
        '<button class="publish"',
        $html,
        1
    );
    return str_replace('</body>', $scripts . '</body>', $html);
});

$announcementDestination = static function (string $audience, ?string $targetBarangay = null): string {
    if ($audience === 'barangay') return 'Barangay ' . ($targetBarangay ?: 'not specified');
    if ($audience === 'public') return 'Senior Portal Public';
    if ($audience === 'staff') return 'Department and all barangay dashboards';
    return 'Everyone';
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireSameOriginMutation();
    if (!hash_equals($_SESSION['announcement_csrf'], (string)($_POST['csrf_token'] ?? ''))) {
        $error = 'The request expired. Refresh the page and try again.';
    } elseif (($_POST['action'] ?? '') === 'publish') {
        $title = trim((string)($_POST['title'] ?? ''));
        $body = trim((string)($_POST['message'] ?? ''));
        $category = in_array($_POST['category'] ?? '', ['announcement', 'benefit', 'other'], true) ? $_POST['category'] : 'announcement';
        $audience = in_array($_POST['audience'] ?? '', ['all', 'public', 'staff', 'barangay'], true) ? $_POST['audience'] : 'all';
        $targetBarangay = $audience === 'barangay' ? trim((string)($_POST['target_barangay'] ?? '')) : null;
        $startsAt = null;
        $endsAt = null;
        if ($title === '' || mb_strlen($title) > 140 || $body === '' || mb_strlen($body) > 2000) {
            $error = 'Enter a title up to 140 characters and a message up to 2,000 characters.';
        } elseif ($audience === 'barangay' && !in_array($targetBarangay, $barangays_list, true)) {
            $error = 'Select a valid barangay for this update.';
        } else {
            try {
                $stmt = $conn->prepare('INSERT INTO announcements (title, message, category, audience, target_barangay, starts_at, ends_at, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$title, $body, $category, $audience, $targetBarangay, $startsAt, $endsAt, (int)$_SESSION['user_id']]);
                $destination = $announcementDestination($audience, $targetBarangay);
                logAudit(
                    $conn,
                    'PUBLISH_ANNOUNCEMENT',
                    "Published {$category} update '{$title}' to {$destination}. Status: active."
                );
                $_SESSION['announcement_notice'] = ['message' => 'Published successfully. The update is now visible in the selected locations.'];
                header('Location: announcements.php');
                exit;
            } catch (PDOException $e) {
                error_log('Announcement publish failed: ' . $e->getMessage());
                $error = 'The update could not be published. Please try again.';
            }
        }
    } elseif (($_POST['action'] ?? '') === 'toggle') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) $error = 'Invalid announcement.';
        else {
            try {
                $lookup = $conn->prepare('SELECT title, category, audience, target_barangay, is_active FROM announcements WHERE id = ? LIMIT 1');
                $lookup->execute([$id]);
                $announcement = $lookup->fetch(PDO::FETCH_ASSOC);
                if (!$announcement) throw new RuntimeException('Announcement not found.');
                $newActiveState = (int)!((bool)$announcement['is_active']);
                $stmt = $conn->prepare('UPDATE announcements SET is_active = ? WHERE id = ?');
                $stmt->execute([$newActiveState, $id]);
                $destination = $announcementDestination((string)$announcement['audience'], $announcement['target_barangay'] ?? null);
                $stateLabel = $newActiveState === 1 ? 'active (republished)' : 'unpublished';
                logAudit(
                    $conn,
                    'TOGGLE_ANNOUNCEMENT',
                    "Changed {$announcement['category']} update '{$announcement['title']}' for {$destination}. Status: {$stateLabel}."
                );
                $_SESSION['announcement_notice'] = ['message' => 'Announcement visibility updated.'];
                header('Location: announcements.php');
                exit;
            } catch (Throwable $e) {
                error_log('Announcement toggle failed: ' . $e->getMessage());
                $error = 'The announcement could not be updated.';
            }
        }
    } elseif (($_POST['action'] ?? '') === 'delete') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) $error = 'Invalid announcement.';
        else {
            try {
                $lookup = $conn->prepare('SELECT title, category, audience, target_barangay FROM announcements WHERE id = ? LIMIT 1');
                $lookup->execute([$id]);
                $announcement = $lookup->fetch(PDO::FETCH_ASSOC);
                if (!$announcement) throw new RuntimeException('Announcement not found.');

                $stmt = $conn->prepare('DELETE FROM announcements WHERE id = ?');
                $stmt->execute([$id]);
                if ($stmt->rowCount() !== 1) throw new RuntimeException('Announcement was not deleted.');

                $destination = $announcementDestination((string)$announcement['audience'], $announcement['target_barangay'] ?? null);
                logAudit(
                    $conn,
                    'DELETE_ANNOUNCEMENT',
                    "Permanently deleted {$announcement['category']} update '{$announcement['title']}' previously displayed to {$destination}."
                );
                $_SESSION['announcement_notice'] = ['message' => 'Announcement deleted permanently.'];
                header('Location: announcements.php');
                exit;
            } catch (Throwable $e) {
                error_log('Announcement delete failed: ' . $e->getMessage());
                $error = 'The announcement could not be deleted.';
            }
        }
    }
}

$items = $conn->query('SELECT a.*, TRIM(CONCAT(COALESCE(u.first_name, ""), " ", COALESCE(u.last_name, ""))) author, u.username author_username FROM announcements a LEFT JOIN users u ON u.id = a.created_by ORDER BY a.created_at DESC')->fetchAll();
$announcementAuditRows = [];
foreach ($items as $announcementItem) {
    $authorName = trim((string)($announcementItem['author'] ?? ''));
    if ($authorName === '') $authorName = trim((string)($announcementItem['author_username'] ?? ''));
    if ($authorName === '') $authorName = 'System administrator';
    $announcementAuditRows[(string)$announcementItem['id']] = ['author' => $authorName];
}
$announcementAuditConfig = json_encode(
    $announcementAuditRows,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?: '{}';
$audienceLabels = ['all' => 'Everyone', 'public' => 'Senior Portal Public', 'staff' => 'Department and all barangay dashboards', 'barangay' => 'Specific barangay'];
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Centralized Announcements &amp; Benefit Updates — SENIORLINK</title><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"><link rel="stylesheet" href="../assets/css/department-sidebar.css?v=6"><link rel="stylesheet" href="../assets/css/seniorlink-ui.css?v=23"><link rel="stylesheet" href="../assets/css/announcements.css?v=1"><style>
*{box-sizing:border-box}body{margin:0;background:#f1f5f9;color:#172033;font-family:Inter,"Segoe UI",Arial,sans-serif}.main-content{width:calc(100% - 252px);margin-left:252px;padding:24px clamp(18px,2vw,30px);min-height:100vh}.shell{width:100%;max-width:1500px;margin:auto}.page-head{display:flex;justify-content:space-between;gap:16px;align-items:end;margin-bottom:16px}.eyebrow{color:#2563eb;font-size:.72rem;font-weight:900;letter-spacing:.08em;text-transform:uppercase}.page-head h1{margin:3px 0 0;font-size:clamp(1.55rem,2vw,2rem)}.page-head p{margin:0;color:#64748b}.delivery{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:16px}.delivery-card{display:flex;align-items:flex-start;gap:11px;padding:13px 14px;border:1px solid #dbe5f1;border-radius:13px;background:#fff;box-shadow:0 4px 15px rgba(15,23,42,.04)}.delivery-card>i{display:grid;place-items:center;width:36px;height:36px;flex:0 0 36px;border-radius:10px;background:#eff6ff;color:#2563eb}.delivery-card strong,.delivery-card small{display:block}.delivery-card strong{font-size:.86rem}.delivery-card small{margin-top:2px;color:#64748b;font-size:.73rem;line-height:1.35}.grid{display:grid;grid-template-columns:minmax(420px,.82fr) minmax(480px,1.18fr);gap:18px;align-items:start}.card{min-width:0;padding:20px;border:1px solid #d9e3ee;border-radius:15px;background:#fff;box-shadow:0 8px 24px rgba(15,23,42,.06)}.card h2{margin:0 0 4px;font-size:1.08rem}.card>p{margin:0 0 14px;color:#64748b;font-size:.84rem}.field{min-width:0;margin-bottom:11px}.field label{display:block;margin-bottom:5px;color:#475569;font-size:.73rem;font-weight:900;text-transform:uppercase}.field input,.field select,.field textarea{width:100%;min-width:0;min-height:46px;padding:10px 12px;border:1px solid #cbd5e1;border-radius:9px;background:#fff;font:inherit}.field input:focus,.field select:focus,.field textarea:focus{outline:3px solid rgba(37,99,235,.14);border-color:#2563eb}.field textarea{min-height:104px;resize:vertical}.field-help{display:block;margin-top:5px;color:#64748b;font-size:.7rem;line-height:1.35}.two{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:12px}.publish{width:100%;min-height:46px;padding:11px;border:0;border-radius:9px;background:#2563eb;color:#fff;font-weight:800;cursor:pointer}.publish:hover{background:#1d4ed8}.notice{margin-bottom:14px;padding:12px 14px;border-radius:9px}.notice.ok{background:#ecfdf5;color:#166534}.notice.error{background:#fef2f2;color:#991b1b}.items{display:grid;gap:11px;max-height:620px;overflow:auto;padding-right:3px}.empty{padding:34px 24px;text-align:center;border:1px dashed #cbd5e1;border-radius:11px;color:#64748b;background:#f8fafc}.item{padding:14px;border:1px solid #dbe4ef;border-radius:11px;background:#f8fafc}.item.off{opacity:.62}.item-top{display:flex;justify-content:space-between;gap:12px}.item h3{margin:7px 0 5px;font-size:.95rem}.item p{margin:0;color:#526178;font-size:.82rem;line-height:1.5}.meta{margin-top:9px;color:#64748b;font-size:.71rem}.badge{display:inline-block;padding:4px 8px;border-radius:999px;background:#dbeafe;color:#1d4ed8;font-size:.66rem;font-weight:900;text-transform:uppercase}.badge.benefit{background:#dcfce7;color:#166534}.item-actions{display:flex;align-items:center;gap:7px;flex-wrap:wrap;justify-content:flex-end}.item-actions form{margin:0}.toggle,.delete-announcement{min-height:36px;border-radius:7px;padding:7px 10px;font:inherit;font-size:.75rem;font-weight:800;cursor:pointer}.toggle{border:1px solid #cbd5e1;background:#fff;color:#334155}.delete-announcement{border:1px solid #fecaca;background:#fff7f7;color:#b91c1c}.delete-announcement:hover{border-color:#f87171;background:#fee2e2}.toggle:focus-visible,.delete-announcement:focus-visible{outline:3px solid rgba(37,99,235,.22);outline-offset:2px}@media(max-width:1120px){.grid{grid-template-columns:1fr}.items{max-height:none}}@media(max-width:900px){.main-content{width:100%;margin:0;padding:80px 12px 24px}.delivery{grid-template-columns:1fr}.page-head{align-items:flex-start;flex-direction:column}}@media(max-width:560px){.two{grid-template-columns:1fr}.card{padding:17px}.page-head p{font-size:.82rem}.item-top{align-items:flex-start;flex-direction:column}.item-actions{width:100%;justify-content:flex-end}}
</style></head><body><?php include '../partials/department_sidebar.php'; ?><main class="main-content"><div class="shell"><header class="page-head"><div><span class="eyebrow">Official centralized updates</span><h1>Announcements &amp; Benefits</h1></div><p>Publish once; keep every barangay informed.</p></header><?php if($message): ?><div class="notice ok" role="status"><i class="fas fa-circle-check"></i> <?= htmlspecialchars($message) ?></div><?php endif; ?><?php if($error): ?><div class="notice error" role="alert"><i class="fas fa-triangle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?><div class="notice ok" role="note"><i class="fas fa-circle-info"></i> <strong>Centralized publishing:</strong> Official announcements and benefit updates are created here by the System/Department Admin. Barangays do not need to post separately—the selected update is automatically synchronized to every barangay dashboard.</div><section class="delivery" aria-label="Announcement display locations"><article class="delivery-card"><i class="fas fa-globe"></i><div><strong>Senior Portal Public</strong><small>Home, application, benefits, and tracking pages.</small></div></article><article class="delivery-card"><i class="fas fa-building-user"></i><div><strong>All barangay dashboards</strong><small>The same official update reaches every barangay at once.</small></div></article><article class="delivery-card"><i class="fas fa-users"></i><div><strong>Everyone</strong><small>Senior Portal Public, Department Admin, and all barangays.</small></div></article></section><div class="grid"><section class="card"><h2>Publish an official update</h2><p>Choose where it appears and optionally schedule its visibility.</p><form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['announcement_csrf']) ?>"><input type="hidden" name="action" value="publish"><div class="field"><label for="title">Title</label><input id="title" name="title" maxlength="140" required value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"></div><div class="field"><label for="announcementMessage">Message</label><textarea id="announcementMessage" name="message" maxlength="2000" required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea></div><div class="two"><div class="field"><label for="category">Update type</label><select id="category" name="category"><option value="announcement" <?= ($_POST['category'] ?? '') === 'announcement' ? 'selected' : '' ?>>Announcement</option><option value="benefit" <?= ($_POST['category'] ?? '') === 'benefit' ? 'selected' : '' ?>>Benefit update</option></select></div><div class="field"><label for="audience">Display location</label><select id="audience" name="audience"><option value="all" <?= ($_POST['audience'] ?? 'all') === 'all' ? 'selected' : '' ?>>Everyone</option><option value="public" <?= ($_POST['audience'] ?? '') === 'public' ? 'selected' : '' ?>>Senior Portal Public</option><option value="staff" <?= ($_POST['audience'] ?? '') === 'staff' ? 'selected' : '' ?>>All staff and barangay dashboards</option></select><small class="field-help">One publication is distributed automatically to the selected locations.</small></div></div><div class="two"><div class="field"><label for="starts_at">Starts (optional)</label><input id="starts_at" name="starts_at" type="datetime-local" value="<?= htmlspecialchars($_POST['starts_at'] ?? '') ?>"></div><div class="field"><label for="ends_at">Ends (optional)</label><input id="ends_at" name="ends_at" type="datetime-local" value="<?= htmlspecialchars($_POST['ends_at'] ?? '') ?>"></div></div><button class="publish" type="submit"><i class="fas fa-bullhorn"></i> Publish to selected locations</button></form></section><section class="card"><h2>Published updates</h2><p>Manage every official centralized announcement from here.</p><div class="items"><?php if(!$items): ?><div class="empty"><i class="fas fa-bullhorn"></i><br>No announcements yet. Publish the first official update using the form.</div><?php endif; ?><?php foreach($items as $item): ?><article class="item <?= $item['is_active'] ? '' : 'off' ?>"><div class="item-top"><div><span class="badge <?= htmlspecialchars($item['category']) ?>"><?= htmlspecialchars($item['category']) ?></span><h3><?= htmlspecialchars($item['title']) ?></h3></div><div class="item-actions"><form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['announcement_csrf']) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><button class="toggle" type="submit"><?= $item['is_active'] ? 'Unpublish' : 'Republish' ?></button></form><form method="post" onsubmit="return confirm('Delete this announcement permanently? This action cannot be undone.');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['announcement_csrf']) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><button class="delete-announcement" type="submit"><i class="fas fa-trash-can" aria-hidden="true"></i> Delete</button></form></div></div><p><?= nl2br(htmlspecialchars($item['message'])) ?></p><div class="meta">Displays on: <?= htmlspecialchars($audienceLabels[$item['audience']] ?? 'Everyone') ?> · <?= $item['is_active'] ? 'Active' : 'Unpublished' ?> · <?= htmlspecialchars(date('M j, Y g:i A', strtotime($item['created_at']))) ?></div></article><?php endforeach; ?></div></section></div></div></main></body></html>
