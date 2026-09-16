<?php
require __DIR__ . '/_helpers.php';
$age = oscaAge($app['birth_date'] ?? null);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Land Bank Cash Card — <?php echo oscaVal($app['full_name']); ?></title>
    <style><?php echo oscaPrintStyles('F2'); ?></style>
</head>
<body>
<div class="print-bar"><button onclick="window.print()">Print / Save as PDF</button><button onclick="window.close()">Close</button></div>
<div class="form-page">
    <?php oscaPrintHeader('', 'Land Bank Cash Card Enrollment Form'); ?>
    <div class="section-title">Agency / Purchaser Information</div>
    <?php oscaFieldRow(['Name of Agency/Purchaser' => 'City Government of Pasig — OSCA', 'Date' => oscaFmtDate($app['date_submitted'] ?? date('Y-m-d'))]); ?>
    <?php oscaFieldRow(['Address' => 'Pasig City Hall, Caruncho Avenue, Pasig City', 'Email Address' => '']); ?>
    <?php oscaFieldRow(['Last Name' => $app['lastName'] ?? '', 'First Name' => $app['firstName'] ?? '', 'Middle Name' => $app['middleName'] ?? '']); ?>
    <?php oscaFieldRow(['Name on Card (23 chars)' => $app['name_on_card'] ?? '', 'Date of Birth' => oscaFmtDate($app['birth_date'] ?? null), 'Age' => $age]); ?>
    <?php oscaFieldRow(['Home Address' => $app['complete_address'] ?? '', 'ZIP Code' => $app['zip_code'] ?? '', 'Contact No.' => $app['contact_number'] ?? '']); ?>
    <?php oscaFieldRow(["Mother's Maiden Name" => $app['mothers_maiden_name'] ?? '', 'Birth Place' => $app['place_of_birth'] ?? '']); ?>
    <?php oscaFieldRow(['ID Presented' => $app['id_type_presented'] ?? 'OSCA', 'ID Number' => oscaSeniorId($app)]); ?>
    <?php oscaFieldRow(['Permanent / Present Address' => $app['complete_address'] ?? '', 'Nationality' => $app['nationality'] ?? '', 'Source of Funds' => $app['source_of_funds'] ?? '']); ?>
    <?php oscaFieldRow(['Existing LBP Account' => '☐ Yes   ☐ No', 'Account No.' => '', 'Control #' => $app['control_no'] ?? $app['id_number'] ?? '']); ?>
    <div class="cert-box">Cardholder certifies that the information provided is true and correct.</div>
    <div class="sig-line">Cardholder Signature over Printed Name &nbsp;&nbsp;&nbsp; Date Signed</div>
    <div class="section-title">For Bank Use</div>
    <?php oscaFieldRow(['Received / Checked By' => '', 'Cash Card / Account Number' => '', 'Date' => '']); ?>
    <div class="stub"><strong>LANDBANK CASH CARD APPLICATION</strong><br>Name of Senior Citizen: <?php echo oscaVal($app['full_name']); ?> | Barangay: <?php echo oscaVal($app['barangay']); ?> | Control #: <?php echo oscaVal($app['control_no'] ?? $app['id_number'] ?? ''); ?> | Date of File: <?php echo oscaFmtDate($app['date_submitted'] ?? null); ?></div>
</div>
</body>
</html>
