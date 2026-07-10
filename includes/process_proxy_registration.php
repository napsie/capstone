<?php
require_once __DIR__ . '/crypto.php';
require_once __DIR__ . '/db_connect.php';

/**
 * Helper function to save uploaded files to the uploads/ directory
 */
function saveUploadedProxyFile(string $fileKey, string $prefix, string $fieldName): ?string
{
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    
    $tmpName = $_FILES[$fileKey]['tmp_name'];
    $origName = $_FILES[$fileKey]['name'];
    $size = $_FILES[$fileKey]['size'];
    
    if ($size <= 0) {
        return null;
    }

    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($tmpName);
    
    if (!in_array($mimeType, $allowedMimes)) {
        return null; // Invalid type, will not save
    }

    $ext = pathinfo($origName, PATHINFO_EXTENSION);
    if (empty($ext)) {
        $ext = ($mimeType === 'application/pdf') ? 'pdf' : 'jpg';
    }
    
    $newFilename = $prefix . '_' . $fieldName . '.' . strtolower($ext);
    $dest = __DIR__ . '/../uploads/' . $newFilename;
    
    // Ensure uploads directory exists just in case
    if (!is_dir(__DIR__ . '/../uploads/')) {
        mkdir(__DIR__ . '/../uploads/', 0777, true);
    }

    if (move_uploaded_file($tmpName, $dest)) {
        return $newFilename;
    }
    
    return null;
}

/**
 * Process proxy pre-registration and benefit claim POST requests.
 */
