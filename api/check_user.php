<?php
require_once '../includes/db_connect.php';

header('Content-Type: application/json');

if (isset($_GET['field'])) {
    $field = $_GET['field'];
    $value = $_GET['value'];

    if ($field === 'username') {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = :value");
        $stmt->execute(['value' => $value]);
        $exists = $stmt->fetchColumn() > 0;
        echo json_encode(['exists' => $exists]);

    } else if ($field === 'email') {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = :value");
        $stmt->execute(['value' => $value]);
        $exists = $stmt->fetchColumn() > 0;
        echo json_encode(['exists' => $exists]);

    } else if ($field === 'barangay') {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE role = 'barangay_staff' AND barangay = :value");
        $stmt->execute(['value' => $value]);
        $count = $stmt->fetchColumn();
        echo json_encode(['count' => $count]);

    } else {
        echo json_encode(['error' => 'Invalid field specified.']);
    }
} else {
    echo json_encode(['error' => 'No field specified.']);
}
?>