<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/application_types.php';

// Check if the user is logged in and has the correct role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'barangay_staff') {
    header('Location: ../index.php');
    exit;
}

$loggedInBarangay = htmlspecialchars($_SESSION['barangay'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CPRAS Dashboard - Barangay <?php echo $loggedInBarangay; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/barangay-sidebar.css?v=1.1">
    <link rel="stylesheet" href="../assets/css/main-dark-mode.css?v=1.1">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .image-placeholder {
            margin-top: 10px;
            width: 100%;
            height: 200px;
            border: 2px dashed #ccc;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }

        .image-placeholder img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

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
            border-left: 4px solid #f59e0b !important;
        }

        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        /* Layout fixes for submit application page */
        .container {
            width: 100%;
            min-width: 0;
        }

        .main-content {
            padding: 20px;
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            box-sizing: border-box;
            min-width: 0;
        }

        .applications-table {
            width: 100%;
            margin: 0 0 30px;
            padding: 20px 18px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
            overflow: visible;
        }

        .table-header {
            flex-wrap: wrap;
            gap: 10px;
            justify-content: space-between;
        }

        .table-controls {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 10px;
            width: 100%;
            max-width: none;
        }

        .table-controls > * {
            min-width: 140px;
        }

        .applications-table-header {
            width: 100%;
            overflow-x: auto;
            border-radius: 12px;
            margin-top: 10px;
        }

        .applications-table-header table {
            min-width: 100%;
            width: 100%;
            border-spacing: 0;
        }

        .applications-table-header th,
        .applications-table-header td {
            white-space: nowrap;
        }

        .page-title {
            width: 100%;
            margin: 0 0 20px;
        }

        .page-title p {
            max-width: none;
        }

        /* FSM Badges */
        .badge-received { background-color: #e2e8f0; color: #475569; }
        .badge-review { background-color: #dbeafe; color: #1d4ed8; }
        .badge-verified { background-color: #ccfbf1; color: #0f766e; }
        .badge-approved { background-color: #dcfce7; color: #15803d; }
        .badge-released { background-color: #f3e8ff; color: #6b21a8; }

        /* Warning alert for blurry documents */
        .alert-blurry {
            color: #e74c3c;
            font-size: 0.8rem;
            font-weight: 600;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
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
            color: #64748b;
        }

        .step.active .step-circle {
            background-color: #3b82f6;
            color: white;
        }

        .step.active .step-label {
            color: #3b82f6;
        }

        .step.completed .step-circle {
            background-color: #10b981;
            color: white;
        }

        .step.completed .step-label {
            color: #10b981;
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
            background-color: #3b82f6;
            border: 2px solid white;
        }

        .timeline-time {
            font-size: 0.75rem;
            color: #64748b;
            margin-bottom: 4px;
        }

        .timeline-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text, #333);
        }

        .timeline-desc {
            font-size: 0.85rem;
            color: #475569;
            margin-top: 4px;
            font-style: italic;
        }
        .grid-modal {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 30px;
        }
        .checkbox-group label { display: inline-block; margin-right: 12px; font-size: 0.85rem; }
        #applicationModal .modal-body { max-height: 75vh; overflow-y: auto; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <?php include '../partials/barangay_sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <div class="header-content">
                    <div class="welcome-message" data-first-name="<?php echo htmlspecialchars($_SESSION['first_name']); ?>" data-last-name="<?php echo htmlspecialchars($_SESSION['last_name']); ?>"></div>
                </div>
                <div class="header-actions">
                    <a href="new_application.php" class="btn"><i class="fas fa-plus"></i> Add Application</a>
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php
                                $profilePic = isset($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : 'default.jpg';
                                $profilePicPath = '../images/profile_pictures/' . $profilePic;
                                if (!file_exists($profilePicPath) || is_dir($profilePicPath)) {
                                    $profilePicPath = '../images/profile_pictures/default.jpg';
                                }
                            ?>
                            <img src="<?php echo $profilePicPath; ?>" alt="Profile Picture" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                        </div>
                        <div class="user-details">
                            <h2><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></h2>
                            <p><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $_SESSION['role']))) . ' • ' . htmlspecialchars($_SESSION['barangay']); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Page Title -->
            <div class="page-title">
                <p style="color: var(--text);">Manage applications for your barangay. View, track the status of applications, and resubmit files rejected for blurry scans.</p>
            </div>

            <!-- Applications Table -->
            <div class="applications-table">
                <div class="table-header">
                    <h2>Applications Queue</h2>
                    <div class="table-controls" style="display: flex; gap: 10px; align-items: center; width: 100%;">
                        <input type="text" class="search-box" placeholder="Search applications..." style="flex: 1; margin: 0;">
                        <button class="btn btn-accent" id="scanQrBtn" onclick="openProxyModal()" style="display: flex; align-items: center; gap: 6px; white-space: nowrap;"><i class="fas fa-qrcode"></i> Scan Token</button>
                        <select id="applicationTypeFilter" class="btn" style="margin: 0;">
                            <option value="">All Types</option>
                            <?php foreach (getApplicationTypeOptions() as $val => $label): ?>
                            <option value="<?php echo $val; ?>"><?php echo htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="statusFilter" class="btn">
                            <option value="" selected>All States</option>
                            <option value="Received">Received</option>
                            <option value="For Review">For Review</option>
                            <option value="Verified">Verified</option>
                            <option value="Approved">Approved</option>
                            <option value="Released">Released</option>
                        </select>
                        <button class="btn" id="applyFilterBtn"><i class="fas fa-filter"></i> Filter</button>
                    </div>
                </div>
               
                <table class="applications-table-header">
                    <thead>
                        <tr>
                            <th>Priority</th>
                            <th>Name</th>
                            <th>Application Type</th>
                            <th>Birth Date</th>
                            <th>Contact Number</th>
                            <th>Date Submitted</th>
                            <th>FSM State</th>
                            <th>Complete Address</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="applicationsTableBody">
                        <tr><td colspan="9">Loading applications...</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Footer -->
            <div class="footer">
                <p>Centralized Profiling and Record Authentication System | Barangay <?php echo $loggedInBarangay; ?> &copy; 2024</p>
            </div>
        </div>
    </div>

    <!-- Application Detail Modal -->
    <div id="applicationModal" class="modal">
        <div class="modal-content" style="max-width: 950px;">
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
                    <!-- Left: Details Forms -->
                    <div>
                        <div id="returnedWarningBox" style="display:none; background-color: rgba(231, 76, 60, 0.1); border: 1px solid #e74c3c; padding: 15px; border-radius: 8px; margin-bottom: 20px; color: #ff6b6b; font-size: 0.9rem;">
                            <strong><i class="fas fa-exclamation-triangle"></i> SCANS REJECTED BY OFFICE REVIEWER:</strong>
                            <p id="returnedReasonText" style="margin-top: 5px; font-style: italic;"></p>
                            <p style="margin-top: 10px; font-weight: bold; text-decoration: underline;">Please upload clean, high-resolution scans below and save changes to update.</p>
                        </div>

                        <form id="applicationDetailForm" method="POST" action="../api/update_application.php" enctype="multipart/form-data">
                            <input type="hidden" id="applicationId" name="applicationId">
                            
                            <div class="form-section">
                                <h3><i class="fas fa-user"></i> Basic Information</h3>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="applicationType">Application Type</label>
                                        <select id="applicationType" name="applicationType" required disabled>
                                            <?php foreach (getApplicationTypeOptions() as $val => $label): ?>
                                            <option value="<?php echo $val; ?>"><?php echo htmlspecialchars($label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="lastName">Last Name</label>
                                        <input type="text" id="lastName" name="lastName" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="firstName">First Name</label>
                                        <input type="text" id="firstName" name="firstName" required>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="middleName">Middle Name</label>
                                        <input type="text" id="middleName" name="middleName">
                                    </div>
                                    <div class="form-group">
                                        <label for="suffix">Suffix</label>
                                        <input type="text" id="suffix" name="suffix">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="birthDate">Birth Date</label>
                                        <input type="date" id="birthDate" name="birthDate" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="contactNumber">Contact Number</label>
                                        <input type="text" id="contactNumber" name="contactNumber" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="completeAddress">Complete Address</label>
                                    <textarea id="completeAddress" name="completeAddress" required></textarea>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="emergencyContactName">Emergency Contact Name</label>
                                        <input type="text" id="emergencyContactName" name="emergencyContactName">
                                    </div>
                                    <div class="form-group">
                                        <label for="emergencyContact">Emergency Contact Number</label>
                                        <input type="text" id="emergencyContact" name="emergencyContact">
                                    </div>
                                </div>
                            </div>

                            <?php $formFieldPrefix = ''; include '../partials/osca_form_sections.php'; ?>

                            <div id="pension-fields-modal" class="form-section" style="display:none;">
                                <h3><i class="fas fa-wallet"></i> Social Pension</h3>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="sssNumber">SSS Number</label>
                                        <input type="text" id="sssNumber" name="sssNumber">
                                    </div>
                                    <div class="form-group">
                                        <label for="pensionAmount">Verified Monthly Pension (PHP)</label>
                                        <input type="number" step="0.01" id="pensionAmount" name="pensionAmount" readonly>
                                    </div>
                                </div>
                            </div>

                            <div id="burial-fields-modal" class="form-section" style="display:none;">
                                <h3><i class="fas fa-ribbon"></i> Burial Assistance</h3>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="dateOfDeath">Date of Passing</label>
                                        <input type="date" id="dateOfDeath" name="dateOfDeath">
                                    </div>
                                    <div class="form-group">
                                        <label for="relationshipToDeceased">Relationship to Deceased</label>
                                        <input type="text" id="relationshipToDeceased" name="relationshipToDeceased">
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h3><i class="fas fa-file-alt"></i> Required Documents</h3>
                                <div class="form-group">
                                    <label for="proofOfAddress">Proof of Address</label>
                                    <input type="file" id="proofOfAddress" name="proofOfAddress">
                                    <div class="image-placeholder" id="proofOfAddressPreview"></div>
                                </div>
                                <div class="form-group">
                                    <label for="idImage">ID Image</label>
                                    <input type="file" id="idImage" name="idImage">
                                    <div class="image-placeholder" id="idImagePreview"></div>
                                </div>
                            </div>

                            <!-- Uploaded Proxy/Pension Documents section -->
                            <div class="form-section" id="proxyDocumentsSection" style="display:none; margin-top:20px;">
                                <h3><i class="fas fa-user-shield"></i> Uploaded Proxy/Pension Documents</h3>
                                <div id="proxyDocumentsList" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 15px;"></div>
                            </div>

                            <div class="form-actions" id="modalFormActions">
                                <button type="submit" class="btn"><i class="fas fa-save"></i> Save Changes</button>
                                <button type="button" class="btn btn-accent" onclick="exportApplicationDetails(document.getElementById('applicationId').value)"><i class="fas fa-print"></i> Print Official Form</button>
                                <button type="button" class="btn" style="background-color: #3b82f6;" id="btnForwardReview" onclick="forwardToReviewDesk()">Submit to Review Desk</button>
                            </div>
                        </form>
                    </div>

                    <!-- Right: FSM Audit Trail history log -->
                    <div>
                        <h4 style="font-size: 0.95rem; margin-bottom: 15px; color: var(--text); border-bottom: 1px solid #cbd5e1; padding-bottom: 5px;"><i class="fas fa-history"></i> Audit Trail History Log</h4>
                        <div class="timeline" id="timelineList"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/sidebar-toggle.js"></script>
    <script src="../assets/js/dark-mode.js"></script>
    <script src="../assets/js/osca-form-fields.js"></script>
    <script>
        const TYPE_LABELS = <?php echo json_encode(getApplicationTypeOptions()); ?>;
        document.addEventListener('DOMContentLoaded', function() {
            // Event delegation for View Details
            const tableBody = document.querySelector('#applicationsTableBody');
            tableBody.addEventListener('click', function(event) {
                const clickedElement = event.target.closest('.name-link');
                if (clickedElement) {
                    const appId = clickedElement.dataset.id;
                    openApplicationModal(appId);
                }
            });

            const closeModalBtn = document.querySelector('#applicationModal .close-modal');
            closeModalBtn.addEventListener('click', () => {
                document.getElementById('applicationModal').style.display = 'none';
            });

            // Update welcome message based on time of day
            const welcomeMessage = document.querySelector('.welcome-message');
            if (welcomeMessage) {
                const firstName = welcomeMessage.dataset.firstName;
                const lastName = welcomeMessage.dataset.lastName;
                const hour = new Date().getHours();
                let greeting;
                
                if (hour < 12) {
                    greeting = "Good morning";
                } else if (hour < 18) {
                    greeting = "Good afternoon";
                } else {
                    greeting = "Good evening";
                }
                
                welcomeMessage.innerHTML = `${greeting}, <strong>${firstName} ${lastName}</strong>!`;
            }
        });

        let currentAppId = null;

        function openApplicationModal(appId) {
            currentAppId = appId;
            document.getElementById('applicationModal').style.display = 'block';

            fetch(`../api/get_application_details.php?id=${appId}`)
                .then(response => response.json())
                .then(application => {
                    if (application.error) {
                        alert(application.error);
                        return;
                    }

                    document.getElementById('modalAppTitle').textContent = `Reviewing: ${application.full_name} (${application.id_number})`;
                    document.getElementById('applicationId').value = application.id_number;
                    document.getElementById('applicationType').value = application.application_type;
                    document.getElementById('lastName').value = application.lastName || '';
                    document.getElementById('firstName').value = application.firstName || '';
                    document.getElementById('middleName').value = application.middleName || '';
                    document.getElementById('suffix').value = application.suffix || '';
                    document.getElementById('birthDate').value = application.birth_date || '';
                    document.getElementById('contactNumber').value = application.contact_number || '';
                    document.getElementById('completeAddress').value = application.complete_address || '';
                    document.getElementById('emergencyContactName').value = application.emergency_contact_name || '';
                    document.getElementById('emergencyContact').value = application.emergency_contact || '';

                    populateOscaFields(application, '');

                    const pensionModal = document.getElementById('pension-fields-modal');
                    const burialModal = document.getElementById('burial-fields-modal');
                    pensionModal.style.display = 'none';
                    burialModal.style.display = 'none';
                    if (application.application_type === 'pension' || application.application_type === 'national_pension') {
                        pensionModal.style.display = 'block';
                        document.getElementById('sssNumber').value = application.sss_number || '';
                        document.getElementById('pensionAmount').value = application.pension_amount || '';
                    }
                    if (application.application_type === 'burial') {
                        burialModal.style.display = 'block';
                        document.getElementById('dateOfDeath').value = application.date_of_death || '';
                        document.getElementById('relationshipToDeceased').value = application.relationship_to_deceased || '';
                    }
                    toggleOscaFormFields(application.application_type, '');

                    // Display Stepper Progress
                    const steps = ['Received', 'For Review', 'Verified', 'Approved', 'Released'];
                    const currentState = application.workflow_state || 'Received';
                    let currentStepIndex = steps.indexOf(currentState);
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

                    // Render Document Previews
                    document.getElementById('proofOfAddressPreview').innerHTML = application.has_proof_of_address 
                        ? `<img src="../api/get_document.php?id=${appId}&doc_type=proof_of_address" alt="Proof of Address">` 
                        : '<p>No document uploaded.</p>';
                    
                    document.getElementById('idImagePreview').innerHTML = application.has_id_image 
                        ? `<img src="../api/get_document.php?id=${appId}&doc_type=id_image" alt="ID Image">` 
                        : '<p>No document uploaded.</p>';

                    // Set up Blur/Return alerts
                    const warningBox = document.getElementById('returnedWarningBox');
                    const reasonText = document.getElementById('returnedReasonText');
                    if ((currentState === 'Received' || currentState === 'Submitted') && application.return_comments) {
                        warningBox.style.display = 'block';
                        reasonText.textContent = `"${application.return_comments}"`;
                    } else {
                        warningBox.style.display = 'none';
                    }

                    // Render Proxy Documents if applicable
                    const proxySec = document.getElementById('proxyDocumentsSection');
                    const proxyList = document.getElementById('proxyDocumentsList');
                    proxySec.style.display = 'none';
                    proxyList.innerHTML = '';
                    
                    if (application.is_proxy_application == 1) {
                        proxySec.style.display = 'block';
                        const docs = [
                            { key: 'psa_birth_cert', label: 'PSA Birth Cert' },
                            { key: 'barangay_residency', label: 'Barangay Residency' },
                            { key: 'comelec_cert', label: 'COMELEC Cert' },
                            { key: 'proof_of_life', label: 'Proof of Life (In Bed)' },
                            { key: 'auth_letter', label: 'Auth Letter' },
                            { key: 'proxy_id', label: 'Proxy Gov ID' },
                            { key: 'proxy_birth_cert', label: 'Proxy Birth Cert' },
                            { key: 'home_visitation_form', label: 'Home Visitation Form' },
                            { key: 'landbank_enrollment_form', label: 'Land Bank Card Form' }
                        ];
                        docs.forEach(doc => {
                            if (application[doc.key]) {
                                const docUrl = `../api/get_document.php?id=${encodeURIComponent(appId)}&doc_type=${doc.key}`;
                                let previewHtml = '';
                                if (application[doc.key].toLowerCase().endsWith('.pdf')) {
                                    previewHtml = `<div class="pdf-preview-icon" style="font-size:3rem; text-align:center; padding:15px 0;"><i class="fas fa-file-pdf" style="color:#ef4444;"></i></div>`;
                                } else {
                                    previewHtml = `<img src="${docUrl}" style="max-height:100%; max-width:100%; object-fit:contain; border-radius:4px;">`;
                                }
                                proxyList.innerHTML += `
                                    <div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px; background: #fff; text-align: center;">
                                        <span style="font-size:0.75rem; font-weight:700; color:#475569; display:block; margin-bottom:5px; height: 32px; overflow: hidden; line-height: 1.2;">${doc.label}</span>
                                        <div class="image-placeholder" style="height:120px; display:flex; align-items:center; justify-content:center; background:#f8fafc; overflow:hidden; border:1px dashed #cbd5e1; border-radius:6px; margin-bottom:8px;">
                                            ${previewHtml}
                                        </div>
                                        <a href="${docUrl}" target="_blank" class="btn btn-small" style="font-size:0.72rem; padding: 4px 8px; width:100%; display:block; text-align:center; box-sizing:border-box;"><i class="fas fa-eye"></i> View Original</a>
                                    </div>
                                `;
                            }
                        });
                    }

                    // Render Actions - edit locked if state is past "Received" / "Submitted"
                    const inputs = document.querySelectorAll('#applicationDetailForm input, #applicationDetailForm textarea');
                    const formActions = document.getElementById('modalFormActions');

                    const isEditable = (currentState === 'Received' || currentState === 'Submitted');
                    if (!isEditable) {
                        inputs.forEach(inp => inp.setAttribute('disabled', 'disabled'));
                    } else {
                        inputs.forEach(inp => {
                            if (inp.id !== 'applicationType' && inp.id !== 'applicationId') {
                                inp.removeAttribute('disabled');
                            }
                        });
                    }

                    // Dynamic FSM transition action buttons
                    let actionsHtml = '';
                    if (isEditable) {
                        actionsHtml += `<button type="submit" class="btn" style="background:#0f172a; border-color:#0f172a;"><i class="fas fa-save"></i> Save Changes</button>`;
                    }
                    actionsHtml += `<button type="button" class="btn btn-accent" onclick="exportApplicationDetails('${application.id_number}')"><i class="fas fa-print"></i> Print Official Form</button>`;
                    
                    if (currentState === 'Submitted' || currentState === 'Received') {
                        actionsHtml += `
                            <button type="button" class="btn" style="background-color: #3b82f6; border-color: #3b82f6;" onclick="transitionApplicationState('next', 'File marked as Under Review after Proof of Life verification.')">
                                <i class="fas fa-search"></i> Mark Under Review
                            </button>
                        `;
                    } else if (currentState === 'For Review') {
                        actionsHtml += `
                            <button type="button" class="btn" style="background-color: #10b981; border-color: #10b981;" onclick="transitionApplicationState('next', 'Counter verification successful. Original physical documents match uploaded records.')">
                                <i class="fas fa-check-circle"></i> Verify and Approve
                            </button>
                        `;
                    } else {
                        actionsHtml += `
                            <span style="color:#64748b; font-size:0.82rem; font-style:italic; font-weight:600; display:inline-flex; align-items:center; gap:5px; margin-left:10px;">
                                <i class="fas fa-lock"></i> FSM State: [${currentState}]
                            </span>
                        `;
                    }
                    formActions.innerHTML = actionsHtml;

                    // Render Timeline Audit Trail
                    let timelineHtml = "";
                    if (application.history && application.history.length > 0) {
                        application.history.forEach(log => {
                            timelineHtml += `
                                <div class="timeline-event">
                                    <div class="timeline-time">${log.changed_at}</div>
                                    <div class="timeline-title">${log.previous_state} &rarr; ${log.new_state} (by ${log.changed_by})</div>
                                    <div class="timeline-desc">${log.comments || 'No comments.'}</div>
                                </div>`;
                        });
                    } else {
                        timelineHtml = "<p style='color: #64748b; font-size: 0.85rem; font-style: italic;'>No transitions logged.</p>";
                    }
                    document.getElementById('timelineList').innerHTML = timelineHtml;
                });
        }

        async function forwardToReviewDesk() {
            if (!confirm("Are you sure you want to submit this application to the department desk for review?")) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('applicationId', currentAppId);
                formData.append('action', 'next');
                formData.append('comments', 'Forwarded to the review queue by Barangay Staff.');

                const response = await fetch('../api/update_fsm_state.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();

                if (result.success) {
                    alert(result.message);
                    document.getElementById('applicationModal').style.display = 'none';
                    fetchApplications();
                } else {
                    alert("Submission Error: " + result.message);
                }
            } catch (err) {
                console.error(err);
                alert("Failed to submit to review desk due to a connection issue.");
            }
        }

        function deleteApplication(appId) {
            if (confirm('Are you sure you want to delete this application?')) {
                fetch('../api/delete_application.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id=${appId}`,
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Application deleted successfully!');
                        fetchApplications();
                    } else {
                        alert(data.message);
                    }
                });
            }
        }

        function exportApplicationDetails(appId) {
            window.open(`../api/export_application_pdf.php?id=${appId}`, '_blank');
        }

        // Form Submit handler
        document.getElementById('applicationDetailForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);

            fetch(form.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    document.getElementById('applicationModal').style.display = 'none';
                    fetchApplications();
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Error updating application:', error);
                alert('An error occurred while updating the application.');
            });
        });

        const searchInput = document.querySelector('.search-box');
        const applicationTypeFilter = document.querySelector('#applicationTypeFilter');
        const statusFilter = document.querySelector('#statusFilter');
        const applyFilterBtn = document.querySelector('#applyFilterBtn');

        const userBarangay = "<?php echo $loggedInBarangay; ?>";

        function fetchApplications() {
            const query = searchInput.value;
            const type = applicationTypeFilter.value;
            const status = statusFilter.value;

            let url = `../api/search_applications.php?query=${query}`;
            if (type) {
                url += `&type=${type}`;
            }
            if (status) {
                url += `&status=${status}`;
            }
            if (userBarangay) {
                url += `&barangay=${userBarangay}`;
            }

            fetch(url)
                .then(response => response.json())
                .then(applications => {
                    const tableBody = document.querySelector('#applicationsTableBody');
                    tableBody.innerHTML = '';

                    if (applications.length > 0) {
                        applications.forEach(app => {
                            const isHigh = (app.priority_level === 'high');
                            const rowClass = isHigh ? 'priority-high-row' : '';
                            
                            const state = app.workflow_state || 'Received';
                            let stateBadgeClass = 'badge-received';
                            if (state === 'For Review') stateBadgeClass = 'badge-review';
                            if (state === 'Verified') stateBadgeClass = 'badge-verified';
                            if (state === 'Approved') stateBadgeClass = 'badge-approved';
                            if (state === 'Released') stateBadgeClass = 'badge-released';

                            let typeLabel = TYPE_LABELS[app.application_type] || app.application_type;

                            // Detect blurry scan returned reason
                            let blurryWarning = '';
                            if (state === 'Received' && app.return_comments) {
                                blurryWarning = `<div class="alert-blurry"><i class="fas fa-triangle-exclamation"></i> Resubmit scans: ${app.return_comments}</div>`;
                            }

                            const row = `
                                <tr class="${rowClass}">
                                    <td>${isHigh ? "<span class='priority-badge'><i class='fas fa-star'></i> HIGH</span>" : "<span style='color: #94a3b8; font-size: 0.8rem;'>Normal</span>"}</td>
                                    <td>
                                        <a href="#" class="name-link" data-id="${app.id}" style="font-weight: 700;">${app.full_name}</a>
                                        ${blurryWarning}
                                    </td>
                                    <td>${typeLabel}</td>
                                    <td>${app.birth_date}</td>
                                    <td>${app.contact_number}</td>
                                    <td>${app.date_submitted}</td>
                                    <td><span class="status-badge ${stateBadgeClass}">${state}</span></td>
                                    <td>${app.complete_address}</td>
                                    <td>
                                        ${state === 'Received' ? `<button class="btn btn-danger btn-small" onclick="deleteApplication(${app.id})"><i class="fas fa-trash"></i> Delete</button>` : `<span style="color:#64748b; font-size:0.8rem; font-style:italic;">Locked</span>`}
                                    </td>
                                </tr>
                            `;
                            tableBody.innerHTML += row;
                        });
                    } else {
                        tableBody.innerHTML = '<tr><td colspan="9">No applications found.</td></tr>';
                    }
                })
                .catch(error => {
                    console.error('Error fetching applications:', error);
                    const tableBody = document.querySelector('#applicationsTableBody');
                    tableBody.innerHTML = '<tr><td colspan="9">Error loading applications.</td></tr>';
                });
        }

        searchInput.addEventListener('keyup', fetchApplications);
        applicationTypeFilter.addEventListener('change', fetchApplications);
        statusFilter.addEventListener('change', fetchApplications);
        applyFilterBtn.addEventListener('click', fetchApplications);

        fetchApplications();

        // FSM Transition Function
        async function transitionApplicationState(action, defaultComment) {
            let comment = prompt("Enter FSM transition remarks/comments:", defaultComment);
            if (comment === null) return; // cancel
            
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
                    document.getElementById('applicationModal').style.display = 'none';
                    fetchApplications();
                } else {
                    alert("State Transition Error: " + result.message);
                }
            } catch (err) {
                console.error(err);
                alert("Connection error occurred during state transition.");
            }
        }

        // QR Scanner Modal Helpers
        function openProxyModal() {
            document.getElementById('proxyModal').style.display = 'block';
            document.getElementById('modalToken').value = '';
            document.getElementById('modalError').textContent = '';
        }

        function closeProxyModal() {
            document.getElementById('proxyModal').style.display = 'none';
        }

        async function searchByQrToken() {
            const tokenInput = document.getElementById('modalToken').value.trim();
            const modalError = document.getElementById('modalError');
            
            if (!tokenInput) {
                modalError.textContent = 'Token input cannot be empty.';
                return;
            }

            modalError.innerHTML = '<span style="color:#2563eb;"><i class="fas fa-spinner fa-spin"></i> Parsing token...</span>';
            let token = tokenInput;
            
            // Extract token if they pasted a full redirect URL
            if (tokenInput.includes('token=')) {
                try {
                    const url = new URL(tokenInput);
                    token = url.searchParams.get('token');
                } catch(e) {}
            }

            try {
                let transactionId = token;
                
                // If it looks like an encrypted token, decrypt it first via API
                if (token.length > 50) {
                    const response = await fetch(`../api/scan_proxy_qr.php?token=${encodeURIComponent(token)}`);
                    const result = await response.json();
                    
                    if (result.success && result.data && result.data.transactionId) {
                        transactionId = result.data.transactionId;
                    } else {
                        modalError.textContent = 'Failed to decrypt token. Please try again.';
                        return;
                    }
                }
                
                // Search database or list to verify if the application ID exists
                const verifyResponse = await fetch(`../api/get_application_details.php?id=${encodeURIComponent(transactionId)}`);
                const verifyResult = await verifyResponse.json();
                
                if (verifyResult && !verifyResult.error) {
                    // Close QR modal and open details
                    closeProxyModal();
                    openApplicationModal(transactionId);
                } else {
                    modalError.textContent = `Application ID: [${transactionId}] not found in your database or barangay isolation.`;
                }
            } catch (err) {
                console.error(err);
                modalError.textContent = 'Connection error occurred during verification.';
            }
        }

        // Close scan modal when clicking outside
        window.addEventListener('click', function(event) {
            const proxyModal = document.getElementById('proxyModal');
            if (event.target === proxyModal) {
                closeProxyModal();
            }
        });
    </script>

    <!-- Scan Proxy QR Modal -->
    <div id="proxyModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h2 style="display: flex; align-items: center; gap: 8px; color: #0f172a;"><i class="fas fa-qrcode"></i> Scan Proxy QR Token</h2>
                <button class="close-modal" onclick="closeProxyModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
            </div>
            <div class="modal-body" style="padding-top: 15px;">
                <p style="margin-bottom: 15px; font-size: 0.88rem; color: #64748b;">
                    Paste the encrypted QR token link or type the unique transaction priority token (e.g., PRX-XXXXXX) to instantly display the senior's profile details.
                </p>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="modalToken" style="font-weight: 600; font-size: 0.85rem; display: block; margin-bottom: 6px;">QR Code Redirect Link or Token ID</label>
                    <textarea id="modalToken" class="form-control" rows="3" placeholder="Paste scan payload here (e.g. PRX-XXXXXX)..." style="width: 100%; border-radius: 6px; padding: 10px; border: 1px solid #cbd5e1; font-family: monospace;"></textarea>
                </div>
                <button type="button" class="btn" style="background-color: #10b981; color: white; width: 100%; padding: 12px; font-weight: 600; border: none; border-radius: 6px; cursor: pointer;" onclick="searchByQrToken()">
                    <i class="fas fa-search"></i> Search and Open Profile
                </button>
                <div id="modalError" style="color: #e74c3c; font-size: 0.82rem; margin-top: 10px; font-weight: 500;"></div>
            </div>
        </div>
    </div>
</body>
</html>