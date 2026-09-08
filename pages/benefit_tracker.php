<?php
require_once '../includes/db_connect.php';
require_once '../includes/application_types.php';

$token = strtoupper(preg_replace('/\s+/', '', trim((string)($_GET['token'] ?? ''))));
$application = null;
$history = [];
$error = '';

if ($token !== '') {
    if (!preg_match('/^(PEN|PRX)-[A-Z0-9]{4,12}$/', $token)) {
        $error = 'Enter a valid application token, such as PRX-7K2M or PEN-4X9P.';
    } else {
        $stmt = $conn->prepare("SELECT id_number, full_name, application_type, requested_benefit, workflow_state, status, home_visit_status, date_submitted,
                                       senior_id_no, birth_date, complete_address, barangay
                                FROM applications
                                WHERE (id_number = ? OR proxy_token = ?) AND COALESCE(is_archived, 0) = 0
                                LIMIT 1");
        $stmt->execute([$token, $token]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($application) {
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
$homeVisitStatus = ($application['application_type'] ?? '') === 'pension'
    ? trim((string)($application['home_visit_status'] ?? 'Waiting for Home Visit'))
    : '';
if (($application['application_type'] ?? '') === 'pension' && $homeVisitStatus === '') $homeVisitStatus = 'Waiting for Home Visit';
$displayStatus = ($homeVisitStatus !== '' && $homeVisitStatus !== 'Completed') ? 'Pending' : $status;
$steps = ['Received', 'For Review', 'Verified'];
$currentIndex = array_search($status, $steps, true);
if ($currentIndex === false) $currentIndex = -1;
$isRejected = in_array(strtolower($status), ['rejected', 'declined', 'cancelled'], true);
$tokenType = str_starts_with($token, 'PEN-') ? 'Benefit Claim' : 'Pre-Registration';
$serviceLabel = trim((string)($application['requested_benefit'] ?? ''));
if ($serviceLabel === '' && $application) {
    $serviceLabel = applicationTypeLabel($application['application_type']);
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
    <link rel="stylesheet" href="../assets/css/seniorlink-ui.css?v=16">
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
        .digital-id-body { height:72%; box-sizing:border-box; padding:18px 20px 14px; display:grid; grid-template-columns:minmax(0,1fr) 126px; grid-template-rows:1fr auto; gap:10px 18px; }
        .digital-id-fields { min-width:0; }
        .digital-id-number { margin-bottom:12px; color:#dc2626; font-size:1.3rem; font-weight:900; letter-spacing:.04em; }
        .digital-id-number span { color:#334155; font-size:.72rem; margin-right:7px; }
        .digital-field { display:grid; grid-template-columns:72px minmax(0,1fr); align-items:end; margin:8px 0; }
        .digital-field span { font-size:.66rem; font-weight:900; }
        .digital-field strong { overflow:hidden; padding:0 3px 3px; border-bottom:1px solid #475569; font-size:.91rem; line-height:1.1; text-transform:uppercase; white-space:nowrap; text-overflow:ellipsis; }
        .digital-photo-wrap { position:relative; display:grid; place-items:center; width:126px; height:150px; overflow:hidden; border:2px solid #334155; background:#e2e8f0; color:#94a3b8; font-size:2.5rem; }
        .digital-id-photo { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; object-position:center; background:#e2e8f0; }
        .digital-id-footer { grid-column:1/-1; display:grid; grid-template-columns:1fr 1fr; gap:22px; text-align:center; }
        .digital-id-footer div { border-bottom:1px solid #475569; padding-bottom:3px; font-size:.8rem; font-weight:800; }
        .digital-id-footer span { display:block; border:0; margin-top:4px; color:#475569; font-size:.56rem; font-weight:900; letter-spacing:.05em; }
        .digital-id-notice { margin:12px 0 0; color:#475569; text-align:center; font-size:.74rem; font-weight:700; }
        .timeline { border-left:2px solid #dbe3ef; padding-left:18px; }
        .event { position:relative; margin:0 0 18px; }
        .event::before { content:""; position:absolute; left:-24px; top:5px; width:10px; height:10px; border-radius:50%; background:#2563eb; }
        .event strong { display:block; }
        .event time { color:#64748b; font-size:.78rem; }
        .back { display:inline-block; margin-top:20px; color:#1d4ed8; text-decoration:none; font-weight:700; }
        @media(max-width:600px){.lookup{grid-template-columns:1fr}.lookup button{padding:12px}.summary{grid-template-columns:1fr}.progress{grid-template-columns:1fr}.tracker-body{padding:20px}.digital-id-section{padding:12px}.digital-id{aspect-ratio:auto}.digital-id-head{height:auto}.digital-id-logo{width:42px;height:42px}.digital-id-agency strong{font-size:.78rem}.digital-id-agency span{font-size:.58rem}.digital-id-body{height:auto;padding:12px;grid-template-columns:minmax(0,1fr) 88px}.digital-photo-wrap{width:88px;height:108px}.digital-id-number{font-size:.9rem}.digital-field{grid-template-columns:54px 1fr}.digital-field strong{font-size:.68rem}.digital-id::after{font-size:2rem}}
    </style>
</head>
<body>
<main class="tracker-shell">
    <section class="tracker-card">
        <header class="tracker-head">
            <h1>Track Your Application</h1>
            <p>Enter the token provided after pre-registration or printed below your QR code.</p>
        </header>
        <div class="tracker-body">
            <form method="get" class="lookup">
                <label for="token" class="sr-only">Application token</label>
                <input id="token" name="token" value="<?php echo htmlspecialchars($token); ?>" placeholder="PRX-7K2M or PEN-4X9P" maxlength="24" autocomplete="off" spellcheck="false" required>
                <button type="submit"><i class="fas fa-magnifying-glass" aria-hidden="true"></i> Check Status</button>
            </form>

            <?php if ($error): ?><div class="alert" role="alert"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

            <?php if ($application): ?>
                <div class="summary">
                    <div class="fact"><span>Application Token</span><?php echo htmlspecialchars($application['id_number']); ?></div>
                    <div class="fact"><span>Current Status</span><?php echo htmlspecialchars($displayStatus ?: 'Received'); ?></div>
                    <div class="fact"><span>Applicant</span><?php echo htmlspecialchars($application['full_name']); ?></div>
                    <div class="fact"><span>Request Type</span><?php echo htmlspecialchars($tokenType); ?></div>
                    <div class="fact"><span>Service</span><?php echo htmlspecialchars($serviceLabel); ?></div>
                    <div class="fact"><span>Date Submitted</span><?php echo htmlspecialchars(date('F j, Y g:i A', strtotime($application['date_submitted']))); ?></div>
                </div>

                <p class="status-note <?php echo $isRejected ? 'rejected' : ''; ?>" role="status">
                    <?php if ($isRejected): ?>This application is marked <strong><?php echo htmlspecialchars($status); ?></strong>. Please contact your local senior services office for assistance.
                    <?php elseif ($homeVisitStatus !== '' && $homeVisitStatus !== 'Completed'): ?>Your Local Pension application is <strong>Pending</strong> until the required home visit is completed. The reviewing office manages the visit schedule.
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
                                <img class="digital-id-logo" src="../images/logo.jpg" alt="SENIORLINK logo">
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
                                    <div class="digital-field"><span>ADDRESS</span><strong><?php echo htmlspecialchars($application['complete_address']); ?></strong></div>
                                    <div class="digital-field"><span>BARANGAY</span><strong><?php echo htmlspecialchars($application['barangay']); ?>, PASIG CITY</strong></div>
                                </div>
                                <div class="digital-photo-wrap">
                                    <i class="fas fa-user" aria-hidden="true"></i>
                                    <img class="digital-id-photo" src="../api/digital_id_photo.php?token=<?php echo rawurlencode($token); ?>&amp;v=<?php echo rawurlencode((string)$digitalIdIssuedAt); ?>" alt="Applicant ID photo" onerror="this.remove()">
                                </div>
                                <div class="digital-id-footer">
                                    <div><?php echo htmlspecialchars(date('m/d/Y', strtotime($application['birth_date']))); ?><?php echo $digitalIdAge !== null ? ' (' . $digitalIdAge . ')' : ''; ?><span>DATE OF BIRTH / AGE</span></div>
                                    <div><?php echo htmlspecialchars(date('m/d/Y', strtotime($digitalIdIssuedAt))); ?><span>DATE ISSUED</span></div>
                                </div>
                            </div>
                        </div>
                        <p class="digital-id-notice"><i class="fas fa-circle-info" aria-hidden="true"></i> Temporary digital credential only. Use it while waiting for the physical OSCA card.</p>
                    </section>
                <?php endif; ?>

                <h2>Processing Progress</h2>
                <div class="progress" aria-label="Application processing progress">
                    <?php foreach ($steps as $index => $step):
                        $class = $index < $currentIndex ? 'done' : ($index === $currentIndex ? 'current' : ''); ?>
                        <div class="step <?php echo $class; ?>"><?php echo htmlspecialchars($step); ?></div>
                    <?php endforeach; ?>
                </div>

                <h2>Status History</h2>
                <div class="timeline">
                    <?php if (!$history): ?>
                        <div class="event"><strong>Application received</strong><time><?php echo htmlspecialchars(date('F j, Y g:i A', strtotime($application['date_submitted']))); ?></time></div>
                    <?php endif; ?>
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
</body>
</html>
