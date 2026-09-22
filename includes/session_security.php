<?php
/**
 * Shared authenticated-session guard.  It is loaded by db_connect.php so it
 * covers pages and API endpoints consistently after they start a session.
 */
function enforceAuthenticatedSessionTimeout(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE || !isset($_SESSION['user_id'])) return;

    $idleLimit = 300; // Five minutes, as approved for this system.
    $now = time();
    $lastActivity = (int)($_SESSION['last_activity_at'] ?? $now);
    if ($now - $lastActivity > $idleLimit) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
        }
        session_destroy();

        $isApi = str_contains(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), '/api/');
        if ($isApi) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Your session expired after 5 minutes of inactivity. Please sign in again.']);
        } else {
            $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
            $basePath = preg_replace('#/(pages|api)/[^/]+$#', '', $scriptName);
            if ($basePath === $scriptName) $basePath = rtrim(dirname($scriptName), '/');
            header('Location: ' . ($basePath ?: '') . '/index.php?session=expired');
        }
        exit;
    }
    // Background reads (for example dashboard refreshes) must not extend an
    // idle session. Page loads and state-changing requests still count.
    $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $isBackgroundRead = str_contains($scriptName, '/api/')
        && strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET';
    if (!$isBackgroundRead) $_SESSION['last_activity_at'] = $now;
}
