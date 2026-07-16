<?php
session_start();
require_once '../includes/db_connect.php';

header('Content-Type: application/json');

// Auth check — only logged-in users can retrieve documents
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['barangay_staff', 'department_admin', 'super_admin'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized. Please log in.']);
    exit();
}

$userRole     = $_SESSION['role'];
$userBarangay = $_SESSION['barangay'] ?? null;

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo 'Invalid request.';
    exit();
}

$appId   = trim($_GET['id']);
$documentId = isset($_GET['document_id']) ? (int)$_GET['document_id'] : 0;

// Documents submitted through template forms are stored one-per-requirement.
// Serve them first, while applying the same barangay access scope as legacy files.
if ($documentId > 0) {
    try {
        if ($userRole === 'barangay_staff' && $userBarangay) {
            $stmt = $conn->prepare('SELECT d.mime_type, d.document_data FROM application_documents d INNER JOIN applications a ON a.id_number = d.application_id WHERE d.id = ? AND d.application_id = ? AND a.barangay = ?');
            $stmt->execute([$documentId, $appId, $userBarangay]);
        } else {
            $stmt = $conn->prepare('SELECT mime_type, document_data FROM application_documents WHERE id = ? AND application_id = ?');
            $stmt->execute([$documentId, $appId]);
        }
        $document = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$document) {
            http_response_code(404);
            header('Content-Type: text/plain');
            echo 'Document not found or access denied.';
            exit();
        }
        header('Content-Type: ' . $document['mime_type']);
        header('Cache-Control: private, no-store');
        echo $document['document_data'];
        exit();
    } catch (PDOException $e) {
        http_response_code(500);
        error_log('get_document.php template document error: ' . $e->getMessage());
        header('Content-Type: text/plain');
        echo 'A server error occurred.';
        exit();
    }
}

if (!isset($_GET['doc_type'])) {
    http_response_code(400);
    echo 'Invalid request.';
    exit();
}

$docType = trim($_GET['doc_type']);

// Whitelist document types to prevent SQL injection via column name interpolation
$allowed_doc_types = [
    'proof_of_address',
    'id_image',
    'psa_birth_cert',
    'barangay_residency',
    'comelec_cert',
    'proof_of_life',
    'auth_letter',
    'proxy_id',
    'proxy_birth_cert',
    'home_visitation_form',
    'landbank_enrollment_form'
];

if (!in_array($docType, $allowed_doc_types)) {
    http_response_code(400);
    echo 'Invalid document type requested.';
    exit();
}

try {
    // For whitelisted fields, select the column along with id_number and barangay
    if ($userRole === 'barangay_staff' && $userBarangay) {
        $sql  = "SELECT {$docType}, id_number FROM applications WHERE id_number = ? AND barangay = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$appId, $userBarangay]);
    } else {
        $sql  = "SELECT {$docType}, id_number FROM applications WHERE id_number = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$appId]);
    }

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result || !$result[$docType]) {
        http_response_code(404);
        header('Content-Type: text/plain');
        echo 'Document not found or access denied.';
        exit();
    }

    $docValue = $result[$docType];

    // Check if the value points to a file on disk in uploads/
    $uploadDir = __DIR__ . '/../uploads/';
    $filePath = $uploadDir . $docValue;
    if (is_string($docValue) && strlen($docValue) < 255 && file_exists($filePath) && is_file($filePath)) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $contentType = $finfo->file($filePath);
        header('Content-Type: ' . $contentType);
        header('Cache-Control: private, max-age=3600');
        readfile($filePath);
        exit();
    }

    // Otherwise, treat as BLOB database storage (legacy/original)
    // Fetch the mime type from the DB if available, or sniff it
    $mimeCol = $docType . '_type';
    $mimeType = '';
    
    // Fetch mime type column from DB if it exists
    try {
        $mimeSql = "SELECT {$mimeCol} FROM applications WHERE id_number = ?";
        $mimeStmt = $conn->prepare($mimeSql);
        $mimeStmt->execute([$appId]);
        $mimeResult = $mimeStmt->fetch(PDO::FETCH_ASSOC);
        $mimeType = $mimeResult[$mimeCol] ?? '';
    } catch (Exception $e) {
        // Mime column might not exist for some custom fields
    }

    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    if (!in_array($mimeType, $allowedMimeTypes)) {
        $finfo       = new finfo(FILEINFO_MIME_TYPE);
        $sniffedMime = $finfo->buffer($docValue);
        if (in_array($sniffedMime, $allowedMimeTypes)) {
            $mimeType = $sniffedMime;
        } else {
            http_response_code(415);
            header('Content-Type: text/plain');
            echo 'Unsupported document format.';
            exit();
        }
    }

    header('Content-Type: ' . $mimeType);
    header('Cache-Control: private, max-age=3600');
    echo $docValue;

} catch (PDOException $e) {
    http_response_code(500);
    error_log('get_document.php error: ' . $e->getMessage());
    header('Content-Type: text/plain');
    echo 'A server error occurred.';
}
?>
