<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/crypto.php';

// Helper function to calculate working days (excluding Sat/Sun)
function getWorkingDays($startDate, $endDate) {
    $begin = new DateTime($startDate);
    $end = new DateTime($endDate);
    if ($begin > $end) {
        $temp = $begin;
        $begin = $end;
        $end = $temp;
    }
    $no_days = 0;
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($begin, $interval, $end);
    foreach ($period as $dt) {
        $curr = $dt->format('D');
        if ($curr !== 'Sat' && $curr !== 'Sun') {
            $no_days++;
        }
    }
    return $no_days;
}

$errorMessage = "";
$successMessage = "";

// Check if loaded with a Proxy Token from scanning the QR
$loadedProxyData = null;
if (isset($_GET['token'])) {
    $decrypted = ProxyCrypto::decrypt($_GET['token']);
    if ($decrypted) {
        $loadedProxyData = $decrypted;
        $successMessage = "Proxy Pre-Registration details loaded successfully. Priority status set to HIGH Queue.";
    } else {
        $errorMessage = "Failed to decrypt Proxy Token. Data is corrupted or invalid.";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and validate input
    $lastName = isset($_POST['lastName']) ? trim(strip_tags($_POST['lastName'])) : '';
    $firstName = isset($_POST['firstName']) ? trim(strip_tags($_POST['firstName'])) : '';
    $middleName = isset($_POST['middleName']) ? trim(strip_tags($_POST['middleName'])) : '';
    $suffix = isset($_POST['suffix']) ? trim(strip_tags($_POST['suffix'])) : '';
    $fullName = trim($firstName . ' ' . $middleName . ' ' . $lastName . ' ' . $suffix);
    $applicationType = isset($_POST['applicationType']) ? trim(strip_tags($_POST['applicationType'])) : '';
    $birthDate = isset($_POST['birthDate']) ? trim(strip_tags($_POST['birthDate'])) : '';
    $contactNumber = isset($_POST['contactNumber']) ? trim(strip_tags($_POST['contactNumber'])) : '';
    $completeAddress = isset($_POST['completeAddress']) ? trim(strip_tags($_POST['completeAddress'])) : '';
    $emergencyContact = isset($_POST['emergencyContact']) ? trim(strip_tags($_POST['emergencyContact'])) : '';
    $emergencyContactName = isset($_POST['emergencyContactName']) ? trim(strip_tags($_POST['emergencyContactName'])) : '';
    $barangay = $_SESSION['barangay'] ?? ''; // Barangay from session

    $idNumber = isset($_POST['idNumber']) ? trim(strip_tags($_POST['idNumber'])) : '';
    $disabilityType = isset($_POST['disabilityType']) ? implode(', ', (array)$_POST['disabilityType']) : null;

    // Rule-Based Compliance validation on backend
    // Age check for Senior, Pension, and Burial applications (must be >= 60)
    if ($applicationType !== 'pwd' && !empty($birthDate)) {
        $birthDateObj = new DateTime($birthDate);
        $today = new DateTime();
        $age = $today->diff($birthDateObj)->y;
        if ($age < 60) {
            $errorMessage = "Localized Compliance Check Failed: Applicant must be at least 60 years old (current age: $age).";
        }
    }

    // Social Pension validation (SSS cap <= P4,000)
    $sssNumber = null;
    $pensionAmount = null;
    if ($applicationType === 'pension') {
        $sssNumber = isset($_POST['sssNumber']) ? trim(strip_tags($_POST['sssNumber'])) : '';
        $pensionAmount = isset($_POST['pensionAmount']) ? floatval($_POST['pensionAmount']) : 0;
        if (empty($sssNumber)) {
            $errorMessage = "Localized Compliance Check Failed: SSS Number is required for social pension.";
        } else if ($pensionAmount > 4000) {
            $errorMessage = "Localized Compliance Check Failed: Monthly SSS Pension exceeds the local limit of P4,000 (current: P" . number_format($pensionAmount, 2) . ").";
        }
    }

    // Burial Assistance validation (death within 30 working days)
    $dateOfDeath = null;
    $relationshipToDeceased = null;
    if ($applicationType === 'burial') {
        $dateOfDeath = isset($_POST['dateOfDeath']) ? trim(strip_tags($_POST['dateOfDeath'])) : '';
        $relationshipToDeceased = isset($_POST['relationshipToDeceased']) ? trim(strip_tags($_POST['relationshipToDeceased'])) : '';
        if (empty($dateOfDeath)) {
            $errorMessage = "Localized Compliance Check Failed: Date of Death is required for burial assistance.";
        } else {
            $workingDays = getWorkingDays($dateOfDeath, date('Y-m-d'));
            if ($workingDays > 30) {
                $errorMessage = "Localized Compliance Check Failed: Application must be filed within 30 working days from passing (elapsed: $workingDays working days).";
            }
        }
    }

    // Proxy details
    $isProxy = isset($_POST['isProxy']) ? intval($_POST['isProxy']) : 0;
    $proxyName = isset($_POST['proxyName']) ? trim(strip_tags($_POST['proxyName'])) : null;
    $proxyRelationship = isset($_POST['proxyRelationship']) ? trim(strip_tags($_POST['proxyRelationship'])) : null;
    $proxyToken = isset($_POST['proxyToken']) ? trim(strip_tags($_POST['proxyToken'])) : null;
    $priorityLevel = ($isProxy && !empty($proxyToken)) ? 'high' : 'normal';

    if (empty($errorMessage)) {
        $proofOfAddress = isset($_FILES['proofOfAddress']) && $_FILES['proofOfAddress']['error'] == 0 ? file_get_contents($_FILES['proofOfAddress']['tmp_name']) : null;
        $proofOfAddressType = isset($_FILES['proofOfAddress']) && $_FILES['proofOfAddress']['error'] == 0 ? $_FILES['proofOfAddress']['type'] : null;
        $idImage = isset($_FILES['idImage']) && $_FILES['idImage']['error'] == 0 ? file_get_contents($_FILES['idImage']['tmp_name']) : null;
        $idImageType = isset($_FILES['idImage']) && $_FILES['idImage']['error'] == 0 ? $_FILES['idImage']['type'] : null;

        // Prepare and bind - Including new columns
        $stmt = $conn->prepare("INSERT INTO applications (
                    id_number, full_name, application_type, birth_date, contact_number, complete_address,
                    emergency_contact, emergency_contact_name, barangay,
                    proof_of_address, proof_of_address_type, id_image, id_image_type,
                    lastName, firstName, middleName, suffix, disability_type,
                    sss_number, pension_amount, date_of_death, relationship_to_deceased,
                    is_proxy_application, proxy_name, proxy_relationship, proxy_token,
                    priority_level, workflow_state
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if ($stmt->execute([
            $idNumber, $fullName, $applicationType, $birthDate, $contactNumber, $completeAddress,
            $emergencyContact, $emergencyContactName, $barangay,
            $proofOfAddress, $proofOfAddressType, $idImage, $idImageType,
            $lastName, $firstName, $middleName, $suffix, $disabilityType,
            $sssNumber, $pensionAmount, $dateOfDeath, $relationshipToDeceased,
            $isProxy, $proxyName, $proxyRelationship, $proxyToken,
            $priorityLevel, 'Received'
        ])) {
            // Log history
            $stmtHist = $conn->prepare("INSERT INTO application_history (application_id, previous_state, new_state, changed_by, comments) VALUES (?, ?, ?, ?, ?)");
            $userName = $_SESSION['username'] ?? 'barangay_staff';
            $stmtHist->execute([$idNumber, 'None', 'Received', $userName, 'Application created and received at the counter.']);

            header("Location: submit_application.php?success=1");
            exit();
        } else {
            $errorMessage = "Error: " . $stmt->errorInfo()[2];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CPRAS - New Application</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/barangay-sidebar.css">
    <link rel="stylesheet" href="../assets/css/main-dark-mode.css">
    <style>
        .compliance-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-top: 5px;
        }
        .compliance-pass {
            background-color: rgba(46, 204, 113, 0.2);
            color: #2ecc71;
            border: 1px solid #2ecc71;
        }
        .compliance-fail {
            background-color: rgba(231, 76, 60, 0.2);
            color: #e74c3c;
            border: 1px solid #e74c3c;
        }
        .compliance-info {
            background-color: rgba(52, 152, 219, 0.2);
            color: #3498db;
            border: 1px solid #3498db;
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .alert-error {
            background-color: rgba(231, 76, 60, 0.15);
            color: #ff6b6b;
            border: 1px solid #e74c3c;
        }
        .alert-success {
            background-color: rgba(46, 204, 113, 0.15);
            color: #2ecc71;
            border: 1px solid #2ecc71;
        }
        /* Modal for Scan Proxy */
        .modal {
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0;
            top: 0;
            width: 100%; 
            height: 100%; 
            overflow: auto; 
            background-color: rgba(0,0,0,0.6); 
            backdrop-filter: blur(5px);
        }
        .modal-content {
            background-color: var(--card-bg, #1e293b);
            color: var(--text, #f8fafc);
            margin: 15% auto; 
            padding: 30px;
            border: 1px solid var(--border-color, #475569);
            width: 90%;
            max-width: 500px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover {
            color: #fff;
        }
        .proxy-load-btn {
            background-color: #2980b9;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }
        .proxy-load-btn:hover {
            background-color: #1f5f8a;
        }
        .btn-verify-sss {
            background-color: #27ae60;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85rem;
            margin-left: 10px;
        }
        .btn-verify-sss:hover {
            background-color: #1e8449;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include '../partials/barangay_sidebar.php'; ?>
        <div class="main-content">
            <div class="header" style="display: flex; justify-content: space-between; align-items: center;">
                <h1 style="color: var(--text);">New Application</h1>
                <button class="proxy-load-btn" onclick="openProxyModal()"><i class="fas fa-qrcode"></i> Scan Proxy QR Code</button>
            </div>
            
            <div class="application-form">
                <?php if (!empty($errorMessage)): ?>
                    <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo $errorMessage; ?></div>
                <?php endif; ?>
                <?php if (!empty($successMessage)): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $successMessage; ?></div>
                <?php endif; ?>

                <form method="POST" action="new_application.php" enctype="multipart/form-data" id="mainAppForm">
                    <!-- Hidden Proxy Fields -->
                    <input type="hidden" name="isProxy" id="isProxy" value="<?php echo $loadedProxyData ? 1 : 0; ?>">
                    <input type="hidden" name="proxyToken" id="proxyToken" value="<?php echo htmlspecialchars($loadedProxyData['transactionId'] ?? ''); ?>">

                    <!-- Basic Information -->
                    <div class="form-section">
                        <h3><i class="fas fa-user"></i> Basic Information</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="applicationType">Application Type</label>
                                <select id="applicationType" name="applicationType" required onchange="toggleFields()">
                                    <option value="pwd" <?php echo ($loadedProxyData['applicationType'] ?? '') === 'pwd' ? 'selected' : ''; ?>>PWD</option>
                                    <option value="senior" <?php echo ($loadedProxyData['applicationType'] ?? '') === 'senior' ? 'selected' : ''; ?>>Senior Citizen ID Card</option>
                                    <option value="pension" <?php echo ($loadedProxyData['applicationType'] ?? '') === 'pension' ? 'selected' : ''; ?>>Local Social Pension</option>
                                    <option value="burial" <?php echo ($loadedProxyData['applicationType'] ?? '') === 'burial' ? 'selected' : ''; ?>>Burial Assistance</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="idNumber">ID Number / Reference ID</label>
                                <input type="text" id="idNumber" name="idNumber" value="<?php echo htmlspecialchars($loadedProxyData['transactionId'] ?? uniqid('APP-')); ?>" oninput="this.value = this.value.replace(/[^a-zA-Z0-9-]/g, '')" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="lastName">Last Name</label>
                                <input type="text" id="lastName" name="lastName" value="<?php echo htmlspecialchars($loadedProxyData['lastName'] ?? ''); ?>" oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')" required>
                            </div>
                            <div class="form-group">
                                <label for="firstName">First Name</label>
                                <input type="text" id="firstName" name="firstName" value="<?php echo htmlspecialchars($loadedProxyData['firstName'] ?? ''); ?>" oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="middleName">Middle Name</label>
                                <input type="text" id="middleName" name="middleName" value="<?php echo htmlspecialchars($loadedProxyData['middleName'] ?? ''); ?>" oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')">
                            </div>
                            <div class="form-group">
                                <label for="suffix">Suffix</label>
                                <input type="text" id="suffix" name="suffix" value="<?php echo htmlspecialchars($loadedProxyData['suffix'] ?? ''); ?>" oninput="this.value = this.value.replace(/[^a-zA-Z\s.]/g, '')">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="birthDate">Birth Date</label>
                                <input type="date" id="birthDate" name="birthDate" value="<?php echo htmlspecialchars($loadedProxyData['birthDate'] ?? ''); ?>" required onchange="checkAgeCompliance()">
                                <div id="ageComplianceResult"></div>
                            </div>
                            <div class="form-group">
                                <label for="contactNumber">Contact Number</label>
                                <input type="text" id="contactNumber" name="contactNumber" value="<?php echo htmlspecialchars($loadedProxyData['contactNumber'] ?? ''); ?>" oninput="this.value = this.value.replace(/[^0-9]/g, '')" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="completeAddress">Complete Address</label>
                            <textarea id="completeAddress" name="completeAddress" required><?php echo htmlspecialchars($loadedProxyData['completeAddress'] ?? ''); ?></textarea>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="emergencyContactName">Emergency Contact Name</label>
                                <input type="text" id="emergencyContactName" name="emergencyContactName" oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')">
                            </div>
                            <div class="form-group">
                                <label for="emergencyContact">Emergency Contact Number</label>
                                <input type="text" id="emergencyContact" name="emergencyContact" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                            </div>
                        </div>
                    </div>

                    <!-- PWD Specific Fields -->
                    <div id="pwd-fields">
                        <div class="form-section">
                            <h3><i class="fas fa-wheelchair"></i> PWD Specific Information</h3>
                            <div class="form-group">
                                <label>Type of Disability</label>
                                <div>
                                    <input type="checkbox" name="disabilityType[]" value="Deaf/Hard of Hearing"> Deaf/Hard of Hearing<br>
                                    <input type="checkbox" name="disabilityType[]" value="Intellectual Disability"> Intellectual Disability<br>
                                    <input type="checkbox" name="disabilityType[]" value="Learning Disability"> Learning Disability<br>
                                    <input type="checkbox" name="disabilityType[]" value="Mental Disability"> Mental Disability<br>
                                    <input type="checkbox" name="disabilityType[]" value="Orthopedic"> Orthopedic<br>
                                    <input type="checkbox" name="disabilityType[]" value="Physical Disability"> Physical Disability<br>
                                    <input type="checkbox" name="disabilityType[]" value="Psychosocial Disability"> Psychosocial Disability<br>
                                    <input type="checkbox" name="disabilityType[]" value="Speech and Language Impairment"> Speech and Language Impairment<br>
                                    <input type="checkbox" name="disabilityType[]" value="Visual Disability"> Visual Disability<br>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Local Social Pension Fields -->
                    <div id="pension-fields" style="display: none;">
                        <div class="form-section">
                            <h3><i class="fas fa-wallet"></i> Social Pension Verification</h3>
                            <div class="form-row">
                                <div class="form-group" style="display: flex; flex-direction: column;">
                                    <label for="sssNumber">SSS Number</label>
                                    <div style="display: flex;">
                                        <input type="text" id="sssNumber" name="sssNumber" value="<?php echo htmlspecialchars($loadedProxyData['sssNumber'] ?? ''); ?>" placeholder="e.g. 33-1234567-8" style="flex: 1;">
                                        <button type="button" class="btn-verify-sss" onclick="verifySssPension()"><i class="fas fa-search"></i> Verify SSS</button>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="pensionAmount">Verified Monthly Pension (PHP)</label>
                                    <input type="number" step="0.01" id="pensionAmount" name="pensionAmount" value="<?php echo htmlspecialchars($loadedProxyData['pensionAmount'] ?? ''); ?>" readonly>
                                    <div id="pensionComplianceResult"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Burial Assistance Fields -->
                    <div id="burial-fields" style="display: none;">
                        <div class="form-section">
                            <h3><i class="fas fa-ribbon"></i> Burial Assistance Details</h3>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="dateOfDeath">Date of Passing (Deceased Senior)</label>
                                    <input type="date" id="dateOfDeath" name="dateOfDeath" value="<?php echo htmlspecialchars($loadedProxyData['dateOfDeath'] ?? ''); ?>" onchange="checkBurialDeadlineCompliance()">
                                    <div id="burialComplianceResult"></div>
                                </div>
                                <div class="form-group">
                                    <label for="relationshipToDeceased">Relationship of Claimant to Deceased</label>
                                    <input type="text" id="relationshipToDeceased" name="relationshipToDeceased" value="<?php echo htmlspecialchars($loadedProxyData['relationshipToDeceased'] ?? ''); ?>" placeholder="e.g. Spouse, Son, Daughter">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Proxy Representative Information (Visual validation block) -->
                    <div id="proxy-details-section" style="<?php echo $loadedProxyData ? 'display: block;' : 'display: none;'; ?>">
                        <div class="form-section" style="border: 1px dashed #2980b9; padding: 15px; border-radius: 8px; background-color: rgba(41, 128, 185, 0.05);">
                            <h3 style="color: #2980b9;"><i class="fas fa-id-card-alt"></i> Proxy Registration Active (High-Priority Line)</h3>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="proxyName">Proxy Representative Name</label>
                                    <input type="text" id="proxyName" name="proxyName" value="<?php echo htmlspecialchars($loadedProxyData['proxyName'] ?? ''); ?>" readonly>
                                </div>
                                <div class="form-group">
                                    <label for="proxyRelationship">Proxy Relationship</label>
                                    <input type="text" id="proxyRelationship" name="proxyRelationship" value="<?php echo htmlspecialchars($loadedProxyData['proxyRelationship'] ?? ''); ?>" readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Required Documents -->
                    <div class="form-section">
                        <h3><i class="fas fa-file-alt"></i> Required Documents</h3>
                        <div class="form-group">
                            <label for="proofOfAddress">Proof of Address</label>
                            <input type="file" id="proofOfAddress" name="proofOfAddress" required>
                        </div>
                        <div class="form-group">
                            <label for="idImage">ID Image / Supporting Document Photo</label>
                            <input type="file" id="idImage" name="idImage" required>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn" style="background-color: var(--primary);"><i class="fas fa-save"></i> Submit Application</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scan Proxy QR Modal -->
    <div id="proxyModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeProxyModal()">&times;</span>
            <h2 style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px; color: #3498db;"><i class="fas fa-qrcode"></i> Scan Proxy QR Token</h2>
            <p style="margin-bottom: 15px; font-size: 0.9rem; color: #94a3b8;">
                Paste or scan the encrypted Proxy QR token URL/payload generated by the relatives portal to retrieve details and place the senior in the High-Priority Queue.
            </p>
            <div class="form-group">
                <label for="modalToken">Decoded QR Link / Encrypted Token String</label>
                <textarea id="modalToken" class="form-control" rows="4" placeholder="Paste scan payload here..."></textarea>
            </div>
            <button type="button" class="btn" style="background-color: #3498db; margin-top: 15px;" onclick="loadProxyQrData()">
                <i class="fas fa-unlock-alt"></i> Decrypt & Auto-populate Form
            </button>
            <div id="modalError" style="color: #e74c3c; font-size: 0.85rem; margin-top: 10px;"></div>
        </div>
    </div>

    <script src="../assets/js/sidebar-toggle.js"></script>
    <script src="../assets/js/dark-mode.js"></script>
    <script>
        function openProxyModal() {
            document.getElementById('proxyModal').style.display = "block";
            document.getElementById('modalError').textContent = "";
            document.getElementById('modalToken').value = "";
        }

        function closeProxyModal() {
            document.getElementById('proxyModal').style.display = "none";
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            var modal = document.getElementById('proxyModal');
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }

        async function loadProxyQrData() {
            const tokenInput = document.getElementById('modalToken').value.trim();
            if (!tokenInput) {
                document.getElementById('modalError').textContent = "Token input cannot be empty.";
                return;
            }

            let token = tokenInput;
            // Extract token if they pasted a full redirect URL
            if (tokenInput.includes('token=')) {
                try {
                    const url = new URL(tokenInput);
                    token = url.searchParams.get('token');
                } catch(e) {
                    // Ignore and treat as raw token string
                }
            }

            try {
                const response = await fetch(`../api/scan_proxy_qr.php?token=${encodeURIComponent(token)}`);
                const result = await response.json();

                if (result.success) {
                    const data = result.data;
                    
                    // Populate basic details
                    document.getElementById('lastName').value = data.lastName || '';
                    document.getElementById('firstName').value = data.firstName || '';
                    document.getElementById('middleName').value = data.middleName || '';
                    document.getElementById('suffix').value = data.suffix || '';
                    document.getElementById('birthDate').value = data.birthDate || '';
                    document.getElementById('contactNumber').value = data.contactNumber || '';
                    document.getElementById('completeAddress').value = data.completeAddress || '';
                    document.getElementById('idNumber').value = data.transactionId || '';
                    
                    // Application type
                    document.getElementById('applicationType').value = data.applicationType || 'senior';
                    
                    // Proxy fields
                    document.getElementById('isProxy').value = 1;
                    document.getElementById('proxyToken').value = data.transactionId || '';
                    document.getElementById('proxyName').value = data.proxyName || '';
                    document.getElementById('proxyRelationship').value = data.proxyRelationship || '';
                    document.getElementById('proxy-details-section').style.display = 'block';

                    // Optional fields
                    if (data.sssNumber) {
                        document.getElementById('sssNumber').value = data.sssNumber;
                    }
                    if (data.dateOfDeath) {
                        document.getElementById('dateOfDeath').value = data.dateOfDeath;
                    }
                    if (data.relationshipToDeceased) {
                        document.getElementById('relationshipToDeceased').value = data.relationshipToDeceased;
                    }

                    // Refresh dynamics
                    toggleFields();
                    checkAgeCompliance();
                    
                    if (data.applicationType === 'pension' && data.sssNumber) {
                        verifySssPension();
                    }
                    if (data.applicationType === 'burial') {
                        checkBurialDeadlineCompliance();
                    }

                    closeProxyModal();
                    alert("Proxy details loaded! Added senior to the HIGH PRIORITY queue.");
                } else {
                    document.getElementById('modalError').textContent = result.message || 'Decryption failed.';
                }
            } catch (error) {
                console.error("Failed to fetch proxy QR details:", error);
                document.getElementById('modalError').textContent = "Server communication failure.";
            }
        }

        function toggleFields() {
            const type = document.getElementById('applicationType').value;
            
            // Hide all sub-sections
            document.getElementById('pwd-fields').style.display = 'none';
            document.getElementById('pension-fields').style.display = 'none';
            document.getElementById('burial-fields').style.display = 'none';

            // Clear dynamic requirements
            document.getElementById('sssNumber').removeAttribute('required');
            document.getElementById('dateOfDeath').removeAttribute('required');
            document.getElementById('relationshipToDeceased').removeAttribute('required');

            if (type === 'pwd') {
                document.getElementById('pwd-fields').style.display = 'block';
            } else if (type === 'pension') {
                document.getElementById('pension-fields').style.display = 'block';
                document.getElementById('sssNumber').setAttribute('required', 'required');
            } else if (type === 'burial') {
                document.getElementById('burial-fields').style.display = 'block';
                document.getElementById('dateOfDeath').setAttribute('required', 'required');
                document.getElementById('relationshipToDeceased').setAttribute('required', 'required');
            }
            
            // Trigger checks
            checkAgeCompliance();
        }

        // Live Localized Compliance: Age check (60 years old)
        function checkAgeCompliance() {
            const birthDateVal = document.getElementById('birthDate').value;
            const type = document.getElementById('applicationType').value;
            const container = document.getElementById('ageComplianceResult');
            
            if (!birthDateVal) {
                container.innerHTML = "";
                return;
            }

            if (type === 'pwd') {
                container.innerHTML = `<span class="compliance-badge compliance-info"><i class="fas fa-info-circle"></i> PWD application (No senior age restriction)</span>`;
                return;
            }

            // Calculate age
            const today = new Date();
            const birthDate = new Date(birthDateVal);
            let age = today.getFullYear() - birthDate.getFullYear();
            const m = today.getMonth() - birthDate.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }

            if (age >= 60) {
                container.innerHTML = `<span class="compliance-badge compliance-pass"><i class="fas fa-check"></i> Qualifies: Age matches Senior status (${age} years old)</span>`;
            } else {
                container.innerHTML = `<span class="compliance-badge compliance-fail"><i class="fas fa-times"></i> Disqualified: Age ${age} is under 60 (Pasig Ord. 17/2025 compliance)</span>`;
            }
        }

        // Live Localized Compliance: Pension check (SSS Cap P4,000)
        async function verifySssPension() {
            const sssNum = document.getElementById('sssNumber').value.trim();
            const container = document.getElementById('pensionComplianceResult');
            const amountInput = document.getElementById('pensionAmount');
            
            if (!sssNum) {
                alert("Please enter an SSS Number first.");
                return;
            }

            container.innerHTML = `<span class="compliance-badge compliance-info"><i class="fas fa-spinner fa-spin"></i> Checking SSS External Registry...</span>`;
            
            try {
                const response = await fetch(`../api/get_pension_check.php?sss_number=${encodeURIComponent(sssNum)}`);
                const data = await response.json();
                
                if (data.success) {
                    const amount = data.pension_amount;
                    amountInput.value = amount.toFixed(2);
                    
                    if (amount <= 4000) {
                        container.innerHTML = `<span class="compliance-badge compliance-pass"><i class="fas fa-check"></i> Qualifies: Pension (P${amount.toFixed(2)}) is <= P4,000 limit</span>`;
                    } else {
                        container.innerHTML = `<span class="compliance-badge compliance-fail"><i class="fas fa-times"></i> Disqualified: Pension (P${amount.toFixed(2)}) exceeds P4,000 cap</span>`;
                    }
                } else {
                    container.innerHTML = `<span class="compliance-badge compliance-fail"><i class="fas fa-exclamation-circle"></i> SSS Verification failed: ${data.message}</span>`;
                }
            } catch (err) {
                console.error(err);
                container.innerHTML = `<span class="compliance-badge compliance-fail"><i class="fas fa-times"></i> External connection timed out</span>`;
            }
        }

        // Live Localized Compliance: Burial deadline (30 working days)
        function checkBurialDeadlineCompliance() {
            const dateOfDeathVal = document.getElementById('dateOfDeath').value;
            const container = document.getElementById('burialComplianceResult');
            
            if (!dateOfDeathVal) {
                container.innerHTML = "";
                return;
            }

            // Calculate working days
            const start = new Date(dateOfDeathVal);
            const end = new Date();
            if (start > end) {
                container.innerHTML = `<span class="compliance-badge compliance-fail"><i class="fas fa-times"></i> Invalid date (Future death date)</span>`;
                return;
            }

            let workingDays = 0;
            let curDate = new Date(start.getTime());
            while (curDate < end) {
                const dayOfWeek = curDate.getDay();
                if (dayOfWeek !== 0 && dayOfWeek !== 6) { // Exclude Sun/Sat
                    workingDays++;
                }
                curDate.setDate(curDate.getDate() + 1);
            }

            if (workingDays <= 30) {
                container.innerHTML = `<span class="compliance-badge compliance-pass"><i class="fas fa-check"></i> Qualifies: Filed within deadline (${workingDays}/30 working days elapsed)</span>`;
            } else {
                container.innerHTML = `<span class="compliance-badge compliance-fail"><i class="fas fa-times"></i> Disqualified: Outside 30-working-day filing limit (${workingDays} working days elapsed)</span>`;
            }
        }

        // Intercept Form Submit to block invalid compliance cases
        document.getElementById('mainAppForm').addEventListener('submit', function(e) {
            const type = document.getElementById('applicationType').value;
            
            // Age compliance block
            if (type !== 'pwd') {
                const birthDateVal = document.getElementById('birthDate').value;
                const today = new Date();
                const birthDate = new Date(birthDateVal);
                let age = today.getFullYear() - birthDate.getFullYear();
                const m = today.getMonth() - birthDate.getMonth();
                if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
                    age--;
                }
                if (age < 60) {
                    alert("Localized Compliance Error:\nApplicant is under 60 years old. Senior citizen benefits require age 60+.");
                    e.preventDefault();
                    return;
                }
            }

            // Pension compliance block
            if (type === 'pension') {
                const pensionAmt = parseFloat(document.getElementById('pensionAmount').value);
                if (isNaN(pensionAmt)) {
                    alert("Localized Compliance Error:\nPlease verify SSS Pension before submitting.");
                    e.preventDefault();
                    return;
                } else if (pensionAmt > 4000) {
                    alert("Localized Compliance Error:\nApplicant is receiving an SSS pension of P" + pensionAmt.toFixed(2) + ", which exceeds the P4,000 cap mandated under Pasig Ordinance No. 17 (Series of 2025).");
                    e.preventDefault();
                    return;
                }
            }

            // Burial deadline compliance block
            if (type === 'burial') {
                const dateOfDeathVal = document.getElementById('dateOfDeath').value;
                const start = new Date(dateOfDeathVal);
                const end = new Date();
                let workingDays = 0;
                let curDate = new Date(start.getTime());
                while (curDate < end) {
                    const dayOfWeek = curDate.getDay();
                    if (dayOfWeek !== 0 && dayOfWeek !== 6) {
                        workingDays++;
                    }
                    curDate.setDate(curDate.getDate() + 1);
                }
                if (workingDays > 30) {
                    alert("Localized Compliance Error:\nBurial assistance claims must be submitted within 30 working days from the date of death (current: " + workingDays + " working days). Submission is blocked.");
                    e.preventDefault();
                    return;
                }
            }
        });

        // Initialize toggle on load
        toggleFields();
    </script>
</body>
</html>