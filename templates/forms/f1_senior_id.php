<?php
require __DIR__ . '/_helpers.php';
$age = oscaAge($app['birth_date'] ?? null);
$purpose = strtolower((string)($app['id_purpose'] ?? 'new'));
$addr = trim(($app['house_no'] ?? '') . ' ' . ($app['street'] ?? '') . ', ' . ($app['barangay'] ?? '') . ', ' . ($app['city'] ?? 'Pasig City'));
if ($addr === ', , Pasig City') $addr = $app['complete_address'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Senior Citizens ID — <?php echo oscaVal($app['full_name']); ?></title>
    <style><?php echo oscaPrintStyles('F1'); ?></style>
</head>
<body>
<div class="print-bar">
    <button onclick="window.print()">Print / Save as PDF</button>
    <button onclick="window.close()">Close</button>
</div>
<div class="form-page">
    <?php oscaPrintHeader('', 'SENIOR CITIZENS ID APPLICATION'); ?>

    <div class="id-form-heading">
        <div class="checkbox-row id-form-purpose">
            <strong>Purpose:</strong>
            <?php echo oscaCheck($purpose === 'new'); ?> New
            <?php echo oscaCheck($purpose === 'lost'); ?> Lost
            <?php echo oscaCheck($purpose === 'change'); ?> Change
            <?php echo oscaCheck(str_starts_with($purpose, 'transfer')); ?> Transfer
            <br><strong>Date Filed:</strong> <?php echo oscaFmtDate(substr($app['date_submitted'] ?? '', 0, 10), 'F j, Y'); ?>
        </div>
        <div class="id-photo-box">
            <?php if (!empty($app['has_id_image'])): ?>
                <img src="../api/get_document.php?id=<?php echo rawurlencode((string)$app['id_number']); ?>&amp;doc_type=id_image"
                     alt="Uploaded ID photo of <?php echo oscaVal($app['full_name']); ?>">
            <?php else: ?>
                <div class="id-photo-placeholder">1×1 ID PHOTO<br><span>White background</span></div>
            <?php endif; ?>
            <div class="id-photo-caption">Latest Photo</div>
        </div>
    </div>

    <?php oscaFieldRow([
        'OSCA ID #' => oscaSeniorId($app, 'Pending approval'),
        'OSCA Personnel' => $app['senior_id_issued_by'] ?? '',
        'Date Issued' => oscaFmtDate($app['senior_id_issued_at'] ?? null),
    ]); ?>

    <?php oscaFieldRow([
        'Last Name' => $app['lastName'] ?? '',
        'Ext' => $app['suffix'] ?? '',
        'First Name' => $app['firstName'] ?? '',
        'Middle Name' => $app['middleName'] ?? '',
    ]); ?>
    <?php oscaFieldRow([
        'Date of Birth' => oscaFmtDate($app['birth_date'] ?? null),
        'Place of Birth' => $app['place_of_birth'] ?? '',
        'Age' => $age,
    ]); ?>
    <?php oscaFieldRow([
        'Gender' => $app['gender'] ?? '',
        'Civil Status' => $app['civil_status'] ?? '',
        'Contact No.' => $app['contact_number'] ?? '',
    ]); ?>
    <?php oscaFieldRow([
        'Complete Address' => $addr,
        'Landmark' => $app['landmark'] ?? '',
        'ZIP Code' => $app['zip_code'] ?? '',
    ]); ?>
    <?php oscaFieldRow([
        "Mother's Maiden Name" => $app['mothers_maiden_name'] ?? '',
        'Health Status' => $app['health_status'] ?? '',
        'Barangay' => $app['barangay'] ?? '',
    ]); ?>

    <div class="checkbox-row">
        <strong>Health:</strong>
        <?php echo oscaCheck(($app['health_status'] ?? '') === 'Physically Fit'); ?> Physically Fit
        <?php echo oscaCheck(($app['health_status'] ?? '') === 'Bedridden'); ?> Bedridden
        <?php echo oscaCheck(str_contains($app['health_status'] ?? '', 'Frail')); ?> Frail/Sickly:
        <?php echo oscaVal($app['health_condition'] ?? ''); ?>
        <?php echo oscaCheck(($app['health_status'] ?? '') === 'PWD'); ?> PWD:
        <?php echo ($app['health_status'] ?? '') === 'PWD' ? oscaVal($app['health_condition'] ?? '') : ''; ?>
    </div>

    <div class="section-title">REQUIREMENTS</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:8px;line-height:1.35;">
        <div>
            <strong>NEW APPLICANT (FILIPINO CITIZEN)</strong><br>
            • Two (2) recent 1×1 ID pictures, white background<br>
            • Birth Certificate (original and photocopy)<br>
            • Original Barangay Residency Certificate<br>
            <strong>If no Birth Certificate:</strong> Negative Certification of Birth and two valid IDs showing birth date and Pasig address<br>
            <strong>Dual Citizen:</strong> Oath of Allegiance/Naturalization, valid Philippine Passport, and proof of six-month Pasig residency
            <br><br><strong>CHANGE / REPLACEMENT OF SENIOR CITIZEN ID</strong><br>
            • Two (2) recent 1×1 ID pictures<br>• Original Senior Citizen ID
        </div>
        <div>
            <strong>TRANSFER FROM OTHER CITY / MUNICIPALITY</strong><br>
            • Two (2) recent 1×1 ID pictures, white background<br>
            • Certificate of Cancellation from previous OSCA<br>
            • Birth Certificate<br>• Original Barangay Residency Certificate
            <br><br><strong>TRANSFER BETWEEN BARANGAYS</strong><br>
            • Two (2) recent 1×1 ID pictures, white background<br>
            • Original Senior Citizen ID<br>• Certificate of Transfer from previous barangay
            <br><br><strong>LOST SENIOR CITIZEN ID</strong><br>
            • Two (2) recent 1×1 ID pictures, white background<br>
            • Original Affidavit of Loss<br>
            • Photocopy of Senior ID / Landbank cash card / temporary cash-card stub (front and back)
        </div>
    </div>

    <div class="cert-box">
        I hereby certify under law on perjury that the information provided in this form is complete, true, correct, and of my own knowledge.
        I further authorize the City Government of Pasig to process my data, validate, and confirm the answers herein with third parties such as the GSIS, SSS, DSWD and other Government/Private Agencies.
    </div>
    <div class="sig-line">Signature/Thumbmark over printed name of the Senior Citizen</div>

    <?php oscaFieldRow([
        'Emergency Contact' => ($app['emergency_contact_name'] ?? '') . ' — ' . ($app['emergency_contact'] ?? ''),
        'Relationship' => $app['claimant_relationship'] ?? '',
    ]); ?>

    <div class="stub">
        <strong>Application ID:</strong> <?php echo oscaVal($app['id_number']); ?> |
        <strong>Barangay:</strong> <?php echo oscaVal($app['barangay']); ?> |
        <strong>Status:</strong> <?php echo oscaVal($app['workflow_state'] ?? 'Received'); ?>
    </div>
</div>
</body>
</html>