function processProxyRegistration(): array
{
    global $conn;

    $result = [
        'success' => false,
        'message' => '',
        'qrCodeUrl' => '',
        'transactionId' => '',
        'option' => '',
        'applicationType' => '',
    ];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['proxy_submit'])) {
        return $result;
    }

    $portalOption = trim($_POST['portal_option'] ?? 'new_senior');

    if ($portalOption === 'new_senior') {
        // --- OPTION A: PRE-REGISTRATION FOR NEW ID ---
        $lastName = trim($_POST['lastName'] ?? '');
        $firstName = trim($_POST['firstName'] ?? '');
        $middleName = trim($_POST['middleName'] ?? '');
        $suffix = trim($_POST['suffix'] ?? '');
        $fullName = trim($firstName . ' ' . $middleName . ' ' . $lastName . ' ' . $suffix);
        $birthDate = trim($_POST['birthDate'] ?? '');
        $contactNumber = trim($_POST['contactNumber'] ?? '');
        $completeAddress = trim($_POST['completeAddress'] ?? '');
        $barangay = trim($_POST['barangay'] ?? '');
        $applicationType = trim($_POST['applicationType'] ?? 'senior');
        
        $sssNumber = trim($_POST['sssNumber'] ?? '');
        $pensionAmount = !empty($_POST['pensionAmount']) ? floatval($_POST['pensionAmount']) : null;
        $dateOfDeath = trim($_POST['dateOfDeath'] ?? '');
        $relationshipToDeceased = trim($_POST['relationshipToDeceased'] ?? '');
        
        $proxyName = trim($_POST['proxyName'] ?? '');
        $proxyRelationship = trim($_POST['proxyRelationship'] ?? '');
        $proxyContactNumber = trim($_POST['proxyContactNumber'] ?? '');

        // Validation: age check in 2026
        if (!empty($birthDate)) {
            $dob = new DateTime($birthDate);
            $targetDate = new DateTime('2026-07-08');
            $age = $targetDate->diff($dob)->y;
            
            if ($age < 60) {
                $result['message'] = "Eligibility Check Failed: Applicant must be at least 60 years old (Calculated Age in 2026: {$age}).";
                return $result;
            }
        } else {
            $result['message'] = "Birth Date is required.";
            return $result;
        }

        $transactionId = 'PRX-' . strtoupper(bin2hex(random_bytes(3)));

        // Handle File Uploads
        $psaBirthCert = saveUploadedProxyFile('psa_birth_cert_file', $transactionId, 'psa_birth_cert');
        $barangayResidency = saveUploadedProxyFile('barangay_residency_file', $transactionId, 'barangay_residency');
        $comelecCert = saveUploadedProxyFile('comelec_cert_file', $transactionId, 'comelec_cert');
        $proofOfLife = saveUploadedProxyFile('proof_of_life_file', $transactionId, 'proof_of_life');
        $authLetter = saveUploadedProxyFile('auth_letter_file', $transactionId, 'auth_letter');
        $proxyId = saveUploadedProxyFile('proxy_id_file', $transactionId, 'proxy_id');
        $proxyBirthCert = saveUploadedProxyFile('proxy_birth_cert_file', $transactionId, 'proxy_birth_cert');

        try {
            $conn->beginTransaction();

            // Insert into applications table
            // Set status to pending and workflow_state to Submitted
            $sql = "INSERT INTO applications (
                        id_number, full_name, lastName, firstName, middleName, suffix,
                        birth_date, contact_number, complete_address, barangay,
                        status, workflow_state, is_proxy_application, proxy_name,
                        proxy_relationship, proxy_contact_number, proxy_token, priority_level, application_type,
                        sss_number, pension_amount, date_of_death, relationship_to_deceased,
                        psa_birth_cert, barangay_residency, comelec_cert, proof_of_life,
                        auth_letter, proxy_id, proxy_birth_cert
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $transactionId, $fullName, $lastName, $firstName, $middleName, $suffix,
                $birthDate, $contactNumber, $completeAddress, $barangay,
                'pending', 'Submitted', 1, $proxyName,
                $proxyRelationship, $proxyContactNumber, $transactionId, 'high', $applicationType,
                $sssNumber, $pensionAmount, !empty($dateOfDeath) ? $dateOfDeath : null, !empty($relationshipToDeceased) ? $relationshipToDeceased : null,
                $psaBirthCert, $barangayResidency, $comelecCert, $proofOfLife,
                $authLetter, $proxyId, $proxyBirthCert
            ]);

            // Add FSM history entry: [Draft] -> [Submitted]
            $stmtHist = $conn->prepare("INSERT INTO application_history (application_id, previous_state, new_state, changed_by, comments) VALUES (?, ?, ?, ?, ?)");
            $stmtHist->execute([
                $transactionId, 'Draft', 'Submitted', 'Proxy Representative (Maria)', 'Pre-registration submitted via proxy portal.'
            ]);

            $conn->commit();

            // Generate Token redirect payload
            $payload = [
                'transactionId' => $transactionId,
                'lastName' => $lastName,
                'firstName' => $firstName,
                'middleName' => $middleName,
                'suffix' => $suffix,
                'birthDate' => $birthDate,
                'contactNumber' => $contactNumber,
                'completeAddress' => $completeAddress,
                'barangay' => $barangay,
                'applicationType' => $applicationType,
                'sssNumber' => $sssNumber,
                'dateOfDeath' => $dateOfDeath,
                'relationshipToDeceased' => $relationshipToDeceased,
                'proxyName' => $proxyName,
                'proxyRelationship' => $proxyRelationship,
                'proxyContactNumber' => $proxyContactNumber,
                'created_at' => date('Y-m-d H:i:s'),
            ];
            $encryptedToken = ProxyCrypto::encrypt($payload);

            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443)
                ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'];
            $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
            if (basename($scriptDir) !== 'pages') {
                $scriptDir = rtrim($scriptDir, '/') . '/pages';
            }
            $scanUrl = $protocol . $host . $scriptDir . '/scan_proxy_qr_redirect.php?token=' . urlencode($encryptedToken);

            $result['success'] = true;
            $result['transactionId'] = $transactionId;
            $result['qrCodeUrl'] = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($scanUrl);
            $result['option'] = 'new_senior';
            $result['applicationType'] = $applicationType;

        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $result['message'] = "Database error: " . $e->getMessage();
        }

    } else if ($portalOption === 'existing_benefits') {
        // --- OPTION B: PENSION CLAIM FOR EXISTING SENIOR ---
        $seniorCitizenId = trim($_POST['seniorCitizenId'] ?? '');
        
        if (empty($seniorCitizenId)) {
            $result['message'] = "Senior Citizen ID Number is required.";
            return $result;
        }

        try {
            // Verify profile integrity (exists, is approved/verified, is a proxy application)
            $stmtVerify = $conn->prepare("SELECT * FROM applications WHERE id_number = ? AND (workflow_state = 'Approved' OR workflow_state = 'Verified') AND is_proxy_application = 1");
            $stmtVerify->execute([$seniorCitizenId]);
            $senior = $stmtVerify->fetch(PDO::FETCH_ASSOC);

            if (!$senior) {
                $result['message'] = "Profile Integrity Check Failed: No verified/approved bedridden senior citizens found with the ID: {$seniorCitizenId}.";
                return $result;
            }

            // Generate Pension Tracking ID
            $pensionTransactionId = 'PEN-' . strtoupper(bin2hex(random_bytes(3)));

            // Handle uploads
            $homeVisitationForm = saveUploadedProxyFile('home_visitation_form_file', $pensionTransactionId, 'home_visitation_form');
            $landbankForm = saveUploadedProxyFile('landbank_enrollment_form_file', $pensionTransactionId, 'landbank_enrollment_form');

            if (!$homeVisitationForm || !$landbankForm) {
                $result['message'] = "Please upload both required documents (Home Visitation Form and Land Bank Cash Card Enrollment Form).";
                return $result;
            }

            $conn->beginTransaction();

            // Insert sub-process Pension Benefit application
            $sql = "INSERT INTO applications (
                        id_number, full_name, lastName, firstName, middleName, suffix,
                        birth_date, contact_number, complete_address, barangay,
                        status, workflow_state, is_proxy_application, proxy_name,
                        proxy_relationship, proxy_contact_number, proxy_token, priority_level, application_type,
                        parent_senior_id, home_visitation_form, landbank_enrollment_form
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmtPension = $conn->prepare($sql);
            $stmtPension->execute([
                $pensionTransactionId, $senior['full_name'], $senior['lastName'], $senior['firstName'], $senior['middleName'], $senior['suffix'],
                $senior['birth_date'], $senior['contact_number'], $senior['complete_address'], $senior['barangay'],
                'pending', 'Pension Benefit - Submitted', 1, $senior['proxy_name'],
                $senior['proxy_relationship'], $senior['proxy_contact_number'], $senior['proxy_token'], 'high', 'pension',
                $seniorCitizenId, $homeVisitationForm, $landbankForm
            ]);

            // Add FSM history entry: [Draft] -> [Pension Benefit - Submitted]
            $stmtHist = $conn->prepare("INSERT INTO application_history (application_id, previous_state, new_state, changed_by, comments) VALUES (?, ?, ?, ?, ?)");
            $stmtHist->execute([
                $pensionTransactionId, 'Draft', 'Pension Benefit - Submitted', 'Proxy Representative (Maria)', 'Pension benefit application submitted.'
            ]);

            $conn->commit();

            // Tracking QR URL
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443)
                ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'];
            $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
            if (basename($scriptDir) !== 'pages') {
                $scriptDir = rtrim($scriptDir, '/') . '/pages';
            }
            $trackerUrl = $protocol . $host . $scriptDir . '/benefit_tracker.php?token=' . urlencode($pensionTransactionId);

            $result['success'] = true;
            $result['transactionId'] = $pensionTransactionId;
            $result['qrCodeUrl'] = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($trackerUrl);
            $result['option'] = 'existing_benefits';
            $result['applicationType'] = 'pension';

        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $result['message'] = "Database error: " . $e->getMessage();
        }
    }

    return $result;
}
