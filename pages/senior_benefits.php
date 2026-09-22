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
        $stmt = $conn->prepare("SELECT id_number, full_name, lastName, firstName, middleName, suffix, birth_date, contact_number, complete_address, barangay, senior_id_no, id_image, email_address, place_of_birth, gender, civil_status, mothers_maiden_name, house_no, street, zip_code, landmark, health_status, health_condition, emergency_contact_name, emergency_contact, claimant_relationship, id_purpose, date_submitted FROM applications
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
    $stmt = $conn->prepare("SELECT id_number, full_name, lastName, firstName, middleName, suffix, birth_date, contact_number, complete_address, barangay, senior_id_no, id_image, email_address, place_of_birth, gender, civil_status, mothers_maiden_name, house_no, street, zip_code, landmark, health_status, health_condition, emergency_contact_name, emergency_contact, claimant_relationship, id_purpose, date_submitted FROM applications
        WHERE application_type = 'senior' AND senior_id_no = ?
          AND (id_number = ? OR proxy_token = ?)
          AND workflow_state IN ('Verified','Approved','Released')
          AND COALESCE(is_archived, 0) = 0 LIMIT 1");
    $stmt->execute([$access['senior_id_no'] ?? '', $access['token'] ?? '', $access['token'] ?? '']);
    $verifiedSenior = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$verifiedSenior) unset($_SESSION['senior_benefit_access']);
}

