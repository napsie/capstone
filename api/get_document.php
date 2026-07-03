<?php
require_once '../includes/db_connect.php';

if (isset($_GET['id']) && isset($_GET['doc_type'])) {
    $appId = $_GET['id'];
    $docType = $_GET['doc_type'];

    // Whitelist document types to prevent security issues
    $allowed_doc_types = [
        'birth_certificate',
        'medical_certificate',
        'client_identification',
        'proof_of_address',
        'id_image'
    ];

    if (in_array($docType, $allowed_doc_types)) {
        $sql = "SELECT $docType, {$docType}_type FROM applications WHERE id_number = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$appId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && $result[$docType]) {
            $contentType = $result[$docType . '_type'];
            if (!$contentType) {
                $contentType = 'application/octet-stream';
            }

            header("Content-Type: " . $contentType);
            echo $result[$docType];
        } else {
            http_response_code(404);
            echo "Document not found.";
        }
    } else {
        http_response_code(400);
        echo "Invalid document type.";
    }
} else {
    http_response_code(400);
    echo "Invalid request.";
}
?>