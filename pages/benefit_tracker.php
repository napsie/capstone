<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/application_types.php';
require_once '../includes/system_branding.php';
require_once '../includes/data_normalizer.php';

header('Cache-Control: private, no-store');

$token = strtoupper(preg_replace('/\s+/u', '', str_replace(
    ['‐', '‑', '‒', '–', '—', '―', '−', '﹘', '﹣', '－'],
    '-', trim((string)($_GET['token'] ?? ''))
)) ?? '');
$token = preg_replace('/^PRX(?=[A-Z0-9]{4,12}$)/', 'PRX-', $token) ?? $token;
$application = null;
$applications = [];
$permanentToken = $token;
$selectedService = trim((string)($_GET['service'] ?? ''));
$serviceDefinitions = [
    'senior' => 'Senior Citizen ID',
    'landbank' => 'LandBank',
    'pension' => 'Local Senior Pension',
    'milestone_gift' => 'Octogenarian Benefits',
    'burial' => 'Burial Assistance',
];
$serviceApplications = [];
$serviceNotApplied = false;
$applicantName = '';
$history = [];
$error = '';
$photoAccessError = '';

if ($token !== '') {
    if (!preg_match('/^PRX-[A-Z0-9]{4,12}$/', $token)) {
        $error = 'Enter a valid permanent PRX Token ID, such as PRX-7K2M.';
    } else {
        $stmt = $conn->prepare("SELECT id_number, parent_senior_id, proxy_token, full_name, application_type, requested_benefit, workflow_state, status, expected_release_date, home_visit_status, date_submitted, id_purpose,
                                       senior_id_no, birth_date, complete_address, barangay, contact_number,
                                       ((id_image IS NOT NULL AND id_image <> '') OR EXISTS (SELECT 1 FROM application_documents d WHERE d.application_id = applications.id_number AND d.document_key = 'id_image' AND d.is_current = 1)) AS has_id_photo
                                FROM applications
                                WHERE (id_number = ? OR proxy_token = ?) AND COALESCE(is_archived, 0) = 0
                                ORDER BY CASE WHEN id_number = ? THEN 0 ELSE 1 END, date_submitted ASC
                                LIMIT 1");
        $stmt->execute([$token, $token, $token]);
        $matchedApplication = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($matchedApplication) {
            $rootId = (string)($matchedApplication['parent_senior_id'] ?: $matchedApplication['id_number']);
            $rootStmt = $conn->prepare("SELECT id_number, proxy_token, senior_id_no, full_name, id_purpose, birth_date, date_submitted FROM applications WHERE id_number = ? AND COALESCE(is_archived, 0) = 0 LIMIT 1");
            $rootStmt->execute([$rootId]);
            $root = $rootStmt->fetch(PDO::FETCH_ASSOC) ?: $matchedApplication;
            $rootToken = strtoupper(trim((string)($root['proxy_token'] ?? '')));
            $permanentToken = preg_match('/^PRX-[A-Z0-9]{4,12}$/', $rootToken)
                ? $rootToken
                : $token;
            $applicantName = trim((string)($root['full_name'] ?? $matchedApplication['full_name'] ?? ''));
            $servicesStmt = $conn->prepare("SELECT id_number, parent_senior_id, proxy_token, full_name, application_type, requested_benefit, workflow_state, status, expected_release_date, home_visit_status, date_submitted, id_purpose,
                                                   senior_id_no, birth_date, complete_address, barangay, contact_number,
                                                   ((id_image IS NOT NULL AND id_image <> '') OR EXISTS (SELECT 1 FROM application_documents d WHERE d.application_id = applications.id_number AND d.document_key = 'id_image' AND d.is_current = 1)) AS has_id_photo
                                            FROM applications
                                            WHERE (id_number = ? OR parent_senior_id = ? OR (senior_id_no <> '' AND senior_id_no = ?))
                                              AND COALESCE(is_archived, 0) = 0
                                            ORDER BY date_submitted ASC");
            $servicesStmt->execute([$root['id_number'], $root['id_number'], $root['senior_id_no'] ?? '']);
            $applications = $servicesStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($applications as $candidate) {
                $candidateType = (string)($candidate['application_type'] ?? '');
                if (!isset($serviceDefinitions[$candidateType])) continue;
                // A later ID change request must not replace the primary senior ID.
                if ($candidateType === 'senior' && (string)$candidate['id_number'] === (string)$root['id_number']) {
                    $serviceApplications[$candidateType] = $candidate;
                } elseif (!isset($serviceApplications[$candidateType])) {
                    $serviceApplications[$candidateType] = $candidate;
                }
            }
            if ($selectedService === '') $selectedService = (string)($matchedApplication['application_type'] ?? 'senior');
            // Backward compatibility: old tracker links used the application ID
            // itself in the service parameter.
            if (!isset($serviceDefinitions[$selectedService])) {
                foreach ($applications as $candidate) {
                    if (hash_equals((string)$candidate['id_number'], $selectedService)) {
                        $selectedService = (string)$candidate['application_type'];
                        break;
                    }
                }
            }
            if (!isset($serviceApplications[$selectedService])) {
                $matchedType = (string)($matchedApplication['application_type'] ?? '');
                $selectedService = isset($serviceApplications[$matchedType])
                    ? $matchedType
                    : (string)array_key_first($serviceApplications);
            }
            $application = $serviceApplications[$selectedService] ?? $matchedApplication;
            $historyStmt = $conn->prepare("SELECT previous_state, new_state, changed_at
                                           FROM application_history
                                           WHERE application_id = ?
                                           ORDER BY changed_at ASC");
            $historyStmt->execute([$application['id_number']]);
            $history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $error = 'No application was found for that token. Check the code and try again.';
        }
    }
}

$rawStatus = $application['workflow_state'] ?? $application['status'] ?? '';
$status = in_array($rawStatus, ['Approved', 'Released'], true) ? 'Verified' : $rawStatus;
$homeVisitStatus = !$serviceNotApplied && ($application['application_type'] ?? '') === 'pension'
    ? trim((string)($application['home_visit_status'] ?? 'Waiting for Home Visit'))
    : '';
if (!$serviceNotApplied && ($application['application_type'] ?? '') === 'pension' && $homeVisitStatus === '') $homeVisitStatus = 'Waiting for Home Visit';
if ($homeVisitStatus === 'Cancelled') $homeVisitStatus = 'Rejected';
$displayStatus = $serviceNotApplied ? 'Not Applied' : ($homeVisitStatus === 'Rejected'
    ? 'Rejected'
    : (($homeVisitStatus !== '' && $homeVisitStatus !== 'Completed') ? 'Pending' : $status));
$steps = ['Received', 'For Review', 'Verified'];
$currentIndex = array_search($status, $steps, true);
if ($currentIndex === false) $currentIndex = -1;
$isRejected = in_array(strtolower($displayStatus), ['rejected', 'declined', 'cancelled'], true);
$tokenType = ($application['application_type'] ?? '') === 'senior' ? 'Senior Registration' : 'Benefit Claim';
$serviceLabel = trim((string)($application['requested_benefit'] ?? ''));
if ($serviceLabel === '' && $application) {
    $serviceLabel = applicationTypeLabel($application['application_type']);
}
$isLandbankApplication = $application && (($application['application_type'] ?? '') === 'landbank'
    || ($application['requested_benefit'] ?? '') === 'Land Bank Cash Card Enrollment');
$isLandbankVerified = $isLandbankApplication && in_array($rawStatus, ['Verified', 'Approved', 'Released'], true);
$landbankForwardingAt = '';
if ($isLandbankVerified) {
    foreach (array_reverse($history) as $event) {
        if (($event['new_state'] ?? '') === 'Verified') {
            $landbankForwardingAt = (string)($event['changed_at'] ?? '');
            break;
        }
    }
    if ($landbankForwardingAt === '') $landbankForwardingAt = (string)$application['date_submitted'];
}
$releasedAt = '';
foreach (array_reverse($history) as $event) {
    if (($event['new_state'] ?? '') === 'Released') {
        $releasedAt = (string)($event['changed_at'] ?? '');
        break;
    }
}
$expectedReleaseDate = !$serviceNotApplied && in_array($rawStatus, ['Verified', 'Approved', 'Released'], true)
    ? trim((string)($application['expected_release_date'] ?? '')) : '';
$isTransferredSenior = isset($root) && strtolower(trim((string)($root['id_purpose'] ?? ''))) === 'transfer';
$benefitEligibleAt = $isTransferredSenior
    ? date('Y-m-d', strtotime((string)$root['date_submitted'] . ' +2 years'))
    : '';
$isBirthday = false;
$seniorAge = null;
$milestoneAge = null;
$seniorBirthDateValue = trim((string)($root['birth_date'] ?? $application['birth_date'] ?? ''));
if ($application && $seniorBirthDateValue !== '') {
    try {
        $trackerTimezone = new DateTimeZone('Asia/Manila');
        $trackerToday = new DateTimeImmutable('today', $trackerTimezone);
        $seniorBirthDate = new DateTimeImmutable($seniorBirthDateValue, $trackerTimezone);
        if ($seniorBirthDate <= $trackerToday) {
            $seniorAge = $seniorBirthDate->diff($trackerToday)->y;
            $isBirthday = $seniorBirthDate->format('m-d') === $trackerToday->format('m-d');
            $milestoneAge = milestoneAgeForCurrentAge($seniorAge);
        }
    } catch (Throwable $e) {
        $seniorAge = null;
        $milestoneAge = null;
    }
}
$digitalIdEligible = $application
    && ($application['application_type'] ?? '') === 'senior'
    && in_array($rawStatus, ['Verified', 'Approved', 'Released'], true)
    && trim((string)($application['senior_id_no'] ?? '')) !== ''
    && !preg_match('/^OSCA-[0-9]{4}-[0-9A-F]{6}$/i', trim((string)$application['senior_id_no']));
$digitalIdIssuedAt = '';
if ($digitalIdEligible) {
    foreach (array_reverse($history) as $event) {
        if (($event['new_state'] ?? '') === 'Verified') {
            $digitalIdIssuedAt = (string)($event['changed_at'] ?? '');
            break;
        }
    }
    if ($digitalIdIssuedAt === '') $digitalIdIssuedAt = (string)$application['date_submitted'];
}
$digitalIdAge = null;
if ($digitalIdEligible && !empty($application['birth_date'])) {
    try { $digitalIdAge = (new DateTimeImmutable($application['birth_date']))->diff(new DateTimeImmutable('today'))->y; }
    catch (Exception $e) { $digitalIdAge = null; }
}
$photoApplicationId = $digitalIdEligible ? (string)$application['id_number'] : '';
if ($digitalIdEligible && empty($_SESSION['tracker_photo_csrf'])) {
    $_SESSION['tracker_photo_csrf'] = bin2hex(random_bytes(16));
}
if ($digitalIdEligible && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reveal_photo'])) {
    $csrf = (string)($_POST['photo_csrf'] ?? '');
    $submittedPhone = normalizePhoneNumber((string)($_POST['registered_phone'] ?? ''));
    $registeredPhone = normalizePhoneNumber((string)($application['contact_number'] ?? ''));
    $attempts = $_SESSION['tracker_photo_attempts'][$photoApplicationId] ?? ['count' => 0, 'until' => time() + 900];
    if (!is_array($attempts) || (int)($attempts['until'] ?? 0) < time()) {
        $attempts = ['count' => 0, 'until' => time() + 900];
    }
    if ($csrf === '' || !hash_equals((string)$_SESSION['tracker_photo_csrf'], $csrf)) {
        $photoAccessError = 'Please reload the page and try again.';
    } elseif ((int)$attempts['count'] >= 5) {
        $photoAccessError = 'Too many attempts. Please try again later.';
    } elseif (preg_match('/^09\d{9}$/', $submittedPhone) !== 1 || !hash_equals($registeredPhone, $submittedPhone)) {
        $attempts['count'] = (int)$attempts['count'] + 1;
        $_SESSION['tracker_photo_attempts'][$photoApplicationId] = $attempts;
        $photoAccessError = 'The mobile number did not match this application.';
    } else {
        unset($_SESSION['tracker_photo_attempts'][$photoApplicationId]);
        $_SESSION['tracker_photo_access'] = ['id' => $photoApplicationId, 'expires' => time() + 600];
        header('Location: benefit_tracker.php?token=' . rawurlencode($permanentToken) . '&service=senior#digitalIdTitle');
        exit;
    }
}
$photoGrant = $_SESSION['tracker_photo_access'] ?? null;
$photoVerified = $digitalIdEligible && is_array($photoGrant)
    && hash_equals((string)($photoGrant['id'] ?? ''), $photoApplicationId)
    && (int)($photoGrant['expires'] ?? 0) > time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>Track Your Application — SENIORLINK</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/seniorlink-public.css?v=1">
    <link rel="stylesheet" href="../assets/css/seniorlink-ui.css?v=20">
    <style>
        body { margin:0; min-height:100vh; background-color:#eef3f9; background-image:linear-gradient(rgba(238,243,249,.9),rgba(238,243,249,.93)),url('../images/landing-background-new3.png'); background-size:cover; background-position:left bottom; background-repeat:no-repeat; background-attachment:scroll; color:#172033; font-family:Inter,"Segoe UI",Arial,sans-serif; }
        .tracker-shell { width:min(760px,calc(100% - 32px)); margin:40px auto; }
        .tracker-card { background:#fff; border:1px solid #d9e3ee; border-radius:18px; box-shadow:0 18px 46px rgba(15,23,42,.12); overflow:hidden; }
        .tracker-head { padding:28px; color:#fff; background:linear-gradient(135deg,#102a4c,#196b49); }
        .tracker-head h1 { margin:0 0 6px; font-size:1.35rem; }
        .tracker-head p { margin:0; opacity:.84; }
        .tracker-body { padding:28px; }
        .lookup { display:grid; grid-template-columns:1fr auto; gap:10px; margin-bottom:22px; }
        .lookup input { min-width:0; border:1px solid #cbd5e1; border-radius:9px; padding:12px; font:inherit; text-transform:uppercase; }
        .lookup input:focus { outline:3px solid rgba(37,99,235,.2); border-color:#2563eb; }
        .lookup button { min-height:46px; border:0; border-radius:9px; padding:0 18px; background:#178b4b; color:#fff; font-weight:700; cursor:pointer; }
        .lookup button:hover { background:#116f3b; }
        .token-format-help { grid-column:1 / -1; margin:-3px 0 0; color:#475569; font-size:.85rem; line-height:1.4; }
        .qr-tools { margin:-10px 0 22px; }
        .qr-tools summary { cursor:pointer; color:#1d4ed8; font-weight:700; }
        #trackingQrReader { margin-top:12px; max-width:480px; }
        .service-picker { margin:0 0 22px; padding:16px; border:1px solid #bfdbfe; border-radius:12px; background:#eff6ff; }
        .service-picker label { display:block; margin-bottom:7px; color:#1e3a5f; font-size:.76rem; font-weight:900; text-transform:uppercase; }
        .service-picker select { width:100%; min-height:48px; padding:10px 12px; border:1px solid #93c5fd; border-radius:9px; background:#fff; color:#172033; font:inherit; font-size:16px; }
        .alert { padding:12px 14px; border-radius:9px; background:#fef2f2; color:#b91c1c; margin-bottom:18px; }
        .summary { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; margin-bottom:24px; }
        .fact { padding:13px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; }
        .fact span { display:block; color:#64748b; font-size:.7rem; font-weight:800; text-transform:uppercase; margin-bottom:5px; }
        .progress { display:grid; grid-template-columns:repeat(5,1fr); gap:8px; margin:20px 0 26px; }
        .step { padding:10px 4px; border-radius:9px; background:#e2e8f0; color:#64748b; text-align:center; font-size:.72rem; font-weight:800; }
        .step.done { background:#dcfce7; color:#15803d; }
        .step.current { background:#dbeafe; color:#1d4ed8; outline:2px solid #60a5fa; }
        .status-note { margin:0 0 22px; padding:13px 14px; color:#155e35; background:#ecfdf3; border:1px solid #bbf7d0; border-radius:10px; }
        .status-note.rejected { color:#991b1b; background:#fef2f2; border-color:#fecaca; }
        .status-note.landbank { color:#1e3a5f; background:#eff6ff; border-color:#bfdbfe; }
        .senior-notices { display:grid; gap:10px; margin:0 0 22px; }
        .senior-notice { display:flex; align-items:flex-start; gap:12px; padding:14px 16px; border:1px solid; border-radius:12px; line-height:1.5; }
        .senior-notice i { display:grid; place-items:center; flex:0 0 36px; width:36px; height:36px; border-radius:50%; font-size:1rem; }
        .senior-notice strong { display:block; margin-bottom:2px; }
        .senior-notice p { margin:0; font-size:.86rem; }
        .senior-notice.birthday { border-color:#f9a8d4; background:#fdf2f8; color:#9d174d; }
        .senior-notice.birthday i { background:#fce7f3; color:#db2777; }
        .senior-notice.milestone { border-color:#fcd34d; background:#fffbeb; color:#854d0e; }
        .senior-notice.milestone i { background:#fef3c7; color:#d97706; }
        .digital-id-section { margin:28px 0; padding:20px; border:1px solid #bfdbfe; border-radius:14px; background:#eff6ff; }
        .digital-id-heading { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:14px; }
        .digital-id-heading h2 { margin:0 0 4px; font-size:1.05rem; color:#172033; }
        .digital-id-heading p { margin:0; color:#52657d; font-size:.82rem; }
        .temporary-badge { flex:none; padding:6px 9px; border-radius:999px; background:#fef3c7; color:#92400e; font-size:.68rem; font-weight:900; letter-spacing:.06em; }
        .digital-id { position:relative; overflow:hidden; width:min(100%,680px); aspect-ratio:1.586/1; margin:auto; border-radius:18px; background:#fdfdfb; border:1px solid #b7c2d0; box-shadow:0 14px 30px rgba(15,23,42,.18); color:#111827; }
        .digital-id::after { content:"TEMPORARY"; position:absolute; left:20%; top:48%; transform:rotate(-22deg); color:rgba(37,99,235,.07); font-size:3.4rem; font-weight:900; letter-spacing:.1em; pointer-events:none; }
        .digital-id-head { height:28%; padding:13px 18px; box-sizing:border-box; display:flex; align-items:center; gap:13px; color:#fff; background:linear-gradient(135deg,#0b1938,#172554 70%,#1e3a8a); border-bottom:9px solid #3b82f6; }
        .digital-id-logo { width:56px; height:56px; border-radius:50%; object-fit:cover; border:2px solid rgba(255,255,255,.75); }
        .digital-id-agency { flex:1; text-align:center; line-height:1.05; text-transform:uppercase; }
        .digital-id-agency small { display:block; font-size:.62rem; letter-spacing:.12em; margin-bottom:3px; }
        .digital-id-agency strong { display:block; font-size:1.12rem; }
        .digital-id-agency span { display:block; margin-top:4px; font-size:.75rem; font-weight:800; }
        .digital-id-body { height:72%; box-sizing:border-box; padding:18px 20px 14px; display:grid; grid-template-columns:minmax(0,1fr) 126px; grid-template-rows:minmax(0,1fr) auto; gap:10px 18px; }
        .digital-id-fields { min-width:0; align-self:start; }
        .digital-id-number { margin-bottom:12px; color:#dc2626; font-size:1.3rem; font-weight:900; letter-spacing:.04em; }
        .digital-id-number span { color:#334155; font-size:.72rem; margin-right:7px; }
        .digital-field { display:grid; grid-template-columns:72px minmax(0,1fr); align-items:start; margin:8px 0; }
        .digital-field span { padding-top:2px; font-size:.66rem; font-weight:900; }
        .digital-field strong { min-width:0; min-height:20px; padding:0 3px 3px; border-bottom:1px solid #475569; font-size:.88rem; line-height:1.25; text-transform:uppercase; white-space:normal; overflow-wrap:anywhere; word-break:normal; }
        .digital-field--address strong { padding-right:5px; font-size:.82rem; line-height:1.22; }
        .digital-photo-wrap { position:relative; display:grid; place-items:center; width:126px; height:150px; overflow:hidden; border:2px solid #334155; background:#e2e8f0; color:#94a3b8; font-size:2.5rem; }
        .digital-id-photo { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; object-position:center; background:#e2e8f0; }
        .digital-photo-access { margin:14px 0 0; padding:14px; border:1px solid #cbd5e1; border-radius:10px; background:#fff; }
        .digital-photo-access label { display:block; margin-bottom:7px; color:#334155; font-size:.84rem; font-weight:700; }
        .digital-photo-access-row { display:flex; gap:8px; }
        .digital-photo-access input { flex:1; min-width:0; padding:10px; border:1px solid #94a3b8; border-radius:8px; font-size:16px; }
        .digital-photo-access button { padding:10px 14px; border:0; border-radius:8px; background:#1d4ed8; color:#fff; font-weight:700; cursor:pointer; }
        .digital-photo-access-error { margin:8px 0 0; color:#b91c1c; font-size:.84rem; }
        .digital-id-footer { grid-column:1/-1; display:grid; grid-template-columns:1fr 1fr; gap:22px; text-align:center; }
        .digital-id-footer div { border-bottom:1px solid #475569; padding-bottom:3px; font-size:.8rem; font-weight:800; }
        .digital-id-footer span { display:block; border:0; margin-top:4px; color:#475569; font-size:.56rem; font-weight:900; letter-spacing:.05em; }
        .digital-id-notice { margin:12px 0 0; color:#475569; text-align:center; font-size:.74rem; font-weight:700; }
        @media(max-width:480px){.digital-photo-access-row{flex-direction:column}}
        .timeline { border-left:2px solid #dbe3ef; padding-left:18px; }
        .event { position:relative; margin:0 0 18px; }
        .event::before { content:""; position:absolute; left:-24px; top:5px; width:10px; height:10px; border-radius:50%; background:#2563eb; }
        .event strong { display:block; }
        .event time { color:#64748b; font-size:.78rem; }
        .back { display:inline-block; margin-top:20px; color:#1d4ed8; text-decoration:none; font-weight:700; }
        @media(max-width:600px){body{padding-bottom:env(safe-area-inset-bottom)}.tracker-shell{width:min(100% - 20px,760px);margin:12px auto 24px}.tracker-card{border-radius:14px}.tracker-head{padding:20px 18px}.tracker-head h1{font-size:1.2rem}.tracker-head p{font-size:.86rem;line-height:1.5}.lookup{grid-template-columns:1fr;margin-bottom:18px}.lookup input{min-height:48px;font-size:16px}.lookup button{min-height:48px;padding:12px}.qr-tools{margin:-4px 0 18px;padding:10px 12px;border:1px solid #dbeafe;border-radius:10px;background:#f8fbff}.qr-tools summary{display:flex;align-items:center;min-height:28px;line-height:1.4}.summary{grid-template-columns:1fr;gap:8px}.fact{padding:11px 12px}.progress{grid-template-columns:repeat(3,minmax(0,1fr));gap:6px;margin:16px 0 24px}.step{display:grid;place-items:center;min-height:46px;padding:8px 3px;font-size:.66rem;line-height:1.2}.status-note{font-size:.86rem;line-height:1.5}.tracker-body{padding:18px}.digital-id-section{padding:12px}.digital-id-heading{flex-direction:column}.digital-id{aspect-ratio:auto}.digital-id-head{height:auto;min-height:94px;padding:12px 14px}.digital-id-logo{width:42px;height:42px}.digital-id-agency strong{font-size:.78rem}.digital-id-agency span{font-size:.58rem}.digital-id-body{height:auto;min-height:280px;padding:14px 12px 12px;grid-template-columns:minmax(0,1fr) 84px;gap:12px 10px}.digital-photo-wrap{width:84px;height:104px}.digital-id-number{margin-bottom:10px;font-size:.9rem}.digital-field{grid-template-columns:52px minmax(0,1fr);margin:7px 0}.digital-field span{font-size:.55rem}.digital-field strong{font-size:.68rem;line-height:1.25}.digital-field--address strong{font-size:.62rem;line-height:1.28}.digital-id-footer{gap:14px}.digital-id::after{font-size:2rem}.back{display:flex;align-items:center;justify-content:center;min-height:48px;margin-top:18px;border:1px solid #bfdbfe;border-radius:10px;background:#eff6ff}}
        @media(max-width:390px){.tracker-shell{width:min(100% - 16px,760px);margin:16px auto}.tracker-body{padding:14px}.digital-id-section{margin:20px 0;padding:8px}.digital-id{border-radius:13px}.digital-id-head{min-height:86px;border-bottom-width:6px}.digital-id-logo{width:36px;height:36px}.digital-id-agency small{font-size:.48rem}.digital-id-agency strong{font-size:.69rem}.digital-id-agency span{font-size:.5rem}.digital-id-body{grid-template-columns:minmax(0,1fr) 72px;padding:12px 9px 10px;gap:10px 7px}.digital-photo-wrap{width:72px;height:92px}.digital-id-number span{display:block;margin-bottom:2px}.digital-field{grid-template-columns:46px minmax(0,1fr)}.digital-field strong{font-size:.61rem}.digital-field--address strong{font-size:.57rem}.digital-id-footer div{font-size:.68rem}.digital-id-footer span{font-size:.48rem}}
    </style>
</head>
<body>
<main class="tracker-shell">
    <section class="tracker-card">
        <header class="tracker-head">
            <h1>Track Your Application</h1>
            <p>Enter your permanent PRX Token ID, scan your QR code, or upload a QR image to check your application status.</p>
        </header>
        <div class="tracker-body">
            <form method="get" class="lookup">
                <label for="token" class="sr-only">Application token</label>
                <input id="token" name="token" value="<?php echo htmlspecialchars($token ?: 'PRX-'); ?>" aria-describedby="tokenFormatHelp" maxlength="32" autocomplete="off" autocapitalize="characters" spellcheck="false" required>
                <button type="submit"><i class="fas fa-magnifying-glass" aria-hidden="true"></i> Check Status</button>
                <small id="tokenFormatHelp" class="token-format-help">PRX- is added for you. Enter the remaining 4–12 letters or numbers.</small>
            </form>
            <details class="qr-tools">
                <summary><i class="fas fa-camera" aria-hidden="true"></i> Scan QR Code <span aria-hidden="true">|</span> <i class="fas fa-image" aria-hidden="true"></i> Upload QR Image</summary>
                <div id="trackingQrReader"></div>
            </details>

            <?php if ($error): ?><div class="alert" role="alert"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

            <?php if ($application): ?>
                <form method="get" class="service-picker">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($permanentToken); ?>">
                    <label for="service">Service / Application</label>
                    <select id="service" name="service" onchange="this.form.submit()">
                        <?php foreach ($serviceDefinitions as $serviceKey => $serviceName):
                            if (!isset($serviceApplications[$serviceKey])) continue;
                            $serviceRecord = $serviceApplications[$serviceKey];
                            $serviceState = (string)($serviceRecord['workflow_state'] ?: $serviceRecord['status'] ?: 'Received');
                        ?>
                            <option value="<?php echo htmlspecialchars($serviceKey); ?>" <?php echo $serviceKey === $selectedService ? 'selected' : ''; ?>><?php echo htmlspecialchars($serviceName . ' — ' . $serviceState); ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <?php if ($isBirthday || $milestoneAge !== null): ?>
                    <section class="senior-notices" aria-label="Senior citizen notices" aria-live="polite">
                        <?php if ($isBirthday): ?>
                            <div class="senior-notice birthday" role="status">
                                <i class="fas fa-cake-candles" aria-hidden="true"></i>
                                <div><strong>Happy Birthday, <?php echo htmlspecialchars($applicantName); ?>!</strong><p>Wishing you good health and happiness as you celebrate turning <?php echo number_format((int)$seniorAge); ?> today.</p></div>
                            </div>
                        <?php endif; ?>
                        <?php if ($milestoneAge !== null): ?>
                            <div class="senior-notice milestone" role="status">
                                <i class="fas fa-gift" aria-hidden="true"></i>
                                <div><strong>You meet the age requirement for the Milestone Cash Gift.</strong><p>At age <?php echo number_format((int)$seniorAge); ?>, you may apply for the Octogenarian, Nonagenarian, or Centenarian benefit. OSCA will verify your Senior Citizen ID, Pasig residency, and supporting documents.</p></div>
                            </div>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>
                <div class="summary">
                    <div class="fact"><span>Permanent Token ID</span><?php echo htmlspecialchars($permanentToken); ?></div>
                    <div class="fact"><span>Current Status</span><?php echo htmlspecialchars($displayStatus ?: 'Received'); ?></div>
                    <div class="fact"><span>Applicant</span><?php echo htmlspecialchars($applicantName); ?></div>
                    <div class="fact"><span>Request Type</span><?php echo htmlspecialchars($tokenType); ?></div>
                    <div class="fact"><span>Service</span><?php echo htmlspecialchars($serviceLabel); ?></div>
                    <div class="fact"><span>Date Submitted</span><?php echo $application['date_submitted'] ? htmlspecialchars(date('F j, Y g:i A', strtotime($application['date_submitted']))) : '—'; ?></div>
                    <div class="fact"><span>Expected Release Date</span><?php echo $expectedReleaseDate !== '' ? htmlspecialchars(date('F j, Y', strtotime($expectedReleaseDate))) : ($serviceNotApplied || !in_array($rawStatus, ['Verified', 'Approved', 'Released'], true) ? 'Available after verification' : 'Schedule to follow'); ?></div>
                    <div class="fact"><span>Actual Release Date</span><?php echo $releasedAt !== '' ? htmlspecialchars(date('F j, Y g:i A', strtotime($releasedAt))) : 'Not yet released'; ?></div>
                </div>

                <?php if ($isTransferredSenior): ?>
                    <p class="status-note landbank"><i class="fas fa-clock" aria-hidden="true"></i> <strong>Eligible for benefits after 2 years.</strong> Cash-benefit access begins on <?php echo htmlspecialchars(date('F j, Y', strtotime($benefitEligibleAt))); ?>.</p>
                <?php endif; ?>

                <p class="status-note <?php echo $isRejected ? 'rejected' : ($isLandbankApplication ? 'landbank' : ''); ?>" role="status">
                    <?php if ($serviceNotApplied): ?><strong><?php echo htmlspecialchars($serviceDefinitions[$selectedService]); ?></strong> has not been applied for under this permanent Token ID.
                    <?php elseif ($isRejected): ?>This application is marked <strong><?php echo htmlspecialchars($displayStatus); ?></strong>. Please contact your local senior services office for assistance.
                    <?php elseif ($homeVisitStatus === 'Rejected'): ?>The required home visit was <strong>Rejected</strong>. Contact the reviewing office if you need more information.
                    <?php elseif ($homeVisitStatus !== '' && $homeVisitStatus !== 'Completed'): ?>Your Local Pension application is <strong>Pending</strong> until the required home visit is completed. The reviewing office manages the visit schedule.
                    <?php elseif ($isLandbankVerified): ?><i class="fas fa-building-columns" aria-hidden="true"></i> Your application was verified on <strong><?php echo htmlspecialchars(date('F j, Y g:i A', strtotime($landbankForwardingAt))); ?></strong> and is now being forwarded to <strong>LANDBANK</strong> for cash card processing.
                    <?php elseif ($isLandbankApplication): ?><i class="fas fa-circle-info" aria-hidden="true"></i> After OSCA verifies this application, it will be forwarded to <strong>LANDBANK</strong>. Current status: <strong><?php echo htmlspecialchars($status ?: 'Received'); ?></strong>.
                    <?php else: ?>Your application is currently <strong><?php echo htmlspecialchars($status ?: 'Received'); ?></strong>. This page will reflect updates made by the reviewing office.<?php endif; ?>
                </p>

                <?php if ($digitalIdEligible): ?>
                    <section class="digital-id-section" aria-labelledby="digitalIdTitle">
                        <div class="digital-id-heading">
                            <div>
                                <h2 id="digitalIdTitle"><i class="fas fa-id-card" aria-hidden="true"></i> Temporary Digital Senior Citizen ID</h2>
                                <p>Your digital ID is available because your Senior Citizen ID application was approved.</p>
                            </div>
                            <span class="temporary-badge">TEMPORARY</span>
                        </div>
                        <div class="digital-id" role="img" aria-label="Temporary digital Senior Citizen ID for <?php echo htmlspecialchars($application['full_name']); ?>">
                            <div class="digital-id-head">
                                <img class="digital-id-logo" src="<?php echo htmlspecialchars(systemLogoUrl($conn)); ?>" alt="SENIORLINK logo">
                                <div class="digital-id-agency">
                                    <small>Republic of the Philippines</small>
                                    <strong>City Government of Pasig</strong>
                                    <span>Office for Senior Citizens Affairs</span>
                                </div>
                            </div>
                            <div class="digital-id-body">
                                <div class="digital-id-fields">
                                    <div class="digital-id-number"><span>ID NO.</span><?php echo htmlspecialchars($application['senior_id_no']); ?></div>
                                    <div class="digital-field"><span>NAME</span><strong><?php echo htmlspecialchars($application['full_name']); ?></strong></div>
                                    <div class="digital-field digital-field--address"><span>ADDRESS</span><strong><?php echo htmlspecialchars($application['complete_address']); ?></strong></div>
                                    <div class="digital-field"><span>BARANGAY</span><strong><?php echo htmlspecialchars($application['barangay']); ?>, PASIG CITY</strong></div>
                                </div>
                                <div class="digital-photo-wrap">
                                    <i class="fas fa-user" aria-hidden="true"></i>
                                    <?php if ($photoVerified && !empty($application['has_id_photo'])): ?>
                                        <img class="digital-id-photo" src="../api/tracker_id_photo.php?id=<?php echo rawurlencode($photoApplicationId); ?>" alt="Applicant photo">
                                    <?php endif; ?>
                                </div>
                                <div class="digital-id-footer">
                                    <div><?php echo htmlspecialchars(date('m/d/Y', strtotime($application['birth_date']))); ?><?php echo $digitalIdAge !== null ? ' (' . $digitalIdAge . ')' : ''; ?><span>DATE OF BIRTH / AGE</span></div>
                                    <div><?php echo htmlspecialchars(date('m/d/Y', strtotime($digitalIdIssuedAt))); ?><span>DATE ISSUED</span></div>
                                </div>
                            </div>
                        </div>
                        <?php if (empty($application['has_id_photo'])): ?>
                            <p class="digital-id-notice">No applicant photo is stored for this application. Please contact the reviewing office to add it.</p>
                        <?php elseif (!$photoVerified): ?>
                            <form method="post" class="digital-photo-access" autocomplete="off">
                                <label for="registered_phone">To show the applicant photo, enter the mobile number registered with this application.</label>
                                <div class="digital-photo-access-row">
                                    <input id="registered_phone" name="registered_phone" type="tel" inputmode="tel" autocomplete="off" maxlength="20" required>
                                    <input type="hidden" name="photo_csrf" value="<?php echo htmlspecialchars((string)$_SESSION['tracker_photo_csrf']); ?>">
                                    <button type="submit" name="reveal_photo" value="1">Show photo</button>
                                </div>
                                <?php if ($photoAccessError !== ''): ?><p class="digital-photo-access-error" role="alert"><?php echo htmlspecialchars($photoAccessError); ?></p><?php endif; ?>
                            </form>
                        <?php endif; ?>
                        <p class="digital-id-notice"><i class="fas fa-circle-info" aria-hidden="true"></i> Temporary digital credential only. Use it while waiting for the physical OSCA card.</p>
                    </section>
                <?php endif; ?>

                <h2>Processing Progress</h2>
                <div class="progress" aria-label="Application processing progress">
                    <?php if ($serviceNotApplied): ?><div class="step current">Not Applied</div><?php else: ?>
                    <?php foreach ($steps as $index => $step):
                        $class = $index < $currentIndex ? 'done' : ($index === $currentIndex ? 'current' : ''); ?>
                        <div class="step <?php echo $class; ?>"><?php echo htmlspecialchars($step); ?></div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <h2>Status History</h2>
                <div class="timeline">
                    <?php if (!$history && !$serviceNotApplied): ?>
                        <div class="event"><strong>Application received</strong><time><?php echo htmlspecialchars(date('F j, Y g:i A', strtotime($application['date_submitted']))); ?></time></div>
                    <?php endif; ?>
                    <?php if ($serviceNotApplied): ?><div class="event"><strong>No application history for this service</strong></div><?php endif; ?>
                    <?php foreach ($history as $event): ?>
                        <div class="event">
                            <strong><?php echo htmlspecialchars($event['previous_state'] . ' → ' . $event['new_state']); ?></strong>
                            <time><?php echo htmlspecialchars(date('F j, Y g:i A', strtotime($event['changed_at']))); ?></time>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <a class="back" href="../index.php">← Back to SENIORLINK Home</a>
        </div>
    </section>
</main>
<script src="../assets/js/vendor/html5-qrcode.min.js"></script>
<script src="../assets/js/prx-token-input.js"></script>
<script>
(() => {
    window.initPrxTokenInput(document.getElementById('token'));
    const reader = document.getElementById('trackingQrReader');
    if (!reader || typeof Html5QrcodeScanner === 'undefined') return;
    const useResult = decoded => {
        const match = String(decoded || '').toUpperCase().match(/PRX-[A-Z0-9]{4,12}/);
        if (!match) return;
        document.getElementById('token').value = match[0];
        window.location.href = `benefit_tracker.php?token=${encodeURIComponent(match[0])}`;
    };
    const scanner = new Html5QrcodeScanner('trackingQrReader', {
        fps: 8, qrbox: { width: 220, height: 220 }, rememberLastUsedCamera: true,
        supportedScanTypes: [Html5QrcodeScanType.SCAN_TYPE_CAMERA, Html5QrcodeScanType.SCAN_TYPE_FILE]
    }, false);
    scanner.render(useResult, () => {});
})();
</script>
</body>
</html>
