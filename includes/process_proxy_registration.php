<?php
require_once __DIR__ . '/crypto.php';

/**
 * Process proxy pre-registration POST. Returns result array for the view partial.
 */
function processProxyRegistration(): array
{
    $result = [
        'success' => false,
        'qrCodeUrl' => '',
        'transactionId' => '',
    ];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['proxy_submit'])) {
        return $result;
    }

    $lastName = trim($_POST['lastName'] ?? '');
    $firstName = trim($_POST['firstName'] ?? '');
    $middleName = trim($_POST['middleName'] ?? '');
    $suffix = trim($_POST['suffix'] ?? '');
    $birthDate = trim($_POST['birthDate'] ?? '');
    $contactNumber = trim($_POST['contactNumber'] ?? '');
    $completeAddress = trim($_POST['completeAddress'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    $applicationType = trim($_POST['applicationType'] ?? 'senior');
    $sssNumber = trim($_POST['sssNumber'] ?? '');
    $dateOfDeath = trim($_POST['dateOfDeath'] ?? '');
    $relationshipToDeceased = trim($_POST['relationshipToDeceased'] ?? '');
    $proxyName = trim($_POST['proxyName'] ?? '');
    $proxyRelationship = trim($_POST['proxyRelationship'] ?? '');

    $transactionId = 'PRX-' . strtoupper(bin2hex(random_bytes(4)));

    $payload = [
        'transactionId' => $transactionId,
        'lastName' => $lastName,
        'firstName' => $firstName,
        'middleName' => $middleName,
        'suffix' => $suffix,
        'birthDate' => $birthDate,
        'contactNumber' => $contactNumber,
        'completeAddress' => $completeAddress,
        'barangay' => $barangay,
        'applicationType' => $applicationType,
        'sssNumber' => $sssNumber,
        'dateOfDeath' => $dateOfDeath,
        'relationshipToDeceased' => $relationshipToDeceased,
        'proxyName' => $proxyName,
        'proxyRelationship' => $proxyRelationship,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    $encryptedToken = ProxyCrypto::encrypt($payload);

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443)
        ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    if (basename($scriptDir) !== 'pages') {
        $scriptDir = rtrim($scriptDir, '/') . '/pages';
    }
    $scanUrl = $protocol . $host . $scriptDir . '/scan_proxy_qr_redirect.php?token=' . urlencode($encryptedToken);

    $result['success'] = true;
    $result['transactionId'] = $transactionId;
    $result['qrCodeUrl'] = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($scanUrl);

    return $result;
}
