<?php
session_start();
require_once '../includes/db_connect.php';

// Auth check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['barangay_staff', 'department_admin', 'super_admin'])) {
    http_response_code(401);
    echo '<h2>Unauthorized. Please <a href="../index.php">log in</a>.</h2>';
    exit();
}

$userRole     = $_SESSION['role'];
$userBarangay = $_SESSION['barangay'] ?? null;
$appId        = trim($_GET['id'] ?? '');

if (empty($appId)) {
    echo '<h2>Error: No application ID provided.</h2>';
    exit();
}

// Fetch application with barangay isolation for staff
try {
    if ($userRole === 'barangay_staff' && $userBarangay) {
        $stmt = $conn->prepare("SELECT * FROM applications WHERE id_number = ? AND barangay = ?");
        $stmt->execute([$appId, $userBarangay]);
    } else {
        $stmt = $conn->prepare("SELECT * FROM applications WHERE id_number = ?");
        $stmt->execute([$appId]);
    }
    $app = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$app) {
        echo '<h2>Error: Application not found or access denied.</h2>';
        exit();
    }

    // Fetch FSM audit history
    $stmtH = $conn->prepare("SELECT previous_state, new_state, changed_by, changed_at, comments
                              FROM application_history
                              WHERE application_id = ?
                              ORDER BY changed_at ASC");
    $stmtH->execute([$appId]);
    $history = $stmtH->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo '<h2>Database error: ' . htmlspecialchars($e->getMessage()) . '</h2>';
    exit();
}

