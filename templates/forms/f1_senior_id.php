<?php
require __DIR__ . '/_helpers.php';
$age = oscaAge($app['birth_date'] ?? null);
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

    <div class="checkbox-row">
        <strong>Purpose:</strong>
        <?php echo oscaCheck(($app['id_purpose'] ?? 'new') === 'new'); ?> New
        <?php echo oscaCheck(($app['id_purpose'] ?? '') === 'lost'); ?> Lost
        <?php echo oscaCheck(($app['id_purpose'] ?? '') === 'change'); ?> Change
        <?php echo oscaCheck(($app['id_purpose'] ?? '') === 'transfer'); ?> Transfer
        &nbsp;&nbsp; <strong>Date Filed:</strong> <?php echo oscaFmtDate(substr($app['date_submitted'] ?? '', 0, 10), 'F j, Y'); ?>
    </div>

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
        <?php echo oscaCheck(str_contains($app['health_status'] ?? '', 'Frail')); ?> Frail/Sickly
        <?php echo oscaCheck(($app['health_status'] ?? '') === 'PWD'); ?> Disability Support
    </div>

    <div class="cert-box">
        I hereby certify under law on perjury that the information provided in this form is complete, true, correct, and of my own knowledge.
        I further authorize the City Government of Pasig to process my data, validate, and confirm the answers herein with third parties such as the GSIS, SSS, DSWD and other Government/Private Agencies.
    </div>
    <div class="sig-line">Signature/Thumbmark over printed name of the Senior Citizen</div>

    <?php oscaFieldRow([
        'Emergency Contact' => ($app['emergency_contact_name'] ?? '') . ' — ' . ($app['emergency_contact'] ?? ''),
        'Relationship' => '',
        'OSCA ID #' => $app['senior_id_no'] ?? $app['id_number'] ?? '',
    ]); ?>

    <div class="stub">
        <strong>Application ID:</strong> <?php echo oscaVal($app['id_number']); ?> |
        <strong>Barangay:</strong> <?php echo oscaVal($app['barangay']); ?> |
        <strong>Status:</strong> <?php echo oscaVal($app['workflow_state'] ?? 'Received'); ?>
    </div>
</div>
</body>
</html>