$transferResidencyStart = null;
$transferBenefitEligibleAt = null;
$transferBenefitsLocked = false;
if ($verifiedSenior && strtolower(trim((string)($verifiedSenior['id_purpose'] ?? ''))) === 'transfer') {
    $startValue = substr(trim((string)($verifiedSenior['date_submitted'] ?? '')), 0, 10);
    $portalTimezone = new DateTimeZone('Asia/Manila');
    $transferResidencyStart = DateTimeImmutable::createFromFormat('!Y-m-d', $startValue, $portalTimezone) ?: null;
    if ($transferResidencyStart) {
        $transferBenefitEligibleAt = $transferResidencyStart->modify('+2 years');
        $transferBenefitsLocked = new DateTimeImmutable('today', $portalTimezone) < $transferBenefitEligibleAt;
    }
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
        'healthStatus' => $verifiedSenior['health_status'] ?? '', 'healthCondition' => $verifiedSenior['health_condition'] ?? '',
        'emergencyContactName' => $verifiedSenior['emergency_contact_name'] ?? '',
        'emergencyContact' => $verifiedSenior['emergency_contact'] ?? '',
        'emergencyContactRelationship' => $verifiedSenior['claimant_relationship'] ?? '',
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
    <link rel="stylesheet" href="../assets/css/seniorlink-public.css?v=1"><link rel="stylesheet" href="../assets/css/seniorlink-ui.css?v=20">
    <link rel="stylesheet" href="../assets/css/benefit-information-modal.css?v=1">
    <style>
        body{font-family:Inter,sans-serif}.page-bg{position:fixed!important;inset:0!important;width:100vw;height:100vh;transform:none!important;animation:none!important}.page-bg::before,.page-bg::after{transform:none!important;animation:none!important}.benefit-portal{padding-top:80px}.benefit-access{max-width:760px;padding:0}.benefit-access h1{margin-bottom:8px}.benefit-access>p{margin-bottom:20px;color:#64748b;line-height:1.55}
        .access-grid{display:grid;grid-template-columns:1fr 1fr;gap:15px}.access-field label{display:block;margin-bottom:7px;font-size:.78rem;font-weight:800;color:#475569;text-transform:uppercase}.access-field input{width:100%;padding:13px;border:1px solid #cbd5e1;border-radius:10px;font-size:1rem}.access-btn{width:100%;margin-top:18px;padding:13px;border:0;border-radius:10px;background:#2563eb;color:#fff;font-weight:800;cursor:pointer}.access-error{margin:16px 0 0;padding:12px;border-radius:9px;background:#fef2f2;color:#b91c1c}.verified-banner{display:flex;justify-content:space-between;gap:16px;align-items:center;margin:80px auto 18px;max-width:1080px;padding:16px 20px;border:1px solid #86efac;border-radius:13px;background:#f0fdf4;color:#166534}.verified-banner a{color:#166534;font-weight:800}.verified-identity{padding:14px 18px;margin-bottom:20px;border-radius:11px;background:#eff6ff;color:#1e3a8a}.benefit-mode .proxy-panel-header{display:none}.benefit-mode .form-section:first-of-type input,.benefit-mode .form-section:first-of-type select{background:#f8fafc}
        .benefit-mode .information-change-field label{color:#1d4ed8}.benefit-mode .information-change-field input,.benefit-mode .information-change-field select{background:#fff;border-color:#60a5fa;box-shadow:0 0 0 3px rgba(37,99,235,.08)}
        .information-change-guide{display:flex;gap:13px;align-items:flex-start;margin:0 0 18px;padding:16px 18px;border:1px solid #93c5fd;border-radius:12px;background:#eff6ff;color:#1e3a8a;line-height:1.5}.information-change-guide[hidden]{display:none}.information-change-guide__icon{display:grid;place-items:center;flex:0 0 36px;height:36px;border-radius:9px;background:#2563eb;color:#fff}.information-change-guide strong{display:block;margin-bottom:5px;font-size:1rem}.information-change-guide ol{margin:0 0 12px;padding-left:20px}.information-change-guide li+li{margin-top:3px}.information-change-guide__button{padding:8px 11px;border:1px solid #93c5fd;border-radius:8px;background:#fff;color:#1d4ed8;font:inherit;font-size:.86rem;font-weight:700;cursor:pointer}.information-change-guide__button:hover{background:#dbeafe}.information-change-guide__button:focus-visible{outline:3px solid rgba(37,99,235,.3);outline-offset:2px}
        .transfer-residency-notice{display:flex;gap:16px;align-items:flex-start;margin:0 0 22px;padding:20px;border:1px solid #f6c453;border-radius:14px;background:#fffbeb;color:#713f12}.transfer-residency-notice__icon{display:grid;place-items:center;flex:0 0 42px;height:42px;border-radius:11px;background:#f59e0b;color:#fff;font-size:1.1rem}.transfer-residency-notice h2{margin:0 0 7px;font-size:1.08rem;color:#78350f}.transfer-residency-notice p{margin:0;line-height:1.55}.transfer-residency-dates{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px 20px;margin-top:13px;padding-top:13px;border-top:1px solid #fde68a}.transfer-residency-dates span{display:block;font-size:.78rem;font-weight:700;color:#92400e}.transfer-residency-dates strong{display:block;margin-top:3px;color:#451a03}
        @media(max-width:700px){.access-grid{grid-template-columns:1fr}.benefit-portal{padding-top:62px}.verified-banner{margin:72px 12px 16px;align-items:flex-start;flex-direction:column}.transfer-residency-notice{margin-left:0;margin-right:0;padding:16px}.transfer-residency-dates{grid-template-columns:1fr}}
    </style>
</head>
<body>
<script src="../assets/js/session-timeout.js?v=1"></script>
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
    <?php if ($transferBenefitsLocked && $transferResidencyStart && $transferBenefitEligibleAt): ?>
    <aside class="transfer-residency-notice" role="status" aria-labelledby="transfer-residency-heading">
        <span class="transfer-residency-notice__icon"><i class="fas fa-lock" aria-hidden="true"></i></span>
        <div>
            <h2 id="transfer-residency-heading">Benefits Not Yet Available</h2>
            <p>As a transferred senior citizen, you must complete the required 2-year residency period before you can apply for senior citizen benefits. You may apply once your residency requirement has been completed.</p>
            <div class="transfer-residency-dates">
                <div><span>Transfer/Residency Start Date</span><strong><?php echo htmlspecialchars($transferResidencyStart->format('F j, Y')); ?></strong></div>
                <div><span>Eligible to Apply Starting</span><strong><?php echo htmlspecialchars($transferBenefitEligibleAt->format('F j, Y')); ?></strong></div>
            </div>
        </div>
    </aside>
    <?php endif; ?>
    <?php include '../partials/proxy_form.php'; ?>
</div></div></section></div>
<?php endif; ?>
<script>
document.addEventListener('DOMContentLoaded',()=>{
  <?php if ($verifiedSenior): ?>
  const editableChangeFields = new Set(['lastName','firstName','middleName','suffix','birthDate','contactNumber','placeOfBirth','gender','civilStatus','mothersMaidenName','houseNo','street','zipCode','landmark','seniorEmail']);
  document.querySelectorAll('#newSeniorForm input,#newSeniorForm select').forEach(el=>{
    if (['file','hidden','checkbox','radio'].includes(el.type) || el.closest('.benefit-specific-panel')) return;
    el.readOnly=true; if(el.tagName==='SELECT') el.style.pointerEvents='none';
  });
  function syncInformationChangeFields(){
    const canEdit = document.getElementById('requestedBenefit')?.value === 'Senior Citizen ID Registration'
      && document.getElementById('idPurpose')?.value === 'change';
    editableChangeFields.forEach(id=>{
      const field=document.getElementById(id); if(!field)return;
      field.readOnly=!canEdit;
      if(field.tagName==='SELECT')field.style.pointerEvents=canEdit?'':'none';
      field.closest('.form-group')?.classList.toggle('information-change-field',canEdit);
    });
    document.getElementById('informationChangeNotice')?.toggleAttribute('hidden',!canEdit);
  }
  window.focusInformationChangeFields=()=>{
    const firstEditable=document.getElementById('lastName');
    if(!firstEditable)return;
    firstEditable.scrollIntoView({behavior:'smooth',block:'center'});
    window.setTimeout(()=>firstEditable.focus({preventScroll:true}),350);
  };
  document.getElementById('idPurpose')?.addEventListener('change',syncInformationChangeFields);
  document.getElementById('requestedBenefit')?.addEventListener('change',syncInformationChangeFields);
  syncInformationChangeFields();
  <?php endif; ?>
});
</script>
</body></html>
