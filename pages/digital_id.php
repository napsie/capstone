<?php
session_start();
require_once '../includes/db_connect.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['barangay_staff', 'department_admin', 'super_admin'], true)) {
    header('Location: ../index.php');
    exit;
}

$applicationId = trim((string)($_GET['id'] ?? ''));
if ($applicationId === '' || mb_strlen($applicationId) > 255) {
    http_response_code(400);
    exit('A valid application ID is required.');
}

$sql = "SELECT id_number, full_name, birth_date, complete_address, barangay, senior_id_no, date_submitted
        FROM applications
        WHERE id_number = ? AND application_type = 'senior'
          AND workflow_state IN ('Verified', 'Approved', 'Released')
          AND senior_id_no IS NOT NULL AND senior_id_no <> ''
          AND senior_id_no NOT REGEXP '^OSCA-[0-9]{4}-[0-9A-F]{6}$'
          AND COALESCE(is_archived, 0) = 0";
$params = [$applicationId];
if (($_SESSION['role'] ?? '') === 'barangay_staff') {
    $sql .= ' AND barangay = ?';
    $params[] = $_SESSION['barangay'] ?? '';
}
$sql .= ' LIMIT 1';
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$application = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$application) {
    http_response_code(404);
    exit('Digital ID is unavailable or you do not have access to this application.');
}

$issuedStmt = $conn->prepare("SELECT changed_at FROM application_history WHERE application_id = ? AND new_state = 'Verified' ORDER BY changed_at DESC LIMIT 1");
$issuedStmt->execute([$applicationId]);
$issuedAt = $issuedStmt->fetchColumn() ?: $application['date_submitted'];
$birthDate = new DateTimeImmutable($application['birth_date']);
$age = $birthDate->diff(new DateTimeImmutable('today'))->y;
$photoUrl = '../api/get_document.php?id=' . rawurlencode($applicationId) . '&doc_type=id_image';
$embedded = ($_GET['embed'] ?? '') === '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>Temporary Digital ID — SENIORLINK</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{box-sizing:border-box} body{margin:0;min-height:100vh;padding:32px 16px;background:#eef3f9;color:#172033;font-family:Inter,"Segoe UI",Arial,sans-serif}body.embedded{padding:20px;background:#fff}.embedded .topbar{display:none}.embedded .panel{padding:10px;border:0;box-shadow:none}.page{width:min(820px,100%);margin:auto}.topbar{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:20px}.topbar h1{margin:0;font-size:1.25rem}.actions{display:flex;gap:8px}.button{display:inline-flex;align-items:center;gap:7px;padding:10px 14px;border:0;border-radius:8px;background:#fff;color:#334155;box-shadow:0 1px 4px rgba(15,23,42,.12);font:inherit;font-weight:700;text-decoration:none;cursor:pointer}.button.primary{background:#2563eb;color:#fff}.panel{padding:24px;border:1px solid #d9e3ee;border-radius:18px;background:#fff;box-shadow:0 18px 46px rgba(15,23,42,.12)}.intro{display:flex;justify-content:space-between;gap:14px;margin-bottom:16px}.intro h2{margin:0 0 4px;font-size:1rem}.intro p{margin:0;color:#64748b;font-size:.82rem}.badge{height:max-content;padding:6px 9px;border-radius:999px;background:#fef3c7;color:#92400e;font-size:.67rem;font-weight:900;letter-spacing:.06em}.id-card{position:relative;overflow:hidden;width:100%;aspect-ratio:1.586/1;border:1px solid #aab7c7;border-radius:18px;background:#fffefa;box-shadow:0 14px 30px rgba(15,23,42,.18)}.id-card::after{content:'TEMPORARY';position:absolute;left:21%;top:48%;transform:rotate(-22deg);color:rgba(37,99,235,.065);font-size:4rem;font-weight:900;letter-spacing:.1em;pointer-events:none}.card-head{height:28%;padding:13px 20px;display:flex;align-items:center;gap:14px;color:#fff;background:linear-gradient(135deg,#0b1938,#172554 70%,#1e3a8a);border-bottom:10px solid #3b82f6}.logo{width:62px;height:62px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,.75)}.agency{flex:1;text-align:center;text-transform:uppercase;line-height:1.05}.agency small,.agency strong,.agency span{display:block}.agency small{font-size:.65rem;letter-spacing:.12em;margin-bottom:4px}.agency strong{font-size:1.3rem}.agency span{margin-top:4px;font-size:.8rem;font-weight:800}.card-body{height:72%;padding:20px 22px 15px;display:grid;grid-template-columns:minmax(0,1fr) 142px;grid-template-rows:1fr auto;gap:10px 20px}.number{margin-bottom:12px;color:#dc2626;font-size:1.4rem;font-weight:900;letter-spacing:.04em}.number span{color:#334155;font-size:.75rem;margin-right:8px}.field{display:grid;grid-template-columns:78px minmax(0,1fr);align-items:end;margin:9px 0}.field span{font-size:.68rem;font-weight:900}.field strong{overflow:hidden;padding:0 4px 3px;border-bottom:1px solid #475569;font-size:1rem;line-height:1.1;text-transform:uppercase;white-space:nowrap;text-overflow:ellipsis}.photo{position:relative;display:grid;place-items:center;width:142px;height:168px;overflow:hidden;border:2px solid #334155;background:#e2e8f0;color:#94a3b8;font-size:3rem}.photo img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}.dates{grid-column:1/-1;display:grid;grid-template-columns:1fr 1fr;gap:24px;text-align:center}.dates div{padding-bottom:3px;border-bottom:1px solid #475569;font-size:.85rem;font-weight:800}.dates span{display:block;margin-top:4px;color:#475569;font-size:.56rem;font-weight:900;letter-spacing:.05em}.notice{margin:14px 0 0;color:#475569;text-align:center;font-size:.76rem;font-weight:700}@media(max-width:620px){body{padding:16px 10px}body.embedded{padding:8px}.topbar{align-items:flex-start;flex-direction:column}.panel{padding:12px}.id-card{aspect-ratio:auto}.card-head{height:auto}.logo{width:42px;height:42px}.agency strong{font-size:.8rem}.agency span{font-size:.58rem}.card-body{height:auto;padding:12px;grid-template-columns:minmax(0,1fr) 88px}.photo{width:88px;height:108px}.number{font-size:.9rem}.field{grid-template-columns:54px 1fr}.field strong{font-size:.66rem}.id-card::after{font-size:2rem}}@media print{body{padding:0;background:#fff}.topbar,.intro,.notice{display:none}.panel{padding:0;border:0;box-shadow:none}.id-card{width:100%;box-shadow:none}}
    </style>
