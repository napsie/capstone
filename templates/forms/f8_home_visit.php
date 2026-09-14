<?php
require __DIR__ . '/_helpers.php';
$age = oscaAge($app['birth_date'] ?? null);
$purposes = array_map('trim', explode(',', $app['visit_purpose'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Home Visit — <?php echo oscaVal($app['full_name']); ?></title>
    <style><?php echo oscaPrintStyles('F8'); ?></style>
</head>
<body>
<div class="print-bar"><button onclick="window.print()">Print / Save as PDF</button><button onclick="window.close()">Close</button></div>
<div class="form-page">
    <?php oscaPrintHeader('', 'HOME VISITATION / CONFIRMATION FORM'); ?>
    <div class="checkbox-row">
        <?php foreach (['LOCAL PENSION','BURIAL ASSISTANCE','SENIOR ID','CASH GIFT','OCTOGENARIAN','NONAGENARIAN','CENTENARIAN','OTHERS'] as $p): ?>
            <?php echo oscaCheck(in_array($p, $purposes)); ?> <?php echo $p; ?>
        <?php endforeach; ?>
    </div>
    <?php oscaFieldRow(['Last Name' => $app['lastName'] ?? '', 'First Name' => $app['firstName'] ?? '', 'Middle Name' => $app['middleName'] ?? '', 'Age' => $age]); ?>
    <?php oscaFieldRow(['SC ID No.' => oscaSeniorId($app), 'Birth Date' => oscaFmtDate($app['birth_date'] ?? null), 'Gender' => $app['gender'] ?? '', 'Contact' => $app['contact_number'] ?? '']); ?>
    <?php oscaFieldRow(['Present Address' => $app['complete_address'] ?? '', 'Barangay' => $app['barangay'] ?? '']); ?>
    <div class="section-title">CONFIRMED THE FOLLOWING</div>
    <?php oscaFieldRow(['Living Arrangement' => $app['living_arrangement'] ?? '']); ?>
    <div class="section-title">ECONOMIC STATUS</div>
    <?php oscaFieldRow(['Pensioner' => ($app['is_pensioner'] ?? null) === null ? '' : ((int)$app['is_pensioner'] === 1 ? 'Yes' : 'No'), 'Source' => $app['pension_source'] ?? '', 'Amount' => isset($app['pension_amount']) ? 'P' . number_format((float)$app['pension_amount'], 2) : '']); ?>
    <?php oscaFieldRow(['Regular Family Support' => ($app['family_support'] ?? null) === null ? '' : ((int)$app['family_support'] === 1 ? 'Yes' : 'No'), 'Amount' => isset($app['family_support_amount']) ? 'P' . number_format((float)$app['family_support_amount'], 2) : '', 'Personal Income' => ($app['personal_income'] ?? null) === null ? '' : ((int)$app['personal_income'] === 1 ? 'Yes' : 'No'), 'Amount ' => isset($app['personal_income_amount']) ? 'P' . number_format((float)$app['personal_income_amount'], 2) : '']); ?>
    <div class="section-title">HEALTH CONDITION</div>
    <?php oscaFieldRow(['Health Condition' => $app['health_condition'] ?? '', 'With Maintenance' => ($app['with_maintenance'] ?? null) ? 'Yes — ' . ($app['maintenance_spec'] ?? '') : 'No']); ?>
    <div class="section-title">VISIT SUMMARY</div>
    <div class="val" style="border:1px solid #000; min-height:80px; padding:6px;"><?php echo nl2br(oscaVal($app['visit_summary'] ?? '')); ?></div>
    <div class="sig-line">Confirmation Made With — Signature over Printed Name</div>
    <?php oscaFieldRow(['Confirmation Name' => $app['claimant_name'] ?? '', 'Contact No.' => $app['claimant_contact'] ?? '']); ?>
    <div class="sig-line">Visited by: <?php echo oscaVal($app['home_visit_personnel_name'] ?? ''); ?> — Signature over Printed Name &nbsp;&nbsp; Interview: <?php echo !empty($app['home_visit_completed_at']) ? oscaVal(date('F j, Y g:i A', strtotime($app['home_visit_completed_at']))) : ''; ?></div>
    <div class="sig-line">Checked by: <?php echo oscaVal($app['home_visit_assessor_name'] ?? ''); ?> — Signature over Printed Name &nbsp;&nbsp; Date and time: <?php echo !empty($app['home_visit_assessed_at']) ? oscaVal(date('F j, Y g:i A', strtotime($app['home_visit_assessed_at']))) : ''; ?></div>
    <div class="section-title">FINAL EVALUATION</div>
    <div class="checkbox-row"><?php echo oscaCheck(($app['home_visit_eligibility'] ?? '') === 'Eligible'); ?> ELIGIBLE &nbsp;&nbsp; <?php echo oscaCheck(($app['home_visit_eligibility'] ?? '') === 'Not Eligible'); ?> NOT ELIGIBLE</div>
    <div class="val" style="border:1px solid #000;min-height:55px;padding:6px;"><strong>Reason for Decision:</strong> <?php echo nl2br(oscaVal($app['home_visit_eligibility_reason'] ?? '')); ?></div>
    <div class="sig-line">Evaluated by: <?php echo oscaVal($app['home_visit_assessor_name'] ?? ''); ?> — Signature over Printed Name &nbsp;&nbsp; Date and time: <?php echo !empty($app['home_visit_assessed_at']) ? oscaVal(date('F j, Y g:i A', strtotime($app['home_visit_assessed_at']))) : ''; ?></div>
</div>
</body>
</html>
