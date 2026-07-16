<?php
session_start();
require_once '../includes/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['barangay_staff', 'department_admin', 'super_admin'], true)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$appId = trim((string)($_POST['applicationId'] ?? ''));
if ($appId === '') {
    echo json_encode(['success' => false, 'message' => 'Application ID is missing.']);
    exit;
}

try {
    if ($_SESSION['role'] === 'barangay_staff') {
        $check = $conn->prepare('SELECT id_number FROM applications WHERE id_number = ? AND barangay = ?');
        $check->execute([$appId, $_SESSION['barangay'] ?? '']);
    } else {
        $check = $conn->prepare('SELECT id_number FROM applications WHERE id_number = ?');
        $check->execute([$appId]);
    }
    if (!$check->fetchColumn()) {
        echo json_encode(['success' => false, 'message' => 'Application not found or access denied.']);
        exit;
    }

    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    $updates = [];
    $values = [];
    $binaryValues = [];
    foreach (['proofOfAddress' => ['proof_of_address', 'proof_of_address_type'], 'idImage' => ['id_image', 'id_image_type']] as $input => [$column, $typeColumn]) {
        if (!isset($_FILES[$input]) || $_FILES[$input]['error'] === UPLOAD_ERR_NO_FILE) continue;
        if ($_FILES[$input]['error'] !== UPLOAD_ERR_OK || $_FILES[$input]['size'] <= 0) {
            echo json_encode(['success' => false, 'message' => ucfirst($input) . ' upload failed.']);
            exit;
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES[$input]['tmp_name']);
        if (!in_array($mime, $allowed, true)) {
            echo json_encode(['success' => false, 'message' => ucfirst($input) . ' must be a JPEG, PNG, GIF, or PDF.']);
            exit;
        }
        $updates[] = "$column = ?";
        $binaryValues[] = file_get_contents($_FILES[$input]['tmp_name']);
        $values[] = null;
        $updates[] = "$typeColumn = ?";
        $values[] = $mime;
    }

    if (!$updates) {
        echo json_encode(['success' => false, 'message' => 'Choose at least one replacement document.']);
        exit;
    }
    $values[] = $appId;
    $stmt = $conn->prepare('UPDATE applications SET ' . implode(', ', $updates) . ' WHERE id_number = ?');
    $valueIndex = 0;
    foreach ($values as $index => $value) {
        if ($index === count($values) - 1) {
            $stmt->bindValue($index + 1, $value, PDO::PARAM_STR);
            continue;
        }
        // Document payloads must be bound as LOBs; binding them as ordinary
        // strings can truncate or mis-handle binary data on some MySQL setups.
        if (($index % 2) === 0) {
            $stmt->bindValue($index + 1, $binaryValues[$valueIndex++] ?? '', PDO::PARAM_LOB);
        } else {
            $stmt->bindValue($index + 1, $value, PDO::PARAM_STR);
        }
    }
    $stmt->execute();
    if ($stmt->rowCount() < 1) {
        // MySQL can report zero when the same bytes are submitted, but a
        // missing row must never be presented as a successful save.
        $verify = $conn->prepare('SELECT id_number FROM applications WHERE id_number = ?');
        $verify->execute([$appId]);
        if (!$verify->fetchColumn()) {
            echo json_encode(['success' => false, 'message' => 'Application could not be updated.']);
            exit;
        }
    }
    echo json_encode(['success' => true, 'message' => 'Submitted document(s) updated successfully.']);
} catch (Throwable $e) {
    error_log('update_application_documents.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'The documents could not be saved.']);
}
