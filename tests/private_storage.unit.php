<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/includes/private_storage.php';
$root = privateUploadDirectory();
if (str_starts_with(strtolower(str_replace('\\', '/', $root)), strtolower(str_replace('\\', '/', dirname(__DIR__))) . '/')) {
    throw new RuntimeException('Private storage is inside the website.');
}
foreach (['../secret.pdf', '..\\secret.pdf', 'folder/document.pdf', 'folder\\document.pdf'] as $unsafe) {
    if (privateUploadPath($unsafe) !== null) throw new RuntimeException('Unsafe filename accepted.');
}
if (privateUploadPath('safe-document.pdf') === null) throw new RuntimeException('Safe filename rejected.');
if (privateExistingUploadPath('../secret.pdf') !== null) throw new RuntimeException('Unsafe existing file accepted.');
echo "PASS Private storage is outside the site and rejects path traversal.\n";
