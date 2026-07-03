<?php
require_once '../includes/db_connect.php';

$appId = $_POST['id'];

$sql = "UPDATE applications SET status = 'approved' WHERE id_number = ?";
$stmt = $conn->prepare($sql);

if ($stmt->execute([$appId])) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $stmt->errorInfo()[2]]);
}

$stmt = null;
$conn = null;
?>