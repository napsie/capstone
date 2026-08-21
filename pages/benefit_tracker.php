<?php
require_once '../includes/db_connect.php';
require_once '../includes/application_types.php';

$token = strtoupper(trim((string)($_GET['token'] ?? '')));
$application = null;
$history = [];
$error = '';

if ($token !== '') {
    if (!preg_match('/^(PEN|PRX)-[A-F0-9]{6}$/', $token)) {
        $error = 'Enter a valid claim ID, such as PEN-ABC123.';
    } else {
        $stmt = $conn->prepare("SELECT id_number, full_name, application_type, workflow_state, status, date_submitted
                                FROM applications
                                WHERE id_number = ? AND is_proxy_application = 1
                                LIMIT 1");
        $stmt->execute([$token]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($application) {
            $historyStmt = $conn->prepare("SELECT previous_state, new_state, changed_at
                                           FROM application_history
                                           WHERE application_id = ?
                                           ORDER BY changed_at ASC");
            $historyStmt->execute([$token]);
            $history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $error = 'No representative claim was found for that claim ID.';
        }
    }
}

$status = $application['workflow_state'] ?? $application['status'] ?? '';
$steps = ['Received', 'For Review', 'Verified', 'Approved', 'Released'];
$currentIndex = array_search($status, $steps, true);
if ($currentIndex === false) $currentIndex = -1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>Benefit Claim Tracker — SENIORLINK</title>
    <link rel="stylesheet" href="../assets/css/carelink-theme.css?v=5">
    <link rel="stylesheet" href="../assets/css/seniorlink-ui.css?v=11">
    <style>
        body { margin:0; min-height:100vh; background-color:#eef3f9; background-image:linear-gradient(rgba(238,243,249,.9),rgba(238,243,249,.93)),url('../images/landing-background-new3.png'); background-size:cover; background-position:left bottom; background-repeat:no-repeat; background-attachment:fixed; color:#172033; font-family:Inter,"Segoe UI",Arial,sans-serif; }
        .tracker-shell { width:min(760px,calc(100% - 32px)); margin:40px auto; }
        .tracker-card { background:#fff; border:1px solid #d9e3ee; border-radius:18px; box-shadow:0 18px 46px rgba(15,23,42,.12); overflow:hidden; }
        .tracker-head { padding:24px 28px; color:#fff; background:linear-gradient(135deg,#102a4c,#234b78); }
        .tracker-head h1 { margin:0 0 6px; font-size:1.35rem; }
        .tracker-head p { margin:0; opacity:.84; }
        .tracker-body { padding:28px; }
        .lookup { display:grid; grid-template-columns:1fr auto; gap:10px; margin-bottom:22px; }
        .lookup input { min-width:0; border:1px solid #cbd5e1; border-radius:9px; padding:12px; font:inherit; text-transform:uppercase; }
        .lookup button { border:0; border-radius:9px; padding:0 18px; background:#2563eb; color:#fff; font-weight:700; cursor:pointer; }
        .alert { padding:12px 14px; border-radius:9px; background:#fef2f2; color:#b91c1c; margin-bottom:18px; }
        .summary { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; margin-bottom:24px; }
        .fact { padding:13px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; }
        .fact span { display:block; color:#64748b; font-size:.7rem; font-weight:800; text-transform:uppercase; margin-bottom:5px; }
        .progress { display:grid; grid-template-columns:repeat(5,1fr); gap:8px; margin:20px 0 26px; }
        .step { padding:10px 4px; border-radius:9px; background:#e2e8f0; color:#64748b; text-align:center; font-size:.72rem; font-weight:800; }
        .step.done { background:#dcfce7; color:#15803d; }
        .step.current { background:#dbeafe; color:#1d4ed8; outline:2px solid #60a5fa; }
        .timeline { border-left:2px solid #dbe3ef; padding-left:18px; }
        .event { position:relative; margin:0 0 18px; }
        .event::before { content:""; position:absolute; left:-24px; top:5px; width:10px; height:10px; border-radius:50%; background:#2563eb; }
        .event strong { display:block; }
        .event time { color:#64748b; font-size:.78rem; }
        .back { display:inline-block; margin-top:20px; color:#1d4ed8; text-decoration:none; font-weight:700; }
        @media(max-width:600px){.lookup{grid-template-columns:1fr}.lookup button{padding:12px}.summary{grid-template-columns:1fr}.progress{grid-template-columns:1fr}.tracker-body{padding:20px}}
    </style>
</head>
<body>
<main class="tracker-shell">
    <section class="tracker-card">
        <header class="tracker-head">
            <h1>Benefit Claim Tracker</h1>
            <p>Enter the claim ID printed below your tracking QR code.</p>
        </header>
        <div class="tracker-body">
            <form method="get" class="lookup">
                <label for="token" class="sr-only">Claim ID</label>
                <input id="token" name="token" value="<?php echo htmlspecialchars($token); ?>" placeholder="PEN-ABC123" required>
                <button type="submit">Track Claim</button>
            </form>

            <?php if ($error): ?><div class="alert" role="alert"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

            <?php if ($application): ?>
                <div class="summary">
                    <div class="fact"><span>Claim ID</span><?php echo htmlspecialchars($application['id_number']); ?></div>
                    <div class="fact"><span>Current Status</span><?php echo htmlspecialchars($status ?: 'Received'); ?></div>
                    <div class="fact"><span>Applicant</span><?php echo htmlspecialchars($application['full_name']); ?></div>
                    <div class="fact"><span>Benefit</span><?php echo htmlspecialchars(applicationTypeLabel($application['application_type'])); ?></div>
                    <div class="fact"><span>Date Submitted</span><?php echo htmlspecialchars(date('F j, Y g:i A', strtotime($application['date_submitted']))); ?></div>
                </div>

                <h2>Processing Progress</h2>
                <div class="progress" aria-label="Claim processing progress">
                    <?php foreach ($steps as $index => $step):
                        $class = $index < $currentIndex ? 'done' : ($index === $currentIndex ? 'current' : ''); ?>
                        <div class="step <?php echo $class; ?>"><?php echo htmlspecialchars($step); ?></div>
                    <?php endforeach; ?>
                </div>

                <h2>Status History</h2>
                <div class="timeline">
                    <?php foreach ($history as $event): ?>
                        <div class="event">
                            <strong><?php echo htmlspecialchars($event['previous_state'] . ' → ' . $event['new_state']); ?></strong>
                            <time><?php echo htmlspecialchars(date('F j, Y g:i A', strtotime($event['changed_at']))); ?></time>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <a class="back" href="proxy_registration.php">← Back to Representative Portal</a>
        </div>
    </section>
</main>
</body>
</html>
