<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/audit_logger.php';
require_once '../includes/filing_deadline.php';
require_once '../includes/request_security.php';
requireSameOriginMutation();

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

/**
 * Safe string sanitizer — replaces deprecated FILTER_SANITIZE_STRING.
 */
function sanitize_str(?string $value): ?string {
    if ($value === null) return null;
    return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $appId    = sanitize_str($_POST['applicationId'] ?? null);
    $action   = sanitize_str($_POST['action']         ?? null); // 'next', 'return', or 'reject'
    $comments = sanitize_str($_POST['comments']       ?? '') ?? '';

    if (empty($appId) || empty($action)) {
        echo json_encode(['success' => false, 'message' => 'Missing required parameters.']);
        exit();
    }

    // SHDO can only perform the first forward transition.
    if ($isStaff && $action !== 'next') {
        echo json_encode(['success' => false, 'message' => 'SHDO can only submit applications for review — no other transitions are permitted.']);
        exit();
    }

    try {
        // Fetch current application state (and enforce barangay segregation for staff)
        if ($isStaff) {
            $stmt = $conn->prepare("SELECT * FROM applications WHERE id_number = ? AND barangay = ?");
            $stmt->execute([$appId, $_SESSION['barangay']]);
        } else {
            $stmt = $conn->prepare("SELECT * FROM applications WHERE id_number = ?");
            $stmt->execute([$appId]);
        }
        $app = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$app) {
            echo json_encode(['success' => false, 'message' => 'Application not found or access denied.']);
            exit();
        }

        if (!empty($app['is_archived'])) {
            echo json_encode(['success' => false, 'message' => 'Archived applications cannot change status.']);
            exit;
        }
        $currentStatus  = $app['workflow_state'] ?: 'Received';
        $applicationType = $app['application_type'] ?? '';
        $nextStatus     = $currentStatus;

        // Direct, rule-based status handling.
        if ($action === 'next') {
            // Staff can only move from initial states.
            if ($isStaff && !in_array($currentStatus, ['Received', 'Submitted', 'Needs Correction'], true)) {
                echo json_encode(['success' => false, 'message' => 'SHDO can only submit newly received applications for department review.']);
                exit();
            }

            if (in_array($currentStatus, ['Submitted', 'Received', 'Needs Correction'], true)) {
                if ($currentStatus === 'Needs Correction' && !$isStaff) {
                    echo json_encode(['success' => false, 'message' => 'Barangay staff must correct and resubmit this application.']);
                    exit;
                }
                $nextStatus = 'For Review';
            } elseif ($currentStatus === 'For Review') {
                $nextStatus = 'Verified';
            } elseif (in_array($currentStatus, ['Verified', 'Approved', 'Released'], true)) {
                echo json_encode(['success' => false, 'message' => 'Application is already verified and complete.']);
                exit();
            } else {
                echo json_encode(['success' => false, 'message' => 'This application cannot advance from its current state.']);
                exit;
            }

            // ──────────────────────────────────────────────────────────────────
            // POLICY ENFORCEMENT: For Review → Verified (final) transition checks
            // ──────────────────────────────────────────────────────────────────
            if ($currentStatus === 'For Review' && $nextStatus === 'Verified') {

                // ── (A) LOCAL SOCIAL PENSION: Ordinance 17-2025 checks ──────────
                if ($applicationType === 'pension') {
                    if (($app['home_visit_status'] ?? '') !== 'Completed') {
                        echo json_encode([
                            'success' => false,
                            'message' => 'APPLICATION BLOCKED: The required home visit must be completed before this Local Pension application can be verified.'
                        ]);
                        exit();
                    }

                    // Rule 1: SSS pension must not exceed ₱4,000
                    $sssAmount = floatval($app['pension_amount'] ?? 0);
                    if ($sssAmount > 4000) {
                        echo json_encode([
                            'success' => false,
                            'message' => "APPLICATION BLOCKED: EXCEEDS PENSION CUT-OFF LIMIT — SSS pension of ₱" . number_format($sssAmount, 2) . " exceeds the ₱4,000 monthly limit under Pasig Ordinance 17-2025."
                        ]);
                        exit();
                    }

                    // Rule 2: Must not be an active DSWD National Pension beneficiary
                    $stmtCheck = $conn->prepare(
                        "SELECT COUNT(*) FROM applications 
                         WHERE (lastName = ? OR full_name LIKE ?)
                         AND birth_date = ?
                         AND application_type = 'national_pension'
                         AND workflow_state IN ('Verified','Approved','Released')
                         AND id_number != ?"
                    );
                    $nameLike = '%' . $app['lastName'] . '%';
                    $stmtCheck->execute([$app['lastName'], $nameLike, $app['birth_date'], $appId]);
                    $nationalPensionCount = (int)$stmtCheck->fetchColumn();

                    if ($nationalPensionCount > 0) {
                        echo json_encode([
                            'success' => false,
                            'message' => "APPLICATION BLOCKED: EXCEEDS PENSION CUT-OFF LIMIT — Applicant is already an active DSWD National Pension (RA 11916) beneficiary. Dual-pension enrollment is not allowed."
                        ]);
                        exit();
                    }
                }

                // ── (B) NATIONAL DSWD PENSION: SSS pension must be ₱0 ──────────
                if ($applicationType === 'national_pension') {
                    $sssAmount = floatval($app['pension_amount'] ?? 0);
                    if ($sssAmount > 0) {
                        echo json_encode([
                            'success' => false,
                            'message' => "APPLICATION BLOCKED: EXCEEDS PENSION CUT-OFF LIMIT — National DSWD Social Pension (RA 11916) is restricted to indigent seniors with no other pension benefits. Verified SSS pension: ₱" . number_format($sssAmount, 2) . "."
                        ]);
                        exit();
                    }
                }

                // ── (C) BURIAL ASSISTANCE: Must be filed within 30 working days ─
                if ($applicationType === 'burial') {
                    $dateOfDeath = $app['date_of_death'] ?? '';
                    $elapsedWorkingDays = filingWorkingDays($dateOfDeath, $app['date_submitted'] ?? null);
                    if ($elapsedWorkingDays === null) {
                        echo json_encode(['success' => false, 'message' => 'A valid death date on or before the original submission date is required. Return the application for correction.']);
                        exit;
                    }
                    if ($elapsedWorkingDays > 30) {
                        echo json_encode([
                            'success' => false,
                            'message' => "APPLICATION BLOCKED — POLICY VIOLATION: SUBMITTED BEYOND THE 30-DAY LIMIT — Burial assistance must be filed within 30 working days of death registration. Elapsed: {$elapsedWorkingDays} working days (Pasig Ordinance 3-2026)."
                        ]);
                        exit();
                    }
                }
            }

            $defaultComment = "Application status changed from $currentStatus to $nextStatus.";

        } else if ($action === 'return') {
            // Only Admins can return an application back
            if (!$isAdmin) {
                echo json_encode(['success' => false, 'message' => 'Only department admins can return applications.']);
                exit();
            }
            if (!in_array($currentStatus, ['Received', 'Submitted', 'For Review'], true)) {
                echo json_encode(['success' => false, 'message' => 'Only active applications awaiting review can be returned for correction.']);
                exit();
            }
            $correctionDocuments = trim(strip_tags((string)($_POST['correctionDocuments'] ?? '')));
            $reason = trim(strip_tags((string)($_POST['comments'] ?? '')));
            if ($reason === '' || $correctionDocuments === '') {
                echo json_encode(['success' => false, 'message' => 'List the documents or fields needing correction and explain what must be fixed.']);
                exit;
            }
            $comments = "Items to correct: {$correctionDocuments}\nReason: {$reason}";
            if (mb_strlen($comments) > 500) {
                echo json_encode(['success' => false, 'message' => 'Keep the correction items and reason within 500 characters combined.']);
                exit;
            }
            $nextStatus = 'Needs Correction';
            $defaultComment = $comments;
        } else if ($action === 'reject') {
            if (!$isAdmin) {
                echo json_encode(['success' => false, 'message' => 'Only department admins can reject applications.']);
                exit();
            }
            if (!in_array($currentStatus, ['Received', 'Submitted', 'For Review', 'Needs Correction'], true)) {
                echo json_encode(['success' => false, 'message' => 'Only active applications can be rejected.']);
                exit();
            }
            if ($comments === '') {
                echo json_encode(['success' => false, 'message' => 'A rejection reason is required.']);
                exit();
            }
            $nextStatus = 'Rejected';
            $defaultComment = 'Application rejected and automatically moved to the archive.';
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action.']);
            exit();
        }

        $finalComment = !empty($comments) ? $comments : $defaultComment;
        $operator = $_SESSION['username'] ?? 'System';

        // Begin Transaction
        $conn->beginTransaction();
        // Reject stale actions before any state change or verification side effect.
        $lock = $conn->prepare('SELECT workflow_state, is_archived FROM applications WHERE id_number = ? FOR UPDATE');
        $lock->execute([$appId]);
        $locked = $lock->fetch(PDO::FETCH_ASSOC);
        if (!$locked || !empty($locked['is_archived']) || ($locked['workflow_state'] ?: 'Received') !== $currentStatus) {
            $conn->rollBack();
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'This application changed while you were reviewing it. Reload it and try again.']);
            exit;
        }
        $oscaIdNo = null;

        // 1. Update applications table state
        if ($nextStatus === 'Rejected') {
            $archiveActor = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
            if ($archiveActor === '') $archiveActor = $operator;
            $stmtUpdate = $conn->prepare("UPDATE applications
                SET workflow_state = 'Rejected', status = 'rejected', is_archived = 1,
                    archived_at = NOW(), archived_by = ?
                WHERE id_number = ? AND COALESCE(is_archived, 0) = 0");
            $stmtUpdate->execute([$archiveActor, $appId]);
            if ($stmtUpdate->rowCount() !== 1) {
                throw new RuntimeException('The application is already archived or could not be rejected.');
            }
        } else {
            $stmtUpdate = $conn->prepare("UPDATE applications SET workflow_state = ?, return_reason = ? WHERE id_number = ?");
            $stmtUpdate->execute([$nextStatus, $nextStatus === 'Needs Correction' ? $finalComment : null, $appId]);
        }

        // ──────────────────────────────────────────────────────────────────────
        // Final-verification side effects
        // ──────────────────────────────────────────────────────────────────────

        // (B) BURIAL ASSISTANCE VERIFICATION → Mark deceased senior's status + log Landbank freeze
        if ($applicationType === 'burial' && $nextStatus === 'Verified') {
            // Update the deceased senior's record to 'deceased' status
            // Match by deceased name fields in the burial application
            $deceasedLastName  = $app['deceased_last_name']  ?? $app['lastName']  ?? '';
            $deceasedFirstName = $app['deceased_first_name'] ?? $app['firstName'] ?? '';
            $deceasedBirthDate = $app['deceased_birth_date'] ?? $app['birth_date'] ?? '';

            if (!empty($deceasedLastName) && !empty($deceasedFirstName)) {
                $stmtDeceased = $conn->prepare(
                    "UPDATE applications SET status = 'deceased', workflow_state = 'Deceased'
                     WHERE lastName = ? AND firstName = ?
                     AND (birth_date = ? OR ? = '')
                     AND application_type = 'senior'
                     AND workflow_state NOT IN ('Deceased')"
                );
                $stmtDeceased->execute([
                    $deceasedLastName,
                    $deceasedFirstName,
                    $deceasedBirthDate,
                    $deceasedBirthDate,
                ]);

                // Log a Landbank card freeze simulation in history
                $stmtFreeze = $conn->prepare(
                    "INSERT INTO application_history (application_id, previous_state, new_state, changed_by, comments)
                     VALUES (?, ?, ?, ?, ?)"
                );
                $stmtFreeze->execute([
                    $appId,
                    'Verified',
                    'Landbank-Card-Freeze-Logged',
                    $operator,
                    "SYSTEM ACTION: Deceased senior '{$deceasedFirstName} {$deceasedLastName}' profile updated to status=deceased. Landbank Blue Cash Card scheduled for deactivation to prevent unauthorized pension withdrawals post-mortem. (Pasig Ordinance 3-2026 §7c)"
                ]);
            }
        }

        // 2. Insert into history log
        $stmtHistory = $conn->prepare("INSERT INTO application_history (application_id, previous_state, new_state, changed_by, comments) VALUES (?, ?, ?, ?, ?)");
        $stmtHistory->execute([$appId, $currentStatus, $nextStatus, $operator, $finalComment]);

        if ($nextStatus === 'Rejected') {
            logAudit(
                $conn,
                'ARCHIVE_APPLICATION',
                "Rejected and archived application {$appId} ({$app['full_name']} - " . ucfirst($applicationType) . " in Barangay {$app['barangay']}). Reason: {$finalComment}"
            );
        }

        $conn->commit();

        // Build enhanced response with OSCA ID if newly generated
        $responseData = [
            'success'       => true,
            'message'        => $nextStatus === 'Rejected'
                ? 'Application rejected and moved to the Archive.'
                : "Application status updated to: $nextStatus",
            'current_status' => $nextStatus,
            // Backward-compatible response key for any older clients.
            'current_state'  => $nextStatus
        ];

        if ($applicationType === 'senior' && $nextStatus === 'Verified' && !empty($oscaIdNo)) {
            $responseData['osca_id_generated'] = $oscaIdNo;
            $responseData['message'] .= " | OSCA ID assigned: {$oscaIdNo}";
        }

        if ($applicationType === 'burial' && $nextStatus === 'Verified') {
            $responseData['message'] .= " | Deceased senior profile updated and Landbank card freeze logged.";
        }

        echo json_encode($responseData);
        exit();

    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log('Workflow update error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'The application could not be updated. Please try again.']);
        exit();
    }
} else {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}
?>
