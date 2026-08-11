<?php
require __DIR__ . '/_helpers.php';
$age = oscaAge($app['birth_date'] ?? null);
$milestones = ['80' => '₱10,000', '85' => '₱15,000', '90' => '₱25,000', '95' => '₱25,000', '100' => '₱25,000'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Octogenarian — <?php echo oscaVal($app['full_name']); ?></title>
    <style><?php echo oscaPrintStyles('F5'); ?></style>
</head>
<body>
<div class="print-bar"><button onclick="window.print()">Print / Save as PDF</button><button onclick="window.close()">Close</button></div>
<div class="form-page">
    <?php oscaPrintHeader('', 'OCTOGENARIAN, NONAGENARIAN, & CENTENARIAN APPLICATION FORM'); ?>
    <div class="checkbox-row"><strong>Milestone Age:</strong>
        <?php foreach (array_keys($milestones) as $m): ?>
            <?php echo oscaCheck(($app['milestone_age'] ?? '') == $m); ?> <?php echo $m; ?>
        <?php endforeach; ?>
    </div>
    <?php oscaFieldRow(['Last Name' => $app['lastName'] ?? '', 'Ext' => $app['suffix'] ?? '', 'First Name' => $app['firstName'] ?? '', 'Middle Name' => $app['middleName'] ?? '']); ?>
    <?php oscaFieldRow(['Date of Birth' => oscaFmtDate($app['birth_date'] ?? null), 'Place of Birth' => $app['place_of_birth'] ?? '', 'Age' => $age]); ?>
    <?php oscaFieldRow(['Gender' => $app['gender'] ?? '', 'Civil Status' => $app['civil_status'] ?? '', 'Contact No.' => $app['contact_number'] ?? '']); ?>
    <?php oscaFieldRow(['Complete Address' => $app['complete_address'] ?? '', 'Barangay' => $app['barangay'] ?? '', 'Senior ID No.' => oscaSeniorId($app)]); ?>
    <div class="section-title">STAGGERED SCHEME FINANCIAL ASSISTANCE</div>
    <table class="field-table">
        <?php foreach ($milestones as $m => $amt): ?>
        <tr><th><?php echo $m; ?> years</th><td class="val"><?php echo oscaCheck(($app['milestone_age'] ?? '') == $m); ?> <?php echo $amt; ?></td></tr>
        <?php endforeach; ?>
    </table>
    <?php oscaFieldRow(['Claimant Name' => $app['claimant_name'] ?? '', 'Relationship' => $app['claimant_relationship'] ?? '', 'Contact' => $app['claimant_contact'] ?? '']); ?>
    <div class="cert-box">I authorize the City Government of Pasig to process and validate my data with GSIS, SSS, DSWD and other agencies.</div>
    <div class="sig-line">Signature/Thumbmark over printed name of the Senior Citizen</div>
    <div class="stub"><strong>OCTO LOCAL</strong> — <?php echo oscaVal($app['full_name']); ?> | Claimant: <?php echo oscaVal($app['claimant_name'] ?? ''); ?> | Barangay: <?php echo oscaVal($app['barangay']); ?></div>
</div>
</body>
</html>
