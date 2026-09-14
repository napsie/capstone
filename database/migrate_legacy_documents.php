<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/document_repository.php';

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$definitions = [
    'proof_of_address'=>['Proof of Address','proof_of_address_type',true], 'id_image'=>['ID / Identification Photo','id_image_type',true],
    'psa_birth_cert'=>['PSA Birth Certificate',null,false], 'barangay_residency'=>['Barangay Residency Certificate',null,false],
    'comelec_cert'=>['COMELEC Certificate',null,false], 'proof_of_life'=>['Current Senior Photo / Proof of Life',null,false],
    'auth_letter'=>['Authorization Letter',null,false], 'proxy_id'=>['Representative Government ID',null,false],
    'proxy_birth_cert'=>['Representative Birth Certificate',null,false], 'home_visitation_form'=>['Home Visitation Form',null,false],
    'landbank_enrollment_form'=>['Land Bank Enrollment Form',null,false],
];
$columns = implode(',', array_merge(['id_number'], array_keys($definitions), array_values(array_filter(array_column($definitions,1)))));
$rows = $conn->query("SELECT {$columns} FROM applications")->fetchAll(PDO::FETCH_ASSOC); $migrated=0; $skipped=0;
foreach ($rows as $row) {
    foreach ($definitions as $key => [$label,$mimeColumn,$isBlob]) {
        $value=$row[$key]??null; if ($value===null || $value==='') continue;
        $exists=$conn->prepare('SELECT 1 FROM application_documents WHERE application_id=? AND document_key=? AND is_current=1'); $exists->execute([$row['id_number'],$key]);
        if ($exists->fetchColumn()) { $skipped++; continue; }
        $data=$value; $filename=null; $mime=$mimeColumn ? ($row[$mimeColumn]??'') : '';
        if (!$isBlob) {
            $filename=basename((string)$value); $path=__DIR__.'/../uploads/'.$filename;
            if (!is_file($path)) { $skipped++; continue; }
            $data=file_get_contents($path); $mime=(new finfo(FILEINFO_MIME_TYPE))->file($path);
        }
        if (!$mime) $mime='application/octet-stream';
        if (strlen($data) > 8 * 1024 * 1024) { fwrite(STDERR, "Skipped oversized document {$row['id_number']}/{$key}\n"); $skipped++; continue; }
        $conn->beginTransaction();
        try { saveDocumentVersion($conn,['application_id'=>$row['id_number'],'document_key'=>$key,'document_label'=>$label,'mime_type'=>$mime,'document_data'=>$data,'uploaded_by'=>'Legacy migration','original_filename'=>$filename,'source'=>'legacy_migration']); $conn->commit(); $migrated++; }
        catch(Throwable $e){ try { if($conn->inTransaction())$conn->rollBack(); } catch(Throwable $ignored) {} throw $e; }
    }
}
echo "Migrated {$migrated} document(s); skipped {$skipped} existing or unavailable document(s).\n";
