<?php
session_start();
require_once '../includes/db_connect.php';

// Check if user is logged in and authorized
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['department_admin', 'super_admin'])) {
    header("Location: ../index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SENIORLINK — Verify Documents</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/department-sidebar.css?v=1.1">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        :root {
            --primary: #0f172a;
            --secondary: #1e3a5f;
            --accent: #2563eb;
            --success: #10b981;
            --warning: #f59e0b;
            --light: #f8fafc;
            --dark: #020617;
            --border: #374151;
            --gray: #94a3b8;
        }

        body {
            background-color: #f1f5f9;
            color: #334155;
            line-height: 1.6;
            height: 100vh;
            overflow: auto;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            padding: 30px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border);
        }

        .header h1 {
            color: var(--primary);
            font-size: 1.8rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 15px;
            background: white;
            border-radius: 25px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .user-avatar img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .user-details h2 {
            font-size: 14px;
            color: var(--primary);
        }

        .user-details p {
            color: var(--gray);
            font-size: 12px;
        }

        /* Queue sorting styling */
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            padding: 25px;
            margin-bottom: 30px;
        }

        .card h3 {
            font-size: 1.2rem;
            margin-bottom: 20px;
            color: #fff;
            background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%);
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 18px;
            border-radius: 10px;
            width: 100%;
        }

        .card h3 i {
            color: #fff;
        }

        /* Priority Queue badge */
        .priority-badge {
            background-color: #fef3c7;
            color: #d97706;
            border: 1px solid #fcd34d;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .priority-high-row {
            background-color: rgba(245, 158, 11, 0.04);
            border-left: 4px solid var(--warning) !important;
        }

        /* Table */
        .table-container {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th, .table td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.9rem;
        }

        .table th {
            background: #f8fafc;
            color: var(--primary);
            font-weight: 600;
        }

        .table tr:hover {
            background: #f1f5f9;
        }

        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        /* FSM Badges */
        .badge-received { background-color: #e2e8f0; color: #475569; }
        .badge-review { background-color: #dbeafe; color: #1d4ed8; }
        .badge-verified { background-color: #ccfbf1; color: #0f766e; }
        .badge-approved { background-color: #dcfce7; color: #15803d; }
        .badge-released { background-color: #f3e8ff; color: #6b21a8; }

        .btn {
            background: var(--secondary);
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            border: 1px solid transparent;
            cursor: pointer;
            font-size: 0.85rem;
            transition: background-color 0.2s, transform 0.2s, opacity 0.2s;
        }

        .btn:hover {
            background-color: #153860;
            opacity: 0.95;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--secondary);
            color: white;
            border-color: transparent;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 0.8rem;
        }

        /* Stepper progress */
        .stepper {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            padding: 20px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .step {
            flex: 1;
            text-align: center;
            position: relative;
        }

        .step::after {
            content: '';
            position: absolute;
            top: 15px;
            left: 50%;
            width: 100%;
            height: 3px;
            background-color: #cbd5e1;
            z-index: 1;
        }

        .step:last-child::after {
            display: none;
        }

        .step-circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: #cbd5e1;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px;
            font-weight: bold;
            position: relative;
            z-index: 2;
        }

        .step-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--gray);
        }

        .step.active .step-circle {
            background-color: var(--secondary);
            color: white;
        }

        .step.active .step-label {
            color: var(--secondary);
        }

        .step.completed .step-circle {
            background-color: var(--success);
            color: white;
        }

        .step.completed .step-label {
            color: var(--success);
        }

        /* Compliance engine results */
        .compliance-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 25px;
        }

        .compliance-title {
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 12px;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .compliance-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px dashed #e2e8f0;
            font-size: 0.9rem;
        }

        .compliance-item:last-child {
            border-bottom: none;
        }

        .pass-tag {
            color: var(--success);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .fail-tag {
            color: var(--accent);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* Audit history log */
        .timeline {
            margin-top: 20px;
            padding-left: 10px;
            border-left: 2px solid #e2e8f0;
        }

        .timeline-event {
            position: relative;
            padding-bottom: 20px;
            padding-left: 20px;
        }

        .timeline-event::before {
            content: '';
            position: absolute;
            left: -17px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: var(--secondary);
            border: 2px solid white;
        }

        .timeline-time {
            font-size: 0.75rem;
            color: var(--gray);
            margin-bottom: 4px;
        }

        .timeline-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--primary);
        }

        .timeline-desc {
            font-size: 0.85rem;
            color: #475569;
            margin-top: 4px;
            font-style: italic;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            overflow-y: auto;
            backdrop-filter: blur(4px);
        }

        .modal-content {
            background-color: white;
            margin: 40px auto;
            width: 95%;
            max-width: 950px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 30px;
            background-color: var(--light);
            border-bottom: 1px solid var(--border);
        }

        .modal-header h2 {
            color: var(--primary);
            font-size: 1.3rem;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--gray);
        }

        .close-modal:hover {
            color: var(--accent);
        }

        .modal-body {
            padding: 30px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .grid-modal {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 30px;
        }

        @media (max-width: 768px) {
            .grid-modal {
                grid-template-columns: 1fr;
            }
        }

        .image-placeholder {
            margin-top: 10px;
            width: 100%;
            height: 250px;
            border: 2px dashed var(--border);
            border-radius: 8px;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            background-color: #f8fafc;
        }

        .image-placeholder img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .fsm-actions {
            background-color: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            margin-bottom: 25px;
        }

        .comment-box {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 6px;
            margin-top: 10px;
            margin-bottom: 15px;
            font-size: 0.85rem;
            resize: vertical;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include '../partials/department_sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <div class="header-content">
                    <h1>Document Verification Terminal</h1>
                </div>
                <div class="header-actions">
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php
                                $profilePic = isset($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : 'default.jpg';
                                $profilePicPath = '../images/profile_pictures/' . $profilePic;
                                if (!file_exists($profilePicPath) || is_dir($profilePicPath)) {
                                    $profilePicPath = '../images/profile_pictures/default.jpg';
                                }
                            ?>
                            <img src="<?php echo $profilePicPath; ?>" alt="Profile Picture">
                        </div>
                        <div class="user-details">
                            <h2><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></h2>
                            <p><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $_SESSION['role']))); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Documents List (Prioritizing High Priority Queue) -->
            <div class="card">
                <h3><i class="fas fa-list-ol"></i> Application Review Queue</h3>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Priority</th>
                                <th>ID Number</th>
                                <th>Applicant Name</th>
                                <th>Type</th>
                                <th>Barangay</th>
                                <th>Date Submitted</th>
                                <th>Workflow State</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Priority sorting: high priority first, then normal. Ordered by date_submitted.
                            $sql = "SELECT id_number as id, full_name, application_type, barangay, date_submitted, status, workflow_state, priority_level 
                                    FROM applications 
                                    ORDER BY CASE WHEN priority_level = 'high' THEN 0 ELSE 1 END, date_submitted DESC";
                            $stmt = $conn->prepare($sql);
                            $stmt->execute();
                            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            if (count($result) === 0) {
                                echo "<tr><td colspan='8' style='text-align: center;'>No applications in the queue.</td></tr>";
                            }
                            
                            foreach ($result as $row) {
                                $isHigh = ($row['priority_level'] === 'high');
                                $rowClass = $isHigh ? 'priority-high-row' : '';
                                
                                $state = $row['workflow_state'] ?: 'Received';
                                $stateBadgeClass = 'badge-received';
                                if ($state === 'For Review') $stateBadgeClass = 'badge-review';
                                if ($state === 'Verified') $stateBadgeClass = 'badge-verified';
                                if ($state === 'Approved') $stateBadgeClass = 'badge-approved';
                                if ($state === 'Released') $stateBadgeClass = 'badge-released';

                                $typeLabel = '';
                                if ($row['application_type'] === 'pwd') $typeLabel = 'Disability Support';
                                if ($row['application_type'] === 'senior') $typeLabel = 'Senior Citizen ID';
                                if ($row['application_type'] === 'pension') $typeLabel = 'Local Social Pension';
                                if ($row['application_type'] === 'burial') $typeLabel = 'Burial Assistance';

                                echo "<tr class='$rowClass'>";
                                echo "<td>" . ($isHigh ? "<span class='priority-badge'><i class='fas fa-star'></i> HIGH</span>" : "<span style='color: #94a3b8; font-size: 0.8rem;'>Normal</span>") . "</td>";
                                echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
                                echo "<td>" . htmlspecialchars($typeLabel) . "</td>";
                                echo "<td>" . htmlspecialchars($row['barangay']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['date_submitted']) . "</td>";
                                echo "<td><span class='status-badge $stateBadgeClass'>" . htmlspecialchars($state) . "</span></td>";
                                echo "<td><button class='btn btn-small view-details-btn' data-id='" . htmlspecialchars($row['id']) . "'><i class='fas fa-folder-open'></i> Open Review</button></td>";
                                echo "</tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Application Detail Modal -->
    <div id="applicationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalAppTitle">Application Details</h2>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <!-- FSM Stepper -->
                <div class="stepper" id="fsmStepper">
                    <div class="step" id="step-Received"><div class="step-circle">1</div><div class="step-label">Received</div></div>
                    <div class="step" id="step-For-Review"><div class="step-circle">2</div><div class="step-label">For Review</div></div>
                    <div class="step" id="step-Verified"><div class="step-circle">3</div><div class="step-label">Verified</div></div>
                    <div class="step" id="step-Approved"><div class="step-circle">4</div><div class="step-label">Approved</div></div>
                    <div class="step" id="step-Released"><div class="step-circle">5</div><div class="step-label">Released</div></div>
                </div>

                <div class="grid-modal">
                    <!-- Left: Details & Documents -->
                    <div>
                        <!-- Compliance Card -->
                        <div class="compliance-card" id="complianceCard">
                            <div class="compliance-title"><i class="fas fa-shield-halved"></i> Localized Compliance Engine Audit</div>
                            <div id="complianceList"></div>
                        </div>

                        <!-- Info Sections -->
                        <div style="margin-bottom: 25px;">
                            <h3 style="font-size: 1rem; color: var(--primary); margin-bottom: 10px; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px;">Applicant Basic Details</h3>
                            <p style="font-size: 0.9rem; margin-bottom: 5px;"><strong>Full Name:</strong> <span id="infoName"></span></p>
                            <p style="font-size: 0.9rem; margin-bottom: 5px;"><strong>Birth Date / Age:</strong> <span id="infoBirth"></span></p>
                            <p style="font-size: 0.9rem; margin-bottom: 5px;"><strong>Contact Number:</strong> <span id="infoContact"></span></p>
                            <p style="font-size: 0.9rem; margin-bottom: 5px;"><strong>Home Address:</strong> <span id="infoAddress"></span></p>
                            <p style="font-size: 0.9rem; margin-bottom: 5px;"><strong>Barangay:</strong> <span id="infoBarangay"></span></p>
                        </div>

                        <div id="dynamicDetailsSection" style="margin-bottom: 25px;">
                            <!-- Will be filled dynamically -->
                        </div>

                        <div style="margin-bottom: 25px;">
                            <h3 style="font-size: 1rem; color: var(--primary); margin-bottom: 10px; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px;">Required Documents Scans</h3>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <div>
                                    <p style="font-size: 0.85rem; font-weight: 600;">Proof of Address</p>
                                    <div class="image-placeholder" id="previewProof"></div>
                                </div>
                                <div>
                                    <p style="font-size: 0.85rem; font-weight: 600;">ID Image / Support Photo</p>
                                    <div class="image-placeholder" id="previewIdImage"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: FSM Actions & History Trail -->
                    <div>
                        <!-- FSM Operations -->
                        <div class="fsm-actions">
                            <h4 style="font-size: 0.95rem; margin-bottom: 15px; color: var(--primary);"><i class="fas fa-sliders"></i> Workflow Control Actions</h4>
                            
                            <div id="fsmInstructions" style="font-size: 0.85rem; margin-bottom: 12px; color: var(--gray);"></div>

                            <textarea id="fsmComment" class="comment-box" placeholder="Write transition details or reason for return/blurry scan here..."></textarea>
                            
                            <div style="display: flex; gap: 10px;">
                                <button type="button" class="btn" id="btnNextState" onclick="submitFsmTransition('next')">Advance State</button>
                                <button type="button" class="btn btn-secondary" id="btnReturnState" onclick="submitFsmTransition('return')">Return to Barangay</button>
                            </div>
                        </div>

                        <!-- History Timeline -->
                        <div>
                            <h4 style="font-size: 0.95rem; margin-bottom: 15px; color: var(--primary);"><i class="fas fa-history"></i> Audit Trail History Log</h4>
                            <div class="timeline" id="timelineList"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/sidebar-toggle.js"></script>
    <script>
        let currentAppId = null;
        let currentWorkflowState = 'Received';

        document.addEventListener('DOMContentLoaded', function() {
            // Setup View Details button event listeners
            document.querySelectorAll('.view-details-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const appId = this.dataset.id;
                    openApplicationModal(appId);
                });
            });

            // Modal Close Handler
            document.querySelector('.close-modal').addEventListener('click', () => {
                document.getElementById('applicationModal').style.display = 'none';
            });
        });

        // Helper: Calculate working days (excluding Saturdays and Sundays)
        function calculateWorkingDays(startDateVal) {
            if (!startDateVal) return 0;
            const start = new Date(startDateVal);
            const end = new Date();
            if (start > end) return 0;
            
            let workingDays = 0;
            let curDate = new Date(start.getTime());
            while (curDate < end) {
                const day = curDate.getDay();
                if (day !== 0 && day !== 6) { // Exclude Sat/Sun
                    workingDays++;
                }
                curDate.setDate(curDate.getDate() + 1);
            }
            return workingDays;
        }

        async function openApplicationModal(appId) {
            currentAppId = appId;
            document.getElementById('applicationModal').style.display = 'block';
            
            // Clean modal
            document.getElementById('fsmComment').value = "";
            document.getElementById('complianceList').innerHTML = "";
            document.getElementById('dynamicDetailsSection').innerHTML = "";
            document.getElementById('timelineList').innerHTML = "";

            try {
                const response = await fetch(`../api/get_application_details.php?id=${encodeURIComponent(appId)}`);
                const app = await response.json();

                if (app.error) {
                    alert("Error: " + app.error);
                    return;
                }

                currentWorkflowState = app.workflow_state || 'Received';
                document.getElementById('modalAppTitle').textContent = `Reviewing: ${app.full_name} (${app.id_number})`;

                // Render stepper
                const steps = ['Received', 'For Review', 'Verified', 'Approved', 'Released'];
                let currentStepIndex = steps.indexOf(currentWorkflowState);
                if (currentStepIndex === -1) currentStepIndex = 0;

                steps.forEach((step, idx) => {
                    const stepId = 'step-' + step.replace(' ', '-');
                    const element = document.getElementById(stepId);
                    if (element) {
                        element.className = 'step';
                        if (idx < currentStepIndex) {
                            element.classList.add('completed');
                        } else if (idx === currentStepIndex) {
                            element.classList.add('active');
                        }
                    }
                });

                // Populate Basic Info
                document.getElementById('infoName').innerHTML = `<strong>${app.lastName}, ${app.firstName} ${app.middleName || ''} ${app.suffix || ''}</strong>`;
                
                // Calculate Age
                const birth = new Date(app.birth_date);
                const today = new Date();
                let age = today.getFullYear() - birth.getFullYear();
                const m = today.getMonth() - birth.getMonth();
                if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) {
                    age--;
                }
                document.getElementById('infoBirth').textContent = `${app.birth_date} (${age} years old)`;
                document.getElementById('infoContact').textContent = app.contact_number;
                document.getElementById('infoAddress').textContent = app.complete_address;
                document.getElementById('infoBarangay').textContent = app.barangay;

                // Compliance Checks
                let complianceHtml = "";
                
                // 1. Age Check (All types except PWD)
                if (app.application_type !== 'pwd') {
                    if (age >= 60) {
                        complianceHtml += `
                            <div class="compliance-item">
                                <span>Age Compliance (Senior citizen check)</span>
                                <span class="pass-tag"><i class="fas fa-circle-check"></i> PASS: Age ${age} >= 60</span>
                            </div>`;
                    } else {
                        complianceHtml += `
                            <div class="compliance-item">
                                <span>Age Compliance (Senior citizen check)</span>
                                <span class="fail-tag"><i class="fas fa-circle-xmark"></i> FAIL: Age ${age} is under 60</span>
                            </div>`;
                    }
                } else {
                    complianceHtml += `
                        <div class="compliance-item">
                            <span>Age Compliance (Disability support check)</span>
                            <span class="pass-tag" style="color: #3498db;"><i class="fas fa-info-circle"></i> No senior restriction</span>
                        </div>`;
                }

                // 2. Pension checks
                if (app.application_type === 'pension') {
                    const pAmount = parseFloat(app.pension_amount);
                    if (pAmount <= 4000) {
                        complianceHtml += `
                            <div class="compliance-item">
                                <span>SSS Pension Limit Check (Pasig Ord. 17/2025)</span>
                                <span class="pass-tag"><i class="fas fa-circle-check"></i> PASS: Pension P${pAmount.toFixed(2)} <= P4,000</span>
                            </div>`;
                    } else {
                        complianceHtml += `
                            <div class="compliance-item">
                                <span>SSS Pension Limit Check (Pasig Ord. 17/2025)</span>
                                <span class="fail-tag"><i class="fas fa-circle-xmark"></i> FAIL: Pension P${pAmount.toFixed(2)} exceeds P4,000</span>
                            </div>`;
                    }
                }

                // 3. Burial Check
                if (app.application_type === 'burial') {
                    const elapsedDays = calculateWorkingDays(app.date_of_death);
                    if (elapsedDays <= 30) {
                        complianceHtml += `
                            <div class="compliance-item">
                                <span>Filing Deadline Check (Pasig Ord. 3/2026)</span>
                                <span class="pass-tag"><i class="fas fa-circle-check"></i> PASS: Filed in ${elapsedDays} working days</span>
                            </div>`;
                    } else {
                        complianceHtml += `
                            <div class="compliance-item">
                                <span>Filing Deadline Check (Pasig Ord. 3/2026)</span>
                                <span class="fail-tag"><i class="fas fa-circle-xmark"></i> FAIL: Outside 30-working-day limit (${elapsedDays} days elapsed)</span>
                            </div>`;
                    }
                }

                // 4. Priority status display
                if (app.priority_level === 'high') {
                    complianceHtml += `
                        <div class="compliance-item" style="background-color: rgba(245, 158, 11, 0.08); padding: 5px; border-radius: 4px;">
                            <span>Priority Queue Placement</span>
                            <span style="color: #d97706; font-weight: 700;"><i class="fas fa-star"></i> High-Priority (Bedridden Senior)</span>
                        </div>`;
                }

                document.getElementById('complianceList').innerHTML = complianceHtml;

                // Dynamic type details section
                let dynamicHtml = "";
                if (app.application_type === 'pwd') {
                    dynamicHtml = `
                        <h3 style="font-size: 1rem; color: var(--primary); margin-bottom: 10px; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px;">Disability Support Details</h3>
                        <p style="font-size: 0.9rem; margin-bottom: 5px;"><strong>Disability Type:</strong> ${app.disability_type || 'None selected'}</p>
                    `;
                } else if (app.application_type === 'pension') {
                    dynamicHtml = `
                        <h3 style="font-size: 1rem; color: var(--primary); margin-bottom: 10px; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px;">Social Pension Information</h3>
                        <p style="font-size: 0.9rem; margin-bottom: 5px;"><strong>SSS Number:</strong> ${app.sss_number || 'N/A'}</p>
                        <p style="font-size: 0.9rem; margin-bottom: 5px;"><strong>Monthly SSS Pension:</strong> PHP ${parseFloat(app.pension_amount || 0).toFixed(2)}</p>
                    `;
                } else if (app.application_type === 'burial') {
                    dynamicHtml = `
                        <h3 style="font-size: 1rem; color: var(--primary); margin-bottom: 10px; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px;">Burial Assistance Details</h3>
                        <p style="font-size: 0.9rem; margin-bottom: 5px;"><strong>Date of Passing:</strong> ${app.date_of_death || 'N/A'}</p>
                        <p style="font-size: 0.9rem; margin-bottom: 5px;"><strong>Relationship to Deceased:</strong> ${app.relationship_to_deceased || 'N/A'}</p>
                    `;
                }
                
                if (app.is_proxy_application == 1) {
                    dynamicHtml += `
                        <div style="margin-top: 15px; border-top: 1px dashed var(--border); padding-top: 10px;">
                            <p style="font-size: 0.9rem; margin-bottom: 5px;"><strong>Proxy pre-registration Token:</strong> ${app.proxy_token || 'N/A'}</p>
                            <p style="font-size: 0.9rem; margin-bottom: 5px;"><strong>Proxy Relative's Name:</strong> ${app.proxy_name || 'N/A'}</p>
                            <p style="font-size: 0.9rem; margin-bottom: 5px;"><strong>Proxy Relationship:</strong> ${app.proxy_relationship || 'N/A'}</p>
                        </div>
                    `;
                }
                document.getElementById('dynamicDetailsSection').innerHTML = dynamicHtml;

                // Setup Document Image Previews
                document.getElementById('previewProof').innerHTML = app.has_proof_of_address 
                    ? `<img src="../api/get_document.php?id=${appId}&doc_type=proof_of_address" alt="Proof of Address">` 
                    : '<p style="color: var(--gray); font-size: 0.8rem;">No file uploaded.</p>';
                
                document.getElementById('previewIdImage').innerHTML = app.has_id_image 
                    ? `<img src="../api/get_document.php?id=${appId}&doc_type=id_image" alt="ID Document Photo">` 
                    : '<p style="color: var(--gray); font-size: 0.8rem;">No file uploaded.</p>';

                // FSM Operation controls
                const btnNext = document.getElementById('btnNextState');
                const btnReturn = document.getElementById('btnReturnState');
                const instruction = document.getElementById('fsmInstructions');

                // Toggle actions based on current FSM State
                btnReturn.style.display = 'block'; // standard display
                btnNext.style.display = 'block';

                if (currentWorkflowState === 'Received') {
                    instruction.innerHTML = "<strong>State Action:</strong> Forward this application to the department desk for detailed evaluation.";
                    btnNext.textContent = "Forward to Review Desk";
                    btnReturn.style.display = 'none'; // cannot return from initial
                } else if (currentWorkflowState === 'For Review') {
                    instruction.innerHTML = "<strong>State Action:</strong> Mark document audits as Verified and lock the compliance records.";
                    btnNext.textContent = "Verify Application";
                } else if (currentWorkflowState === 'Verified') {
                    instruction.innerHTML = "<strong>State Action:</strong> Authorize senior credentials and approve for payroll distribution.";
                    btnNext.textContent = "Approve for Payroll";
                } else if (currentWorkflowState === 'Approved') {
                    instruction.innerHTML = "<strong>State Action:</strong> Finalize payroll check and mark credentials as Released.";
                    btnNext.textContent = "Release Benefits";
                } else if (currentWorkflowState === 'Released') {
                    instruction.innerHTML = "<strong>State Action:</strong> The workflow has completed. Benefits are released to the citizen.";
                    btnNext.style.display = 'none';
                    btnReturn.style.display = 'none';
                }

                // Render Timeline Audit Trail
                let timelineHtml = "";
                if (app.history && app.history.length > 0) {
                    app.history.forEach(log => {
                        timelineHtml += `
                            <div class="timeline-event">
                                <div class="timeline-time">${log.changed_at}</div>
                                <div class="timeline-title">${log.previous_state} &rarr; ${log.new_state} (by ${log.changed_by})</div>
                                <div class="timeline-desc">${log.comments || 'No comments.'}</div>
                            </div>`;
                    });
                } else {
                    timelineHtml = "<p style='color: var(--gray); font-size: 0.85rem; font-style: italic;'>No transitions logged.</p>";
                }
                document.getElementById('timelineList').innerHTML = timelineHtml;

            } catch (error) {
                console.error("Error opening modal:", error);
                alert("Failed to load application details.");
            }
        }

        async function submitFsmTransition(action) {
            const comment = document.getElementById('fsmComment').value.trim();

            if (action === 'return' && !comment) {
                alert("Please add comment details explaining why this application is being returned to the Barangay (e.g. Blurry Documents).");
                return;
            }

            if (!confirm(`Are you sure you want to trigger this transition?`)) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('applicationId', currentAppId);
                formData.append('action', action);
                formData.append('comments', comment);

                const response = await fetch('../api/update_fsm_state.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();

                if (result.success) {
                    alert(result.message);
                    // Reload modal with new details
                    openApplicationModal(currentAppId);
                    
                    // Reload table queue in background
                    location.reload();
                } else {
                    alert("Transition Error: " + result.message);
                }
            } catch (error) {
                console.error(error);
                alert("Connection error during state transition.");
            }
        }
    </script>
</body>
</html>
