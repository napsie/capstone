<?php
session_start();
require_once '../includes/db_connect.php';

header('Content-Type: application/json');

// Role check — only logged-in users
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

$userRole = $_SESSION['role'];
$isAdmin  = in_array($userRole, ['department_admin', 'super_admin']);
$isStaff  = ($userRole === 'barangay_staff');

if (!$isAdmin && !$isStaff) {
    echo json_encode(['success' => false, 'message' => 'Access denied. Insufficient role.']);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $appId    = filter_input(INPUT_POST, 'applicationId', FILTER_SANITIZE_STRING);
    $action   = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING); // 'next' or 'return'
    $comments = filter_input(INPUT_POST, 'comments', FILTER_SANITIZE_STRING) ?? '';

    if (empty($appId) || empty($action)) {
        echo json_encode(['success' => false, 'message' => 'Missing required parameters.']);
        exit();
    }

    // Barangay Staff can only perform the first forward transition
    if ($isStaff && $action !== 'next') {
        echo json_encode(['success' => false, 'message' => 'Barangay staff can only submit applications for review — no other transitions are permitted.']);
        exit();
    }

    try {
        // Fetch current application state (and enforce barangay segregation for staff)
        if ($isStaff) {
            $stmt = $conn->prepare("SELECT workflow_state FROM applications WHERE id_number = ? AND barangay = ?");
            $stmt->execute([$appId, $_SESSION['barangay']]);
        } else {
            $stmt = $conn->prepare("SELECT workflow_state FROM applications WHERE id_number = ?");
            $stmt->execute([$appId]);
        }
        $app = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$app) {
            echo json_encode(['success' => false, 'message' => 'Application not found or access denied.']);
            exit();
        }

        $currentState = $app['workflow_state'] ?: 'Received';
        $nextState    = $currentState;

        // FSM Transition Logic
        if ($action === 'next') {
            // Staff can ONLY move Received → For Review
            if ($isStaff && $currentState !== 'Received') {
                echo json_encode(['success' => false, 'message' => 'Barangay staff can only submit applications that are in the Received state.']);
                exit();
            }

            switch ($currentState) {
                case 'Received':  $nextState = 'For Review'; break;
                case 'For Review': $nextState = 'Verified';  break;
                case 'Verified':   $nextState = 'Approved';  break;
                case 'Approved':   $nextState = 'Released';  break;
                case 'Released':
                    echo json_encode(['success' => false, 'message' => 'Application is already in its final state (Released).']);
                    exit();
                default: $nextState = 'Received';
            }
            $defaultComment = "Application moved from $currentState to $nextState.";
        } else if ($action === 'return') {
            // Only Admins can return an application back
            if (!$isAdmin) {
                echo json_encode(['success' => false, 'message' => 'Only department admins can return applications.']);
                exit();
            }
            if ($currentState === 'Received') {
                echo json_encode(['success' => false, 'message' => 'Application is already in the Received state.']);
                exit();
            }
            $nextState = 'Received';
            $defaultComment = "Returned to Received state for document correction / rescan.";
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action.']);
            exit();
        }

        $finalComment = !empty($comments) ? $comments : $defaultComment;
        $operator = $_SESSION['username'] ?? 'System';

        // Begin Transaction
        $conn->beginTransaction();

        // 1. Update applications table state
        $stmtUpdate = $conn->prepare("UPDATE applications SET workflow_state = ? WHERE id_number = ?");
        $stmtUpdate->execute([$nextState, $appId]);

        // 2. Insert into history log
        $stmtHistory = $conn->prepare("INSERT INTO application_history (application_id, previous_state, new_state, changed_by, comments) VALUES (?, ?, ?, ?, ?)");
        $stmtHistory->execute([$appId, $currentState, $nextState, $operator, $finalComment]);

        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => "Successfully transitioned application state to: $nextState",
            'current_state' => $nextState
        ]);
        exit();

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit();
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}
?>
