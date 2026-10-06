<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/request_security.php';
require_once '../includes/audit_logger.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['department_admin', 'super_admin'], true)) {
    header('Location: ../index.php');
    exit;
}
if (empty($_SESSION['announcement_csrf'])) $_SESSION['announcement_csrf'] = bin2hex(random_bytes(32));
$message = (string)($_SESSION['announcement_notice']['message'] ?? '');
$error = (string)($_SESSION['announcement_notice']['error'] ?? '');
unset($_SESSION['announcement_notice']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireSameOriginMutation();
    if (!hash_equals($_SESSION['announcement_csrf'], (string)($_POST['csrf_token'] ?? ''))) {
        $error = 'The request expired. Refresh the page and try again.';
    } elseif (($_POST['action'] ?? '') === 'publish') {
        $title = trim((string)($_POST['title'] ?? ''));
        $body = trim((string)($_POST['message'] ?? ''));
        $category = in_array($_POST['category'] ?? '', ['announcement', 'benefit'], true) ? $_POST['category'] : 'announcement';
        $audience = in_array($_POST['audience'] ?? '', ['all', 'public', 'staff'], true) ? $_POST['audience'] : 'all';
        $normalizeDate = static function (string $value): ?string {
            $value = trim($value);
            if ($value === '') return null;
            $timestamp = strtotime($value);
            return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
        };
        $rawStart = (string)($_POST['starts_at'] ?? '');
        $rawEnd = (string)($_POST['ends_at'] ?? '');
        $startsAt = $normalizeDate($rawStart);
        $endsAt = $normalizeDate($rawEnd);
        if ($title === '' || mb_strlen($title) > 140 || $body === '' || mb_strlen($body) > 2000) {
            $error = 'Enter a title up to 140 characters and a message up to 2,000 characters.';
        } elseif (($rawStart !== '' && !$startsAt) || ($rawEnd !== '' && !$endsAt)) {
            $error = 'Enter a valid publishing date and time.';
        } elseif ($startsAt && $endsAt && strtotime($endsAt) <= strtotime($startsAt)) {
            $error = 'The end date must be later than the start date.';
        } else {
            try {
                $stmt = $conn->prepare('INSERT INTO announcements (title, message, category, audience, starts_at, ends_at, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$title, $body, $category, $audience, $startsAt, $endsAt, (int)$_SESSION['user_id']]);
                logAudit($conn, 'PUBLISH_ANNOUNCEMENT', "Published {$category}: {$title}");
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
                $stmt = $conn->prepare('UPDATE announcements SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?');
                $stmt->execute([$id]);
                logAudit($conn, 'TOGGLE_ANNOUNCEMENT', 'Changed announcement visibility: #' . $id);
                $_SESSION['announcement_notice'] = ['message' => 'Announcement visibility updated.'];
                header('Location: announcements.php');
                exit;
            } catch (PDOException $e) {
                error_log('Announcement toggle failed: ' . $e->getMessage());
                $error = 'The announcement could not be updated.';
            }
        }
    }
}

$items = $conn->query('SELECT a.*, CONCAT(COALESCE(u.first_name, ""), " ", COALESCE(u.last_name, "")) author FROM announcements a LEFT JOIN users u ON u.id = a.created_by ORDER BY a.created_at DESC')->fetchAll();
$audienceLabels = ['all' => 'Public portal and staff dashboards', 'public' => 'Public portal only', 'staff' => 'Staff dashboards only'];
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Announcements &amp; Benefits — SENIORLINK</title><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"><link rel="stylesheet" href="../assets/css/department-sidebar.css?v=5"><link rel="stylesheet" href="../assets/css/seniorlink-ui.css?v=23"><style>
*{box-sizing:border-box}body{margin:0;background:#f1f5f9;color:#172033;font-family:Inter,"Segoe UI",Arial,sans-serif}.main-content{width:calc(100% - 220px);margin-left:220px;padding:30px;min-height:100vh}.shell{max-width:1200px;margin:auto}.page-head{display:flex;justify-content:space-between;gap:16px;align-items:end;margin-bottom:22px}.eyebrow{color:#2563eb;font-size:.75rem;font-weight:900;letter-spacing:.08em;text-transform:uppercase}.page-head h1{margin:4px 0 0;font-size:2rem}.page-head p{margin:0;color:#64748b}.delivery{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:20px}.delivery-card{display:flex;align-items:flex-start;gap:12px;padding:16px;border:1px solid #dbe5f1;border-radius:13px;background:#fff;box-shadow:0 4px 15px rgba(15,23,42,.04)}.delivery-card>i{display:grid;place-items:center;width:38px;height:38px;flex:0 0 38px;border-radius:10px;background:#eff6ff;color:#2563eb}.delivery-card strong,.delivery-card small{display:block}.delivery-card strong{font-size:.86rem}.delivery-card small{margin-top:3px;color:#64748b;font-size:.73rem;line-height:1.4}.grid{display:grid;grid-template-columns:minmax(300px,.85fr) minmax(420px,1.15fr);gap:20px}.card{padding:22px;border:1px solid #d9e3ee;border-radius:15px;background:#fff;box-shadow:0 8px 24px rgba(15,23,42,.06)}.card h2{margin:0 0 5px;font-size:1.08rem}.card>p{margin:0 0 18px;color:#64748b;font-size:.84rem}.field{margin-bottom:14px}.field label{display:block;margin-bottom:6px;color:#475569;font-size:.76rem;font-weight:900;text-transform:uppercase}.field input,.field select,.field textarea{width:100%;padding:11px 12px;border:1px solid #cbd5e1;border-radius:9px;font:inherit}.field input:focus,.field select:focus,.field textarea:focus{outline:3px solid rgba(37,99,235,.14);border-color:#2563eb}.field textarea{min-height:130px;resize:vertical}.field-help{display:block;margin-top:6px;color:#64748b;font-size:.72rem;line-height:1.4}.two{display:grid;grid-template-columns:1fr 1fr;gap:12px}.publish{width:100%;min-height:48px;padding:12px;border:0;border-radius:9px;background:#2563eb;color:#fff;font-weight:800;cursor:pointer}.publish:hover{background:#1d4ed8}.notice{margin-bottom:16px;padding:12px 14px;border-radius:9px}.notice.ok{background:#ecfdf5;color:#166534}.notice.error{background:#fef2f2;color:#991b1b}.items{display:grid;gap:11px}.empty{padding:28px;text-align:center;border:1px dashed #cbd5e1;border-radius:11px;color:#64748b;background:#f8fafc}.item{padding:14px;border:1px solid #dbe4ef;border-radius:11px;background:#f8fafc}.item.off{opacity:.62}.item-top{display:flex;justify-content:space-between;gap:12px}.item h3{margin:7px 0 5px;font-size:.95rem}.item p{margin:0;color:#526178;font-size:.82rem;line-height:1.5}.meta{margin-top:9px;color:#64748b;font-size:.71rem}.badge{display:inline-block;padding:4px 8px;border-radius:999px;background:#dbeafe;color:#1d4ed8;font-size:.66rem;font-weight:900;text-transform:uppercase}.badge.benefit{background:#dcfce7;color:#166534}.toggle{border:1px solid #cbd5e1;border-radius:7px;background:#fff;color:#334155;padding:7px 9px;font-weight:700;cursor:pointer}@media(max-width:900px){.main-content{width:100%;margin:0;padding:80px 12px 24px}.grid,.delivery{grid-template-columns:1fr}.page-head{align-items:flex-start;flex-direction:column}}@media(max-width:560px){.two{grid-template-columns:1fr}.card{padding:17px}}
</style></head><body><?php include '../partials/department_sidebar.php'; ?><main class="main-content"><div class="shell"><header class="page-head"><div><span class="eyebrow">Centralized rollout</span><h1>Announcements &amp; Benefits</h1></div><p>Publish once and distribute through SENIORLINK.</p></header><?php if($message): ?><div class="notice ok" role="status"><i class="fas fa-circle-check"></i> <?= htmlspecialchars($message) ?></div><?php endif; ?><?php if($error): ?><div class="notice error" role="alert"><i class="fas fa-triangle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?><section class="delivery" aria-label="Announcement display locations"><article class="delivery-card"><i class="fas fa-globe"></i><div><strong>Public portal</strong><small>Home, application, benefits, and tracking pages.</small></div></article><article class="delivery-card"><i class="fas fa-building-user"></i><div><strong>Staff dashboards</strong><small>Department Admin and barangay dashboards.</small></div></article><article class="delivery-card"><i class="fas fa-users"></i><div><strong>Everywhere</strong><small>All public and staff locations listed here.</small></div></article></section><div class="grid"><section class="card"><h2>Publish an update</h2><p>Choose its display location and optionally schedule it.</p><form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['announcement_csrf']) ?>"><input type="hidden" name="action" value="publish"><div class="field"><label for="title">Title</label><input id="title" name="title" maxlength="140" required value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"></div><div class="field"><label for="announcementMessage">Message</label><textarea id="announcementMessage" name="message" maxlength="2000" required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea></div><div class="two"><div class="field"><label for="category">Update type</label><select id="category" name="category"><option value="announcement">Announcement</option><option value="benefit">Benefit update</option></select></div><div class="field"><label for="audience">Display location</label><select id="audience" name="audience"><option value="all">Everywhere</option><option value="public">Public portal only</option><option value="staff">Staff dashboards only</option></select><small class="field-help">This determines exactly where it appears.</small></div></div><div class="two"><div class="field"><label for="starts_at">Starts (optional)</label><input id="starts_at" name="starts_at" type="datetime-local"></div><div class="field"><label for="ends_at">Ends (optional)</label><input id="ends_at" name="ends_at" type="datetime-local"></div></div><button class="publish" type="submit"><i class="fas fa-bullhorn"></i> Publish now</button></form></section><section class="card"><h2>Published updates</h2><p>Manage every centralized announcement from here.</p><div class="items"><?php if(!$items): ?><div class="empty"><i class="fas fa-bullhorn"></i><br>No announcements yet. Publish the first update using the form.</div><?php endif; ?><?php foreach($items as $item): ?><article class="item <?= $item['is_active'] ? '' : 'off' ?>"><div class="item-top"><div><span class="badge <?= htmlspecialchars($item['category']) ?>"><?= htmlspecialchars($item['category']) ?></span><h3><?= htmlspecialchars($item['title']) ?></h3></div><form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['announcement_csrf']) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><button class="toggle" type="submit"><?= $item['is_active'] ? 'Unpublish' : 'Republish' ?></button></form></div><p><?= nl2br(htmlspecialchars($item['message'])) ?></p><div class="meta">Displays on: <?= htmlspecialchars($audienceLabels[$item['audience']] ?? 'Everywhere') ?> · <?= $item['is_active'] ? 'Active' : 'Unpublished' ?> · <?= htmlspecialchars(date('M j, Y g:i A', strtotime($item['created_at']))) ?></div></article><?php endforeach; ?></div></section></div></div></main></body></html>
