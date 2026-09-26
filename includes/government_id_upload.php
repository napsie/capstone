<?php

function governmentIdPairError(string $fileKey, string $documentLabel = 'valid government ID'): ?string
{
    $usesDefaultLabel = $documentLabel === 'valid government ID';
    $files = $_FILES[$fileKey] ?? null;
    $errors = $files['error'] ?? [];
    if (!is_array($errors) || count($errors) < 2) {
        return $usesDefaultLabel
            ? 'Please upload both the front and back of your valid government ID.'
            : 'Please upload both required pictures for ' . $documentLabel . '.';
    }
    if (count($errors) > 2) {
        return $usesDefaultLabel
            ? 'You can only upload 2 images: front and back of the ID.'
            : 'You can only upload 2 images for ' . $documentLabel . '.';
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    foreach ([0, 1] as $index) {
        $name = (string)($files['name'][$index] ?? '');
        $tmp = (string)($files['tmp_name'][$index] ?? '');
        $size = (int)($files['size'][$index] ?? 0);
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = is_file($tmp) ? $finfo->file($tmp) : '';
        if (($errors[$index] ?? null) !== UPLOAD_ERR_OK || $size < 1 || $size > 10 * 1024 * 1024
            || !in_array($extension, ['png', 'jpg', 'jpeg'], true)
            || !in_array($mime, ['image/png', 'image/jpeg'], true)
            || ($extension === 'png' && $mime !== 'image/png')
            || ($extension !== 'png' && $mime !== 'image/jpeg')) {
            return $usesDefaultLabel
                ? 'Upload PNG, JPG, or JPEG images only for both sides of the valid government ID (up to 10 MB each).'
                : 'Upload PNG, JPG, or JPEG images only for ' . $documentLabel . ' (up to 10 MB each).';
        }
    }
    return null;
}
