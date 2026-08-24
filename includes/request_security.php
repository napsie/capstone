<?php
/** Reject browser mutation requests that are explicitly cross-site. */
function requireSameOriginMutation(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        echo json_encode(['success' => false, 'message' => 'POST is required.']);
        exit;
    }

    $fetchSite = strtolower((string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? ''));
    if ($fetchSite === 'cross-site') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Cross-site request rejected.']);
        exit;
    }

    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin !== '') {
        $originHost = strtolower((string)(parse_url($origin, PHP_URL_HOST) ?? ''));
        $requestHost = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
        if ($originHost === '' || !hash_equals($requestHost, $originHost)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Request origin rejected.']);
            exit;
        }
    }
}
