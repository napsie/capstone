<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/document_repository.php';
$applicationId = $conn->query('SELECT id_number FROM applications LIMIT 1')->fetchColumn();
if (!$applicationId) { echo "SKIP no application fixture\n"; exit; }
$key = 'version_test_' . bin2hex(random_bytes(4));
$conn->beginTransaction();
try {
    saveDocumentVersion($conn, ['application_id'=>$applicationId,'document_key'=>$key,'document_label'=>'Version Test','mime_type'=>'text/plain','document_data'=>'original','uploaded_by'=>'Test']);
    saveDocumentVersion($conn, ['application_id'=>$applicationId,'document_key'=>$key,'document_label'=>'Version Test','mime_type'=>'text/plain','document_data'=>'corrected','uploaded_by'=>'Test','source'=>'correction']);
    $stmt=$conn->prepare('SELECT version,is_current,document_data FROM application_documents WHERE application_id=? AND document_key=? ORDER BY version'); $stmt->execute([$applicationId,$key]); $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows)!==2 || (int)$rows[0]['version']!==1 || (int)$rows[0]['is_current']!==0 || (int)$rows[1]['version']!==2 || (int)$rows[1]['is_current']!==1 || $rows[0]['document_data']!=='original') throw new RuntimeException('Version history assertion failed.');
    echo "PASS immutable original and current corrected version\n";
    $conn->rollBack();
} catch(Throwable $e) { if($conn->inTransaction())$conn->rollBack(); fwrite(STDERR,'FAIL '.$e->getMessage()."\n"); exit(1); }

