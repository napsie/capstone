<?php
session_start();
require_once '../includes/db_connect.php';
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

/**
 * Calculate working days (Mon–Fri only) from a given date to today.
 */
function countWorkingDays(string $startDate): int {
    $start = new DateTime($startDate);
    $end   = new DateTime();
    if ($start > $end) return 0;
    $days = 0;
    $cur  = clone $start;
    while ($cur <= $end) {
        $dow = (int)$cur->format('N');
        if ($dow < 6) $days++;
        $cur->modify('+1 day');
    }
    return $days;
}

/** Generate an official OSCA ID not already present in any application. */
function generateUniqueSeniorId(PDO $conn): string {
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $candidate = 'OSCA-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $stmt = $conn->prepare('SELECT 1 FROM applications WHERE senior_id_no = ? LIMIT 1');
        $stmt->execute([$candidate]);
        if (!$stmt->fetchColumn()) return $candidate;
    }

    throw new RuntimeException('Unable to generate a unique OSCA ID.');
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $appId    = sanitize_str($_POST['applicationId'] ?? null);
    $action   = sanitize_str($_POST['action']         ?? null); // 'next' or 'return'
    $comments = sanitize_str($_POST['comments']       ?? '') ?? '';

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

        $currentStatus  = $app['workflow_state'] ?: 'Received';
        $applicationType = $app['application_type'] ?? '';
        $nextStatus     = $currentStatus;

        // Direct, rule-based status handling.
        if ($action === 'next') {
            // Staff can only move from initial states
            if ($isStaff && !in_array($currentStatus, ['Received', 'Submitted'], true)) {
                echo json_encode(['success' => false, 'message' => 'Barangay staff can only submit newly received applications for department review.']);
                exit();
            }

            if (in_array($currentStatus, ['Submitted', 'Received'], true)) {
                $nextStatus = 'For Review';
            } elseif ($currentStatus === 'For Review') {
                $nextStatus = 'Verified';
            } elseif ($currentStatus === 'Verified') {
                $nextStatus = 'Approved';
            } elseif ($currentStatus === 'Approved') {
                $nextStatus = 'Released';
            } elseif ($currentStatus === 'Released') {
                echo json_encode(['success' => false, 'message' => 'Application is already finalized and released.']);
                exit();
            } else {
                $nextStatus = 'Received';
            }

            // ──────────────────────────────────────────────────────────────────
            // PDF POLICY ENFORCEMENT: Verified → Approved transition checks
            // ──────────────────────────────────────────────────────────────────
            if ($currentStatus === 'Verified' && $nextStatus === 'Approved') {

                // ── (A) LOCAL SOCIAL PENSION: Ordinance 17-2025 checks ──────────
                if ($applicationType === 'pension') {
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
                         AND workflow_state IN ('Approved','Released')
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
                    if (!empty($dateOfDeath)) {
                        $elapsedWorkingDays = countWorkingDays($dateOfDeath);
                        if ($elapsedWorkingDays > 30) {
                            echo json_encode([
                                'success' => false,
                                'message' => "APPLICATION BLOCKED — POLICY VIOLATION: SUBMITTED BEYOND THE 30-DAY LIMIT — Burial assistance must be filed within 30 working days of death registration. Elapsed: {$elapsedWorkingDays} working days (Pasig Ordinance 3-2026)."
                            ]);
                            exit();
                        }
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
            if ($currentStatus === 'Received') {
                echo json_encode(['success' => false, 'message' => 'Application is already in the Received state.']);
                exit();
            }
            $nextStatus = 'Received';
            $defaultComment = "Returned to Received state for document correction / rescan.";
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action.']);
            exit();
        }

        $finalComment = !empty($comments) ? $comments : $defaultComment;
        $operator = $_SESSION['username'] ?? 'System';

        // Begin Transaction
        $conn->beginTransaction();
        $oscaIdNo = null;

        // 1. Update applications table state
        $stmtUpdate = $conn->prepare("UPDATE applications SET workflow_state = ? WHERE id_number = ?");
        $stmtUpdate->execute([$nextStatus, $appId]);

        // ──────────────────────────────────────────────────────────────────────
        // PDF SIDE EFFECTS on specific approvals
        // ──────────────────────────────────────────────────────────────────────

        // (A) SENIOR ID APPROVAL → Auto-generate OSCA ID number if not yet set
        if ($applicationType === 'senior' && $nextStatus === 'Approved') {
            $existingOscaId = $app['senior_id_no'] ?? '';
            if (empty($existingOscaId)) {
                $oscaIdNo = generateUniqueSeniorId($conn);
                $stmtOsca = $conn->prepare("UPDATE applications SET senior_id_no = ? WHERE id_number = ?");
                $stmtOsca->execute([$oscaIdNo, $appId]);
                $finalComment .= " | OSCA ID auto-generated: {$oscaIdNo}";
            }
        }

        // (B) BURIAL ASSISTANCE APPROVAL → Mark deceased senior's status + log Landbank freeze
        if ($applicationType === 'burial' && $nextStatus === 'Approved') {
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
                    'Approved',
                    'Landbank-Card-Freeze-Logged',
                    $operator,
                    "SYSTEM ACTION: Deceased senior '{$deceasedFirstName} {$deceasedLastName}' profile updated to status=deceased. Landbank Blue Cash Card scheduled for deactivation to prevent unauthorized pension withdrawals post-mortem. (Pasig Ordinance 3-2026 §7c)"
                ]);
            }
        }

        // 2. Insert into history log
        $stmtHistory = $conn->prepare("INSERT INTO application_history (application_id, previous_state, new_state, changed_by, comments) VALUES (?, ?, ?, ?, ?)");
        $stmtHistory->execute([$appId, $currentStatus, $nextStatus, $operator, $finalComment]);

        $conn->commit();

        // Build enhanced response with OSCA ID if newly generated
        $responseData = [
            'success'       => true,
            'message'        => "Application status updated to: $nextStatus",
            'current_status' => $nextStatus,
            // Backward-compatible response key for any older clients.
            'current_state'  => $nextStatus
        ];

        if ($applicationType === 'senior' && $nextStatus === 'Approved' && !empty($oscaIdNo)) {
            $responseData['osca_id_generated'] = $oscaIdNo;
            $responseData['message'] .= " | OSCA ID assigned: {$oscaIdNo}";
        }

        if ($applicationType === 'burial' && $nextStatus === 'Approved') {
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

