<?php
session_start();
require_once '../includes/db_connect.php';

// Check if the user is logged in and has the correct role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'department_admin') {
    header('Location: ../index.php');
    exit;
}

// Check for POST request and CSRF token
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['deleteUser'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error'] = 'CSRF token validation failed.';
        header('Location: User_Management.php');
        exit;
    }

    $id = filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT);

    if ($id) {
        try {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = :id");
            $stmt->execute(['id' => $id]);

            if ($stmt->rowCount() > 0) {
                $_SESSION['message'] = 'User deleted successfully!';
            } else {
                $_SESSION['error'] = 'User not found or could not be deleted.';
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error deleting user: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = 'Invalid user ID.';
    }
} else {
    $_SESSION['error'] = 'Invalid request.';
}

header('Location: User_Management.php');
exit;
?>