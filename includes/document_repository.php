<?php
function saveDocumentVersion(PDO $conn, array $document): int {
    $appId = (string)$document['application_id'];
    $key = (string)$document['document_key'];
    $lock = $conn->prepare('SELECT id, version FROM application_documents WHERE application_id=? AND document_key=? AND is_current=1 ORDER BY version DESC, id DESC LIMIT 1 FOR UPDATE');
    $lock->execute([$appId, $key]);
    $previous = $lock->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($previous) {
        $conn->prepare('UPDATE application_documents SET is_current=0 WHERE application_id=? AND document_key=? AND is_current=1')->execute([$appId, $key]);
    }
    $version = ((int)($previous['version'] ?? 0)) + 1;
    $payload = $document['document_data'];
    $stmt = $conn->prepare('INSERT INTO application_documents (application_id,document_key,document_label,version,is_current,uploaded_by,uploader_id,original_filename,mime_type,file_size,checksum_sha256,source,supersedes_document_id,document_data) VALUES (?,?,?,?,1,?,?,?,?,?,?,?,?,?)');
    $stmt->bindValue(1, $appId); $stmt->bindValue(2, $key); $stmt->bindValue(3, (string)$document['document_label']);
    $stmt->bindValue(4, $version, PDO::PARAM_INT); $stmt->bindValue(5, $document['uploaded_by'] ?? null);
    $stmt->bindValue(6, $document['uploader_id'] ?? null, PDO::PARAM_INT); $stmt->bindValue(7, $document['original_filename'] ?? null);
    $stmt->bindValue(8, (string)$document['mime_type']); $stmt->bindValue(9, strlen($payload), PDO::PARAM_INT);
    $stmt->bindValue(10, hash('sha256', $payload)); $stmt->bindValue(11, $document['source'] ?? 'application');
    $stmt->bindValue(12, $previous['id'] ?? null, PDO::PARAM_INT); $stmt->bindValue(13, $payload, PDO::PARAM_LOB);
    $stmt->execute();
    return (int)$conn->lastInsertId();
}

