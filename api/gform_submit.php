<?php
require_once '../includes/db_connect.php';

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if ($data) {
    $sql = "INSERT INTO applications (id_number, full_name, application_type, birth_date, contact_number, complete_address, barangay, status, date_submitted) VALUES (:id_number, :full_name, :application_type, :birth_date, :contact_number, :complete_address, :barangay, 'pending', NOW())";
    $stmt = $conn->prepare($sql);

    $stmt->bindParam(':id_number', $data['ID Number']);
    $stmt->bindParam(':full_name', $data['Full Name']);
    $stmt->bindParam(':application_type', $data['Application Type']);
    $stmt->bindParam(':birth_date', $data['Birth Date']);
    $stmt->bindParam(':contact_number', $data['Contact Number']);
    $stmt->bindParam(':complete_address', $data['Complete Address']);
    $stmt->bindParam(':barangay', $data['Barangay']);

    if ($stmt->execute()) {
        http_response_code(200);
        echo json_encode(array("success" => true));
    } else {
        http_response_code(500);
        echo json_encode(array("success" => false, "error" => "Database error"));
    }
} else {
    http_response_code(400);
    echo json_encode(array("success" => false, "error" => "Invalid data"));
}
?>