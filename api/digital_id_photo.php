<?php
require_once '../includes/db_connect.php';

$token = strtoupper(preg_replace('/\s+/', '', trim((string)($_GET['token'] ?? ''))));
if (!preg_match('/^(PEN|PRX)-[A-Z0-9]{4,12}$/', $token)) {
    http_response_code(400);
    exit('Invalid tracking token.');
}

$stmt = $conn->prepare("SELECT id_number, id_image, id_image_type
                        FROM applications
                        WHERE (id_number = ? OR proxy_token = ?)
                          AND application_type = 'senior'
                          AND workflow_state IN ('Verified', 'Approved', 'Released')
                          AND senior_id_no IS NOT NULL AND senior_id_no <> ''
                          AND senior_id_no NOT REGEXP '^OSCA-[0-9]{4}-[0-9A-F]{6}$'
                          AND COALESCE(is_archived, 0) = 0
                        LIMIT 1");
$stmt->execute([$token, $token]);
$application = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$application) {
    http_response_code(404);
    exit('Digital ID photo is not available.');
}

$replacement = $conn->prepare("SELECT mime_type, document_data
                               FROM application_documents
                               WHERE application_id = ? AND document_key = 'id_image'
                               ORDER BY id DESC LIMIT 1");
$replacement->execute([$application['id_number']]);
$document = $replacement->fetch(PDO::FETCH_ASSOC);
$data = $document['document_data'] ?? $application['id_image'] ?? null;
$mime = trim((string)($document['mime_type'] ?? $application['id_image_type'] ?? ''));

if (!$data) {
    http_response_code(404);
    exit('No applicant photo is available.');
}

// Public applications save uploads as randomized filenames, while staff
// applications and document replacements may store the image as a database
// BLOB. Resolve either format without exposing arbitrary filesystem paths.
if (is_string($data) && strlen($data) < 255 && basename($data) === $data) {
    $uploadDirectory = realpath(__DIR__ . '/../uploads');
    $photoPath = realpath(__DIR__ . '/../uploads/' . $data);
    if ($uploadDirectory !== false && $photoPath !== false
        && str_starts_with($photoPath, $uploadDirectory . DIRECTORY_SEPARATOR)
        && is_file($photoPath)) {
        $detected = (new finfo(FILEINFO_MIME_TYPE))->file($photoPath);
        if (!in_array($detected, ['image/jpeg', 'image/png', 'image/gif'], true)) {
            http_response_code(415);
            exit('The submitted file is not a supported ID photo.');
        }
        header('Content-Type: ' . $detected);
        header('Content-Length: ' . filesize($photoPath));
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        readfile($photoPath);
        exit;
    }
}

if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif'], true)) {
    $detected = (new finfo(FILEINFO_MIME_TYPE))->buffer($data);
    if (!in_array($detected, ['image/jpeg', 'image/png', 'image/gif'], true)) {
        http_response_code(415);
        exit('The submitted file is not a supported ID photo.');
    }
    $mime = $detected;
}

header('Content-Type: ' . $mime);
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
echo $data;
