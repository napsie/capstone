<?php
require_once '../includes/db_connect.php';

header('Content-Type: application/json');

try {
    $sql = "SELECT id_number as id, full_name, application_type, birth_date, contact_number, date_submitted, status FROM applications ORDER BY date_submitted DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($applications);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

$stmt = null;
$conn = null;
?>