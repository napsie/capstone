<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/filing_deadline.php';

/**
 * Helper function to save uploaded files to the uploads/ directory
 */
function saveUploadedProxyFile(string $fileKey, string $prefix, string $fieldName, bool $imageOnly = false): ?string
{
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    
    $tmpName = $_FILES[$fileKey]['tmp_name'];
    $size = $_FILES[$fileKey]['size'];
    
    if ($size <= 0 || $size > 10 * 1024 * 1024) {
        return null;
    }

    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'application/pdf' => 'pdf',
    ];
    if ($imageOnly) {
        $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
        ];
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($tmpName);
    
    if (!isset($allowedMimes[$mimeType])) {
        return null; // Invalid type, will not save
    }

    $ext = $allowedMimes[$mimeType];
    
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
 * Generate a compact, non-sequential tracking code. Four characters provide
 * more than one million combinations; the code grows automatically when half
 * of the available space for the current length is in use.
 */
function generateCompactApplicationToken(PDO $conn, string $prefix): string
{
    $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $alphabetSize = strlen($alphabet);
    $countStmt = $conn->prepare('SELECT COUNT(*) FROM applications WHERE id_number LIKE ?');
    $countStmt->execute([$prefix . '-%']);
    $existingCount = (int)$countStmt->fetchColumn();
    $length = 4;

    while ($existingCount >= (int)floor(($alphabetSize ** $length) / 2)) {
        $length++;
    }

    $existsStmt = $conn->prepare('SELECT 1 FROM applications WHERE id_number = ? OR proxy_token = ? LIMIT 1');
    for ($attempt = 0; $attempt < 80; $attempt++) {
        $suffix = '';
        for ($i = 0; $i < $length; $i++) {
            $suffix .= $alphabet[random_int(0, $alphabetSize - 1)];
        }
        $token = $prefix . '-' . $suffix;
        $existsStmt->execute([$token, $token]);
        if (!$existsStmt->fetchColumn()) {
            return $token;
        }

        // An unusual number of collisions indicates a crowded code space.
        if ($attempt === 39) {
            $length++;
        }
    }

    throw new RuntimeException('Unable to generate a unique application token.');
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
        $placeOfBirth = trim($_POST['placeOfBirth'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $civilStatus = trim($_POST['civilStatus'] ?? '');
        $mothersMaidenName = trim($_POST['mothersMaidenName'] ?? '');
        $houseNo = trim($_POST['houseNo'] ?? '');
        $street = trim($_POST['street'] ?? '');
        $barangay = trim($_POST['barangay'] ?? '');
        $zipCode = trim($_POST['zipCode'] ?? '');
        $landmark = trim($_POST['landmark'] ?? '');
        $seniorEmail = trim($_POST['seniorEmail'] ?? '');
        $completeAddress = trim(implode(', ', array_filter([
            $houseNo, $street, $barangay !== '' ? 'Barangay ' . $barangay : '',
            'Pasig City', $zipCode,
        ])));
        $healthCondition = '';
        $mobilityStatus = trim($_POST['mobilityStatus'] ?? '');
        $livingArrangement = trim($_POST['livingArrangement'] ?? '');
        $applicationType = trim($_POST['applicationType'] ?? 'senior');
        $requestedBenefit = trim($_POST['requestedBenefit'] ?? '');
        $visitPurpose = $requestedBenefit;
        $idPurpose = trim($_POST['idPurpose'] ?? '');
        $emergencyContactName = trim($_POST['emergencyContactName'] ?? '');
        $emergencyContact = trim($_POST['emergencyContact'] ?? '');
        $visitSummary = trim($_POST['visitSummary'] ?? '');
        $isPensioner = isset($_POST['isPensioner']) && in_array((string) $_POST['isPensioner'], ['0', '1'], true)
            ? (int) $_POST['isPensioner'] : null;
        $pensionSource = trim($_POST['pensionSource'] ?? '');
        $familySupport = isset($_POST['familySupport']) && in_array((string) $_POST['familySupport'], ['0', '1'], true)
            ? (int) $_POST['familySupport'] : null;
        $familySupportAmount = isset($_POST['familySupportAmount']) && $_POST['familySupportAmount'] !== ''
            ? (float) $_POST['familySupportAmount'] : null;
        $personalIncome = isset($_POST['personalIncome']) && in_array((string) $_POST['personalIncome'], ['0', '1'], true)
            ? (int) $_POST['personalIncome'] : null;
        $personalIncomeAmount = isset($_POST['personalIncomeAmount']) && $_POST['personalIncomeAmount'] !== ''
            ? (float) $_POST['personalIncomeAmount'] : null;
        $incomeSource = trim($_POST['incomeSource'] ?? '');
        $ownsHouse = isset($_POST['ownsHouse']) && in_array((string) $_POST['ownsHouse'], ['0', '1'], true)
            ? (int) $_POST['ownsHouse'] : null;
        $isRenter = isset($_POST['isRenter']) && in_array((string) $_POST['isRenter'], ['0', '1'], true)
            ? (int) $_POST['isRenter'] : null;
        $nameOnCard = trim($_POST['nameOnCard'] ?? '');
        $tin = trim($_POST['tin'] ?? '');
        $seniorIdTypePresented = trim($_POST['seniorIdTypePresented'] ?? '');
        $nationality = trim($_POST['nationality'] ?? '');
        $sourceOfFunds = trim($_POST['sourceOfFunds'] ?? '');
        $milestoneAge = trim($_POST['milestoneAge'] ?? '');
        $claimantName = trim($_POST['claimantName'] ?? '');
        $claimantRelationship = trim($_POST['claimantRelationship'] ?? '');
        $claimantContact = trim($_POST['claimantContact'] ?? '');
        $otherAssistanceDetails = trim($_POST['otherAssistanceDetails'] ?? '');
        
        $sssNumber = trim($_POST['sssNumber'] ?? '');
        $pensionAmount = !empty($_POST['pensionAmount']) ? floatval($_POST['pensionAmount']) : null;
        $dateOfDeath = trim($_POST['dateOfDeath'] ?? '');
        $relationshipToDeceased = trim($_POST['relationshipToDeceased'] ?? '');
        
        // Representative intake is intentionally disabled for the public form.
        $proxyName = $proxyRelationship = $proxyContactNumber = '';
        $proxyBirthDate = $proxyEmail = $proxyAddress = $proxyIdType = $proxyIdNumber = '';

        $requiredFields = [
            'Benefit or Service Requested' => $requestedBenefit,
            'Last Name' => $lastName, 'First Name' => $firstName,
            'Place of Birth' => $placeOfBirth, 'Sex' => $gender,
            'Civil Status' => $civilStatus, 'House / Unit Number' => $houseNo,
            'Street / Subdivision' => $street, 'Barangay' => $barangay,
            'Mobility Status' => $mobilityStatus, 'Living Arrangement' => $livingArrangement,
        ];
        foreach ($requiredFields as $label => $value) {
            if ($value === '') {
                $result['message'] = $label . ' is required.';
                return $result;
            }
        }

        if (!preg_match('/^09\d{9}$/', $contactNumber)) {
            $result['message'] = 'Contact number must be a valid 11-digit Philippine mobile number.';
            return $result;
        }
        if ($seniorEmail !== '' && !filter_var($seniorEmail, FILTER_VALIDATE_EMAIL)) {
            $result['message'] = 'Please provide a valid email address.';
            return $result;
        }
        if (!isset($_POST['confirmPrivacy'])) {
            $result['message'] = 'Applicant certification and privacy consent are required.';
            return $result;
        }
        if (!in_array($mobilityStatus, ['Physically Fit', 'Needs Mobility Assistance', 'Bedridden', 'Frail / Sickly', 'PWD'], true)) {
            $result['message'] = 'Please select a valid mobility status.';
            return $result;
        }

        $allowedBenefits = [
            'Senior Citizen ID Registration',
            'Local Social Pension Assessment',
            'Land Bank Cash Card Enrollment',
            'Milestone Cash Gift',
            'Burial Assistance',
        ];
        if (!in_array($requestedBenefit, $allowedBenefits, true)) {
            $result['message'] = 'Please select a valid benefit or service.';
            return $result;
        }
        $applicationType = [
            'Senior Citizen ID Registration' => 'senior',
            'Local Social Pension Assessment' => 'pension',
            'Land Bank Cash Card Enrollment' => 'landbank',
            'Milestone Cash Gift' => 'milestone_gift',
            'Burial Assistance' => 'burial',
        ][$requestedBenefit];

        switch ($requestedBenefit) {
            case 'Senior Citizen ID Registration':
                if (!in_array($idPurpose, ['new', 'lost', 'change', 'transfer'], true)) {
                    $result['message'] = 'Please select the ID application purpose.';
                    return $result;
                }
                if ($emergencyContactName === '' || !preg_match('/^09\d{9}$/', $emergencyContact)) {
                    $result['message'] = 'Please provide the emergency contact name and a valid 11-digit mobile number.';
                    return $result;
                }
                break;
            case 'Local Social Pension Assessment':
                if ($isPensioner === null || $familySupport === null || $personalIncome === null ||
                    $incomeSource === '' || $ownsHouse === null || $isRenter === null) {
                    $result['message'] = 'Please complete all pension and household-income questions.';
                    return $result;
                }
                if ($isPensioner === 1 && ($pensionSource === '' || $pensionAmount === null)) {
                    $result['message'] = 'Pension source and monthly amount are required when the senior receives a pension.';
                    return $result;
                }
                if ($familySupport === 1 && $familySupportAmount === null) {
                    $result['message'] = 'Enter the monthly family support amount.';
                    return $result;
                }
                if ($personalIncome === 1 && $personalIncomeAmount === null) {
                    $result['message'] = 'Enter the senior\'s monthly personal income.';
                    return $result;
                }
                break;
            case 'Land Bank Cash Card Enrollment':
                if ($nameOnCard === '' || $tin === '' || $nationality === '' || $seniorIdTypePresented === '' || $sourceOfFunds === '') {
                    $result['message'] = 'Please complete all required Land Bank enrollment information.';
                    return $result;
                }
                if (mb_strlen($nameOnCard) > 23) {
                    $result['message'] = 'The name on the cash card cannot exceed 23 characters.';
                    return $result;
                }
                break;
            case 'Milestone Cash Gift':
                if (!in_array($milestoneAge, ['80', '85', '90', '95', '100'], true)) {
                    $result['message'] = 'Please select the milestone age being claimed.';
                    return $result;
                }
                if ($claimantName === '' || $claimantRelationship === '' || !preg_match('/^09\d{9}$/', $claimantContact)) {
                    $result['message'] = 'Please complete the claimant details with a valid 11-digit mobile number.';
                    return $result;
                }
                break;
            case 'Burial Assistance':
                if ($dateOfDeath === '' || $relationshipToDeceased === '') {
                    $result['message'] = 'Date of passing and relationship to the deceased are required.';
                    return $result;
                }
                $burialFilingDays = filingWorkingDays($dateOfDeath, date('Y-m-d'));
                if ($burialFilingDays === null) {
                    $result['message'] = 'Please provide a valid date of passing that is not in the future.';
                    return $result;
                }
                if ($burialFilingDays > 30) {
                    $result['message'] = "Burial assistance must be filed within 30 working days from the date of passing ({$burialFilingDays} working days elapsed).";
                    return $result;
                }
                break;
        }

        foreach ([$pensionAmount, $familySupportAmount, $personalIncomeAmount] as $amount) {
            if ($amount !== null && $amount < 0) {
                $result['message'] = 'Financial amounts cannot be negative.';
                return $result;
            }
        }

        if ($requestedBenefit !== 'Senior Citizen ID Registration') {
            $idPurpose = '';
            $emergencyContactName = '';
            $emergencyContact = '';
        }
        $visitSummary = '';
        if ($requestedBenefit !== 'Local Social Pension Assessment') {
            $isPensioner = null;
            $pensionSource = '';
            $sssNumber = '';
            $pensionAmount = null;
            $familySupport = null;
            $familySupportAmount = null;
            $personalIncome = null;
            $personalIncomeAmount = null;
            $incomeSource = '';
            $ownsHouse = null;
            $isRenter = null;
        } else {
            if ($isPensioner === 0) { $pensionSource = ''; $pensionAmount = null; }
            if ($familySupport === 0) { $familySupportAmount = null; }
            if ($personalIncome === 0) { $personalIncomeAmount = null; }
        }
        if ($requestedBenefit !== 'Land Bank Cash Card Enrollment') {
            $nameOnCard = '';
            $tin = '';
            $seniorIdTypePresented = '';
            $nationality = '';
            $sourceOfFunds = '';
        }
        if ($requestedBenefit !== 'Milestone Cash Gift') {
            $milestoneAge = '';
            $claimantName = '';
            $claimantRelationship = '';
            $claimantContact = '';
        }
        $otherAssistanceDetails = '';

        if (!in_array($gender, ['Male', 'Female'], true) ||
            !in_array($civilStatus, ['Single', 'Married', 'Widowed', 'Separated'], true) ||
            !in_array($livingArrangement, ['Living alone', 'With spouse', 'With children or relatives', 'With caregiver', 'Care facility'], true)) {
            $result['message'] = 'One or more selected options are invalid. Please review the form.';
            return $result;
        }

        // Applicant age validation
        try {
            if ($birthDate === '') {
                throw new Exception('Missing birth date');
            }
            $dob = new DateTime($birthDate);
            $targetDate = new DateTime('today');
            $age = $targetDate->diff($dob)->y;
            if ($dob > $targetDate || $age < 60) {
                $result['message'] = "Eligibility Check Failed: Applicant must be at least 60 years old (Calculated Age in 2026: {$age}).";
                return $result;
            }
            if ($requestedBenefit === 'Local Social Pension Assessment' && $age < 65) {
                $result['message'] = 'Local Social Pension assessment requires the senior to be at least 65 years old.';
                return $result;
            }
            if ($requestedBenefit === 'Milestone Cash Gift') {
                $eligibleMilestone = $milestoneAge === '100' ? $age >= 100 : $age === (int) $milestoneAge;
                if (!$eligibleMilestone) {
                    $result['message'] = "The selected milestone ({$milestoneAge}) does not match the senior's current age ({$age}).";
                    return $result;
                }
            }
        } catch (Exception $e) {
            $result['message'] = 'Please provide a valid senior birth date.';
            return $result;
        }

        $benefitDocumentLabels = [
            'Senior Citizen ID Registration' => ['PSA Birth Certificate', 'Barangay Residency Certificate', 'COMELEC Certification'],
            'Local Social Pension Assessment' => ['Senior Citizen ID or Valid Government ID', 'Barangay Certificate of Indigency', 'SSS / GSIS Pension Record or Certification'],
            'Land Bank Cash Card Enrollment' => ['Senior Citizen ID or Proof of Registration', 'Valid Government-Issued ID', 'Proof of Address'],
            'Milestone Cash Gift' => ['Senior Citizen ID', 'Certified PSA Birth Certificate', 'Latest Whole-Body Photo'],
            'Burial Assistance' => ['Death Certificate', 'Senior Citizen ID or Proof of Senior Status', 'Claimant ID and Proof of Relationship'],
        ];
        $selectedDocumentLabels = $benefitDocumentLabels[$requestedBenefit];
        $requiredUploads = [
            'psa_birth_cert_file' => $selectedDocumentLabels[0],
            'barangay_residency_file' => $selectedDocumentLabels[1],
            'comelec_cert_file' => $selectedDocumentLabels[2],
            'id_photo_file' => 'Senior ID Photo',
            'proof_of_life_file' => 'Current Senior Photo / Proof of Life',
        ];
        foreach ($requiredUploads as $key => $label) {
            if (!isset($_FILES[$key]) || $_FILES[$key]['error'] !== UPLOAD_ERR_OK) {
                $result['message'] = $label . ' is required.';
                return $result;
            }
        }

        $transactionId = generateCompactApplicationToken($conn, 'PRX');
        $priorityLevel = 'normal';

        // Handle File Uploads
        $psaBirthCert = saveUploadedProxyFile('psa_birth_cert_file', $transactionId, 'psa_birth_cert');
        $barangayResidency = saveUploadedProxyFile('barangay_residency_file', $transactionId, 'barangay_residency');
        $comelecCert = saveUploadedProxyFile('comelec_cert_file', $transactionId, 'comelec_cert');
        $idImage = saveUploadedProxyFile('id_photo_file', $transactionId, 'id_photo', true);
        $proofOfLife = saveUploadedProxyFile('proof_of_life_file', $transactionId, 'proof_of_life');
        $authLetter = $proxyId = $proxyBirthCert = null;

        $requiredProcessedFiles = [$psaBirthCert, $barangayResidency, $comelecCert, $idImage, $proofOfLife];
        if (in_array(null, $requiredProcessedFiles, true)) {
            $result['message'] = 'One or more documents could not be processed. Upload only valid JPEG, PNG, GIF, or PDF files and try again.';
            return $result;
        }

        try {
            $conn->beginTransaction();

            // A completed public application is immediately available in the
            // standard barangay counter queue.
            $sql = "INSERT INTO applications (
                        id_number, full_name, lastName, firstName, middleName, suffix,
                        birth_date, contact_number, emergency_contact, emergency_contact_name, complete_address, barangay,
                        status, workflow_state, requested_benefit, is_proxy_application, proxy_name,
                        proxy_relationship, proxy_contact_number, proxy_token, priority_level, application_type,
                        sss_number, pension_amount, date_of_death, relationship_to_deceased,
                        psa_birth_cert, barangay_residency, comelec_cert, proof_of_life,
                        auth_letter, proxy_id, proxy_birth_cert,
                        email_address, place_of_birth, gender, civil_status, mothers_maiden_name,
                        house_no, street, city, province, zip_code, landmark, health_status,
                        health_condition, living_arrangement, visit_purpose,
                        id_purpose, visit_summary, is_pensioner, pension_source,
                        family_support, family_support_amount, personal_income, personal_income_amount,
                        income_source, owns_house, is_renter,
                        name_on_card, tin, id_type_presented, nationality, source_of_funds, milestone_age,
                        claimant_name, claimant_relationship, claimant_contact, additional_notes,
                        proxy_birth_date, proxy_email, proxy_address, proxy_id_type, proxy_id_number,
                        id_image
                    ) VALUES (" . implode(', ', array_fill(0, 75, '?')) . ")";

            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $transactionId, $fullName, $lastName, $firstName, $middleName, $suffix,
                $birthDate, $contactNumber, $emergencyContact, $emergencyContactName ?: null, $completeAddress, $barangay,
                'pending', 'For Review', $requestedBenefit, 0, null,
                $proxyRelationship ?: null, $proxyContactNumber ?: null, $transactionId, $priorityLevel, $applicationType,
                $sssNumber, $pensionAmount, !empty($dateOfDeath) ? $dateOfDeath : null, !empty($relationshipToDeceased) ? $relationshipToDeceased : null,
                $psaBirthCert, $barangayResidency, $comelecCert, $proofOfLife,
                $authLetter, $proxyId, $proxyBirthCert,
                $seniorEmail ?: null, $placeOfBirth, $gender, $civilStatus, $mothersMaidenName ?: null,
                $houseNo, $street, 'Pasig City', 'Metro Manila', $zipCode ?: null, $landmark ?: null, $mobilityStatus,
                $healthCondition, $livingArrangement, $visitPurpose,
                $idPurpose ?: null, $visitSummary ?: null, $isPensioner, $pensionSource ?: null,
                $familySupport, $familySupportAmount, $personalIncome, $personalIncomeAmount,
                $incomeSource ?: null, $ownsHouse, $isRenter,
                $nameOnCard ?: null, $tin ?: null, $seniorIdTypePresented ?: null, $nationality ?: null,
                $sourceOfFunds ?: null, $milestoneAge ?: null,
                $claimantName ?: null, $claimantRelationship ?: null, $claimantContact ?: null,
                $otherAssistanceDetails ?: null,
                $proxyBirthDate ?: null, $proxyEmail ?: null, $proxyAddress ?: null, $proxyIdType ?: null, $proxyIdNumber ?: null,
                $idImage
            ]);

            // Add workflow history entry for the active counter queue.
            $stmtHist = $conn->prepare("INSERT INTO application_history (application_id, previous_state, new_state, changed_by, comments) VALUES (?, ?, ?, ?, ?)");
            $stmtHist->execute([
                $transactionId, 'Draft', 'For Review', 'Senior Applicant',
                'Public senior application submitted directly to the Department Admin verification queue.'
            ]);

            $conn->commit();

            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443)
                ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'];
            $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
            if (basename($scriptDir) !== 'pages') {
                $scriptDir = rtrim($scriptDir, '/') . '/pages';
            }
            // Keep QR content short so ordinary webcams can read it from a
            // phone screen or printed photo. The reference contains no PII;
            // personal details are resolved only inside an authenticated page.
            $scanUrl = $protocol . $host . $scriptDir . '/scan_proxy_qr_redirect.php?token=' . urlencode($transactionId);

            $result['success'] = true;
            $result['transactionId'] = $transactionId;
            $result['qrCodeUrl'] = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&ecc=M&qzone=4&data=' . urlencode($scanUrl);
            $result['option'] = 'new_senior';
            $result['applicationType'] = $applicationType;

        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            // The uploaded files are named with this transaction token and do
            // not belong in storage when the database record was not created.
            foreach ([$psaBirthCert, $barangayResidency, $comelecCert, $proofOfLife, $idImage] as $uploadedFile) {
                if (!$uploadedFile) continue;
                $uploadedPath = __DIR__ . '/../uploads/' . basename($uploadedFile);
                if (is_file($uploadedPath)) @unlink($uploadedPath);
            }
            error_log(sprintf(
                'Public %s application save failed for token %s: %s',
                $applicationType,
                $transactionId,
                $e->getMessage()
            ));
            $result['message'] = "We could not save the pre-registration. Please review the form and try again.";
        }

    } else if ($portalOption === 'existing_benefits') {
        // --- OPTION B: PENSION CLAIM FOR EXISTING SENIOR ---
        $seniorCitizenId = trim($_POST['seniorCitizenId'] ?? '');
        
        if (empty($seniorCitizenId)) {
            $result['message'] = "Senior Citizen ID Number is required.";
            return $result;
        }

        try {
            // The benefit request must reference the official Senior Citizen ID;
            // PRX/PEN application tokens are only for status tracking.
            $stmtVerify = $conn->prepare("SELECT * FROM applications
                                          WHERE application_type = 'senior'
                                            AND senior_id_no = ?
                                            AND workflow_state IN ('Verified', 'Approved', 'Released')
                                            AND COALESCE(is_archived, 0) = 0
                                          LIMIT 1");
            $stmtVerify->execute([$seniorCitizenId]);
            $senior = $stmtVerify->fetch(PDO::FETCH_ASSOC);

            if (!$senior) {
                $result['message'] = "Profile Integrity Check Failed: No verified or approved senior citizen was found with the ID: {$seniorCitizenId}.";
                return $result;
            }

            // Generate Pension Tracking ID
            $pensionTransactionId = generateCompactApplicationToken($conn, 'PEN');

            // Handle uploads
            $homeVisitationForm = saveUploadedProxyFile('home_visitation_form_file', $pensionTransactionId, 'home_visitation_form');
            $landbankForm = saveUploadedProxyFile('landbank_enrollment_form_file', $pensionTransactionId, 'landbank_enrollment_form');
            $pensionIdImage = saveUploadedProxyFile('pension_id_photo_file', $pensionTransactionId, 'id_photo', true);

            if (!$homeVisitationForm || !$landbankForm || !$pensionIdImage) {
                $result['message'] = "Please upload the Senior ID Photo, Home Visitation Form, and Land Bank Cash Card Enrollment Form.";
                return $result;
            }

            $conn->beginTransaction();

            // Insert sub-process Pension Benefit application
            $sql = "INSERT INTO applications (
                        id_number, full_name, lastName, firstName, middleName, suffix,
                        birth_date, contact_number, complete_address, barangay,
                        status, workflow_state, requested_benefit, is_proxy_application, proxy_name,
                        proxy_relationship, proxy_contact_number, proxy_token, priority_level, application_type,
                        senior_id_no, parent_senior_id, home_visitation_form, landbank_enrollment_form,
                        proxy_birth_date, proxy_email, proxy_address, proxy_id_type, proxy_id_number,
                        home_visit_status, id_image
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmtPension = $conn->prepare($sql);
            $stmtPension->execute([
                $pensionTransactionId, $senior['full_name'], $senior['lastName'], $senior['firstName'], $senior['middleName'], $senior['suffix'],
                $senior['birth_date'], $senior['contact_number'], $senior['complete_address'], $senior['barangay'],
                'pending', 'For Review', 'Local Senior Pension Benefit', 0, null,
                null, null, $pensionTransactionId, 'normal', 'pension',
                $senior['senior_id_no'], $senior['id_number'], $homeVisitationForm, $landbankForm,
                null, null, null, null, null,
                'Waiting for Home Visit', $pensionIdImage
            ]);

            // Enter the standard processing queue so barangay and department
            // screens can review this benefit claim normally.
            $stmtHist = $conn->prepare("INSERT INTO application_history (application_id, previous_state, new_state, changed_by, comments) VALUES (?, ?, ?, ?, ?)");
            $stmtHist->execute([
                $pensionTransactionId, 'Draft', 'For Review', 'Senior Applicant', 'Pension benefit application submitted directly to the Department Admin verification queue.'
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
            $result['qrCodeUrl'] = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&ecc=M&qzone=4&data=' . urlencode($trackerUrl);
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
