<?php
require __DIR__ . '/_helpers.php';
$age = oscaAge($app['birth_date'] ?? null);
$purposes = array_map('trim', explode(',', $app['visit_purpose'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>F8 — Home Visit — <?php echo oscaVal($app['full_name']); ?></title>
    <style><?php echo oscaPrintStyles('F8'); ?></style>
</head>
<body>
<div class="print-bar"><button onclick="window.print()">Print / Save as PDF</button><button onclick="window.close()">Close</button></div>
<div class="form-page">
    <?php oscaPrintHeader('F8', 'HOME VISITATION / CONFIRMATION FORM'); ?>
    <div class="checkbox-row">
        <?php foreach (['LOCAL PENSION','BURIAL ASSISTANCE','SENIOR ID','CASH GIFT','OCTOGENARIAN'] as $p): ?>
            <?php echo oscaCheck(in_array($p, $purposes)); ?> <?php echo $p; ?>
        <?php endforeach; ?>
    </div>
    <?php oscaFieldRow(['Last Name' => $app['lastName'] ?? '', 'First Name' => $app['firstName'] ?? '', 'Middle Name' => $app['middleName'] ?? '', 'Age' => $age]); ?>
    <?php oscaFieldRow(['SC ID No.' => $app['senior_id_no'] ?? '', 'Birth Date' => oscaFmtDate($app['birth_date'] ?? null), 'Gender' => $app['gender'] ?? '', 'Contact' => $app['contact_number'] ?? '']); ?>
    <?php oscaFieldRow(['Present Address' => $app['complete_address'] ?? '', 'Barangay' => $app['barangay'] ?? '']); ?>
    <div class="section-title">CONFIRMED THE FOLLOWING</div>
    <?php oscaFieldRow(['Living Arrangement' => $app['living_arrangement'] ?? '', 'Pensioner' => ($app['is_pensioner'] ?? null) === '1' || ($app['is_pensioner'] ?? null) === 1 ? 'Yes' : 'No', 'Pension Source' => $app['pension_source'] ?? '']); ?>
    <?php oscaFieldRow(['Health Condition' => $app['health_condition'] ?? '', 'With Maintenance' => ($app['with_maintenance'] ?? null) ? 'Yes — ' . ($app['maintenance_spec'] ?? '') : 'No']); ?>
    <div class="section-title">VISIT SUMMARY</div>
    <div class="val" style="border:1px solid #000; min-height:80px; padding:6px;"><?php echo nl2br(oscaVal($app['visit_summary'] ?? '')); ?></div>
    <div class="sig-line">Confirmation Made With — Signature over Printed Name</div>
    <div class="sig-line">Visited by — Signature over Printed Name &nbsp;&nbsp; Date and time of interview</div>
</div>
</body>
</html>
