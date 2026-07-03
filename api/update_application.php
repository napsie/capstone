<?php
session_start();
require_once '../includes/db_connect.php';

header('Content-Type: application/json');

// Auth check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['barangay_staff', 'department_admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit();
}

/**
 * Safe string sanitizer — replaces deprecated FILTER_SANITIZE_STRING.
 * Returns null if $value is null/empty and $required is false,
 * or empty string (caller decides what to do with it).
 */
function sanitize_str(?string $value): ?string {
    if ($value === null) return null;
    return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

// Detect silent POST failure caused by upload size exceeding post_max_size
// When this happens $_POST is empty but the request method is POST
if (empty($_POST) && empty($_FILES) && $_SERVER['CONTENT_LENGTH'] > 0) {
    $maxSize = ini_get('post_max_size');
    echo json_encode([
        'success' => false,
        'message' => "Upload failed: request size exceeds the server limit (post_max_size = {$maxSize}). Please reduce your file sizes or contact your administrator."
    ]);
    exit();
}

$appId = sanitize_str($_POST['applicationId'] ?? null);

if (empty($appId)) {
    echo json_encode(['success' => false, 'message' => 'Application ID is missing.']);
    exit();
}

// Sanitize all text inputs
$lastName             = sanitize_str($_POST['lastName']             ?? null);
$firstName            = sanitize_str($_POST['firstName']            ?? null);
$middleName           = sanitize_str($_POST['middleName']           ?? null);
$suffix               = sanitize_str($_POST['suffix']               ?? null);
$applicationType      = sanitize_str($_POST['applicationType']      ?? null);
$birthDate            = sanitize_str($_POST['birthDate']            ?? null);
$contactNumber        = sanitize_str($_POST['contactNumber']        ?? null);
$completeAddress      = sanitize_str($_POST['completeAddress']      ?? null);
$emergencyContact     = sanitize_str($_POST['emergencyContact']     ?? '') ?? '';
$emergencyContactName = sanitize_str($_POST['emergencyContactName'] ?? '') ?? '';

// Validate required fields
$required = [
    'Last Name'         => $lastName,
    'First Name'        => $firstName,
    'Application Type'  => $applicationType,
    'Birth Date'        => $birthDate,
    'Contact Number'    => $contactNumber,
    'Complete Address'  => $completeAddress,
];
foreach ($required as $fieldName => $fieldValue) {
    if (empty($fieldValue)) {
        echo json_encode(['success' => false, 'message' => "Required field '{$fieldName}' is missing or empty."]);
        exit();
    }
}

$fullName = trim("{$firstName} {$middleName} {$lastName} {$suffix}");

// PWD-specific fields
$disabilityType = isset($_POST['disabilityType']) ? implode(', ', (array)$_POST['disabilityType']) : null;

// Pension fields
$sssNumber    = !empty($_POST['sssNumber'])    ? sanitize_str($_POST['sssNumber'])    : null;
$pensionAmount = !empty($_POST['pensionAmount']) ? floatval($_POST['pensionAmount'])   : null;

// Burial fields
$dateOfDeath            = !empty($_POST['dateOfDeath'])            ? sanitize_str($_POST['dateOfDeath'])            : null;
$relationshipToDeceased = !empty($_POST['relationshipToDeceased']) ? sanitize_str($_POST['relationshipToDeceased']) : null;

// Fetch existing document data to retain it when no new file is uploaded
$stmtExisting = $conn->prepare("SELECT proof_of_address, proof_of_address_type, id_image, id_image_type FROM applications WHERE id_number = ?");
$stmtExisting->execute([$appId]);
$existingApplication = $stmtExisting->fetch(PDO::FETCH_ASSOC);

if (!$existingApplication) {
    echo json_encode(['success' => false, 'message' => 'Application not found.']);
    exit();
}

// Allowed MIME types for uploaded documents
$allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];

// Handle Proof of Address upload
$proofOfAddress     = $existingApplication['proof_of_address']      ?? null;
$proofOfAddressType = $existingApplication['proof_of_address_type'] ?? null;
if (isset($_FILES['proofOfAddress']) && $_FILES['proofOfAddress']['error'] === UPLOAD_ERR_OK && $_FILES['proofOfAddress']['size'] > 0) {
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($_FILES['proofOfAddress']['tmp_name']);
    if (!in_array($mimeType, $allowedMimes)) {
        echo json_encode(['success' => false, 'message' => 'Proof of Address: only JPEG, PNG, GIF and PDF files are accepted.']);
        exit();
    }
    $proofOfAddress     = file_get_contents($_FILES['proofOfAddress']['tmp_name']);
    $proofOfAddressType = $mimeType;
}

// Handle ID Image upload
$idImage     = $existingApplication['id_image']      ?? null;
$idImageType = $existingApplication['id_image_type'] ?? null;
if (isset($_FILES['idImage']) && $_FILES['idImage']['error'] === UPLOAD_ERR_OK && $_FILES['idImage']['size'] > 0) {
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($_FILES['idImage']['tmp_name']);
    if (!in_array($mimeType, $allowedMimes)) {
        echo json_encode(['success' => false, 'message' => 'ID Image: only JPEG, PNG, GIF and PDF files are accepted.']);
        exit();
    }
    $idImage     = file_get_contents($_FILES['idImage']['tmp_name']);
    $idImageType = $mimeType;
}

// Prepare and execute UPDATE
$sql = "UPDATE applications SET
            full_name                = ?,
            application_type         = ?,
            birth_date               = ?,
            contact_number           = ?,
            complete_address         = ?,
            emergency_contact        = ?,
            emergency_contact_name   = ?,
            proof_of_address         = ?,
            proof_of_address_type    = ?,
            id_image                 = ?,
            id_image_type            = ?,
            lastName                 = ?,
            firstName                = ?,
            middleName               = ?,
            suffix                   = ?,
            disability_type          = ?,
            sss_number               = ?,
            pension_amount           = ?,
            date_of_death            = ?,
            relationship_to_deceased = ?
        WHERE id_number = ?";

$stmt   = $conn->prepare($sql);
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
    $appId,
];

try {
    $stmt->execute($params);
    echo json_encode(['success' => true, 'message' => 'Application updated successfully!']);
} catch (PDOException $e) {
    error_log('update_application.php PDO error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error while updating. Please check server logs.']);
}

$stmt = null;
$conn = null;
?>
