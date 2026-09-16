<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/application_types.php';
require_once '../includes/request_security.php';
require_once '../includes/data_normalizer.php';
require_once '../includes/barangays_list.php';
requireSameOriginMutation();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['barangay_staff', 'department_admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit();
}

function sanitize_str(?string $value): ?string {
    if ($value === null) return null;
    return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

if (empty($_POST) && empty($_FILES) && $_SERVER['CONTENT_LENGTH'] > 0) {
    echo json_encode(['success' => false, 'message' => 'Upload failed: request size exceeds server limit.']);
    exit();
}

$_POST = normalizeApplicationInput($_POST, $barangays_list);

$appId = sanitize_str($_POST['applicationId'] ?? null);
if (empty($appId)) {
    echo json_encode(['success' => false, 'message' => 'Application ID is missing.']);
    exit();
}

// Check ownership and editable state before reading or changing applicant data.
$accessSql = 'SELECT workflow_state FROM applications WHERE id_number = ? AND COALESCE(is_archived, 0) = 0';
$accessParams = [$appId];
if ($_SESSION['role'] === 'barangay_staff') {
    $accessSql .= ' AND barangay = ?';
    $accessParams[] = $_SESSION['barangay'] ?? '';
}
$conn->beginTransaction();
$access = $conn->prepare($accessSql . ' FOR UPDATE');
$access->execute($accessParams);
$editableApplication = $access->fetch(PDO::FETCH_ASSOC);
if (!$editableApplication || !in_array($editableApplication['workflow_state'] ?: 'Received', ['Received', 'Submitted', 'Needs Correction'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Application not found, outside your barangay, or locked for review.']);
    exit;
}

$lastName             = sanitize_str($_POST['lastName'] ?? null);
$firstName            = sanitize_str($_POST['firstName'] ?? null);
$middleName           = sanitize_str($_POST['middleName'] ?? null);
$suffix               = sanitize_str($_POST['suffix'] ?? null);
$applicationType      = sanitize_str($_POST['applicationType'] ?? null);
$birthDate            = sanitize_str($_POST['birthDate'] ?? null);
$contactNumber        = sanitize_str($_POST['contactNumber'] ?? null);
$completeAddress      = sanitize_str($_POST['completeAddress'] ?? null);
$emergencyContact     = sanitize_str($_POST['emergencyContact'] ?? '') ?? '';
$emergencyContactName = sanitize_str($_POST['emergencyContactName'] ?? '') ?? '';

// If applicationType was not submitted (disabled select not mirrored), fetch it from the DB
if (empty($applicationType)) {
    $stmtType = $conn->prepare("SELECT application_type FROM applications WHERE id_number = ?");
    $stmtType->execute([$appId]);
    $rowType = $stmtType->fetch(PDO::FETCH_ASSOC);
    $applicationType = $rowType['application_type'] ?? null;
}

$required = [
    'Last Name' => $lastName, 'First Name' => $firstName, 'Application Type' => $applicationType,
    'Birth Date' => $birthDate, 'Contact Number' => $contactNumber, 'Complete Address' => $completeAddress,
];
foreach ($required as $fieldName => $fieldValue) {
    if (empty($fieldValue)) {
        echo json_encode(['success' => false, 'message' => "Required field '{$fieldName}' is missing or empty."]);
        exit();
    }
}

$fullName = trim("{$firstName} {$middleName} {$lastName} {$suffix}");
$oscaData = parseOscaFormPost($_POST);

if (!isValidPhilippineMobileNumber($contactNumber)) {
    echo json_encode(['success' => false, 'message' => 'Contact number must contain exactly 11 digits and begin with 09.']);
    exit;
}
if ($emergencyContact !== '' && !isValidPhilippineMobileNumber($emergencyContact)) {
    echo json_encode(['success' => false, 'message' => 'Emergency contact number must contain exactly 11 digits and begin with 09.']);
    exit;
}
if (($oscaData['claimant_contact'] ?? '') !== '' && !isValidPhilippineMobileNumber($oscaData['claimant_contact'])) {
    echo json_encode(['success' => false, 'message' => 'Claimant contact number must contain exactly 11 digits and begin with 09.']);
    exit;
}

// Only update a form-specific column when its field was actually present in
// the submitted modal. This keeps stored application data intact when a
// field is intentionally not part of the selected official form.
$oscaInputMap = [
    'place_of_birth' => 'placeOfBirth', 'gender' => 'gender', 'civil_status' => 'civilStatus',
    'mothers_maiden_name' => 'mothersMaidenName', 'house_no' => 'houseNo', 'street' => 'street',
    'city' => 'city', 'province' => 'province', 'zip_code' => 'zipCode', 'landmark' => 'landmark',
    // senior_id_no is intentionally excluded: only OSCA approval may issue or change it.
    'health_status' => 'healthStatus', 'id_purpose' => 'idPurpose',
    'milestone_age' => 'milestoneAge', 'claimant_name' => 'claimantName',
    'claimant_relationship' => array_key_exists('emergencyContactRelationship', $_POST) ? 'emergencyContactRelationship' : 'claimantRelationship', 'claimant_contact' => 'claimantContact',
    'deceased_last_name' => 'deceasedLastName', 'deceased_first_name' => 'deceasedFirstName',
    'deceased_middle_name' => 'deceasedMiddleName', 'deceased_suffix' => 'deceasedSuffix',
    'deceased_birth_date' => 'deceasedBirthDate', 'death_registration_date' => 'deathRegistrationDate',
    'landbank_card_no' => 'landbankCardNo',
    'applicant_name' => 'applicantName', 'visit_purpose' => 'visit_purpose',
    'living_arrangement' => 'livingArrangement', 'is_pensioner' => 'isPensioner',
    'pension_source' => 'pensionSource', 'family_support' => 'familySupport',
    'family_support_type' => 'familySupportType',
    'family_support_amount' => 'familySupportAmount', 'personal_income' => 'personalIncome',
    'personal_income_amount' => 'personalIncomeAmount', 'health_condition' => 'healthCondition',
    'with_maintenance' => 'withMaintenance', 'maintenance_spec' => 'maintenanceSpec',
    'visit_summary' => 'visitSummary', 'name_on_card' => 'nameOnCard',
    'id_type_presented' => 'idTypePresented', 'nationality' => 'nationality',
    'source_of_funds' => 'sourceOfFunds', 'atm_card_no' => 'atmCardNo',
    'control_no' => 'controlNo', 'is_permanent_income' => 'isPermanentIncome',
    'income_source' => 'incomeSource', 'owns_house' => 'ownsHouse', 'is_renter' => 'isRenter',
];
$oscaData = array_filter(
    $oscaData,
    static fn($column) => isset($oscaInputMap[$column]) && array_key_exists($oscaInputMap[$column], $_POST),
    ARRAY_FILTER_USE_KEY
);
// For burial assistance this is the deceased person's existing OSCA ID, not a
// newly issued ID, so authorized staff may correct it with the rest of F7.
if ($applicationType === 'burial' && array_key_exists('seniorIdNo', $_POST)) {
    $oscaData['senior_id_no'] = sanitize_str($_POST['seniorIdNo']);
}
if ($applicationType === 'milestone_gift' && $birthDate) {
    try {
        $dob = new DateTimeImmutable($birthDate);
        $today = new DateTimeImmutable('today');
        $automaticMilestone = $dob <= $today ? milestoneAgeForCurrentAge($today->diff($dob)->y) : null;
        if ($automaticMilestone === null) {
            echo json_encode(['success' => false, 'message' => 'The birth date is not currently eligible for a milestone cash gift.']);
            exit;
        }
        $oscaData['milestone_age'] = (string)$automaticMilestone;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Please provide a valid birth date.']);
        exit;
    }
}
if ($applicationType === 'milestone_gift' && (
    empty($oscaData['claimant_name']) || empty($oscaData['claimant_relationship']) || empty($oscaData['claimant_contact'])
)) {
    echo json_encode(['success' => false, 'message' => 'Complete the family representative or claimant details.']);
    exit;
}

if (!empty($oscaData['house_no']) || !empty($oscaData['street'])) {
    $parts = array_filter([
        $oscaData['house_no'], $oscaData['street'],
        $_SESSION['barangay'] ?? '', $oscaData['city'], $oscaData['province'], $oscaData['zip_code']
    ]);
    if ($parts) $completeAddress = implode(', ', $parts);
}

$disabilityType = isset($_POST['disabilityType']) ? implode(', ', (array)$_POST['disabilityType']) : null;
$sssNumber    = !empty($_POST['sssNumber']) ? sanitize_str($_POST['sssNumber']) : null;
$pensionAmount = !empty($_POST['pensionAmount']) ? floatval($_POST['pensionAmount']) : null;
$dateOfDeath            = !empty($_POST['dateOfDeath']) ? sanitize_str($_POST['dateOfDeath']) : null;
$relationshipToDeceased = !empty($_POST['relationshipToDeceased']) ? sanitize_str($_POST['relationshipToDeceased']) : null;
if ($applicationType === 'burial' && !in_array($relationshipToDeceased, getDeceasedRelationshipOptions(), true)) {
    echo json_encode(['success' => false, 'message' => 'Please select a valid relationship to the deceased.']);
    exit;
}
if ($applicationType === 'burial') {
    if ($dateOfDeath === null || empty($oscaData['senior_id_no']) ||
        empty($oscaData['landbank_card_no']) || empty($oscaData['claimant_name']) ||
        empty($oscaData['claimant_contact']) || empty($oscaData['id_type_presented'])) {
        echo json_encode(['success' => false, 'message' => 'Complete all required deceased, claimant, and proof-of-relationship fields.']);
        exit;
    }
    if (!isValidPhilippineMobileNumber((string)$oscaData['claimant_contact'])) {
        echo json_encode(['success' => false, 'message' => 'Claimant contact number must contain exactly 11 digits and begin with 09.']);
        exit;
    }
    if (!in_array($oscaData['id_type_presented'], ['Marriage Contract', 'Birth Certificate', 'Other'], true)) {
        echo json_encode(['success' => false, 'message' => 'Please select a valid proof of relationship.']);
        exit;
    }
}
$emailAddress          = array_key_exists('emailAddress', $_POST) && !empty($_POST['emailAddress']) ? sanitize_str($_POST['emailAddress']) : null;
$additionalNotes       = !empty($_POST['additionalNotes']) ? sanitize_str($_POST['additionalNotes']) : null;

$stmtExisting = $conn->prepare("SELECT proof_of_address, proof_of_address_type, id_image, id_image_type, email_address, emergency_contact, emergency_contact_name, additional_notes FROM applications WHERE id_number = ?");
$stmtExisting->execute([$appId]);
$existingApplication = $stmtExisting->fetch(PDO::FETCH_ASSOC);

if (!$existingApplication) {
    echo json_encode(['success' => false, 'message' => 'Application not found.']);
    exit();
}

// Email is intentionally not exposed in the editing modal. Preserve its
// existing value unless a trusted caller explicitly supplies this field.
if (!array_key_exists('emailAddress', $_POST)) {
    $emailAddress = $existingApplication['email_address'] ?? null;
}
if (!array_key_exists('emergencyContact', $_POST)) {
    $emergencyContact = $existingApplication['emergency_contact'] ?? '';
}
if (!array_key_exists('emergencyContactName', $_POST)) {
    $emergencyContactName = $existingApplication['emergency_contact_name'] ?? '';
}
if (!array_key_exists('additionalNotes', $_POST)) {
    $additionalNotes = $existingApplication['additional_notes'] ?? null;
}

$allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
$proofOfAddress     = $existingApplication['proof_of_address'] ?? null;
$proofOfAddressType = $existingApplication['proof_of_address_type'] ?? null;
$idImage     = $existingApplication['id_image'] ?? null;
$idImageType = $existingApplication['id_image_type'] ?? null;

if (isset($_FILES['proofOfAddress']) && $_FILES['proofOfAddress']['error'] === UPLOAD_ERR_OK && $_FILES['proofOfAddress']['size'] > 0) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($_FILES['proofOfAddress']['tmp_name']);
    if (!in_array($mimeType, $allowedMimes)) {
        echo json_encode(['success' => false, 'message' => 'Proof of Address: only JPEG, PNG, GIF and PDF accepted.']);
        exit();
    }
    $proofOfAddress     = file_get_contents($_FILES['proofOfAddress']['tmp_name']);
    $proofOfAddressType = $mimeType;
}

if (isset($_FILES['idImage']) && $_FILES['idImage']['error'] === UPLOAD_ERR_OK && $_FILES['idImage']['size'] > 0) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($_FILES['idImage']['tmp_name']);
    if (!in_array($mimeType, $allowedMimes)) {
        echo json_encode(['success' => false, 'message' => 'ID Image: only JPEG, PNG, GIF and PDF accepted.']);
        exit();
    }
    $idImage     = file_get_contents($_FILES['idImage']['tmp_name']);
    $idImageType = $mimeType;
}

$setParts = [
    'full_name = ?', 'application_type = ?', 'birth_date = ?', 'contact_number = ?', 'complete_address = ?',
    'emergency_contact = ?', 'emergency_contact_name = ?',
    'proof_of_address = ?', 'proof_of_address_type = ?', 'id_image = ?', 'id_image_type = ?',
    'lastName = ?', 'firstName = ?', 'middleName = ?', 'suffix = ?', 'disability_type = ?',
    'sss_number = ?', 'pension_amount = ?', 'date_of_death = ?', 'relationship_to_deceased = ?',
    'email_address = ?', 'additional_notes = ?',
];
$params = [
    $fullName, $applicationType, $birthDate, $contactNumber, $completeAddress,
    $emergencyContact, $emergencyContactName,
    $proofOfAddress, $proofOfAddressType, $idImage, $idImageType,
    $lastName, $firstName, $middleName, $suffix, $disabilityType,
    $sssNumber, $pensionAmount, $dateOfDeath, $relationshipToDeceased,
    $emailAddress, $additionalNotes,
];

foreach ($oscaData as $col => $val) {
    $setParts[] = "$col = ?";
    $params[] = $val;
}
$params[] = $appId;

$sql = "UPDATE applications SET " . implode(', ', $setParts) . " WHERE id_number = ? AND COALESCE(is_archived, 0) = 0 AND COALESCE(workflow_state, 'Received') IN ('Received', 'Submitted', 'Needs Correction')";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Application updated successfully!']);
} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    error_log('update_application.php PDO error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error while updating.']);
}

$stmt = null;
$conn = null;
