<?php
require __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../../includes/filing_deadline.php';
$deceased = oscaFullName($app, 'deceased_');
if ($deceased === '') {
    $deceased = trim(($app['deceased_first_name'] ?? $app['firstName'] ?? '') . ' ' .
        ($app['deceased_middle_name'] ?? $app['middleName'] ?? '') . ' ' .
        ($app['deceased_last_name'] ?? $app['lastName'] ?? ''));
}
$filingStart = $app['date_of_death'] ?? null;
$filingDays = function_exists('filingWorkingDays') ? filingWorkingDays($filingStart, $app['date_submitted'] ?? null) : null;
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
    <?php oscaFieldRow(['Senior ID No.' => oscaSeniorId($app), 'Birth Date of Deceased' => oscaFmtDate($app['deceased_birth_date'] ?? $app['birth_date'] ?? null), 'Date of Death' => oscaFmtDate($app['date_of_death'] ?? null)]); ?>
    <?php oscaFieldRow(['Landbank Cash Card No.' => $app['landbank_card_no'] ?? '', 'Days Filed' => $filingDays === null ? '' : $filingDays . ' working day(s)', 'Contact No.' => $app['claimant_contact'] ?? $app['contact_number'] ?? '']); ?>
    <?php oscaFieldRow(['Name of Applicant' => $app['applicant_name'] ?? $app['claimant_name'] ?? '', 'Relationship' => $app['relationship_to_deceased'] ?? '']); ?>
    <?php oscaFieldRow(['Full Address' => $app['complete_address'] ?? '', 'Barangay' => $app['barangay'] ?? '']); ?>
    <div class="checkbox-row" style="font-weight:700;text-align:center;">SUBMIT WITHIN 30 WORKING DAYS FROM REGISTRATION OF THE DEATH CERTIFICATE WITH THE LOCAL CIVIL REGISTRY</div>
    <div class="section-title">REQUIREMENTS CHECKLIST</div>
    <div class="checkbox-row" style="font-size:9px;line-height:1.55;">
        ☐ Certified True Copy of Death Certificate with registry no. (original + 1 photocopy)<br>
        ☐ Two valid claimant IDs, front and back, with three signatures (original + 2 photocopies)<br>
        ☐ Senior Citizen ID and Landbank Cash Card of deceased, front and back (original + 2 photocopies)<br>
        ☐ Proof of relationship (original + 2 photocopies):
        <?php echo oscaCheck(($app['id_type_presented'] ?? '') === 'Marriage Contract'); ?> Marriage Contract &nbsp;
        <?php echo oscaCheck(($app['id_type_presented'] ?? '') === 'Birth Certificate'); ?> Birth Certificate &nbsp;
        <?php echo oscaCheck(($app['id_type_presented'] ?? '') === 'Other'); ?> Other<br>
        ☐ Original Copy of Affidavit, if applicable (original + 1 photocopy):
        <?php foreach (['Kinship','Discrepancy','Died single without a child','Cohabitation','Other'] as $type): ?>
            <?php echo oscaCheck(($app['control_no'] ?? '') === $type); ?> <?php echo oscaVal($type); ?>&nbsp;
        <?php endforeach; ?>
    </div>
    <div class="cert-box">
        I <?php echo oscaVal($app['applicant_name'] ?? $app['full_name']); ?> hereby certify that I am the legal heir of the deceased Senior Citizen
        and that upon receipt of the burial benefit of <strong>Five Thousand Pesos (Php 5,000.00)</strong>, I declare OSCA and the City Government of Pasig free from liability.
    </div>
    <div class="section-title">REMARKS / NOTES</div>
    <div style="min-height:65px;border:1px solid #d6e0ec;border-radius:7px;padding:8px;"><?php echo oscaVal($app['visit_summary'] ?? ''); ?></div>
    <div class="sig-line">Signature over printed name</div>
    <div class="stub"><strong>BURIAL ASSISTANCE — PRESENT UPON CLAIMING</strong><br>Claimant: <?php echo oscaVal($app['applicant_name'] ?? $app['claimant_name'] ?? ''); ?> | Deceased: <?php echo oscaVal($deceased); ?> | Date Filed: <?php echo oscaFmtDate($app['date_submitted'] ?? null); ?> | Barangay: <?php echo oscaVal($app['barangay']); ?></div>
</div>
</body>
</html>
