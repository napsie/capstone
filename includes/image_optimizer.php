<?php

/** Preserve the original upload and create smaller JPEG display derivatives. */
function createImageDerivatives(string $sourcePath, int $displayMax = 1600, int $thumbnailMax = 320): array
{
    if (!extension_loaded('gd') || !is_file($sourcePath)) return [];
    $info = @getimagesize($sourcePath);
    if (!$info || empty($info[0]) || empty($info[1])) return [];

    $create = match ($info['mime'] ?? '') {
        'image/jpeg' => 'imagecreatefromjpeg',
        'image/png' => 'imagecreatefrompng',
        'image/gif' => 'imagecreatefromgif',
        default => null,
    };
    if (!$create || !function_exists($create)) return [];
    $source = @$create($sourcePath);
    if (!$source) return [];

    $directory = dirname($sourcePath);
    $stem = pathinfo($sourcePath, PATHINFO_FILENAME);
    $results = [];
    foreach (['display' => $displayMax, 'thumb' => $thumbnailMax] as $kind => $maximum) {
        $scale = min(1, $maximum / max($info[0], $info[1]));
        $width = max(1, (int)round($info[0] * $scale));
        $height = max(1, (int)round($info[1] * $scale));
        $canvas = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, $info[0], $info[1]);
        $filename = $stem . '.' . $kind . '.jpg';
        $destination = $directory . DIRECTORY_SEPARATOR . $filename;
        if (@imagejpeg($canvas, $destination, $kind === 'thumb' ? 78 : 84)) $results[$kind] = $filename;
        imagedestroy($canvas);
    }
    imagedestroy($source);
    return $results;
}

function optimizedImageName(string $directory, string $originalName, string $kind = 'thumb'): string
{
    $candidate = pathinfo($originalName, PATHINFO_FILENAME) . '.' . $kind . '.jpg';
    return is_file(rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $candidate) ? $candidate : $originalName;
}

function deleteImageWithDerivatives(string $sourcePath): void
{
    $directory = dirname($sourcePath);
    $stem = pathinfo($sourcePath, PATHINFO_FILENAME);
    foreach ([$sourcePath, $directory . DIRECTORY_SEPARATOR . $stem . '.display.jpg', $directory . DIRECTORY_SEPARATOR . $stem . '.thumb.jpg'] as $path) {
        if (is_file($path)) @unlink($path);
    }
}
