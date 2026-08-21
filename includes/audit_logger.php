<?php
/**
 * SENIORLINK Audit Trail Logger Helper
 */
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

if (!function_exists('logAudit')) {
    /**
     * Log an action to the system audit trail table.
     *
     * @param PDO $conn Database connection handle
     * @param string $action Action code/identifier (e.g., 'ARCHIVE_APPLICATION', 'LOGIN')
     * @param string $description Detailed human-readable log description
     * @param int|null $userId Optional explicit user ID (defaults to current session user_id)
     * @param array $context Optional actor overrides for events recorded before a session exists
     * @return bool True on success, false on failure
     */
    function logAudit($conn, string $action, string $description, ?int $userId = null, array $context = []): bool {
        if (!$conn) {
            return false;
        }

        try {
            $uId = $userId ?? ($_SESSION['user_id'] ?? null);
            $username = trim((string)($context['username'] ?? ($_SESSION['username'] ?? 'System'))) ?: 'System';
            $role = trim((string)($context['role'] ?? ($_SESSION['role'] ?? 'system'))) ?: 'system';
            $barangay = $context['barangay'] ?? ($_SESSION['barangay'] ?? null);
            $barangay = is_string($barangay) && trim($barangay) !== '' ? trim($barangay) : null;
            $username = substr($username, 0, 100);
            $role = substr($role, 0, 50);
            $barangay = $barangay !== null ? substr($barangay, 0, 100) : null;
            $action = substr(trim($action), 0, 100);
            
            $ipAddress = $context['ip_address']
                ?? $_SERVER['HTTP_CLIENT_IP']
                ?? $_SERVER['HTTP_X_FORWARDED_FOR']
                ?? $_SERVER['REMOTE_ADDR'] 
                ?? '127.0.0.1';
            // Forwarded headers may contain a comma-separated proxy chain.
            $ipAddress = trim(explode(',', (string)$ipAddress)[0]);
            $ipAddress = substr($ipAddress, 0, 45);

            $stmt = $conn->prepare("INSERT INTO audit_trail (user_id, username, role, barangay, action, description, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
            return $stmt->execute([$uId, $username, $role, $barangay, $action, $description, $ipAddress]);
        } catch (Exception $e) {
            error_log("Audit Trail Error: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('auditActionPresentation')) {
    /**
     * Return a consistent, human-readable presentation for an audit action.
     */
    function auditActionPresentation(string $action): array {
        $normalized = strtoupper(trim($action));
        $label = ucwords(strtolower(str_replace('_', ' ', $normalized)));
        $meta = ['label' => $label ?: 'System Activity', 'icon' => 'fa-shield-halved', 'tone' => 'info'];

        if ($normalized === 'LOGIN') return ['label' => 'Signed in', 'icon' => 'fa-right-to-bracket', 'tone' => 'success'];
        if ($normalized === 'LOGOUT') return ['label' => 'Signed out', 'icon' => 'fa-right-from-bracket', 'tone' => 'neutral'];
        if ($normalized === 'FAILED_LOGIN') return ['label' => 'Failed sign-in', 'icon' => 'fa-triangle-exclamation', 'tone' => 'danger'];
        if (str_starts_with($normalized, 'ARCHIVE_')) return ['label' => $label, 'icon' => 'fa-box-archive', 'tone' => 'warning'];
        if (str_starts_with($normalized, 'RESTORE_')) return ['label' => $label, 'icon' => 'fa-rotate-left', 'tone' => 'success'];
        if (str_contains($normalized, 'DELETE')) return ['label' => 'Permanent removal', 'icon' => 'fa-circle-minus', 'tone' => 'danger'];
        if (str_starts_with($normalized, 'ADD_') || str_starts_with($normalized, 'CREATE_')) return ['label' => $label, 'icon' => 'fa-circle-plus', 'tone' => 'success'];
        if (str_starts_with($normalized, 'UPDATE_') || str_starts_with($normalized, 'TOGGLE_') || str_starts_with($normalized, 'REVIEW_')) return ['label' => $label, 'icon' => 'fa-pen-to-square', 'tone' => 'info'];

        return $meta;
    }
}

if (!function_exists('auditDescriptionPresentation')) {
    /** Preserve historical meaning while using the archive UI's removal terminology. */
    function auditDescriptionPresentation(string $description): string {
        return str_ireplace(
            ['permanent deletion', 'permanently deleted', 'deleted', 'delete'],
            ['permanent removal', 'permanently removed', 'removed', 'remove'],
            $description
        );
    }
}

if (!function_exists('auditIpLabel')) {
    function auditIpLabel(?string $ipAddress): string {
        $ip = trim((string)$ipAddress);
        if ($ip === '' || $ip === '127.0.0.1' || $ip === '::1') return 'Local device';
        return $ip;
    }
}
