<?php
session_start();
require_once '../includes/db_connect.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $appId = filter_input(INPUT_POST, 'applicationId', FILTER_SANITIZE_STRING);

    if (!$appId) {
        echo json_encode(['success' => false, 'message' => 'Application ID (id_number) is missing.']);
        exit();
    }

    // Sanitize and validate input
    $lastName = filter_input(INPUT_POST, 'lastName', FILTER_SANITIZE_STRING);
    $firstName = filter_input(INPUT_POST, 'firstName', FILTER_SANITIZE_STRING);
    $middleName = filter_input(INPUT_POST, 'middleName', FILTER_SANITIZE_STRING);
    $suffix = filter_input(INPUT_POST, 'suffix', FILTER_SANITIZE_STRING);
    $fullName = trim($firstName . ' ' . $middleName . ' ' . $lastName . ' ' . $suffix);
    $applicationType = filter_input(INPUT_POST, 'applicationType', FILTER_SANITIZE_STRING);
    $birthDate = filter_input(INPUT_POST, 'birthDate', FILTER_SANITIZE_STRING);
    $contactNumber = filter_input(INPUT_POST, 'contactNumber', FILTER_SANITIZE_STRING);
    $completeAddress = filter_input(INPUT_POST, 'completeAddress', FILTER_SANITIZE_STRING);
    $emergencyContact = filter_input(INPUT_POST, 'emergencyContact', FILTER_SANITIZE_STRING) ?? '';
    $emergencyContactName = filter_input(INPUT_POST, 'emergencyContactName', FILTER_SANITIZE_STRING) ?? '';
    
    // PWD-specific fields
    $disabilityType = isset($_POST['disabilityType']) ? implode(', ', (array)$_POST['disabilityType']) : null; 

    // Pension fields
    $sssNumber = filter_input(INPUT_POST, 'sssNumber', FILTER_SANITIZE_STRING) ?: null;
    $pensionAmount = filter_input(INPUT_POST, 'pensionAmount', FILTER_VALIDATE_FLOAT) ?: null;

    // Burial fields
    $dateOfDeath = filter_input(INPUT_POST, 'dateOfDeath', FILTER_SANITIZE_STRING) ?: null;
    $relationshipToDeceased = filter_input(INPUT_POST, 'relationshipToDeceased', FILTER_SANITIZE_STRING) ?: null;

    // Fetch existing application data to retain current document paths if no new file is uploaded
    $stmt = $conn->prepare("SELECT proof_of_address, proof_of_address_type, id_image, id_image_type FROM applications WHERE id_number = ?");
    $stmt->execute([$appId]);
    $existingApplication = $stmt->fetch(PDO::FETCH_ASSOC);

    // Initialize with existing values for documents
    $proofOfAddress = $existingApplication['proof_of_address'] ?? null;
    $proofOfAddressType = $existingApplication['proof_of_address_type'] ?? null;
    if (isset($_FILES['proofOfAddress']) && $_FILES['proofOfAddress']['error'] == UPLOAD_ERR_OK && $_FILES['proofOfAddress']['size'] > 0) {
        $proofOfAddress = file_get_contents($_FILES['proofOfAddress']['tmp_name']);
        $proofOfAddressType = $_FILES['proofOfAddress']['type'];
    }

    $idImage = $existingApplication['id_image'] ?? null;
    $idImageType = $existingApplication['id_image_type'] ?? null;
    if (isset($_FILES['idImage']) && $_FILES['idImage']['error'] == UPLOAD_ERR_OK && $_FILES['idImage']['size'] > 0) {
        $idImage = file_get_contents($_FILES['idImage']['tmp_name']);
        $idImageType = $_FILES['idImage']['type'];
    }

    // Prepare and bind for update
    $sql = "UPDATE applications SET 
                full_name = ?, 
                application_type = ?, 
                birth_date = ?, 
                contact_number = ?, 
                complete_address = ?, 
                emergency_contact = ?, 
                emergency_contact_name = ?, 
                proof_of_address = ?, 
                proof_of_address_type = ?, 
                id_image = ?, 
                id_image_type = ?, 
                lastName = ?, 
                firstName = ?, 
                middleName = ?, 
                suffix = ?,
                disability_type = ?,
                sss_number = ?,
                pension_amount = ?,
                date_of_death = ?,
                relationship_to_deceased = ?
            WHERE id_number = ?";
            
    $stmt = $conn->prepare($sql);
    
    $params = [
        $fullName, 
        $applicationType, 
        $birthDate, 
        $contactNumber, 
        $completeAddress, 
        $emergencyContact, 
        $emergencyContactName, 
        $proofOfAddress, 
        $proofOfAddressType, 
        $idImage, 
        $idImageType, 
        $lastName, 
        $firstName, 
        $middleName, 
        $suffix,
        $disabilityType,
        $sssNumber,
        $pensionAmount,
        $dateOfDeath,
        $relationshipToDeceased,
        $appId
    ];

    if ($stmt->execute($params)) {
        echo json_encode(['success' => true, 'message' => 'Application updated successfully!']);
    } else {
        error_log("PDO Error updating application: " . implode(" - ", $stmt->errorInfo()));
        echo json_encode(['success' => false, 'message' => 'Error updating application. Please check server logs.']);
    }

    $stmt = null;
    $conn = null;
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