// Helpers
function fmtDate(?string $d): string {
    if (!$d) return '—';
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt ? $dt->format('F j, Y') : htmlspecialchars($d);
}
function fmtDateTime(?string $d): string {
    if (!$d) return '—';
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $d);
    return $dt ? $dt->format('F j, Y g:i A') : htmlspecialchars($d);
}
function appTypeLabel(string $t): string {
    return match($t) {
        'pwd'    => 'PWD (Person with Disability)',
        'senior' => 'Senior Citizen ID Card',
        'pension'=> 'Local Social Pension',
        'burial' => 'Burial Assistance',
        default  => htmlspecialchars($t),
    };
}
$state       = $app['workflow_state'] ?? 'Received';
$stateColors = [
    'Received'   => '#64748b',
    'For Review' => '#1d4ed8',
    'Verified'   => '#0f766e',
    'Approved'   => '#15803d',
    'Released'   => '#6b21a8',
];
$stateColor = $stateColors[$state] ?? '#333';
$exportedBy = htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']);
$exportedAt = date('F j, Y g:i A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export — <?php echo htmlspecialchars($app['full_name']); ?> (<?php echo htmlspecialchars($appId); ?>)</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', Arial, sans-serif;
            font-size: 13px;
            color: #1e293b;
            background: #f1f5f9;
            padding: 20px;
        }

        .print-btn-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .print-btn-bar button {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-print  { background: #1d4ed8; color: white; }
        .btn-close  { background: #64748b; color: white; }

        .document {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 24px rgba(0,0,0,.12);
            overflow: hidden;
        }

        .doc-header {
            background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
            color: white;
            padding: 28px 30px 22px;
        }

        .doc-header .system-name {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            opacity: 0.8;
            margin-bottom: 4px;
        }

        .doc-header h1 {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .doc-header .meta {
            font-size: 12px;
            opacity: 0.75;
        }

        .state-banner {
            padding: 10px 30px;
            font-size: 13px;
            font-weight: 700;
            color: white;
            background: <?php echo $stateColor; ?>;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .doc-body { padding: 28px 30px; }

        .section { margin-bottom: 26px; }

        .section-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #64748b;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 6px;
            margin-bottom: 14px;
        }

        .field-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px 20px;
        }

        .field-item label {
            display: block;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #94a3b8;
            margin-bottom: 3px;
        }

        .field-item span {
            font-size: 13.5px;
            font-weight: 500;
            color: #1e293b;
        }

        .field-full { grid-column: 1 / -1; }

        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        .badge-priority-high { background: #fef3c7; color: #d97706; border: 1px solid #fcd34d; }
        .badge-priority-norm { background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; }

        /* Timeline */
        .timeline { border-left: 3px solid #e2e8f0; padding-left: 18px; }

        .tl-event {
            position: relative;
            padding-bottom: 18px;
        }

        .tl-event::before {
            content: '';
            position: absolute;
            left: -23px;
            top: 5px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #1d4ed8;
            border: 2px solid white;
            outline: 2px solid #1d4ed8;
        }

        .tl-time  { font-size: 11px; color: #94a3b8; margin-bottom: 2px; }
        .tl-title { font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 2px; }
        .tl-desc  { font-size: 12px; color: #475569; font-style: italic; }

        .doc-images {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .doc-image-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
            text-align: center;
        }

        .doc-image-card .card-label {
            padding: 8px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            background: #f8fafc;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
        }

        .doc-image-card img {
            max-width: 100%;
            max-height: 220px;
            object-fit: contain;
            display: block;
            margin: 0 auto;
            padding: 8px;
        }

        .doc-image-card .no-doc {
            padding: 30px;
            color: #cbd5e1;
            font-size: 12px;
        }

        .doc-footer {
            border-top: 1px solid #e2e8f0;
            padding: 16px 30px;
            background: #f8fafc;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11px;
            color: #94a3b8;
        }

        .signature-area {
            margin-top: 36px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
        }

        .sig-box {
            border-top: 1px solid #1e293b;
            padding-top: 8px;
        }

        .sig-box .sig-label { font-size: 11px; color: #64748b; }
        .sig-box .sig-name  { font-size: 13px; font-weight: 700; color: #1e293b; }

        @media print {
            body { background: white; padding: 0; }
            .print-btn-bar { display: none; }
            .document { box-shadow: none; border-radius: 0; }
        }
    </style>
</head>
<body>

<div class="print-btn-bar">
    <button class="btn-print" onclick="window.print()">🖨️ Print / Save as PDF</button>
    <button class="btn-close" onclick="window.close()">✕ Close</button>
</div>

<div class="document">
    <!-- Header -->
    <div class="doc-header">
        <div class="system-name">CPRAS — Centralized Profiling and Record Authentication System</div>
        <h1><?php echo htmlspecialchars($app['full_name']); ?></h1>
        <div class="meta">
            Application ID: <strong><?php echo htmlspecialchars($appId); ?></strong> &nbsp;|&nbsp;
            Barangay: <strong><?php echo htmlspecialchars($app['barangay']); ?></strong> &nbsp;|&nbsp;
            Submitted: <strong><?php echo fmtDateTime($app['date_submitted']); ?></strong>
        </div>
    </div>

    <!-- State Banner -->
    <div class="state-banner">
        &#9679; Current Status: <?php echo htmlspecialchars($state); ?>
        &nbsp;|&nbsp;
        Priority: <?php echo ucfirst($app['priority_level'] ?? 'normal'); ?>
    </div>

    <div class="doc-body">

        <!-- Basic Information -->
        <div class="section">
            <div class="section-title">Basic Information</div>
            <div class="field-grid">
                <div class="field-item">
                    <label>Last Name</label>
                    <span><?php echo htmlspecialchars($app['lastName'] ?? '—'); ?></span>
                </div>
                <div class="field-item">
                    <label>First Name</label>
                    <span><?php echo htmlspecialchars($app['firstName'] ?? '—'); ?></span>
                </div>
                <div class="field-item">
                    <label>Middle Name</label>
                    <span><?php echo htmlspecialchars($app['middleName'] ?? '—'); ?></span>
                </div>
                <div class="field-item">
                    <label>Suffix</label>
                    <span><?php echo htmlspecialchars($app['suffix'] ?: '—'); ?></span>
                </div>
                <div class="field-item">
                    <label>Application Type</label>
                    <span><?php echo appTypeLabel($app['application_type']); ?></span>
                </div>
                <div class="field-item">
                    <label>Date of Birth</label>
                    <span><?php echo fmtDate($app['birth_date']); ?></span>
                </div>
                <div class="field-item">
                    <label>Contact Number</label>
                    <span><?php echo htmlspecialchars($app['contact_number'] ?? '—'); ?></span>
                </div>
                <div class="field-item">
                    <label>Priority Level</label>
                    <span>
                        <?php if (($app['priority_level'] ?? 'normal') === 'high'): ?>
                            <span class="badge badge-priority-high">⭐ HIGH</span>
                        <?php else: ?>
                            <span class="badge badge-priority-norm">Normal</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="field-item field-full">
                    <label>Complete Address</label>
                    <span><?php echo htmlspecialchars($app['complete_address'] ?? '—'); ?></span>
                </div>
                <div class="field-item">
                    <label>Emergency Contact Name</label>
                    <span><?php echo htmlspecialchars($app['emergency_contact_name'] ?? '—'); ?></span>
                </div>
                <div class="field-item">
                    <label>Emergency Contact Number</label>
                    <span><?php echo htmlspecialchars($app['emergency_contact'] ?? '—'); ?></span>
                </div>
            </div>
        </div>

        <?php if ($app['application_type'] === 'pwd' && !empty($app['disability_type'])): ?>
        <!-- PWD Info -->
        <div class="section">
            <div class="section-title">PWD — Disability Type(s)</div>
            <div class="field-grid">
                <div class="field-item field-full">
                    <label>Disability</label>
                    <span><?php echo htmlspecialchars($app['disability_type']); ?></span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($app['application_type'] === 'pension'): ?>
        <!-- Pension Info -->
        <div class="section">
            <div class="section-title">Social Pension Details</div>
            <div class="field-grid">
                <div class="field-item">
                    <label>SSS Number</label>
                    <span><?php echo htmlspecialchars($app['sss_number'] ?? '—'); ?></span>
                </div>
                <div class="field-item">
                    <label>Verified Monthly Pension</label>
                    <span>P<?php echo number_format((float)($app['pension_amount'] ?? 0), 2); ?></span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($app['application_type'] === 'burial'): ?>
        <!-- Burial Info -->
        <div class="section">
            <div class="section-title">Burial Assistance Details</div>
            <div class="field-grid">
                <div class="field-item">
                    <label>Date of Passing</label>
                    <span><?php echo fmtDate($app['date_of_death']); ?></span>
                </div>
                <div class="field-item">
                    <label>Claimant Relationship</label>
                    <span><?php echo htmlspecialchars($app['relationship_to_deceased'] ?? '—'); ?></span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($app['proxy_name'])): ?>
        <!-- Proxy Info -->
        <div class="section">
            <div class="section-title">Proxy Representative</div>
            <div class="field-grid">
                <div class="field-item">
                    <label>Proxy Name</label>
                    <span><?php echo htmlspecialchars($app['proxy_name']); ?></span>
                </div>
                <div class="field-item">
                    <label>Relationship to Applicant</label>
                    <span><?php echo htmlspecialchars($app['proxy_relationship'] ?? '—'); ?></span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Documents -->
        <div class="section">
            <div class="section-title">Submitted Documents</div>
            <div class="doc-images">
                <div class="doc-image-card">
                    <div class="card-label">Proof of Address</div>
                    <?php if (!empty($app['proof_of_address'])): ?>
                        <?php if (str_starts_with($app['proof_of_address_type'] ?? '', 'image/')): ?>
                            <img src="../api/get_document.php?id=<?php echo urlencode($appId); ?>&doc_type=proof_of_address" alt="Proof of Address">
                        <?php else: ?>
                            <div class="no-doc">📄 PDF document on file</div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="no-doc">No document uploaded</div>
                    <?php endif; ?>
                </div>
                <div class="doc-image-card">
                    <div class="card-label">ID Image</div>
                    <?php if (!empty($app['id_image'])): ?>
                        <?php if (str_starts_with($app['id_image_type'] ?? '', 'image/')): ?>
                            <img src="../api/get_document.php?id=<?php echo urlencode($appId); ?>&doc_type=id_image" alt="ID Image">
                        <?php else: ?>
                            <div class="no-doc">📄 PDF document on file</div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="no-doc">No document uploaded</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- FSM Audit Trail -->
        <div class="section">
            <div class="section-title">Workflow Audit Trail</div>
            <?php if (empty($history)): ?>
                <p style="color:#94a3b8; font-style:italic; font-size:12px;">No workflow transitions recorded.</p>
            <?php else: ?>
                <div class="timeline">
                    <?php foreach ($history as $log): ?>
                    <div class="tl-event">
                        <div class="tl-time"><?php echo fmtDateTime($log['changed_at']); ?></div>
                        <div class="tl-title">
                            <?php echo htmlspecialchars($log['previous_state']); ?>
                            &rarr;
                            <?php echo htmlspecialchars($log['new_state']); ?>
                            <span style="color:#64748b; font-weight:400;">(by <?php echo htmlspecialchars($log['changed_by']); ?>)</span>
                        </div>
                        <div class="tl-desc"><?php echo htmlspecialchars($log['comments'] ?? 'No comment.'); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Signature Block -->
        <div class="signature-area">
            <div class="sig-box">
                <div class="sig-label">Prepared / Exported by</div>
                <div class="sig-name"><?php echo $exportedBy; ?></div>
                <div style="font-size:11px; color:#94a3b8; margin-top:2px;"><?php echo $exportedAt; ?></div>
            </div>
            <div class="sig-box">
                <div class="sig-label">Authorized Department Reviewer</div>
                <div class="sig-name">&nbsp;</div>
                <div style="font-size:11px; color:#94a3b8; margin-top:2px;">Signature over Printed Name</div>
            </div>
        </div>

    </div><!-- /.doc-body -->

    <div class="doc-footer">
        <span>CPRAS &copy; <?php echo date('Y'); ?> — Barangay <?php echo htmlspecialchars($app['barangay']); ?></span>
        <span>Document ID: <?php echo htmlspecialchars($appId); ?> | Exported: <?php echo $exportedAt; ?></span>
    </div>
</div>

<script>
    // Auto-trigger print dialog when page loads for seamless export UX
    window.addEventListener('load', function() {
        // Small delay to ensure images are loaded
        setTimeout(function() {
            // Only auto-print if this was opened as a popup/tab from the app
            if (window.opener || document.referrer) {
                window.print();
            }
        }, 800);
    });
</script>
</body>
</html>
