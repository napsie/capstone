<?php
require_once dirname(__DIR__) . '/includes/government_id_upload.php';

$png = tempnam(sys_get_temp_dir(), 'seniorlink-id-png-');
$pdf = tempnam(sys_get_temp_dir(), 'seniorlink-id-pdf-');
file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScLytQAAAABJRU5ErkJggg=='));
file_put_contents($pdf, "%PDF-1.4\n%%EOF");
try {
    $check = static function (bool $passed): void { if (!$passed) throw new RuntimeException('Government ID validation failed.'); };
    $setFiles = static function (array $names, array $paths): void {
        $_FILES['government_id'] = [
            'name' => $names,
            'tmp_name' => $paths,
            'error' => array_fill(0, count($names), UPLOAD_ERR_OK),
            'size' => array_map('filesize', $paths),
        ];
    };
    $setFiles(['front.png'], [$png]);
    $check(governmentIdPairError('government_id') === 'Please upload both the front and back of your valid government ID.');
    $setFiles(['front.png', 'back.png', 'extra.png'], [$png, $png, $png]);
    $check(governmentIdPairError('government_id') === 'You can only upload 2 images: front and back of the ID.');
    $setFiles(['front.png', 'back.png'], [$png, $png]);
    $check(governmentIdPairError('government_id') === null);
    $setFiles(['front.png', 'back.pdf'], [$png, $pdf]);
    $check(governmentIdPairError('government_id') !== null);
    $setFiles(['front.png', 'back.jpg'], [$png, $png]);
    $check(governmentIdPairError('government_id') !== null);
    echo "Government ID pair validation passed.\n";
} finally {
    unlink($png);
    unlink($pdf);
}
