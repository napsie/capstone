<?php

function readDashboardResponseCache(string $key, int $ttlSeconds = 45): ?array
{
    $path = dashboardResponseCachePath($key);
    if (!is_file($path) || filemtime($path) < time() - $ttlSeconds) return null;
    $decoded = json_decode((string)file_get_contents($path), true);
    return is_array($decoded) ? $decoded : null;
}

function writeDashboardResponseCache(string $key, array $response): void
{
    $path = dashboardResponseCachePath($key);
    $directory = dirname($path);
    if (!is_dir($directory)) @mkdir($directory, 0750, true);
    @file_put_contents($path, json_encode($response), LOCK_EX);
}

function dashboardResponseCachePath(string $key): string
{
    return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR .
        'seniorlink-dashboard-cache' . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.json';
}

/** Clear cached dashboard aggregates after a data-changing action. */
function clearDashboardResponseCache(): void
{
    $directory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'seniorlink-dashboard-cache';
    foreach (glob($directory . DIRECTORY_SEPARATOR . '*.json') ?: [] as $path) {
        @unlink($path);
    }
}
