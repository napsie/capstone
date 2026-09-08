<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/request_security.php';
requireSameOriginMutation();

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
    $conn->beginTransaction();
    if ($_SESSION['role'] === 'barangay_staff') {
        $check = $conn->prepare('SELECT id_number, workflow_state FROM applications WHERE id_number = ? AND barangay = ? AND COALESCE(is_archived, 0) = 0 FOR UPDATE');
        $check->execute([$appId, $_SESSION['barangay'] ?? '']);
    } else {
        $check = $conn->prepare('SELECT id_number, workflow_state FROM applications WHERE id_number = ? AND COALESCE(is_archived, 0) = 0 FOR UPDATE');
        $check->execute([$appId]);
    }
    $application = $check->fetch(PDO::FETCH_ASSOC);
    if (!$application) {
        echo json_encode(['success' => false, 'message' => 'Application not found or access denied.']);
        exit;
    }

    if (!in_array($application['workflow_state'] ?: 'Received', ['Received', 'Submitted', 'Needs Correction'], true)) {
        echo json_encode(['success' => false, 'message' => 'Documents can only be corrected while the application is returned to the barangay.']);
        exit;
    }

    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];

    // Missing requirements and legacy file cards use the same protected document store.
    $documentKey = trim((string)($_POST['documentKey'] ?? ''));
    $documentLabel = trim(strip_tags((string)($_POST['documentLabel'] ?? '')));
    if ($documentKey !== '' || $documentLabel !== '') {
        $legacyLabels = [
            'proof_of_address' => 'Proof of Address', 'id_image' => 'ID / Identification Photo',
            'psa_birth_cert' => 'PSA Birth Certificate', 'barangay_residency' => 'Barangay Residency Certificate',
            'comelec_cert' => 'COMELEC Certificate', 'proof_of_life' => 'Current Senior Photo / Proof of Life',
            'auth_letter' => 'Authorization Letter', 'proxy_id' => 'Representative Government ID',
            'proxy_birth_cert' => 'Representative Birth Certificate', 'home_visitation_form' => 'Home Visitation Form',
            'landbank_enrollment_form' => 'Land Bank Enrollment Form',
        ];
        if ($documentKey !== '' && !isset($legacyLabels[$documentKey])) {
            throw new RuntimeException('Invalid document key.');
        }
        $documentLabel = $legacyLabels[$documentKey] ?? $documentLabel;
        if (mb_strlen($documentLabel) > 150) throw new RuntimeException('Document label is too long.');
        $key = $documentKey ?: 'correction_' . substr(hash('sha256', mb_strtolower($documentLabel)), 0, 24);
        $file = $_FILES['replacementDocument'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK || $file['size'] <= 0 || $file['size'] > 8 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'Choose a document no larger than 8 MB.']);
            exit;
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!in_array($mime, $allowed, true)) {
            echo json_encode(['success' => false, 'message' => 'Only JPEG, PNG, GIF, and PDF documents are accepted.']);
            exit;
        }
        $payload = file_get_contents($file['tmp_name']);
        $save = $conn->prepare('INSERT INTO application_documents (application_id, document_key, document_label, mime_type, document_data)
            VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE document_data = VALUES(document_data), mime_type = VALUES(mime_type), updated_at = CURRENT_TIMESTAMP');
        $save->execute([$appId, $key, $documentLabel, $mime, $payload]);
        $history = $conn->prepare('INSERT INTO application_history (application_id, previous_state, new_state, changed_by, comments) VALUES (?, ?, ?, ?, ?)');
        $history->execute([$appId, $application['workflow_state'], $application['workflow_state'], $_SESSION['username'] ?? 'Staff', 'Saved correction document: ' . $documentLabel]);
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Document saved. Review the remaining correction items before resubmitting.']);
        exit;
    }

    // Complete document store: replace exactly the document card selected in
    // the returned application modal, without affecting other requirements.
    $documentId = filter_input(INPUT_POST, 'documentId', FILTER_VALIDATE_INT);
    if ($documentId) {
        $file = $_FILES['replacementDocument'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK || $file['size'] <= 0) {
            echo json_encode(['success' => false, 'message' => 'Choose the corrected document before saving.']);
            exit;
        }
        if ($file['size'] > 8 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'The replacement document must be 8 MB or smaller.']);
            exit;
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!in_array($mime, $allowed, true)) {
            echo json_encode(['success' => false, 'message' => 'Only JPEG, PNG, GIF, and PDF documents are accepted.']);
            exit;
        }
        $documentCheck = $conn->prepare('SELECT id FROM application_documents WHERE id = ? AND application_id = ?');
        $documentCheck->execute([$documentId, $appId]);
        if (!$documentCheck->fetchColumn()) {
            echo json_encode(['success' => false, 'message' => 'The selected document does not belong to this application.']);
            exit;
        }
        $replacement = file_get_contents($file['tmp_name']);
        $replace = $conn->prepare('UPDATE application_documents SET document_data = ?, mime_type = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND application_id = ?');
        $replace->bindValue(1, $replacement, PDO::PARAM_LOB);
        $replace->bindValue(2, $mime, PDO::PARAM_STR);
        $replace->bindValue(3, $documentId, PDO::PARAM_INT);
        $replace->bindValue(4, $appId, PDO::PARAM_STR);
        $replace->execute();
        $history = $conn->prepare('INSERT INTO application_history (application_id, previous_state, new_state, changed_by, comments) VALUES (?, ?, ?, ?, ?)');
        $history->execute([$appId, $application['workflow_state'], $application['workflow_state'], $_SESSION['username'] ?? 'Staff', 'Replaced submitted document #' . $documentId]);
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Document replaced successfully. You may now resubmit the application.']);
        exit;
    }

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
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Submitted document(s) updated successfully.']);
} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    error_log('update_application_documents.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'The documents could not be saved.']);
}
