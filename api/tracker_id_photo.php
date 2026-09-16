<?php
// The public tracking token alone must never grant access to an applicant photo.
session_start();
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');

$id = trim((string)($_GET['id'] ?? ''));
$grant = $_SESSION['tracker_photo_access'] ?? null;
if ($id === '' || !is_array($grant)
    || !hash_equals((string)($grant['id'] ?? ''), $id)
    || (int)($grant['expires'] ?? 0) <= time()) {
    http_response_code(403);
    exit;
}

require_once '../includes/db_connect.php';
require_once '../includes/private_storage.php';

try {
    $stmt = $conn->prepare("SELECT id_image FROM applications
        WHERE id_number = ? AND application_type = 'senior'
          AND workflow_state IN ('Verified', 'Approved', 'Released')
          AND senior_id_no IS NOT NULL AND senior_id_no <> ''
          AND senior_id_no NOT REGEXP '^OSCA-[0-9]{4}-[0-9A-Fa-f]{6}$'
          AND COALESCE(is_archived, 0) = 0 LIMIT 1");
    $stmt->execute([$id]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$application) {
        http_response_code(404);
        exit;
    }

    // The current replacement, if any, supersedes the original uploaded photo.
    $replacementStmt = $conn->prepare("SELECT document_data FROM application_documents
        WHERE application_id = ? AND document_key = 'id_image' AND is_current = 1
        ORDER BY id DESC LIMIT 1");
    $replacementStmt->execute([$id]);
    $replacement = $replacementStmt->fetch(PDO::FETCH_ASSOC);
    if ($replacement) {
        $bytes = $replacement['document_data'];
    } else {
        $stored = $application['id_image'];
        if (!is_string($stored) || $stored === '') {
            http_response_code(404);
            exit;
        }
        $path = strlen($stored) < 255 ? privateExistingUploadPath($stored) : null;
        $bytes = $path !== null ? file_get_contents($path) : $stored;
    }

    if (!is_string($bytes) || $bytes === '') {
        http_response_code(404);
        exit;
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
        http_response_code(415);
        exit;
    }
    header('Content-Type: ' . $mime);
    echo $bytes;
} catch (Throwable $e) {
    error_log('tracker_id_photo.php: ' . $e->getMessage());
    http_response_code(500);
}
