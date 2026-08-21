<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/audit_logger.php';

// Check if the user is logged in and has the correct role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'department_admin') {
    header('Location: ../index.php');
    exit;
}

// Check for POST request and CSRF token
if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['deleteUser']) || isset($_POST['archiveUser']))) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error'] = 'CSRF token validation failed.';
        header('Location: user_management.php');
        exit;
    }

    $id = filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT);
    $isPermanent = isset($_POST['permanent']) && ($_POST['permanent'] === '1' || $_POST['permanent'] === 'true');

    if ($id) {
        try {
            $fetchStmt = $conn->prepare("SELECT username, first_name, last_name, role, barangay FROM users WHERE id = :id");
            $fetchStmt->execute(['id' => $id]);
            $targetUser = $fetchStmt->fetch(PDO::FETCH_ASSOC);

            if (!$targetUser) {
                $_SESSION['error'] = 'User not found.';
                header('Location: user_management.php');
                exit;
            }

            if (!$isPermanent) {
                // Soft-delete (archive)
                $username = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
                if ($username === '') $username = $_SESSION['username'] ?? 'System Admin';
                $stmt = $conn->prepare("UPDATE users SET is_archived = 1, archived_at = NOW(), archived_by = :archived_by WHERE id = :id");
                $stmt->execute(['archived_by' => $username, 'id' => $id]);

                if ($stmt->rowCount() > 0) {
                    logAudit($conn, 'ARCHIVE_USER', "Archived user account '{$targetUser['username']}' ({$targetUser['first_name']} {$targetUser['last_name']}, Role: {$targetUser['role']})", (int)$id);
                    $_SESSION['message'] = 'User archived successfully!';
                } else {
                    $_SESSION['error'] = 'User could not be archived or is already archived.';
                }
            } else {
                // Hard-delete (permanent)
                $stmt = $conn->prepare("DELETE FROM users WHERE id = :id");
                $stmt->execute(['id' => $id]);

                if ($stmt->rowCount() > 0) {
                    logAudit($conn, 'PERMANENT_DELETE_USER', "Permanently deleted user account '{$targetUser['username']}' ({$targetUser['first_name']} {$targetUser['last_name']})", (int)$id);
                    $_SESSION['message'] = 'User permanently deleted!';
                } else {
                    $_SESSION['error'] = 'User not found or could not be deleted.';
                }
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Database error: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = 'Invalid user ID.';
    }
} else {
    $_SESSION['error'] = 'Invalid request.';
}

$redirectPage = isset($_POST['redirect']) && $_POST['redirect'] === 'archive' ? 'department_archive.php' : 'user_management.php';
header('Location: ' . $redirectPage);
exit;
?>
