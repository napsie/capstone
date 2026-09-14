<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/filing_deadline.php';
require_once __DIR__ . '/application_types.php';
require_once __DIR__ . '/data_normalizer.php';
require_once __DIR__ . '/barangays_list.php';
require_once __DIR__ . '/document_repository.php';

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

function persistProxyDocument(PDO $conn, string $applicationId, string $key, string $label, ?string $storedPath): void {
    if (!$storedPath) return;
    $path = dirname(__DIR__) . '/uploads/' . basename($storedPath);
    if (!is_file($path)) return;
    saveDocumentVersion($conn, [
        'application_id'=>$applicationId, 'document_key'=>$key, 'document_label'=>$label,
        'mime_type'=>(new finfo(FILEINFO_MIME_TYPE))->file($path), 'document_data'=>file_get_contents($path),
        'uploaded_by'=>'Senior Applicant', 'original_filename'=>basename($storedPath), 'source'=>'public_application',
    ]);
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
    global $conn, $barangays_list;

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

    $_POST = normalizeApplicationInput($_POST, $barangays_list);

    $portalOption = trim($_POST['portal_option'] ?? 'new_senior');

    if (in_array($portalOption, ['new_senior', 'verified_benefits'], true)) {
        // --- OPTION A: PRE-REGISTRATION FOR NEW ID ---
        $verifiedSenior = null;
        if ($portalOption === 'verified_benefits') {
            $access = $_SESSION['senior_benefit_access'] ?? null;
            $postedSeniorId = strtoupper(trim((string)($_POST['seniorCitizenId'] ?? '')));
            $postedToken = strtoupper(trim((string)($_POST['permanentToken'] ?? '')));
            if (!is_array($access)
                || (int)($access['verified_at'] ?? 0) < time() - 1800
                || !hash_equals((string)($access['senior_id_no'] ?? ''), $postedSeniorId)
                || !hash_equals((string)($access['token'] ?? ''), $postedToken)) {
                $result['message'] = 'Benefit access expired or the ID credentials do not match. Verify the Senior ID and permanent token again.';
                return $result;
            }
            $verifyStmt = $conn->prepare("SELECT * FROM applications
                WHERE application_type = 'senior'
                  AND senior_id_no = ?
                  AND (id_number = ? OR proxy_token = ?)
                  AND workflow_state IN ('Verified','Approved','Released')
                  AND COALESCE(is_archived, 0) = 0
                LIMIT 1");
            $verifyStmt->execute([$postedSeniorId, $postedToken, $postedToken]);
            $verifiedSenior = $verifyStmt->fetch(PDO::FETCH_ASSOC);
            if (!$verifiedSenior) {
                unset($_SESSION['senior_benefit_access']);
                $result['message'] = 'Senior identity validation failed. Use the official Senior ID and its original permanent token.';
                return $result;
            }
        }
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
        $healthStatus = trim($_POST['healthStatus'] ?? '');
        $healthCondition = trim($_POST['healthCondition'] ?? '');
        $mobilityStatus = trim($_POST['mobilityStatus'] ?? '');
        $livingArrangement = trim($_POST['livingArrangement'] ?? '');
        $applicationType = trim($_POST['applicationType'] ?? 'senior');
        $requestedBenefit = trim($_POST['requestedBenefit'] ?? '');
        $visitPurpose = $requestedBenefit;
        $idPurpose = trim($_POST['idPurpose'] ?? '');
        $emergencyContactName = trim($_POST['emergencyContactName'] ?? '');
        $emergencyContact = trim($_POST['emergencyContact'] ?? '');
        $emergencyContactRelationship = trim($_POST['emergencyContactRelationship'] ?? '');
        $visitSummary = trim($_POST['visitSummary'] ?? '');
        $isPensioner = isset($_POST['isPensioner']) && in_array((string) $_POST['isPensioner'], ['0', '1'], true)
            ? (int) $_POST['isPensioner'] : null;
        $pensionSource = trim($_POST['pensionSource'] ?? '');
        $familySupport = isset($_POST['familySupport']) && in_array((string) $_POST['familySupport'], ['0', '1'], true)
            ? (int) $_POST['familySupport'] : null;
        $familySupportAmount = isset($_POST['familySupportAmount']) && $_POST['familySupportAmount'] !== ''
            ? (float) $_POST['familySupportAmount'] : null;
        $familySupportType = trim($_POST['familySupportType'] ?? '');
        $personalIncome = isset($_POST['personalIncome']) && in_array((string) $_POST['personalIncome'], ['0', '1'], true)
            ? (int) $_POST['personalIncome'] : null;
        $personalIncomeAmount = isset($_POST['personalIncomeAmount']) && $_POST['personalIncomeAmount'] !== ''
            ? (float) $_POST['personalIncomeAmount'] : null;
        $incomeSource = trim($_POST['incomeSource'] ?? '');
        $isPermanentIncome = isset($_POST['isPermanentIncome']) && in_array((string) $_POST['isPermanentIncome'], ['0', '1'], true)
            ? (int) $_POST['isPermanentIncome'] : null;
        $ownsHouse = isset($_POST['ownsHouse']) && in_array((string) $_POST['ownsHouse'], ['0', '1'], true)
            ? (int) $_POST['ownsHouse'] : null;
        $isRenter = isset($_POST['isRenter']) && in_array((string) $_POST['isRenter'], ['0', '1'], true)
            ? (int) $_POST['isRenter'] : null;
        $nameOnCard = trim($_POST['nameOnCard'] ?? '');
        $tin = trim($_POST['tin'] ?? '');
        $seniorIdTypePresented = trim($_POST['idTypePresented'] ?? $_POST['seniorIdTypePresented'] ?? '');
        $controlNo = trim($_POST['controlNo'] ?? '');
        $atmCardNo = trim($_POST['atmCardNo'] ?? '');
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
        $deathRegistrationDate = trim($_POST['deathRegistrationDate'] ?? '');
        $relationshipToDeceased = trim($_POST['relationshipToDeceased'] ?? '');
        $landbankCardNo = trim($_POST['landbankCardNo'] ?? '');
        
        // Representative intake is intentionally disabled for the public form.
        $proxyName = $proxyRelationship = $proxyContactNumber = '';
        $proxyBirthDate = $proxyEmail = $proxyAddress = $proxyIdType = $proxyIdNumber = '';

        // Benefit applications inherit identity data from the verified Senior ID
        // record. Posted copies are never trusted as the identity source.
        if ($verifiedSenior) {
            $lastName = (string)($verifiedSenior['lastName'] ?? '');
            $firstName = (string)($verifiedSenior['firstName'] ?? '');
            $middleName = (string)($verifiedSenior['middleName'] ?? '');
            $suffix = (string)($verifiedSenior['suffix'] ?? '');
            $fullName = (string)($verifiedSenior['full_name'] ?? '');
            $birthDate = (string)($verifiedSenior['birth_date'] ?? '');
            $contactNumber = (string)($verifiedSenior['contact_number'] ?? '');
            $placeOfBirth = (string)($verifiedSenior['place_of_birth'] ?? '');
            $gender = (string)($verifiedSenior['gender'] ?? '');
            $civilStatus = (string)($verifiedSenior['civil_status'] ?? '');
            $mothersMaidenName = (string)($verifiedSenior['mothers_maiden_name'] ?? '');
            $houseNo = (string)($verifiedSenior['house_no'] ?? '');
            $street = (string)($verifiedSenior['street'] ?? '');
            $barangay = (string)($verifiedSenior['barangay'] ?? '');
            $zipCode = (string)($verifiedSenior['zip_code'] ?? '');
            $landmark = (string)($verifiedSenior['landmark'] ?? '');
            $seniorEmail = (string)($verifiedSenior['email_address'] ?? '');
            $completeAddress = (string)($verifiedSenior['complete_address'] ?? '');
        }

        if ($portalOption === 'new_senior' && $requestedBenefit !== 'Senior Citizen ID Registration') {
            $result['message'] = 'The first application must be for a Senior Citizen ID.';
            return $result;
        }
        if ($portalOption === 'verified_benefits' && $requestedBenefit === 'Senior Citizen ID Registration') {
            $result['message'] = 'Select an available senior benefit.';
            return $result;
        }

        $requiredFields = [
            'Benefit or Service Requested' => $requestedBenefit,
            'Last Name' => $lastName, 'First Name' => $firstName,
            'Sex' => $gender, 'House / Unit Number' => $houseNo,
            'Street / Subdivision' => $street, 'Barangay' => $barangay,
        ];
        if ($requestedBenefit !== 'Local Social Pension Assessment') {
            $requiredFields['Place of Birth'] = $placeOfBirth;
            $requiredFields['Civil Status'] = $civilStatus;
        }
        foreach ($requiredFields as $label => $value) {
            if ($value === '') {
                $result['message'] = $label . ' is required.';
                return $result;
            }
        }

        if (!isValidPhilippineMobileNumber($contactNumber)) {
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

        $benefitDefinition = getPublicBenefitDefinition($requestedBenefit);
        if (!$benefitDefinition) {
            $result['message'] = 'Please select a valid benefit or service.';
            return $result;
        }
        $applicationType = $benefitDefinition['type'];

        if ($verifiedSenior && $requestedBenefit !== 'Senior Citizen ID Registration') {
            $duplicateBenefitStmt = $conn->prepare("SELECT id_number, workflow_state, status
                FROM applications
                WHERE id_number <> ?
                  AND (parent_senior_id = ? OR senior_id_no = ?)
                  AND requested_benefit = ?
                  AND COALESCE(is_archived, 0) = 0
                  AND COALESCE(workflow_state, '') <> 'Rejected'
                  AND LOWER(COALESCE(status, '')) <> 'rejected'
                LIMIT 1");
            $duplicateBenefitStmt->execute([
                $verifiedSenior['id_number'],
                $verifiedSenior['id_number'],
                $verifiedSenior['senior_id_no'] ?? '',
                $requestedBenefit,
            ]);
            if ($duplicateBenefitStmt->fetch(PDO::FETCH_ASSOC)) {
                $result['message'] = "This senior already has an active or verified {$requestedBenefit} application. Please select another available service.";
                return $result;
            }
        }

        foreach ($benefitDefinition['required_fields'] ?? [] as $fieldName) {
            if (!array_key_exists($fieldName, $_POST) || trim((string)$_POST[$fieldName]) === '') {
                $fieldLabel = preg_replace('/(?<!^)[A-Z]/', ' $0', $fieldName);
                $result['message'] = ucwords((string)$fieldLabel) . ' is required.';
                return $result;
            }
        }

        switch ($requestedBenefit) {
            case 'Senior Citizen ID Registration':
                if (!in_array($idPurpose, ['new', 'lost', 'change', 'transfer'], true)) {
                    $result['message'] = 'Please select the ID application purpose.';
                    return $result;
                }
                if (!in_array($healthStatus, ['Physically Fit', 'Bedridden', 'Frail/Sickly', 'PWD'], true)) {
                    $result['message'] = 'Please select a valid health status.';
                    return $result;
                }
                if (in_array($healthStatus, ['Frail/Sickly', 'PWD'], true) && $healthCondition === '') {
                    $result['message'] = 'Please specify the senior citizen\'s health condition.';
                    return $result;
                }
                if ($emergencyContactName === '' || $emergencyContactRelationship === '' || !isValidPhilippineMobileNumber($emergencyContact)) {
                    $result['message'] = 'Please provide the emergency contact name, relationship, and a valid 11-digit mobile number.';
                    return $result;
                }
                $claimantRelationship = $emergencyContactRelationship;
                break;
            case 'Local Social Pension Assessment':
                if ($isPensioner === null || $isPermanentIncome === null || $familySupport === null ||
                    $healthCondition === '' || $ownsHouse === null || $isRenter === null || $atmCardNo === '') {
                    $result['message'] = 'Please complete all fields on the Local Senior Pension economic-status form.';
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
                if ($familySupport === 1 && $familySupportType === '') {
                    $result['message'] = 'Enter the type of regular family support.';
                    return $result;
                }
                if ($isPermanentIncome === 1 && $incomeSource === '') {
                    $result['message'] = 'Enter the permanent source of income.';
                    return $result;
                }
                break;
            case 'Land Bank Cash Card Enrollment':
                if ($nameOnCard === '' || $tin === '' || $seniorIdTypePresented === '' || $nationality === '' || $sourceOfFunds === '' || $mothersMaidenName === '') {
                    $result['message'] = 'Please complete all required Land Bank enrollment information.';
                    return $result;
                }
                if (mb_strlen($nameOnCard) > 23) {
                    $result['message'] = 'The name on the cash card cannot exceed 23 characters.';
                    return $result;
                }
                break;
            case 'Milestone Cash Gift':
                if ($claimantName === '' || $claimantRelationship === '' || !isValidPhilippineMobileNumber($claimantContact)) {
                    $result['message'] = 'Please complete the claimant details with a valid 11-digit mobile number.';
                    return $result;
                }
                break;
            case 'Burial Assistance':
                if ($dateOfDeath === '' || $relationshipToDeceased === '' || $claimantName === '' || $landbankCardNo === '') {
                    $result['message'] = 'Complete the date of passing, Landbank card number, claimant name, and relationship to the deceased.';
                    return $result;
                }
                if (!in_array($relationshipToDeceased, getDeceasedRelationshipOptions(), true)) {
                    $result['message'] = 'Please select a valid relationship to the deceased.';
                    return $result;
                }
                if (!isValidPhilippineMobileNumber($claimantContact)) {
                    $result['message'] = 'Claimant contact number must be a valid 11-digit Philippine mobile number.';
                    return $result;
                }
                if (!in_array($seniorIdTypePresented, ['Marriage Contract', 'Birth Certificate', 'Other'], true)) {
                    $result['message'] = 'Please select the proof of relationship to the deceased.';
                    return $result;
                }
                if ($controlNo !== '' && !in_array($controlNo, ['Kinship', 'Discrepancy', 'Died single without a child', 'Cohabitation', 'Other'], true)) {
                    $result['message'] = 'Please select a valid affidavit type.';
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
        if ($requestedBenefit !== 'Burial Assistance') {
            $visitSummary = '';
        }
        if ($requestedBenefit !== 'Local Social Pension Assessment') {
            $isPensioner = null;
            $pensionSource = '';
            $sssNumber = '';
            $pensionAmount = null;
            $familySupport = null;
            $familySupportType = '';
            $familySupportAmount = null;
            $personalIncome = null;
            $personalIncomeAmount = null;
            $incomeSource = '';
            $isPermanentIncome = null;
            $ownsHouse = null;
            $isRenter = null;
            $mobilityStatus = '';
            $livingArrangement = '';
        } else {
            $mobilityStatus = '';
            $livingArrangement = '';
            $sssNumber = '';
            if ($isPensioner === 0) { $pensionSource = ''; $pensionAmount = null; }
            if ($familySupport === 0) { $familySupportAmount = null; }
            if ($familySupport === 0) { $familySupportType = ''; }
            if ($isPermanentIncome === 0) { $incomeSource = ''; }
            if ($personalIncome === 0) { $personalIncomeAmount = null; }
        }
        if (!in_array($requestedBenefit, ['Land Bank Cash Card Enrollment', 'Burial Assistance'], true)) {
            $nameOnCard = '';
            $tin = '';
            $seniorIdTypePresented = '';
            $nationality = '';
            $sourceOfFunds = '';
        }
        if (!in_array($requestedBenefit, ['Milestone Cash Gift', 'Burial Assistance'], true)) {
            $milestoneAge = '';
            $claimantName = '';
            $claimantRelationship = '';
            $claimantContact = '';
        }
        $otherAssistanceDetails = '';

        if (!in_array($gender, ['Male', 'Female'], true) ||
            ($requestedBenefit !== 'Local Social Pension Assessment' &&
             !in_array($civilStatus, ['Single', 'Married', 'Widowed', 'Separated'], true))) {
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
            $minimumAge = (int)$benefitDefinition['minimum_age'];
            if ($dob > $targetDate || $age < $minimumAge) {
                $result['message'] = "Eligibility Check Failed: Applicant must be at least {$minimumAge} years old (current age: {$age}).";
                return $result;
            }
            if ($requestedBenefit === 'Milestone Cash Gift') {
                $automaticMilestone = milestoneAgeForCurrentAge($age);
                if ($automaticMilestone === null) {
                    $result['message'] = "Milestone cash gifts are not available at the senior's current age ({$age}).";
                    return $result;
                }
                $milestoneAge = (string)$automaticMilestone;
            }
        } catch (Exception $e) {
            $result['message'] = 'Please provide a valid senior birth date.';
            return $result;
        }

        $requiredUploads = [];
        foreach ($benefitDefinition['form_documents'] ?? [] as $document) {
            $requiredUploads[$document['field']] = $document['label'];
        }
        foreach ($requiredUploads as $key => $label) {
            if (!isset($_FILES[$key]) || $_FILES[$key]['error'] !== UPLOAD_ERR_OK) {
                $result['message'] = $label . ' is required.';
                return $result;
            }
        }

        $transactionId = generateCompactApplicationToken($conn, $portalOption === 'verified_benefits' ? 'PEN' : 'PRX');
        $priorityLevel = 'normal';

        // Handle File Uploads
        $psaBirthCert = saveUploadedProxyFile('psa_birth_cert_file', $transactionId, 'psa_birth_cert');
        $barangayResidency = saveUploadedProxyFile('barangay_residency_file', $transactionId, 'barangay_residency');
        $comelecCert = saveUploadedProxyFile(
            'comelec_cert_file',
            $transactionId,
            'comelec_cert',
            $requestedBenefit === 'Milestone Cash Gift'
        );
        $idImage = saveUploadedProxyFile('id_photo_file', $transactionId, 'id_photo', true);
        $deceasedLandbankCard = null;
        $proofOfLife = null;
        $authLetter = $proxyId = $proxyBirthCert = null;
        if ($requestedBenefit === 'Burial Assistance') {
            $deceasedLandbankCard = saveUploadedProxyFile('deceased_landbank_card_file', $transactionId, 'deceased_landbank_card');
            $proofOfLife = saveUploadedProxyFile('proof_of_life_file', $transactionId, 'proof_of_relationship');
            $authLetter = saveUploadedProxyFile('auth_letter_file', $transactionId, 'applicable_affidavit');
        }

        $requiredProcessedFiles = [$psaBirthCert, $barangayResidency, $comelecCert];
        if ($requestedBenefit === 'Burial Assistance') {
            $requiredProcessedFiles = [$psaBirthCert, $barangayResidency, $comelecCert, $deceasedLandbankCard, $proofOfLife];
            if (trim($_POST['controlNo'] ?? '') !== '') $requiredProcessedFiles[] = $authLetter;
        } elseif ($requestedBenefit === 'Senior Citizen ID Registration') {
            $requiredProcessedFiles = match ($idPurpose) {
                'change' => [$psaBirthCert, $idImage],
                'new', 'lost' => [$psaBirthCert, $barangayResidency, $idImage],
                'transfer' => [$psaBirthCert, $barangayResidency, $comelecCert, $idImage],
                default => [null],
            };
        } elseif ($requestedBenefit === 'Local Social Pension Assessment') {
            $requiredProcessedFiles = [$psaBirthCert, $barangayResidency, $comelecCert, $idImage];
        } elseif ($requestedBenefit === 'Local Social Pension Assessment') {
            $requiredProcessedFiles = [$psaBirthCert, $barangayResidency, $comelecCert, $idImage];
        }
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
                        sss_number, pension_amount, date_of_death, death_registration_date, relationship_to_deceased,
                        psa_birth_cert, barangay_residency, comelec_cert, deceased_landbank_card, proof_of_life,
                        auth_letter, proxy_id, proxy_birth_cert,
                        email_address, place_of_birth, gender, civil_status, mothers_maiden_name,
                        house_no, street, city, province, zip_code, landmark, health_status,
                        health_condition, living_arrangement, visit_purpose,
                        id_purpose, visit_summary, control_no, is_pensioner, pension_source,
                        family_support, family_support_type, family_support_amount, personal_income, personal_income_amount,
                        is_permanent_income, income_source, owns_house, is_renter, atm_card_no,
                        name_on_card, tin, id_type_presented, nationality, source_of_funds, milestone_age, landbank_card_no,
                        applicant_name, claimant_name, claimant_relationship, claimant_contact, additional_notes,
                        proxy_birth_date, proxy_email, proxy_address, proxy_id_type, proxy_id_number,
                        id_image
                    ) VALUES (" . implode(', ', array_fill(0, 83, '?')) . ")";

            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $transactionId, $fullName, $lastName, $firstName, $middleName, $suffix,
                $birthDate, $contactNumber, $emergencyContact, $emergencyContactName ?: null, $completeAddress, $barangay,
                'pending', 'For Review', $requestedBenefit, 0, null,
                $proxyRelationship ?: null, $proxyContactNumber ?: null, $transactionId, $priorityLevel, $applicationType,
                $sssNumber, $pensionAmount, !empty($dateOfDeath) ? $dateOfDeath : null, !empty($deathRegistrationDate) ? $deathRegistrationDate : null, !empty($relationshipToDeceased) ? $relationshipToDeceased : null,
                $psaBirthCert, $barangayResidency, $comelecCert, $deceasedLandbankCard, $proofOfLife,
                $authLetter, $proxyId, $proxyBirthCert,
                $seniorEmail ?: null, $placeOfBirth, $gender, $civilStatus, $mothersMaidenName ?: null,
                $houseNo, $street, 'Pasig City', 'Metro Manila', $zipCode ?: null, $landmark ?: null,
                $requestedBenefit === 'Senior Citizen ID Registration' ? $healthStatus : $mobilityStatus,
                $healthCondition, $livingArrangement, $visitPurpose,
                $idPurpose ?: null, $visitSummary ?: null, $controlNo ?: null, $isPensioner, $pensionSource ?: null,
                $familySupport, $familySupportType ?: null, $familySupportAmount, $personalIncome, $personalIncomeAmount,
                $isPermanentIncome, $incomeSource ?: null, $ownsHouse, $isRenter, $atmCardNo ?: null,
                $nameOnCard ?: null, $tin ?: null, $seniorIdTypePresented ?: null, $nationality ?: null,
                $sourceOfFunds ?: null, $milestoneAge ?: null, $landbankCardNo ?: null,
                $claimantName ?: null, $claimantName ?: null, $claimantRelationship ?: null, $claimantContact ?: null,
                $otherAssistanceDetails ?: null,
                $proxyBirthDate ?: null, $proxyEmail ?: null, $proxyAddress ?: null, $proxyIdType ?: null, $proxyIdNumber ?: null,
                $idImage
            ]);
            if ($verifiedSenior) {
                $linkStmt = $conn->prepare('UPDATE applications SET senior_id_no = ?, parent_senior_id = ? WHERE id_number = ?');
                $linkStmt->execute([$verifiedSenior['senior_id_no'], $verifiedSenior['id_number'], $transactionId]);
            }
            $seniorDocumentLabels = match ($idPurpose) {
                'change' => ['Original Senior Citizen ID', '', ''],
                'lost' => ['Original Affidavit of Loss', 'Copy of Senior ID / Landbank Card / Temporary Stub', ''],
                'transfer' => ['Certificate of Cancellation from Previous OSCA', 'Birth Certificate', 'Original Barangay Residency Certificate'],
                default => ['PSA Birth Certificate', 'Original Barangay Residency Certificate', ''],
            };
            $benefitDocumentLabels = array_map(
                static fn(array $document): string => (string)($document['label'] ?? ''),
                $benefitDefinition['form_documents'] ?? []
            );
            foreach ([
                ['psa_birth_cert',$requestedBenefit === 'Senior Citizen ID Registration' ? $seniorDocumentLabels[0] : ($benefitDocumentLabels[0] ?? 'PSA Birth Certificate'),$psaBirthCert],
                ['barangay_residency',$requestedBenefit === 'Senior Citizen ID Registration' ? $seniorDocumentLabels[1] : ($benefitDocumentLabels[1] ?? 'Barangay Residency Certificate'),$barangayResidency],
                ['comelec_cert',$requestedBenefit === 'Senior Citizen ID Registration' ? $seniorDocumentLabels[2] : ($benefitDocumentLabels[2] ?? 'COMELEC Certificate'),$comelecCert],
                ['deceased_landbank_card',$benefitDocumentLabels[3] ?? 'Deceased Landbank Cash Card',$deceasedLandbankCard],
                ['id_image','ID / Identification Photo',$idImage],
                ['proof_of_life','Proof of Relationship',$proofOfLife],
                ['auth_letter','Original Copy of Affidavit (if applicable)',$authLetter],
            ] as [$key,$label,$path]) {
                if ($label !== '' && $path !== null) persistProxyDocument($conn,$transactionId,$key,$label,$path);
            }

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
            $scanUrl = $portalOption === 'verified_benefits'
                ? $protocol . $host . $scriptDir . '/benefit_tracker.php?token=' . urlencode($transactionId)
                : $protocol . $host . $scriptDir . '/scan_proxy_qr_redirect.php?token=' . urlencode($transactionId);

            $result['success'] = true;
            $result['transactionId'] = $transactionId;
            $result['qrCodeUrl'] = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&ecc=M&qzone=4&data=' . urlencode($scanUrl);
            $result['option'] = $portalOption;
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
        // Retired ID-only route. All benefit applications must pass the
        // permanent-token validation performed by senior_benefits.php.
        $result['message'] = 'Use the Senior Benefits page and verify both the official Senior ID and permanent token.';
        return $result;

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

            if (!$homeVisitationForm || !$landbankForm) {
                $result['message'] = "Please upload the Social Worker Confirmation Form and Land Bank Cash Card Enrollment Form.";
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
                'Waiting for Home Visit', $senior['id_image'] ?? null
            ]);
            persistProxyDocument($conn,$pensionTransactionId,'home_visitation_form','Home Visitation Form',$homeVisitationForm);
            persistProxyDocument($conn,$pensionTransactionId,'landbank_enrollment_form','Land Bank Enrollment Form',$landbankForm);

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
