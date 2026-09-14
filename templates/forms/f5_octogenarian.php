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
    <div class="section-title">QUALIFICATIONS</div>
    <div class="checkbox-row">1. Must have a Pasig City Senior Citizen's ID.<br>2. Must have at least two years actual residency in Pasig City.<br>3. Must have reached age 80 years old and above.</div>
    <div class="section-title">PRIMARY REQUIREMENTS</div>
    <div class="checkbox-row" style="font-size:9px;line-height:1.55;">
        ☐ PSA-issued or authenticated Certificate of Live Birth<br>
        ☐ Senior Citizen OSCA ID, front and back<br>
        ☐ Latest A4-size whole-body picture<br>
        <strong>If primary birth documents are unavailable, submit any two:</strong> PSA late-registration certificate; Philippine government ID showing citizenship and birth year; eldest child's birth certificate; valid Philippine passport; baptismal/church record; NCIP certification for Indigenous Peoples; or NCMF certification for Muslim Filipinos.
    </div>
    <?php oscaFieldRow(['Claimant Name' => $app['claimant_name'] ?? '', 'Relationship' => $app['claimant_relationship'] ?? '', 'Contact' => $app['claimant_contact'] ?? '']); ?>
    <div class="cert-box">I hereby certify under law on perjury that the information provided in this form is complete, true, correct, and of my knowledge. I further authorize the City Government of Pasig to process my data, validate, and confirm the answers herein with third parties such as the GSIS, SSS, DSWD and other Government/Private Agencies.</div>
    <div class="sig-line">Signature/Thumbmark over printed name of the Senior Citizen</div>
    <?php oscaFieldRow(['Received by' => '', 'Approved by' => '']); ?>
    <div class="stub"><strong>OCTO LOCAL — PRESENT UPON CLAIMING</strong><br>Name of Senior Citizen: <?php echo oscaVal($app['full_name']); ?> | Name of Claimant: <?php echo oscaVal($app['claimant_name'] ?? ''); ?> | Relationship: <?php echo oscaVal($app['claimant_relationship'] ?? ''); ?> | Barangay: <?php echo oscaVal($app['barangay']); ?> | Date &amp; Time: <?php echo oscaFmtDate($app['date_submitted'] ?? null); ?></div>
</div>
</body>
</html>
