<?php
session_start();

// Wrap everything so we always output JSON, never HTML error pages
try {
    require_once '../includes/db_connect.php';
    require_once '../includes/application_types.php';
} catch (Throwable $boot_err) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $boot_err->getMessage()]);
    exit();
}

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['barangay_staff', 'department_admin', 'super_admin'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized. Please log in.']);
    exit();
}

$userRole     = $_SESSION['role'];
$userBarangay = $_SESSION['barangay'] ?? null;

$oscaCols = implode(', ', array_map(fn($c) => "a.$c", getOscaExtraColumns()));
$baseCols = "a.id_number, a.full_name, a.application_type, a.birth_date, a.contact_number, a.complete_address,
             a.emergency_contact, a.emergency_contact_name, a.date_submitted, a.status, a.barangay, a.disability_type,
             (a.proof_of_address IS NOT NULL) as has_proof_of_address, (a.id_image IS NOT NULL) as has_id_image,
             a.lastName, a.firstName, a.middleName, a.suffix,
             a.sss_number, a.pension_amount, a.date_of_death, a.relationship_to_deceased,
             a.home_visit_scheduled_at, a.home_visit_status, a.sms_notification_status,
             a.is_proxy_application, a.proxy_name, a.proxy_relationship, a.proxy_contact_number, a.proxy_token,
             a.priority_level, a.workflow_state, a.additional_notes, a.email_address,
             a.is_archived, a.archived_at,
             COALESCE((SELECT NULLIF(TRIM(CONCAT(u.first_name, ' ', u.last_name)), '') FROM users u WHERE u.username = a.archived_by LIMIT 1), a.archived_by) AS archived_by,
             a.medical_conditions, a.return_reason,
             a.proof_of_address_type, a.id_image_type, a.birth_certificate_type,
             a.medical_certificate_type, a.client_identification_type,
             a.psa_birth_cert, a.barangay_residency, a.comelec_cert, a.proof_of_life,
             a.auth_letter, a.proxy_id, a.proxy_birth_cert, a.home_visitation_form,
             a.landbank_enrollment_form, a.parent_senior_id,
             $oscaCols";

try {
    $appId = isset($_GET['id']) ? trim($_GET['id']) : '';

    if (empty($appId)) {
        echo json_encode(['error' => 'Application ID is required.']);
        exit();
    }

    if ($userRole === 'barangay_staff' && $userBarangay) {
        $sql = "SELECT $baseCols,
                       (SELECT h.comments FROM application_history h
                        WHERE h.application_id = a.id_number AND h.new_state = 'Received'
                          AND h.previous_state IN ('For Review', 'Verified', 'Approved')
                        ORDER BY h.changed_at DESC LIMIT 1) as return_comments
                FROM applications a WHERE a.id_number = ? AND a.barangay = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$appId, $userBarangay]);
    } else {
        $sql = "SELECT $baseCols,
                       (SELECT h.comments FROM application_history h
                        WHERE h.application_id = a.id_number AND h.new_state = 'Received'
                          AND h.previous_state IN ('For Review', 'Verified', 'Approved')
                        ORDER BY h.changed_at DESC LIMIT 1) as return_comments
                FROM applications a WHERE a.id_number = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$appId]);
    }
    $application = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$application) {
        http_response_code(404);
        echo json_encode(['error' => 'Application not found or access denied.']);
        exit();
    }

    if (empty(trim($application['complete_address'] ?? ''))) {
        $addressParts = array_filter([
            $application['house_no'] ?? '',
            $application['street'] ?? '',
            $application['barangay'] ?? '',
            $application['city'] ?? '',
            $application['province'] ?? '',
            $application['zip_code'] ?? '',
        ], fn($part) => trim((string)$part) !== '');

        $application['complete_address'] = implode(', ', $addressParts);
    }

    $stmtHistory = $conn->prepare("SELECT previous_state, new_state, changed_by, changed_at, comments
                                   FROM application_history WHERE application_id = ? ORDER BY changed_at ASC");
    $stmtHistory->execute([$appId]);
    $application['history'] = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);

    // Template-based forms can submit many requirements. Return their
    // metadata separately so every modal can render the same document cards.
    if ($userRole === 'barangay_staff' && $userBarangay) {
        $documentsSql = 'SELECT d.id, d.document_key, d.document_label, d.mime_type
                         FROM application_documents d
                         INNER JOIN applications a ON a.id_number = d.application_id
                         WHERE d.application_id = ? AND a.barangay = ? ORDER BY d.id';
        $documentsStmt = $conn->prepare($documentsSql);
        $documentsStmt->execute([$appId, $userBarangay]);
    } else {
        $documentsStmt = $conn->prepare('SELECT id, document_key, document_label, mime_type FROM application_documents WHERE application_id = ? ORDER BY id');
        $documentsStmt->execute([$appId]);
    }
    // Older databases may contain repeated rows from before the
    // (application_id, document_key) unique index was introduced. Keep only
    // the newest copy of each requirement so every details modal presents one
    // document card per requirement.
    $documentsByKey = [];
    foreach ($documentsStmt->fetchAll(PDO::FETCH_ASSOC) as $document) {
        $key = trim((string)($document['document_key'] ?? ''));
        if ($key === '') {
            $key = 'document_' . (string)($document['id'] ?? count($documentsByKey));
        }
        $documentsByKey[$key] = $document;
    }
    $application['documents'] = array_values($documentsByKey);

    echo json_encode($application);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

$stmt = null;
$conn = null;
