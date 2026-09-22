<?php
require_once '../includes/db_connect.php';
require_once '../includes/api_response.php';

header('Content-Type: application/json');

if (isset($_GET['field'])) {
    $field = (string)$_GET['field'];
    $value = trim((string)($_GET['value'] ?? ''));
    if ($value === '' || mb_strlen($value) > 254) apiRespond(422, false, 'Provide a valid value.');

    if ($field === 'username') {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = :value");
        $stmt->execute(['value' => $value]);
        $exists = $stmt->fetchColumn() > 0;
        apiRespond(200, true, '', ['exists' => $exists]);

    } else if ($field === 'email') {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = :value");
        $stmt->execute(['value' => $value]);
        $exists = $stmt->fetchColumn() > 0;
        apiRespond(200, true, '', ['exists' => $exists]);

    } else if ($field === 'phone') {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE phone = :value");
        $stmt->execute(['value' => $value]);
        $exists = $stmt->fetchColumn() > 0;
        apiRespond(200, true, '', ['exists' => $exists]);

    } else if ($field === 'barangay') {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE role = 'barangay_staff' AND barangay = :value");
        $stmt->execute(['value' => $value]);
        $count = $stmt->fetchColumn();
        apiRespond(200, true, '', ['count' => (int)$count]);

    } else {
        apiRespond(400, false, 'Invalid field specified.');
    }
} else {
    apiRespond(400, false, 'No field specified.');
}
?>
