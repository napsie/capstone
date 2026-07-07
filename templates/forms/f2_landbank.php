<?php
require __DIR__ . '/_helpers.php';
$age = oscaAge($app['birth_date'] ?? null);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>F2 — Landbank Cash Card — <?php echo oscaVal($app['full_name']); ?></title>
    <style><?php echo oscaPrintStyles('F2'); ?></style>
</head>
<body>
<div class="print-bar"><button onclick="window.print()">Print / Save as PDF</button><button onclick="window.close()">Close</button></div>
<div class="form-page">
    <?php oscaPrintHeader('F2', 'Land Bank Cash Card Enrollment Form'); ?>
    <?php oscaFieldRow(['Last Name' => $app['lastName'] ?? '', 'First Name' => $app['firstName'] ?? '', 'Middle Name' => $app['middleName'] ?? '']); ?>
    <?php oscaFieldRow(['Name on Card (23 chars)' => $app['name_on_card'] ?? '', 'Date of Birth' => oscaFmtDate($app['birth_date'] ?? null), 'Age' => $age]); ?>
    <?php oscaFieldRow(['Home Address' => $app['complete_address'] ?? '', 'ZIP Code' => $app['zip_code'] ?? '', 'Contact No.' => $app['contact_number'] ?? '']); ?>
    <?php oscaFieldRow(["Mother's Maiden Name" => $app['mothers_maiden_name'] ?? '', 'Birth Place' => $app['place_of_birth'] ?? '', 'Nationality' => $app['nationality'] ?? 'Filipino']); ?>
    <?php oscaFieldRow(['Type of ID Presented' => $app['id_type_presented'] ?? 'OSCA', 'ID Number' => $app['senior_id_no'] ?? '', 'TIN' => $app['tin'] ?? '']); ?>
    <?php oscaFieldRow(['Source of Funds' => $app['source_of_funds'] ?? '', 'Barangay' => $app['barangay'] ?? '', 'Control #' => $app['control_no'] ?? $app['id_number'] ?? '']); ?>
    <div class="cert-box">Cardholder certifies that the information provided is true and correct.</div>
    <div class="sig-line">Cardholder Signature over Printed Name &nbsp;&nbsp;&nbsp; Date Signed</div>
    <div class="stub"><strong>LANDBANK CASH CARD APPLICATION</strong> — <?php echo oscaVal($app['full_name']); ?> | Barangay: <?php echo oscaVal($app['barangay']); ?></div>
</div>
</body>
</html>
