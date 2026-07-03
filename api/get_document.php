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

if (!isset($_GET['id']) || !isset($_GET['doc_type'])) {
    http_response_code(400);
    echo 'Invalid request.';
    exit();
}

$appId   = trim($_GET['id']);
$docType = trim($_GET['doc_type']);

// Whitelist document types to prevent SQL injection via column name interpolation
$allowed_doc_types = [
    'proof_of_address',
    'id_image',
];

if (!in_array($docType, $allowed_doc_types)) {
    http_response_code(400);
    echo 'Invalid document type requested.';
    exit();
}

try {
    // Enforce barangay isolation for staff
    if ($userRole === 'barangay_staff' && $userBarangay) {
        $sql  = "SELECT {$docType}, {$docType}_type FROM applications WHERE id_number = ? AND barangay = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$appId, $userBarangay]);
    } else {
        $sql  = "SELECT {$docType}, {$docType}_type FROM applications WHERE id_number = ?";
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

    $contentType = $result[$docType . '_type'] ?? '';

    // MIME validation — only allow safe types before serving
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    if (!in_array($contentType, $allowedMimeTypes)) {
        // Fallback: sniff actual MIME from binary content
        $finfo       = new finfo(FILEINFO_MIME_TYPE);
        $sniffedMime = $finfo->buffer($result[$docType]);
        if (in_array($sniffedMime, $allowedMimeTypes)) {
            $contentType = $sniffedMime;
        } else {
            http_response_code(415);
            header('Content-Type: text/plain');
            echo 'Unsupported document format.';
            exit();
        }
    }

    header('Content-Type: ' . $contentType);
    header('Cache-Control: private, max-age=3600');
    echo $result[$docType];

} catch (PDOException $e) {
    http_response_code(500);
    error_log('get_document.php error: ' . $e->getMessage());
    header('Content-Type: text/plain');
    echo 'A server error occurred.';
}
?>