</head>
<body class="<?= $embedded ? 'embedded' : '' ?>">
<main class="page">
    <div class="topbar"><h1>Temporary Digital Senior Citizen ID</h1><div class="actions"><button class="button" type="button" onclick="history.back()"><i class="fas fa-arrow-left"></i> Back</button><button class="button primary" type="button" onclick="window.print()"><i class="fas fa-print"></i> Print / Save PDF</button></div></div>
    <section class="panel">
        <div class="intro"><div><h2>Approved digital credential</h2><p>Available while the applicant waits for the physical OSCA card.<br><strong>Application Token:</strong> <?= htmlspecialchars($application['id_number']) ?> — use this token to track the application.</p></div><span class="badge">TEMPORARY</span></div>
        <div class="id-card">
            <div class="card-head"><img class="logo" src="../images/logo.jpg" alt="SENIORLINK logo"><div class="agency"><small>Republic of the Philippines</small><strong>City Government of Pasig</strong><span>Office for Senior Citizens Affairs</span></div></div>
            <div class="card-body">
                <div><div class="number"><span>ID NO.</span><?= htmlspecialchars($application['senior_id_no']) ?></div><div class="field"><span>NAME</span><strong><?= htmlspecialchars($application['full_name']) ?></strong></div><div class="field"><span>ADDRESS</span><strong><?= htmlspecialchars($application['complete_address']) ?></strong></div><div class="field"><span>BARANGAY</span><strong><?= htmlspecialchars($application['barangay']) ?>, PASIG CITY</strong></div></div>
                <div class="photo"><i class="fas fa-user" aria-hidden="true"></i><img src="<?= htmlspecialchars($photoUrl) ?>" alt="Applicant ID photo" onerror="this.remove()"></div>
                <div class="dates"><div><?= htmlspecialchars($birthDate->format('m/d/Y')) ?> (<?= $age ?>)<span>DATE OF BIRTH / AGE</span></div><div><?= htmlspecialchars(date('m/d/Y', strtotime($issuedAt))) ?><span>DATE ISSUED</span></div></div>
            </div>
        </div>
        <p class="notice"><i class="fas fa-circle-info"></i> Temporary digital credential only. Confirm the application remains approved before accepting it.</p>
    </section>
</main>
</body>
</html>
