<?php
require __DIR__ . '/_helpers.php';
$age = oscaAge($app['birth_date'] ?? null);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Local Senior Pension — <?php echo oscaVal($app['full_name']); ?></title>
    <style><?php echo oscaPrintStyles('P'); ?></style>
</head>
<body>
<div class="print-bar"><button onclick="window.print()">Print / Save as PDF</button><button onclick="window.close()">Close</button></div>
<div class="form-page">
    <?php oscaPrintHeader('', 'LOCAL SENIOR PENSION FORM'); ?>
    <?php oscaFieldRow(['Last Name' => $app['lastName'] ?? '', 'Suffix' => $app['suffix'] ?? '', 'First Name' => $app['firstName'] ?? '', 'Middle Name' => $app['middleName'] ?? '']); ?>
    <?php oscaFieldRow(['Address' => $app['complete_address'] ?? '', 'Control No.' => $app['control_no'] ?? $app['id_number'] ?? '']); ?>
    <?php oscaFieldRow(['Birthdate' => oscaFmtDate($app['birth_date'] ?? null), 'Age' => $age, 'Sex' => $app['gender'] ?? '', 'Contact No.' => $app['contact_number'] ?? '']); ?>
    <?php oscaFieldRow(['Senior ID No.' => oscaSeniorId($app), 'ATM/Temp Card Stub No.' => $app['atm_card_no'] ?? '', "Mother's Maiden Name" => $app['mothers_maiden_name'] ?? '']); ?>
    <div class="section-title">ECONOMIC STATUS</div>
    <?php oscaFieldRow(['Pensioner?' => ($app['is_pensioner'] ?? null) === null ? '' : ((int)$app['is_pensioner'] === 1 ? 'Yes' : 'No'), 'Source' => $app['pension_source'] ?? '', 'Amount' => isset($app['pension_amount']) ? 'P' . number_format((float)$app['pension_amount'], 2) : '']); ?>
    <?php oscaFieldRow(['Permanent source of income?' => ($app['is_permanent_income'] ?? null) === null ? '' : ((int)$app['is_permanent_income'] === 1 ? 'Yes' : 'No'), 'Income Source' => $app['income_source'] ?? '']); ?>
    <?php oscaFieldRow(['Regular family support?' => ($app['family_support'] ?? null) === null ? '' : ((int)$app['family_support'] === 1 ? 'Yes' : 'No'), 'Type of Support' => $app['family_support_type'] ?? '', 'Cash Amount' => isset($app['family_support_amount']) ? 'P' . number_format((float)$app['family_support_amount'], 2) : '']); ?>
    <?php oscaFieldRow(['Condition / Illness' => $app['health_condition'] ?? $app['medical_conditions'] ?? '', 'Own House' => ($app['owns_house'] ?? null) === null ? '' : ((int)$app['owns_house'] === 1 ? 'Yes' : 'No'), 'Renter' => ($app['is_renter'] ?? null) === null ? '' : ((int)$app['is_renter'] === 1 ? 'Yes' : 'No')]); ?>
    <div class="cert-box">I hereby certify under law on perjury that the information provided in this form is complete, true and correct to the best of my knowledge. I further authorize the City Government of Pasig to process my data, validate, and confirm the answers herein with the GSIS, SSS, DSWD and other Government/Private Agencies.</div>
    <div class="sig-line">Signature/Thumbmark over printed name of the Senior Citizen</div>
    <?php oscaFieldRow(["OSCA Personnel's Name & Signature" => '', 'Date' => '']); ?>
    <?php oscaFieldRow(['Name of Encoder' => '', 'Date Encoded' => '']); ?>
    <div class="stub"><strong>PASIG CITY LOCAL SENIOR PENSION</strong> — <?php echo oscaVal($app['full_name']); ?> | Control: <?php echo oscaVal($app['control_no'] ?? $app['id_number']); ?></div>
</div>
</body>
</html>
