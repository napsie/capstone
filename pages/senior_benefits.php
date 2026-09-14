<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/barangays_list.php';
require_once '../includes/application_types.php';
require_once '../includes/process_proxy_registration.php';

$accessError = '';
$verifiedSenior = null;

if (isset($_GET['reset'])) {
    unset($_SESSION['senior_benefit_access']);
    header('Location: senior_benefits.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_benefit_access'])) {
    $seniorId = strtoupper(trim((string)($_POST['seniorCitizenId'] ?? '')));
    $token = strtoupper(trim((string)($_POST['permanentToken'] ?? '')));
    $attempts = (array)($_SESSION['senior_benefit_attempts'] ?? []);
    $attempts = array_values(array_filter($attempts, static fn($time) => (int)$time > time() - 600));

    if (count($attempts) >= 8) {
        $accessError = 'Too many unsuccessful attempts. Please wait 10 minutes before trying again.';
    } elseif ($seniorId === '' || $token === '') {
        $accessError = 'Enter both the official Senior Citizen ID number and permanent token.';
    } elseif (!preg_match('/^PRX-[A-Z0-9]{4,12}$/', $token)) {
        $accessError = 'The Senior ID number and permanent token could not be validated.';
    } else {
        $stmt = $conn->prepare("SELECT * FROM applications
            WHERE application_type = 'senior'
              AND senior_id_no = ?
              AND (id_number = ? OR proxy_token = ?)
              AND workflow_state IN ('Verified','Approved','Released')
              AND COALESCE(is_archived, 0) = 0
            LIMIT 1");
        $stmt->execute([$seniorId, $token, $token]);
        $verifiedSenior = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($verifiedSenior) {
            $_SESSION['senior_benefit_access'] = [
                'senior_id_no' => $seniorId,
                'token' => $token,
                'application_id' => $verifiedSenior['id_number'],
                'verified_at' => time(),
            ];
            unset($_SESSION['senior_benefit_attempts']);
        } else {
            $attempts[] = time();
            $_SESSION['senior_benefit_attempts'] = $attempts;
            $accessError = 'The Senior ID number and permanent token could not be validated. The ID application must already be approved.';
        }
    }
}

if (!$verifiedSenior && !empty($_SESSION['senior_benefit_access'])) {
    $access = $_SESSION['senior_benefit_access'];
    if ((int)($access['verified_at'] ?? 0) < time() - 1800) {
        unset($_SESSION['senior_benefit_access']);
        $access = [];
        $accessError = 'For security, the benefit session expired. Enter the permanent credentials again.';
    }
    $stmt = $conn->prepare("SELECT * FROM applications
        WHERE application_type = 'senior' AND senior_id_no = ?
          AND (id_number = ? OR proxy_token = ?)
          AND workflow_state IN ('Verified','Approved','Released')
          AND COALESCE(is_archived, 0) = 0 LIMIT 1");
    $stmt->execute([$access['senior_id_no'] ?? '', $access['token'] ?? '', $access['token'] ?? '']);
    $verifiedSenior = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$verifiedSenior) unset($_SESSION['senior_benefit_access']);
}

$proxyResult = processProxyRegistration();
$proxySuccess = $proxyResult['success'];
$proxyQrUrl = $proxyResult['qrCodeUrl'];
$proxyTransactionId = $proxyResult['transactionId'];
$proxyOption = $proxyResult['option'] ?? '';
$proxyMessage = $proxyResult['message'] ?? '';
$formAction = 'senior_benefits.php';
$resetUrl = 'senior_benefits.php?reset=1';
$benefitPortalMode = true;
$unavailableBenefitRequests = [];

if ($verifiedSenior) {
    $existingBenefitsStmt = $conn->prepare("SELECT requested_benefit, workflow_state, status
        FROM applications
        WHERE id_number <> ?
          AND (parent_senior_id = ? OR senior_id_no = ?)
          AND requested_benefit IS NOT NULL
          AND requested_benefit <> ''
          AND COALESCE(is_archived, 0) = 0
          AND COALESCE(workflow_state, '') <> 'Rejected'
          AND LOWER(COALESCE(status, '')) <> 'rejected'");
    $existingBenefitsStmt->execute([
        $verifiedSenior['id_number'],
        $verifiedSenior['id_number'],
        $verifiedSenior['senior_id_no'] ?? '',
    ]);
    foreach ($existingBenefitsStmt->fetchAll(PDO::FETCH_ASSOC) as $existingBenefit) {
        $request = trim((string)($existingBenefit['requested_benefit'] ?? ''));
        if ($request !== '') {
            $unavailableBenefitRequests[$request] = (string)($existingBenefit['workflow_state'] ?: $existingBenefit['status'] ?: 'Pending');
        }
    }
}

$benefitPrefill = [];
if ($verifiedSenior) {
    $benefitPrefill = [
        'seniorCitizenId' => $verifiedSenior['senior_id_no'] ?? '',
        'permanentToken' => $_SESSION['senior_benefit_access']['token'] ?? '',
        'lastName' => $verifiedSenior['lastName'] ?? '', 'firstName' => $verifiedSenior['firstName'] ?? '',
        'middleName' => $verifiedSenior['middleName'] ?? '', 'suffix' => $verifiedSenior['suffix'] ?? '',
        'birthDate' => $verifiedSenior['birth_date'] ?? '', 'contactNumber' => $verifiedSenior['contact_number'] ?? '',
        'placeOfBirth' => $verifiedSenior['place_of_birth'] ?? '', 'mothersMaidenName' => $verifiedSenior['mothers_maiden_name'] ?? '',
        'gender' => $verifiedSenior['gender'] ?? '', 'civilStatus' => $verifiedSenior['civil_status'] ?? '',
        'houseNo' => $verifiedSenior['house_no'] ?? '', 'street' => $verifiedSenior['street'] ?? '',
        'barangay' => $verifiedSenior['barangay'] ?? '', 'zipCode' => $verifiedSenior['zip_code'] ?? '',
        'landmark' => $verifiedSenior['landmark'] ?? '', 'seniorEmail' => $verifiedSenior['email_address'] ?? '',
        'completeAddress' => $verifiedSenior['complete_address'] ?? '',
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>SENIORLINK — Senior Benefits</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/seniorlink-public.css?v=1"><link rel="stylesheet" href="../assets/css/seniorlink-ui.css?v=16">
    <link rel="stylesheet" href="../assets/css/benefit-information-modal.css?v=1">
    <style>
        body{font-family:Inter,sans-serif}.page-bg{position:fixed!important;inset:0!important;width:100vw;height:100vh;transform:none!important;animation:none!important}.page-bg::before,.page-bg::after{transform:none!important;animation:none!important}.benefit-portal{padding-top:80px}.benefit-access{max-width:760px;padding:0}.benefit-access h1{margin-bottom:8px}.benefit-access>p{margin-bottom:20px;color:#64748b;line-height:1.55}
        .access-grid{display:grid;grid-template-columns:1fr 1fr;gap:15px}.access-field label{display:block;margin-bottom:7px;font-size:.78rem;font-weight:800;color:#475569;text-transform:uppercase}.access-field input{width:100%;padding:13px;border:1px solid #cbd5e1;border-radius:10px;font-size:1rem}.access-btn{width:100%;margin-top:18px;padding:13px;border:0;border-radius:10px;background:#2563eb;color:#fff;font-weight:800;cursor:pointer}.access-error{margin:16px 0 0;padding:12px;border-radius:9px;background:#fef2f2;color:#b91c1c}.verified-banner{display:flex;justify-content:space-between;gap:16px;align-items:center;margin:80px auto 18px;max-width:1080px;padding:16px 20px;border:1px solid #86efac;border-radius:13px;background:#f0fdf4;color:#166534}.verified-banner a{color:#166534;font-weight:800}.verified-identity{padding:14px 18px;margin-bottom:20px;border-radius:11px;background:#eff6ff;color:#1e3a8a}.benefit-mode .proxy-panel-header{display:none}.benefit-mode .form-section:first-of-type input,.benefit-mode .form-section:first-of-type select{background:#f8fafc}
        @media(max-width:700px){.access-grid{grid-template-columns:1fr}.benefit-portal{padding-top:62px}.verified-banner{margin:72px 12px 16px;align-items:flex-start;flex-direction:column}}
    </style>
</head>
<body>
<a href="../index.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Home</a>
<div class="page-bg page-bg--pages" aria-hidden="true"></div>
<?php if (!$verifiedSenior && !$proxySuccess): ?>
<main class="landing-wrapper benefit-portal">
    <section class="proxy-section" aria-labelledby="benefit-portal-heading">
        <div class="proxy-panel">
            <div class="proxy-panel-header">
                <div class="hero-badge"><i class="fas fa-people-roof" aria-hidden="true"></i> Public Senior Service</div>
                <h2 id="benefit-portal-heading">Senior Application Portal</h2>
                <p>Apply for senior citizen services using your approved Senior Citizen ID.</p>
            </div>
            <div class="proxy-panel-body">
                <div class="benefit-access">
                    <div class="hero-badge"><i class="fas fa-shield-halved"></i> Verified Senior Access</div>
                    <h1>Apply for Senior Benefits</h1>
                    <p>Benefits are available only after the Senior Citizen ID application is approved. Enter the issued ID number and the permanent token received during the original ID application.</p>
                    <form method="post">
                        <input type="hidden" name="verify_benefit_access" value="1">
                        <div class="access-grid">
                            <div class="access-field"><label for="seniorCitizenId">Senior Citizen ID Number</label><input id="seniorCitizenId" name="seniorCitizenId" autocomplete="off" required></div>
                            <div class="access-field"><label for="permanentToken">Permanent ID Token</label><input id="permanentToken" name="permanentToken" placeholder="PRX-XXXX" maxlength="16" autocomplete="off" required></div>
                        </div>
                        <button class="access-btn" type="submit"><i class="fas fa-user-check"></i> Verify Identity</button>
                        <?php if ($accessError): ?><div class="access-error" role="alert"><?php echo htmlspecialchars($accessError); ?></div><?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </section>
</main>
<?php else: ?>
<div class="verified-banner"><div><strong><i class="fas fa-circle-check"></i> Identity verified</strong><br><?php echo htmlspecialchars($verifiedSenior['full_name'] ?? 'Senior citizen'); ?> · <?php echo htmlspecialchars($verifiedSenior['senior_id_no'] ?? ''); ?></div><a href="?reset=1">Use another ID</a></div>
<div class="landing-wrapper benefit-mode"><section class="proxy-section"><div class="proxy-panel"><div class="proxy-panel-body">
    <?php include '../partials/proxy_form.php'; ?>
</div></div></section></div>
<?php endif; ?>
<script>
document.addEventListener('DOMContentLoaded',()=>{
  <?php if ($verifiedSenior): ?>
  document.querySelectorAll('#newSeniorForm input,#newSeniorForm select').forEach(el=>{
    if (['file','hidden','checkbox','radio'].includes(el.type) || el.closest('.benefit-specific-panel')) return;
    el.readOnly=true; if(el.tagName==='SELECT') el.style.pointerEvents='none';
  });
  <?php endif; ?>
});
</script>
</body></html>
