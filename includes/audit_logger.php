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
     * @return bool True on success, false on failure
     */
    function logAudit($conn, string $action, string $description, ?int $userId = null): bool {
        if (!$conn) {
            return false;
        }

        try {
            $uId = $userId ?? ($_SESSION['user_id'] ?? null);
            $username = $_SESSION['username'] ?? 'System';
            $role = $_SESSION['role'] ?? 'system';
            $barangay = $_SESSION['barangay'] ?? null;
            
            $ipAddress = $_SERVER['HTTP_CLIENT_IP'] 
                ?? $_SERVER['HTTP_X_FORWARDED_FOR'] 
                ?? $_SERVER['REMOTE_ADDR'] 
                ?? '127.0.0.1';

            $stmt = $conn->prepare("INSERT INTO audit_trail (user_id, username, role, barangay, action, description, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
            return $stmt->execute([$uId, $username, $role, $barangay, $action, $description, $ipAddress]);
        } catch (Exception $e) {
            error_log("Audit Trail Error: " . $e->getMessage());
            return false;
        }
    }
}
