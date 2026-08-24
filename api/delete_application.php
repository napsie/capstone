<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/db_connect.php';
require_once '../includes/audit_logger.php';
require_once '../includes/request_security.php';
requireSameOriginMutation();

// Authentication check (ensure only authorized roles can delete/archive)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['barangay_staff', 'department_admin', 'super_admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

if (isset($_POST['id'])) {
    $id = trim((string)$_POST['id']);
    $isPermanent = isset($_POST['permanent']) && ($_POST['permanent'] === '1' || $_POST['permanent'] === 'true');

    if ($id === '') {
        echo json_encode(['success' => false, 'message' => 'Application ID is empty.']);
        exit;
    }

    try {
        $where = 'id_number = ?';
        $params = [$id];
        if (($_SESSION['role'] ?? '') === 'barangay_staff') {
            $where .= " AND barangay = ?";
            $params[] = $_SESSION['barangay'] ?? '';
        }

        // Fetch application details first for logging
        $fetchStmt = $conn->prepare("SELECT full_name, barangay, application_type, psa_birth_cert, barangay_residency, comelec_cert, proof_of_life, auth_letter, proxy_id, proxy_birth_cert FROM applications WHERE $where");
        $fetchStmt->execute($params);
        $app = $fetchStmt->fetch(PDO::FETCH_ASSOC);

        if (!$app) {
            echo json_encode(['success' => false, 'message' => 'Application was not found or is outside your scope.']);
            exit;
        }

        if (!$isPermanent) {
            // Soft delete (archive) by default
            $username = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
            if ($username === '') $username = $_SESSION['username'] ?? 'Unknown User';
            $stmt = $conn->prepare("UPDATE applications SET is_archived = 1, archived_at = NOW(), archived_by = ? WHERE $where");
            $updateParams = array_merge([$username], $params);
            
            if ($stmt->execute($updateParams)) {
                if ($stmt->rowCount() > 0) {
                    logAudit($conn, 'ARCHIVE_APPLICATION', "Archived application {$id} ({$app['full_name']} - Barangay {$app['barangay']})");
                    echo json_encode(['success' => true, 'message' => 'Application archived successfully.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Application is already archived or could not be updated.']);
                }
            } else {
                throw new Exception($stmt->errorInfo()[2]);
            }
        } else {
            // Permanent hard deletion
            $stmt = $conn->prepare("DELETE FROM applications WHERE $where");
            
            if ($stmt->execute($params)) {
                if ($stmt->rowCount() > 0) {
                    $uploadDir = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads');
                    if ($uploadDir !== false) {
                        $uploadedFiles = [
                            $app['psa_birth_cert'] ?? '',
                            $app['barangay_residency'] ?? '',
                            $app['comelec_cert'] ?? '',
                            $app['proof_of_life'] ?? '',
                            $app['auth_letter'] ?? '',
                            $app['proxy_id'] ?? '',
                            $app['proxy_birth_cert'] ?? ''
                        ];
                        foreach ($uploadedFiles as $storedName) {
                            if (!is_string($storedName) || $storedName === '') {
                                continue;
                            }
                            $safeName = basename($storedName);
                            if ($safeName !== $storedName) {
                                continue;
                            }
                            $filePath = $uploadDir . DIRECTORY_SEPARATOR . $safeName;
                            if (is_file($filePath) && !unlink($filePath)) {
                                error_log('Unable to remove application upload: ' . $filePath);
                            }
                        }
                    }

                    logAudit($conn, 'PERMANENT_DELETE_APPLICATION', "Permanently deleted application {$id} ({$app['full_name']} - Barangay {$app['barangay']})");
                    echo json_encode(['success' => true, 'message' => 'Application permanently deleted.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Application could not be deleted.']);
                }
            } else {
                throw new Exception($stmt->errorInfo()[2]);
            }
        }
    } catch (Exception $e) {
        error_log('Error in delete_application.php: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again later.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No application ID provided.']);
}
