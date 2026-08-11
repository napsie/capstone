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
    <?php oscaFieldRow(['SSS Number' => $app['sss_number'] ?? '', 'Verified Pension' => isset($app['pension_amount']) ? 'P' . number_format((float)$app['pension_amount'], 2) : '', 'Pension Source' => $app['pension_source'] ?? '']); ?>
    <?php oscaFieldRow(['Permanent Income Source' => $app['income_source'] ?? '', 'Own House' => ($app['owns_house'] ?? null) ? 'Yes' : 'No', 'Renter' => ($app['is_renter'] ?? null) ? 'Yes' : 'No']); ?>
    <?php oscaFieldRow(['Condition / Illness' => $app['health_condition'] ?? $app['medical_conditions'] ?? '']); ?>
    <div class="cert-box">I hereby certify under law on perjury that the information provided is complete, true and correct to the best of my knowledge.</div>
    <div class="sig-line">Signature/Thumbmark over printed name of the Senior Citizen</div>
    <div class="stub"><strong>PASIG CITY LOCAL SENIOR PENSION</strong> — <?php echo oscaVal($app['full_name']); ?> | Control: <?php echo oscaVal($app['control_no'] ?? $app['id_number']); ?></div>
</div>
</body>
</html>
