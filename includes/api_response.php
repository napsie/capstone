<?php
/** Send one consistent JSON response shape from API endpoints. */
function apiRespond(int $status, bool $success, string $message = '', array $data = []): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}
