<?php
require __DIR__ . '/_helpers.php';
$deceased = oscaFullName($app, 'deceased_');
if ($deceased === '') {
    $deceased = trim(($app['deceased_first_name'] ?? $app['firstName'] ?? '') . ' ' .
        ($app['deceased_middle_name'] ?? $app['middleName'] ?? '') . ' ' .
        ($app['deceased_last_name'] ?? $app['lastName'] ?? ''));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Burial Assistance — <?php echo oscaVal($app['full_name']); ?></title>
    <style><?php echo oscaPrintStyles('F7'); ?></style>
</head>
<body>
<div class="print-bar"><button onclick="window.print()">Print / Save as PDF</button><button onclick="window.close()">Close</button></div>
<div class="form-page">
    <?php oscaPrintHeader('', 'SENIOR CITIZEN BURIAL ASSISTANCE FORM'); ?>
    <?php oscaFieldRow(['Deceased Last Name' => $app['deceased_last_name'] ?? $app['lastName'] ?? '', 'First Name' => $app['deceased_first_name'] ?? $app['firstName'] ?? '', 'Middle Name' => $app['deceased_middle_name'] ?? $app['middleName'] ?? '']); ?>
    <?php oscaFieldRow(['Senior ID No.' => $app['senior_id_no'] ?? '', 'Birth Date of Deceased' => oscaFmtDate($app['deceased_birth_date'] ?? $app['birth_date'] ?? null), 'Date of Death' => oscaFmtDate($app['date_of_death'] ?? null)]); ?>
    <?php oscaFieldRow(['Landbank Cash Card No.' => $app['landbank_card_no'] ?? '', 'Contact No.' => $app['contact_number'] ?? '', 'Applicant Name' => $app['applicant_name'] ?? $app['full_name'] ?? '']); ?>
    <?php oscaFieldRow(['Relationship' => $app['relationship_to_deceased'] ?? '', 'Address' => $app['complete_address'] ?? '', 'Barangay' => $app['barangay'] ?? '']); ?>
    <div class="section-title">REQUIREMENTS CHECKLIST</div>
    <div class="checkbox-row">☐ Death Certificate (original + photocopy) &nbsp; ☐ Valid ID of claimant &nbsp; ☐ Senior ID & Landbank Card of deceased &nbsp; ☐ Proof of relationship</div>
    <div class="cert-box">
        I <?php echo oscaVal($app['applicant_name'] ?? $app['full_name']); ?> hereby certify that I am the legal heir of the deceased Senior Citizen
        and that upon receipt of the burial benefit of <strong>Five Thousand Pesos (Php 5,000.00)</strong>, I declare OSCA and the City Government of Pasig free from liability.
    </div>
    <div class="sig-line">Signature over printed name</div>
    <div class="stub"><strong>BURIAL ASSISTANCE</strong> — Claimant: <?php echo oscaVal($app['applicant_name'] ?? $app['full_name']); ?> | Deceased: <?php echo oscaVal($deceased); ?> | Barangay: <?php echo oscaVal($app['barangay']); ?></div>
</div>
</body>
</html>
