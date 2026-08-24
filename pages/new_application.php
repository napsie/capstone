<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/proxy_token_resolver.php';
require_once '../includes/application_types.php';
require_once '../includes/sms_service.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'barangay_staff') {
    header('Location: ../index.php');
    exit;
}

$sessionDisplayName = trim((string)($_SESSION['first_name'] ?? '') . ' ' . (string)($_SESSION['last_name'] ?? ''));
if ($sessionDisplayName === '') {
    $sessionDisplayName = (string)($_SESSION['username'] ?? 'Barangay Staff');
}

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
$barangay = $_SESSION['barangay'] ?? '';
$personnelStmt = $conn->prepare(
    "SELECT id, full_name, position, barangay
     FROM home_visit_personnel
     WHERE is_active = 1 AND (barangay IS NULL OR barangay = '' OR barangay = ?)
     ORDER BY full_name"
);
$personnelStmt->execute([$barangay]);
$homeVisitPersonnel = $personnelStmt->fetchAll(PDO::FETCH_ASSOC);

// Check if loaded with a Proxy Token from scanning the QR
$loadedProxyData = null;
if (isset($_GET['token'])) {
    $decrypted = resolveProxyToken($conn, $_GET['token']);
    if ($decrypted) {
        $loadedProxyData = $decrypted;
        $successMessage = "Representative pre-registration details loaded successfully. Priority status set to HIGH Queue.";
    } else {
        $errorMessage = "The representative QR code is invalid, expired, or no longer linked to an application.";
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
    $availableApplicationTypes = getApplicationTypeOptions();
    unset($availableApplicationTypes['home_visit']);
    $birthDate = isset($_POST['birthDate']) ? trim(strip_tags($_POST['birthDate'])) : '';
    $contactNumber = isset($_POST['contactNumber']) ? trim(strip_tags($_POST['contactNumber'])) : '';
    $completeAddress = isset($_POST['completeAddress']) ? trim(strip_tags($_POST['completeAddress'])) : '';
    $emergencyContact = isset($_POST['emergencyContact']) ? trim(strip_tags($_POST['emergencyContact'])) : '';
    $emergencyContactName = isset($_POST['emergencyContactName']) ? trim(strip_tags($_POST['emergencyContactName'])) : '';
    $barangay = $_SESSION['barangay'] ?? ''; // Barangay from session
    $oscaData = parseOscaFormPost($_POST);
    $homeVisitScheduledAt = null;
    $homeVisitStatus = null;
    $homeVisitEligibility = null;
    $homeVisitEligibilityReason = null;
    $homeVisitPersonnelId = null;

    if ($applicationType === 'pension') {
        $eligibilityRaw = trim((string)($_POST['homeVisitEligibility'] ?? ''));
        $homeVisitEligibilityReason = trim(strip_tags((string)($_POST['homeVisitEligibilityReason'] ?? '')));

        if (!in_array($eligibilityRaw, ['eligible', 'not_eligible'], true)) {
            $errorMessage = 'Please indicate whether the senior is eligible for a home visit.';
        } elseif ($eligibilityRaw === 'not_eligible') {
            $homeVisitEligibility = 'Not Eligible';
            $homeVisitStatus = 'Not Eligible';
        } else {
            $homeVisitEligibility = 'Eligible';
            $homeVisitPersonnelId = filter_var($_POST['homeVisitPersonnelId'] ?? null, FILTER_VALIDATE_INT) ?: null;
            $scheduleRaw = trim((string)($_POST['homeVisitScheduledAt'] ?? ''));
            $timezone = new DateTimeZone('Asia/Manila');
            $scheduled = DateTime::createFromFormat('Y-m-d\TH:i', $scheduleRaw, $timezone);
            $scheduleErrors = DateTime::getLastErrors();
            $scheduleIsValid = $scheduled
                && ($scheduleErrors === false || ($scheduleErrors['warning_count'] === 0 && $scheduleErrors['error_count'] === 0));

            if ($homeVisitEligibilityReason === '') {
                $errorMessage = 'Please enter the reason the senior qualifies for a home visit.';
            } elseif (!$homeVisitPersonnelId) {
                $errorMessage = 'Please assign home visit personnel.';
            } elseif (!$scheduleIsValid) {
                $errorMessage = 'Please select a valid home visitation date and time.';
            } elseif ($scheduled <= new DateTime('now', $timezone)) {
                $errorMessage = 'The home visitation schedule must be in the future.';
            } elseif (in_array($scheduled->format('N'), ['6', '7'], true)) {
                $errorMessage = 'Home visits can only be scheduled from Monday to Friday.';
            } elseif ((int)$scheduled->format('H') < 8 || (int)$scheduled->format('H') >= 17 || !in_array($scheduled->format('i'), ['00', '30'], true)) {
                $errorMessage = 'Choose a home visit slot from 8:00 AM to 4:30 PM, in 30-minute intervals.';
            } elseif (normalizePhilippineMobile($contactNumber) === null) {
                $errorMessage = 'Enter a valid Philippine cellphone number so the home visit confirmation can be sent by text.';
            } else {
                $personCheck = $conn->prepare("SELECT COUNT(*) FROM home_visit_personnel WHERE id = ? AND is_active = 1 AND (barangay IS NULL OR barangay = '' OR barangay = ?)");
                $personCheck->execute([$homeVisitPersonnelId, $barangay]);
                $conflictCheck = $conn->prepare(
                    "SELECT COUNT(*) FROM applications
                     WHERE home_visit_personnel_id = ? AND home_visit_scheduled_at = ?
                       AND COALESCE(home_visit_status, '') NOT IN ('Cancelled', 'Completed')"
                );
                $formattedSchedule = $scheduled->format('Y-m-d H:i:s');
                $conflictCheck->execute([$homeVisitPersonnelId, $formattedSchedule]);

                if ((int)$personCheck->fetchColumn() === 0) {
                    $errorMessage = 'The selected home visit personnel is not available.';
                } elseif ((int)$conflictCheck->fetchColumn() > 0) {
                    $errorMessage = 'The selected personnel already has a visit at that date and time.';
                } else {
                    $homeVisitScheduledAt = $formattedSchedule;
                    $homeVisitStatus = 'Scheduled';
                }
            }
        }
    }

    // New Senior Citizens ID applications never choose their own official ID.
    // OSCA assigns it centrally when the application reaches Approved.
    if ($applicationType === 'senior') {
        $oscaData['senior_id_no'] = null;
    } elseif (!empty($oscaData['senior_id_no'])) {
        $oscaData['senior_id_no'] = strtoupper($oscaData['senior_id_no']);
    }

    $idNumber = isset($_POST['idNumber']) ? trim(strip_tags($_POST['idNumber'])) : '';
    $disabilityType = isset($_POST['disabilityType']) ? implode(', ', array_map('strip_tags', (array)$_POST['disabilityType'])) : null;
    $emailAddress   = isset($_POST['emailAddress']) ? trim(strip_tags($_POST['emailAddress'])) : null;
    $additionalNotes = isset($_POST['additionalNotes']) ? trim(strip_tags($_POST['additionalNotes'])) : null;

    if (!array_key_exists($applicationType, $availableApplicationTypes)) {
        $errorMessage = 'Please select a valid application type.';
    }

    // Rule-Based Compliance validation on backend
    if (!empty($birthDate)) {
        $birthDateObj = new DateTime($birthDate);
        $today = new DateTime();
        $age = $today->diff($birthDateObj)->y;
        
        if (($applicationType === 'senior' || $applicationType === 'burial' || $applicationType === 'landbank') && $age < 60) {
            $errorMessage = "Localized Compliance Check Failed: Applicant must be at least 60 years old (current age: $age).";
        } elseif (($applicationType === 'pension' || $applicationType === 'national_pension') && $age < 65) {
            $errorMessage = "Localized Compliance Check Failed: Local/National Social Pension requires applicant to be at least 65 years old (current age: $age).";
        } elseif ($applicationType === 'milestone_gift') {
            if ($age < 80) {
                $errorMessage = "Localized Compliance Check Failed: Octogenarian / Nonagenarian / Centenarian Cash Gift is available to applicants aged 80 and above (current age: $age).";
            }
        }
    }

    // Pension details are recorded for reference only. SSS verification is not
    // available in this system, so it must not prevent an application submission.
    $sssNumber = null;
    $pensionAmount = null;
    if ($applicationType === 'pension' || $applicationType === 'national_pension') {
        $sssNumber = isset($_POST['sssNumber']) ? trim(strip_tags($_POST['sssNumber'])) : '';
        $pensionAmountRaw = trim($_POST['pensionAmount'] ?? '');
        $pensionAmount = is_numeric($pensionAmountRaw) ? floatval($pensionAmountRaw) : null;
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
    $proxyContactNumber = isset($_POST['proxyContactNumber']) ? trim(strip_tags($_POST['proxyContactNumber'])) : null;
    $proxyToken = isset($_POST['proxyToken']) ? trim(strip_tags($_POST['proxyToken'])) : null;
    $priorityLevel = ($isProxy && !empty($proxyToken)) ? 'high' : 'normal';

    if (!empty($oscaData['house_no']) || !empty($oscaData['street'])) {
        $parts = array_filter([
            $oscaData['house_no'], $oscaData['street'], $barangay,
            $oscaData['city'], $oscaData['province'], $oscaData['zip_code']
        ]);
        if ($parts) $completeAddress = implode(', ', $parts);
    }

    if (empty($errorMessage)) {
        // Detect silent POST failure caused by upload exceeding post_max_size
        if (empty($_FILES) && $_SERVER['CONTENT_LENGTH'] > 0) {
            $maxSize = ini_get('post_max_size');
            $errorMessage = "Upload failed: the total request size exceeds the server limit (post_max_size = {$maxSize}). Please use smaller images (under 5MB each) and try again.";
        } else {
            // Allowed MIME types for uploaded documents
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            // Every file field on an official application form is retained.
            // The older columns below remain for backward compatibility, but
            // this collection is what lets the modal show every requirement.
            $documentLabels = [
                'proofOfAddress' => 'Proof of Address',
                'idImage' => 'ID / Identification Photo',
                'oscaIdPhoto' => 'OSCA ID Photo',
                'lbIdPhoto' => 'Land Bank ID Photo',
                'doc_death_certificate' => 'Death Certificate',
                'doc_relationship_proof' => 'Proof of Relationship',
                'doc_barangay_cert' => 'Barangay Certificate',
                'doc_claimant_id' => 'Claimant Government ID',
                'doc_affidavit_loss' => 'Affidavit of Loss',
                'doc_birth_certificate' => 'Birth Certificate',
                'doc_valid_id' => 'Valid Government ID',
                'doc_indigency_cert' => 'Certificate of Indigency',
                'doc_osca_id' => 'OSCA Senior Citizen ID',
                'validId1' => 'Valid ID (Front)',
                'validId2' => 'Valid ID (Back)',
                'birthOriginal' => 'Birth Certificate (Original)',
                'birthPhotocopy' => 'Birth Certificate (Photocopy)',
                'originalSeniorId' => 'Original Senior Citizen ID',
                'affidavitOfLoss' => 'Affidavit of Loss',
                'cancellationCert' => 'Cancellation Certificate',
                'oscaAdditionalDoc' => 'Additional Supporting Document',
            ];
            $submittedDocuments = [];
            foreach ($documentLabels as $field => $label) {
                if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) continue;
                if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK || $_FILES[$field]['size'] <= 0) {
                    $errorMessage = "{$label} upload failed. Please select the file again.";
                    break;
                }
                $mimeType = $finfo->file($_FILES[$field]['tmp_name']);
                if (!in_array($mimeType, $allowedMimes, true)) {
                    $errorMessage = "{$label}: only JPEG, PNG, GIF, and PDF files are accepted.";
                    break;
                }
                $submittedDocuments[] = [
                    'key' => $field,
                    'label' => $label,
                    'mime' => $mimeType,
                    'data' => file_get_contents($_FILES[$field]['tmp_name']),
                ];
            }

            $proofOfAddress     = null;
            $proofOfAddressType = null;
            if (empty($errorMessage)) {
                $uploadedProofKey = null;
                if (isset($_FILES['proofOfAddress']) && $_FILES['proofOfAddress']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $uploadedProofKey = 'proofOfAddress';
                } elseif (isset($_FILES['doc_barangay_cert']) && $_FILES['doc_barangay_cert']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $uploadedProofKey = 'doc_barangay_cert';
                }

                if ($uploadedProofKey && $_FILES[$uploadedProofKey]['error'] === UPLOAD_ERR_OK && $_FILES[$uploadedProofKey]['size'] > 0) {
                    $mimeType = $finfo->file($_FILES[$uploadedProofKey]['tmp_name']);
                    if (!in_array($mimeType, $allowedMimes)) {
                        $errorMessage = "Proof of Address: only JPEG, PNG, GIF, and PDF files are accepted. You uploaded: {$mimeType}";
                    } else {
                        $proofOfAddress     = file_get_contents($_FILES[$uploadedProofKey]['tmp_name']);
                        $proofOfAddressType = $mimeType;
                    }
                } elseif ($uploadedProofKey && $_FILES[$uploadedProofKey]['error'] !== UPLOAD_ERR_NO_FILE) {
                    $uploadErr = $_FILES[$uploadedProofKey]['error'];
                    $errorMessage = "Proof of Address upload error (code {$uploadErr}). Please try a smaller file.";
                }
            }

            $idImage     = null;
            $idImageType = null;
            if (empty($errorMessage)) {
                $uploadedIdImageKey = null;
                if (isset($_FILES['idImage']) && $_FILES['idImage']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $uploadedIdImageKey = 'idImage';
                } elseif (isset($_FILES['oscaIdPhoto']) && $_FILES['oscaIdPhoto']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $uploadedIdImageKey = 'oscaIdPhoto';
                } elseif (isset($_FILES['lbIdPhoto']) && $_FILES['lbIdPhoto']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $uploadedIdImageKey = 'lbIdPhoto';
                } elseif (isset($_FILES['doc_valid_id']) && $_FILES['doc_valid_id']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $uploadedIdImageKey = 'doc_valid_id';
                } elseif (isset($_FILES['doc_claimant_id']) && $_FILES['doc_claimant_id']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $uploadedIdImageKey = 'doc_claimant_id';
                }

                if ($uploadedIdImageKey && $_FILES[$uploadedIdImageKey]['error'] === UPLOAD_ERR_OK && $_FILES[$uploadedIdImageKey]['size'] > 0) {
                    $mimeType = $finfo->file($_FILES[$uploadedIdImageKey]['tmp_name']);
                    if (!in_array($mimeType, $allowedMimes)) {
                        $errorMessage = "ID Image: only JPEG, PNG, GIF, and PDF files are accepted. You uploaded: {$mimeType}";
                    } else {
                        $idImage     = file_get_contents($_FILES[$uploadedIdImageKey]['tmp_name']);
                        $idImageType = $mimeType;
                    }
                } elseif ($uploadedIdImageKey && $_FILES[$uploadedIdImageKey]['error'] !== UPLOAD_ERR_NO_FILE) {
                    $uploadErr = $_FILES[$uploadedIdImageKey]['error'];
                    $errorMessage = "ID Image upload error (code {$uploadErr}). Please try a smaller file.";
                }
            }
        }

        if (empty($errorMessage)) {
            try {
                $conn->beginTransaction();
                // Prepare and bind - Including new columns
                $oscaCols = getOscaExtraColumns();
                $colList = 'id_number, full_name, application_type, birth_date, contact_number, complete_address,
                            emergency_contact, emergency_contact_name, barangay,
                            proof_of_address, proof_of_address_type, id_image, id_image_type,
                            lastName, firstName, middleName, suffix, disability_type,
                            sss_number, pension_amount, date_of_death, relationship_to_deceased,
                            is_proxy_application, proxy_name, proxy_relationship, proxy_contact_number, proxy_token,
                            priority_level, workflow_state, email_address, additional_notes,
                            home_visit_scheduled_at, home_visit_status, sms_notification_status, ' . implode(', ', $oscaCols);
                $placeholders = implode(', ', array_fill(0, 34 + count($oscaCols), '?'));

                $params = [
                    $idNumber, $fullName, $applicationType, $birthDate, $contactNumber, $completeAddress,
                    $emergencyContact, $emergencyContactName, $barangay,
                    $proofOfAddress, $proofOfAddressType, $idImage, $idImageType,
                    $lastName, $firstName, $middleName, $suffix, $disabilityType,
                    $sssNumber, $pensionAmount, $dateOfDeath, $relationshipToDeceased,
                    $isProxy, $proxyName, $proxyRelationship, $proxyContactNumber, $proxyToken,
                    $priorityLevel, 'Received', $emailAddress, $additionalNotes,
                    $homeVisitScheduledAt, $homeVisitStatus, $homeVisitScheduledAt ? 'queued' : null,
                ];
                foreach ($oscaCols as $col) {
                    $params[] = $oscaData[$col];
                }

                // A representative pre-registration already owns its PRX id.
                // Completing the form must enrich that record instead of
                // attempting a second INSERT with the same primary key.
                $existingProxyState = null;
                if ($isProxy === 1 && !empty($proxyToken) && !empty($idNumber)) {
                    $existingProxyStmt = $conn->prepare(
                        'SELECT workflow_state FROM applications
                         WHERE id_number = ? AND is_proxy_application = 1 AND proxy_token = ?
                         LIMIT 1'
                    );
                    $existingProxyStmt->execute([$idNumber, $proxyToken]);
                    $existingProxyState = $existingProxyStmt->fetchColumn();
                }

                $isCompletingProxyRegistration = ($existingProxyState !== false && $existingProxyState !== null);
                if ($isCompletingProxyRegistration) {
                    $allColumns = array_map('trim', explode(',', preg_replace('/\s+/', ' ', $colList)));
                    $updateColumns = array_slice($allColumns, 1); // id_number remains immutable
                    $assignments = implode(', ', array_map(static fn($column) => "$column = ?", $updateColumns));
                    $updateParams = array_slice($params, 1);
                    $updateParams[] = $idNumber;
                    $updateParams[] = $proxyToken;
                    $stmt = $conn->prepare(
                        "UPDATE applications SET $assignments
                         WHERE id_number = ? AND is_proxy_application = 1 AND proxy_token = ?"
                    );
                    $saved = $stmt->execute($updateParams);
                } else {
                    $stmt = $conn->prepare("INSERT INTO applications ($colList) VALUES ($placeholders)");
                    $saved = $stmt->execute($params);
                }

                if ($saved) {
                    if ($applicationType === 'pension') {
                        $visitAssessmentStmt = $conn->prepare(
                            'UPDATE applications
                             SET home_visit_eligibility = ?, home_visit_eligibility_reason = ?,
                                 home_visit_personnel_id = ?, home_visit_assessed_by = ?,
                                 home_visit_assessed_at = CURRENT_TIMESTAMP
                             WHERE id_number = ?'
                        );
                        $visitAssessmentStmt->execute([
                            $homeVisitEligibility,
                            $homeVisitEligibilityReason !== '' ? $homeVisitEligibilityReason : null,
                            $homeVisitPersonnelId,
                            $_SESSION['user_id'] ?? null,
                            $idNumber,
                        ]);
                    }
                    if ($submittedDocuments) {
                        $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
                        $documentSql = $driver === 'pgsql'
                            ? 'INSERT INTO application_documents (application_id, document_key, document_label, mime_type, document_data) VALUES (?, ?, ?, ?, ?)
                               ON CONFLICT (application_id, document_key) DO UPDATE SET document_label = EXCLUDED.document_label, mime_type = EXCLUDED.mime_type, document_data = EXCLUDED.document_data, updated_at = CURRENT_TIMESTAMP'
                            : 'INSERT INTO application_documents (application_id, document_key, document_label, mime_type, document_data) VALUES (?, ?, ?, ?, ?)
                               ON DUPLICATE KEY UPDATE document_label = VALUES(document_label), mime_type = VALUES(mime_type), document_data = VALUES(document_data), updated_at = CURRENT_TIMESTAMP';
                        $documentStmt = $conn->prepare($documentSql);
                        foreach ($submittedDocuments as $document) {
                            $documentStmt->bindValue(1, $idNumber, PDO::PARAM_STR);
                            $documentStmt->bindValue(2, $document['key'], PDO::PARAM_STR);
                            $documentStmt->bindValue(3, $document['label'], PDO::PARAM_STR);
                            $documentStmt->bindValue(4, $document['mime'], PDO::PARAM_STR);
                            $documentStmt->bindValue(5, $document['data'], PDO::PARAM_LOB);
                            $documentStmt->execute();
                        }
                    }
                    // Log a state transition only for a newly created record.
                    // The pre-registration already entered Received when it
                    // was placed in the priority queue.
                    if (!$isCompletingProxyRegistration) {
                        $stmtHist = $conn->prepare("INSERT INTO application_history (application_id, previous_state, new_state, changed_by, comments) VALUES (?, ?, ?, ?, ?)");
                        $userName = $_SESSION['username'] ?? 'barangay_staff';
                        $stmtHist->execute([$idNumber, 'None', 'Received', $userName, 'Application created and received at the counter.']);
                    }

                    $conn->commit();

                    $submissionMessage = 'Application submitted successfully.';
                    if ($applicationType === 'pension' && $homeVisitScheduledAt) {
                        $visit = new DateTime($homeVisitScheduledAt, new DateTimeZone('Asia/Manila'));
                        $visitLabel = $visit->format('F j, Y \a\t g:i A');
                        $smsVisitLabel = $visit->format('M j, Y g:i A');
                        $smsMessage = "SENIORLINK: Local Pension {$idNumber} received. Home visit: {$smsVisitLabel}. Please keep your phone available. Brgy {$barangay}.";

                        try {
                            $smsResult = sendOrQueueSms($conn, $idNumber, $contactNumber, $smsMessage);
                            $smsStatusStmt = $conn->prepare('UPDATE applications SET sms_notification_status = ? WHERE id_number = ?');
                            $smsStatusStmt->execute([$smsResult['status'], $idNumber]);
                            $submissionMessage = "Application submitted. Home visit scheduled for {$visitLabel}. "
                                . ($smsResult['status'] === 'sent'
                                    ? 'A confirmation text was sent to the applicant.'
                                    : 'The confirmation text is saved in the SMS queue.');
                        } catch (Throwable $smsError) {
                            $submissionMessage = "Application submitted. Home visit scheduled for {$visitLabel}. The text could not be sent yet and can be retried from the SMS queue.";
                        }
                    }

                    $_SESSION['application_submission_notice'] = $submissionMessage;

                    header("Location: submit_application.php?success=1");
                    exit();
                } else {
                    if ($conn->inTransaction()) $conn->rollBack();
                    $errorMessage = "Database error while saving the application. Please try again.";
                }
            } catch (PDOException $e) {
                if ($conn->inTransaction()) $conn->rollBack();
                if (strpos($e->getMessage(), 'max_allowed_packet') !== false || $e->getCode() == '08S01') {
                    $errorMessage = "Upload failed: The size of the uploaded files exceeds the database's transfer limit (max_allowed_packet). Please try uploading smaller images/files (under 2MB each) or contact the administrator to increase the MySQL max_allowed_packet size.";
                } else {
                    $errorMessage = "Database error: " . $e->getMessage();
                }
            }
        } // end inner errorMessage check
    } // end outer errorMessage check
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CPRAS - New Application</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/barangay-sidebar.css?v=4">
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
            border: 1px solid #d0dae8;
            width: 90%;
            max-width: 500px;
            border-radius: 14px;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.1), 0 24px 56px rgba(15, 23, 42, 0.2);
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

        /* ── Google Font ─────────────────────────────── */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        body, .main-content { font-family: 'Inter', sans-serif; }

        /* ─── Page Header ────────────────────────────────────────────────── */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
        }
        .page-header-left .greeting {
            font-family: 'Inter', sans-serif;
            font-size: 0.98rem;
            font-weight: 500;
            color: #6b7280;
            margin-bottom: 6px;
            line-height: 1.3;
        }
        .page-header-left .greeting strong {
            color: #2563eb;
            font-weight: 700;
        }
        .page-header-left h1 {
            font-family: 'Inter', sans-serif;
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: var(--primary, #0f172a);
            margin: 0;
            line-height: 1.05;
        }
        .page-header-left h1 span { color: var(--accent, #2563eb); }
        .header-user {
            display: flex;
            align-items: center;
            gap: 12px;
            border-radius: 30px;
            padding: 7px 13px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 3px solid transparent;
            background: linear-gradient(var(--card, #ffffff), var(--card, #ffffff)) padding-box, linear-gradient(135deg, #0f172a 0%, #3498db 100%) border-box;
        }
        .header-user img {
            width: 40px; height: 40px;
            border-radius: 50%; object-fit: cover;
            border: 2px solid var(--accent, #2563eb);
        }
        .header-user-info h3 { font-size: 0.9rem; font-weight: 700; color: var(--primary, #0f172a); margin: 0; }
        .header-user-info p  { font-size: 0.75rem; color: var(--gray, #94a3b8); margin: 0; }

        /* ── Hero intro banner ───────────────────────── */
        .new-app-hero {
            background: linear-gradient(135deg, #1e3a5f 0%, #1e293b 50%, #0f172a 100%);
            border-radius: 20px;
            padding: 36px 40px;
            margin-bottom: 32px;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(59,130,246,0.2);
        }
        .new-app-hero::before {
            content: '';
            position: absolute;
            top: -60px; right: -60px;
            width: 260px; height: 260px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(59,130,246,0.18) 0%, transparent 70%);
            pointer-events: none;
        }
        .new-app-hero::after {
            content: '';
            position: absolute;
            bottom: -40px; left: 30%;
            width: 180px; height: 180px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(139,92,246,0.12) 0%, transparent 70%);
            pointer-events: none;
        }
        .new-app-hero h2 {
            margin: 0 0 6px;
            font-size: 1.6rem;
            font-weight: 800;
            background: linear-gradient(90deg, #e2e8f0, #93c5fd);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .new-app-hero p {
            margin: 0;
            font-size: 1rem;
            color: #94a3b8;
            max-width: 520px;
            line-height: 1.6;
        }
        .hero-step-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(59,130,246,0.15);
            border: 1px solid rgba(59,130,246,0.3);
            color: #93c5fd;
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 4px 12px;
            border-radius: 100px;
            margin-bottom: 14px;
        }

        /* ═══════════════════════════════════════════════════════════
           APPLICATION TYPE CARD SYSTEM — HCI Premium Design
           ═══════════════════════════════════════════════════════════ */

        /* ── Hint text above cards ─────────────────────────────── */
        .card-section-hint {
            font-size: 0.875rem;
            color: #64748b;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 7px;
        }
        .card-section-hint i { color: #475569; }

        /* ── Card entrance animation ────────────────────────────── */
        @keyframes cardFadeUp {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .app-type-card { animation: cardFadeUp 0.4s ease both; }
        .app-type-card:nth-child(1) { animation-delay: 0.03s; }
        .app-type-card:nth-child(2) { animation-delay: 0.08s; }
        .app-type-card:nth-child(3) { animation-delay: 0.13s; }
        .app-type-card:nth-child(4) { animation-delay: 0.18s; }
        .app-type-card:nth-child(5) { animation-delay: 0.23s; }
        .app-type-card:nth-child(6) { animation-delay: 0.28s; }
        .app-type-card:nth-child(7) { animation-delay: 0.33s; }

        /* ── Card Grid ────────────────────────────────────────────── */
        .app-type-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr)); /* 4 columns layout */
            gap: 22px;
            margin-bottom: 18px;
        }

        /* Responsive fallback for smaller screens */
        @media (max-width: 1200px) {
            .app-type-grid { grid-template-columns: repeat(3, minmax(0,1fr)); }
        }
        @media (max-width: 900px) {
            .app-type-grid { grid-template-columns: repeat(2, minmax(0,1fr)); }
        }
        @media (max-width: 480px) {
            .app-type-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .selected-type-banner {
                align-items: flex-start;
                flex-wrap: wrap;
                gap: 12px;
                padding: 16px;
            }
            .selected-type-banner .banner-icon { flex: 0 0 44px; }
            .selected-type-banner .banner-state { margin-left: 56px; width: calc(100% - 56px); }
            .app-type-card { padding: 20px 18px; }
        }

        /* ── Individual Card ──────────────────────────────────────── */
        .app-type-card {
            position: relative;
            display: flex;
            flex-direction: column;
            gap: 16px;
            padding: 26px 22px 22px;
            border-radius: 18px;
            border: 1.5px solid rgba(255,255,255,0.08);
            background: linear-gradient(145deg, rgba(10,14,23,0.75), rgba(15,23,42,0.85)), var(--card-bg, linear-gradient(160deg, rgba(30,41,59,0.98) 0%, rgba(15,23,42,0.95) 100%));
            cursor: pointer;
            transition: transform 0.22s cubic-bezier(0.34,1.2,0.64,1),
                        border-color 0.22s ease,
                        box-shadow 0.22s ease;
            user-select: none;
            overflow: hidden;
            outline: none;
        }
        /* Glass sheen overlay */
        .app-type-card::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 18px;
            background: linear-gradient(135deg,
                rgba(255,255,255,0.04) 0%,
                transparent 50%);
            pointer-events: none;
        }
        /* Color accent bottom strip */
        .app-type-card::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 3px;
            background: var(--card-accent, transparent);
            border-radius: 0 0 18px 18px;
            opacity: 0;
            transition: opacity 0.22s ease;
        }
        .app-type-card:hover::after { opacity: 1; }
        .app-type-card.selected::after {
            opacity: 1;
            /* shimmer on the strip */
            background: linear-gradient(90deg,
                transparent,
                rgba(255,255,255,0.5),
                transparent);
            background-size: 200% 100%;
            animation: stripShimmer 1.8s linear infinite;
        }
        @keyframes stripShimmer {
            0%   { background-position: -200% 0; }
            100% { background-position:  200% 0; }
        }

        .app-type-card:hover {
            transform: translateY(-6px) scale(1.015);
            border-color: rgba(148,163,184,0.3);
            box-shadow:
                0 16px 40px rgba(0,0,0,0.35),
                0 0 0 1px rgba(255,255,255,0.06) inset;
        }
        .app-type-card:focus-visible {
            border-color: rgba(96,165,250,0.7);
            box-shadow: 0 0 0 3px rgba(59,130,246,0.35);
        }
        .app-type-card.selected {
            transform: translateY(-4px);
            border-color: rgba(99,102,241,0.7);
            background: linear-gradient(160deg,
                rgba(30,27,75,0.95) 0%,
                rgba(15,23,42,0.98) 100%);
            box-shadow:
                0 0 0 3px rgba(99,102,241,0.28),
                0 20px 50px rgba(0,0,0,0.4);
        }

        /* ── Icon tile ────────────────────────────────────────────── */
        .app-type-card .card-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0;
            position: relative;
            transition: transform 0.25s cubic-bezier(0.34,1.4,0.64,1);
            box-shadow: 0 8px 24px rgba(0,0,0,0.3);
        }
        .app-type-card:hover .card-icon {
            transform: scale(1.14) rotate(-5deg);
        }
        .app-type-card.selected .card-icon {
            transform: scale(1.08) rotate(0deg);
        }

        /* ── Text ─────────────────────────────────────────────────── */
        .app-type-card .card-code {
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: #64748b;
            margin-bottom: -8px;
        }

        /* Visually hidden helper for screen readers */
        .sr-only { position: absolute !important; height: 1px; width: 1px; overflow: hidden; clip: rect(1px, 1px, 1px, 1px); white-space: nowrap; }
        .app-type-card.selected .card-code { color: #818cf8; }
        .app-type-card .card-title {
            font-size: 1rem;
            font-weight: 800;
            color: #0b2330; /* darker, high-contrast title */
            line-height: 1.35;
        }
        .app-type-card.selected .card-title { color: #0b2330; }
        .app-type-card .card-desc {
            font-size: 0.86rem;
            color: #606f7a; /* slightly darker description for readability */
            line-height: 1.45;
            margin-top: -2px;
        }
        .app-type-card:hover .card-desc { color: #4b5962; }
        .app-type-card.selected .card-desc { color: #0b2330; }

        /* ── Selected check badge ─────────────────────────────────── */
        .app-type-card .card-check {
            position: absolute;
            top: 14px; right: 14px;
            width: 24px; height: 24px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: #fff;
            font-size: 0.65rem;
            display: none;
            align-items: center;
            justify-content: center;
            box-shadow: 0 3px 10px rgba(99,102,241,0.55);
            animation: popIn 0.25s cubic-bezier(0.34,1.56,0.64,1);
        }
        @keyframes popIn {
            from { transform: scale(0) rotate(-30deg); opacity: 0; }
            to   { transform: scale(1) rotate(0deg);   opacity: 1; }
        }
        .app-type-card.selected .card-check { display: flex; }

        /* ── Arrow accent ─────────────────────────────────────────── */
        .app-type-card .card-arrow {
            position: absolute;
            bottom: 18px; right: 18px;
            font-size: 0.95rem;
            color: #1e293b;
            transition: color 0.22s, transform 0.22s;
        }
        .app-type-card:hover .card-arrow {
            color: #94a3b8;
            transform: translateX(4px);
        }
        .app-type-card.selected .card-arrow { display: none; }

        /* ── Section label above grid ─────────────────────────────── */
        .card-section-label {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
            color: #64748b;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }
        .card-section-label::before,
        .card-section-label::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(255,255,255,0.06);
        }

        /* ── Form body reveal ─────────────────────────────────────── */
        #formBody {
            overflow: hidden;
            max-height: 0;
            opacity: 0;
            transition: max-height 0.6s cubic-bezier(0.4,0,0.2,1), opacity 0.45s ease;
        }
        #formBody.visible { max-height: 8000px; opacity: 1; }

        /* ── Selected type banner ─────────────────────────────────── */
        .selected-type-banner {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px 22px;
            border-radius: 14px;
            border: 1.5px solid rgba(99,102,241,0.4);
            background: linear-gradient(135deg,
                rgba(30,27,75,0.5) 0%,
                rgba(15,23,42,0.7) 100%);
            margin-bottom: 28px;
            backdrop-filter: blur(12px);
            animation: fadeSlideDown 0.35s ease;
        }
        @keyframes fadeSlideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .selected-type-banner .banner-icon {
            width: 46px; height: 46px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; flex-shrink: 0;
        }
        .selected-type-banner .banner-info { flex: 1; }
        .selected-type-banner .banner-info small {
            display: block; font-size: 0.75rem;
            text-transform: uppercase; letter-spacing: 0.08em;
            color: #64748b; margin-bottom: 3px;
        }
        .selected-type-banner .banner-info strong {
            font-size: 1.05rem; font-weight: 700;
            color: #e2e8f0;
        }
        .banner-state {
            display:flex; align-items:center; gap:7px; flex-shrink:0;
            padding:7px 10px; border-radius:999px;
            background:rgba(16,185,129,.12); border:1px solid rgba(16,185,129,.24);
            color:#bbf7d0; font-size:.72rem; font-weight:700;
        }

        /* ── Step headings (inside form) ──────────────────────────── */
        .step-heading {
            display: flex; align-items: flex-start; gap: 14px;
            margin-bottom: 24px;
        }
        .step-number {
            width: 36px; height: 36px; border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6, #6366f1);
            color: #fff; font-size: 1rem; font-weight: 800;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; box-shadow: 0 4px 12px rgba(59,130,246,0.4);
        }
        .step-heading h3 {
            margin: 0 0 3px;
            font-size: 1.15rem; font-weight: 700;
            color: var(--text, #f1f5f9);
        }
        .step-heading p {
            margin: 0; font-size: 0.875rem; color: #64748b;
        }

        .form-top-nav {
            margin-bottom: 18px;
        }
        .btn-form-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 10px;
            border: 1px solid rgba(15,23,42,0.12);
            background: #fff;
            color: #475569;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease;
        }
        .btn-form-back:hover {
            background: #f8fafc;
            border-color: rgba(59,130,246,0.35);
            color: #1e40af;
        }
        .form-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            margin-top: 8px;
        }

        /* ── Individual Card ─────────────────────────── */
        .app-type-card {
            position: relative;
            display: flex;
            flex-direction: column;
            gap: 14px;
            padding: 22px 20px;
            border-radius: 16px;
            border: 1.5px solid rgba(255,255,255,0.07);
            background: linear-gradient(145deg, rgba(10,14,23,0.7), rgba(15,23,42,0.85)), var(--card-bg, linear-gradient(145deg, rgba(30,41,59,0.95), rgba(15,23,42,0.9)));
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4,0,0.2,1);
            user-select: none;
            overflow: hidden;
        }
        .app-type-card::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 16px;
            background: linear-gradient(135deg, transparent 40%, rgba(255,255,255,0.03));
            pointer-events: none;
        }
        .app-type-card:hover {
            transform: translateY(-5px) scale(1.02);
            border-color: rgba(96,165,250,0.5);
            box-shadow: 0 12px 40px rgba(59,130,246,0.2), 0 0 0 1px rgba(96,165,250,0.1);
        }
        .app-type-card.selected {
            border-color: rgba(59,130,246,0.8);
            background: linear-gradient(145deg, rgba(30,58,138,0.5), rgba(15,23,42,0.95));
            box-shadow: 0 0 0 3px rgba(59,130,246,0.25), 0 16px 48px rgba(59,130,246,0.3);
        }
        /* Shimmer sweep on selected */
        .app-type-card.selected::after {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 60%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.06), transparent);
            animation: shimmer 1.8s ease infinite;
        }
        @keyframes shimmer {
            0%   { left: -100%; }
            100% { left: 200%; }
        }

        /* ── Card Icon ───────────────────────────────── */
        .app-type-card .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
            position: relative;
            transition: transform 0.25s ease;
        }
        .app-type-card:hover .card-icon {
            transform: scale(1.12) rotate(-4deg);
        }

        /* ── Card Text ───────────────────────────────── */
        .app-type-card .card-code {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            opacity: 0.55;
            color: var(--text, #f1f5f9);
            margin-bottom: -8px;
        }
        .app-type-card .card-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text, #f1f5f9);
            line-height: 1.35;
        }
        .app-type-card .card-desc {
            font-size: 0.85rem;
            color: #64748b;
            line-height: 1.5;
            margin-top: -4px;
        }
        .app-type-card.selected .card-desc { color: #93c5fd; }

        /* ── Check badge ─────────────────────────────── */
        .app-type-card .card-check {
            position: absolute;
            top: 12px; right: 12px;
            width: 22px; height: 22px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6, #6366f1);
            color: #fff;
            font-size: 0.65rem;
            display: none;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(59,130,246,0.5);
            animation: popIn 0.2s cubic-bezier(0.34,1.56,0.64,1);
        }
        @keyframes popIn {
            from { transform: scale(0); opacity: 0; }
            to   { transform: scale(1); opacity: 1; }
        }
        .app-type-card.selected .card-check { display: flex; }

        /* ── Arrow accent on card ────────────────────── */
        .app-type-card .card-arrow {
            position: absolute;
            bottom: 16px; right: 16px;
            font-size: 1rem;
            color: #334155;
            transition: color 0.2s, transform 0.2s;
        }
        .app-type-card:hover .card-arrow { color: #60a5fa; transform: translateX(3px); }
        .app-type-card.selected .card-arrow { display: none; }

        /* ── Form body reveal ────────────────────────── */
        #formBody {
            overflow: hidden;
            max-height: 0;
            opacity: 0;
            transition: max-height 0.55s cubic-bezier(0.4,0,0.2,1), opacity 0.4s ease;
        }
        #formBody.visible { max-height: 8000px; opacity: 1; }

        /* ── Selected type banner ────────────────────── */
        .selected-type-banner {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px 22px;
            border-radius: 14px;
            border: 1.5px solid rgba(59,130,246,0.4);
            background: linear-gradient(135deg, rgba(30,58,138,0.3), rgba(15,23,42,0.6));
            margin-bottom: 28px;
            backdrop-filter: blur(8px);
            animation: fadeSlideDown 0.35s ease;
        }
        @keyframes fadeSlideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .selected-type-banner .banner-icon {
            width: 46px; height: 46px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; flex-shrink: 0;
        }
        .selected-type-banner .banner-info { flex: 1; }
        .selected-type-banner .banner-info small {
            display: block; font-size: 0.85rem;
            text-transform: uppercase; letter-spacing: 0.08em;
            color: #64748b; margin-bottom: 3px;
        }
        .selected-type-banner .banner-info strong {
            font-size: 1.15rem; font-weight: 700;
            color: #e2e8f0;
        }

        /* ── Step headings ───────────────────────────── */
        .step-heading {
            display: flex; align-items: flex-start; gap: 14px;
            margin-bottom: 24px;
        }
        .step-number {
            width: 36px; height: 36px; border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6, #6366f1);
            color: #fff; font-size: 1rem; font-weight: 800;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; box-shadow: 0 4px 12px rgba(59,130,246,0.4);
        }
        .step-heading h3 {
            margin: 0 0 3px;
            font-size: 1.2rem; font-weight: 700;
            color: var(--text, #f1f5f9);
        }
        .step-heading p {
            margin: 0; font-size: 0.9rem; color: #64748b;
        }
        /* ---------------------------------------------------------------------------
           Simple flat card override (makes application-type cards minimal and static)
           - Removes entrance animations, shimmer, heavy shadows and transforms
           - Uses flat background, thin border, and clear contrast for text
           --------------------------------------------------------------------------- */
        /* entrance animation */
        @keyframes cardLiftIn {
            from { opacity: 0; transform: translateY(12px) scale(.995); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }
        @keyframes cardHoverBounce {
            0%   { transform: translateY(0) scale(1); }
            40%  { transform: translateY(-18px) scale(1.025); }
            65%  { transform: translateY(-10px) scale(1.012); }
            85%  { transform: translateY(-14px) scale(1.018); }
            100% { transform: translateY(-12px) scale(1.015); }
        }
        @keyframes arrowBounce {
            0%, 100% { transform: translateY(-50%) translateX(0); }
            50%      { transform: translateY(-50%) translateX(10px); }
        }
        /* staggered entrance for up to 8 cards */
        #appTypeGrid .app-type-card { animation: cardLiftIn 420ms cubic-bezier(0.2,0.8,0.2,1) both; }
        #appTypeGrid .app-type-card:nth-child(1) { animation-delay: 40ms; }
        #appTypeGrid .app-type-card:nth-child(2) { animation-delay: 90ms; }
        #appTypeGrid .app-type-card:nth-child(3) { animation-delay: 140ms; }
        #appTypeGrid .app-type-card:nth-child(4) { animation-delay: 190ms; }
        #appTypeGrid .app-type-card:nth-child(5) { animation-delay: 240ms; }
        #appTypeGrid .app-type-card:nth-child(6) { animation-delay: 290ms; }
        #appTypeGrid .app-type-card:nth-child(7) { animation-delay: 340ms; }
        #appTypeGrid .app-type-card:nth-child(8) { animation-delay: 390ms; }

        .app-type-card {
            transition: transform 0.35s cubic-bezier(0.34, 1.2, 0.64, 1), box-shadow 260ms ease, border-color 200ms ease;
            background: #ffffff !important;
            border-radius: 14px !important;
            border: 1px solid #d8e0ea !important;
            box-shadow: 0 4px 6px rgba(15, 23, 42, 0.04), 0 12px 28px rgba(15, 23, 42, 0.08) !important;
            display: flex;
            align-items: flex-start;
            gap: 22px;
            padding: 28px 26px;
            min-height: 168px;
            position: relative;
            will-change: transform, box-shadow;
            transform-origin: center bottom;
        }
        .app-type-card::before,
        .app-type-card::after {
            display: none !important;
        }
        .app-type-card:hover {
            animation: cardHoverBounce 0.55s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
            box-shadow: 0 8px 16px rgba(15, 23, 42, 0.06), 0 24px 48px rgba(15, 23, 42, 0.14) !important;
            border-color: #b8c8dc !important;
        }
        .app-type-card .card-icon {
            width: 56px; height: 56px; border-radius: 12px;
            box-shadow: 0 6px 18px rgba(2,6,23,0.06);
            transform: none !important; font-size: 1.25rem;
            display:flex; align-items:center; justify-content:center; color: #ffffff !important;
            flex-shrink: 0;
            transition: transform 260ms cubic-bezier(0.2,0.8,0.2,1), box-shadow 260ms ease;
        }
        .app-type-card:hover .card-icon { transform: translateY(-4px) scale(1.06); box-shadow: 0 18px 36px rgba(2,6,23,0.12); }

        /* subtle icon pop when card is selected */
        .app-type-card.selected .card-icon { animation: popIcon 420ms cubic-bezier(0.2,0.9,0.2,1); }
        @keyframes popIcon { 0% { transform: scale(.9); } 60% { transform: scale(1.12); } 100% { transform: scale(1); } }
        .app-type-card .card-content { display:flex; flex-direction:column; gap:8px; flex:1; }
        .app-type-card .card-desc { margin-top:6px; }
        .app-type-card .card-code { font-size: 0.72rem; opacity: 0.7; color: #6b7280; }
        .app-type-card .card-title { font-size: 1.12rem; font-weight: 800; color: #071727; }
        .app-type-card .card-desc { font-size: 0.92rem; color: #475569; line-height: 1.5; }
        .app-type-card .card-check { display: flex !important; opacity: 0; visibility: hidden; }
        .app-type-card.selected .card-check { opacity: 1; visibility: visible; }
        /* Arrow: right aligned vertically centered */
        .app-type-card .card-arrow {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 1rem;
        }
        .app-type-card:hover .card-arrow {
            color: var(--card-accent, #3b82f6);
            animation: arrowBounce 0.75s ease-in-out infinite;
        }

        /* container shadow around the whole application form area */
        .application-form {
            padding: 18px 20px 28px;
            border-radius: 14px;
            background: linear-gradient(180deg, rgba(255,255,255,0.99), rgba(250,250,250,0.98));
            box-shadow: 0 4px 8px rgba(15, 23, 42, 0.04), 0 16px 40px rgba(15, 23, 42, 0.08);
            border: 1px solid #d8e0ea;
        }
        /* Final UI polish overrides */
        .app-type-card {
            border-left: 4px solid transparent !important;
            overflow: visible;
        }
        .app-type-card::before {
            content: '';
            position: absolute;
            left: 12px; top: 18px; bottom: 18px;
            width: 4px; border-radius: 4px;
            background: linear-gradient(180deg, rgba(0,0,0,0.05), rgba(0,0,0,0.02));
            opacity: 0; transition: opacity 200ms ease, background 200ms ease;
        }
        .app-type-card:hover::before { opacity: 1; }
        .app-type-card:hover { border-left-color: var(--card-accent, #60a5fa) !important; border-color: #b8c8dc !important; }

        .app-type-card .card-icon {
            width: 72px !important; height: 72px !important; border-radius: 50% !important;
            font-size: 1.55rem !important; box-shadow: 0 18px 36px rgba(2,6,23,0.10) !important;
            display:flex; align-items:center; justify-content:center; color: #fff !important;
        }

        .app-type-card .card-title {
            font-size: 1.14rem; font-weight: 800; color: #081022;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis;
        }

        .app-type-card:focus-visible { outline: none; box-shadow: 0 0 0 4px rgba(99,102,241,0.12); }

        .app-type-grid { gap: 24px; }

        .application-form .selected-type-banner {
            border: 1px solid #d0dae8 !important;
            box-shadow: 0 4px 10px rgba(15, 23, 42, 0.05), 0 14px 32px rgba(15, 23, 42, 0.08);
        }
        .application-form .form-section {
            border: 1px solid #d8e0ea;
            border-radius: 14px;
            padding: 22px 24px;
            margin-bottom: 20px;
            background: #ffffff;
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.04), 0 10px 24px rgba(15, 23, 42, 0.06);
        }

        #cardSelectorSection.hidden { display: none; }

        /* Reusable document upload preview shown in every application form. */
        .document-upload-preview {
            display: flex;
            align-items: center;
            gap: 12px;
            min-height: 110px;
            margin-top: 8px;
            padding: 10px;
            overflow: hidden;
            color: #64748b;
            background: rgba(255, 255, 255, .76);
            border: 1.5px dashed #b8c8dc;
            border-radius: 10px;
            transition: border-color .2s ease, background-color .2s ease, box-shadow .2s ease;
        }
        .document-upload-preview.has-file {
            color: #334155;
            background: #fff;
            border-style: solid;
            border-color: #86efac;
            box-shadow: 0 4px 12px rgba(15, 23, 42, .06);
        }
        .document-preview-visual {
            display: grid;
            place-items: center;
            width: 90px;
            min-width: 90px;
            height: 90px;
            overflow: hidden;
            color: #94a3b8;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1.4rem;
        }
        .document-upload-preview.has-file .document-preview-visual { color: #dc2626; background: #f8fafc; }
        .document-preview-visual img { width: 100%; height: 100%; object-fit: contain; background: #fff; }
        .document-preview-details { min-width: 0; flex: 1; }
        .document-preview-title { color: #334155; font-size: .78rem; font-weight: 800; line-height: 1.35; }
        .document-preview-file-name { margin-top: 2px; overflow: hidden; color: #0f172a; font-size: .78rem; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
        .document-preview-meta { margin-top: 2px; color: #64748b; font-size: .7rem; }
        .document-preview-actions { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 7px; }
        .document-preview-action {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            min-height: 30px;
            padding: 5px 9px;
            color: #1d4ed8;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 6px;
            font-size: .7rem;
            font-weight: 800;
            text-decoration: none;
            cursor: pointer;
        }
        button.document-preview-action { color: #b91c1c; background: #fff; border-color: #fecaca; }
        .document-preview-action:hover { filter: brightness(.97); }

        @media (max-width: 560px) {
            .document-upload-preview { align-items: flex-start; min-height: 82px; }
            .document-preview-visual { width: 60px; min-width: 60px; height: 60px; }
        }

        /* Benefit details modal */
        #benefitModal {
            align-items: center;
            justify-content: center;
            padding: 24px;
            box-sizing: border-box;
        }
        #benefitModal .modal-content {
            max-width: 680px;
            width: 100%;
            margin: 0;
            max-height: 90vh;
            border-radius: 18px;
            padding: 0;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            background: #ffffff !important;
            color: #1e293b !important;
            border: 1px solid #d0dae8;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08), 0 28px 70px rgba(15, 23, 42, 0.18);
        }
        .benefit-modal-header {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            padding: 24px 56px 22px 24px;
            background: linear-gradient(135deg, #f0f7ff 0%, #f8fafc 55%, #ffffff 100%);
            border-bottom: 1px solid #e2e8f0;
            flex-shrink: 0;
            position: relative;
        }
        .benefit-modal-header::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 5px;
            background: var(--benefit-accent, #3b82f6);
            border-radius: 18px 0 0 0;
        }
        .benefit-modal-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            color: #fff;
            flex-shrink: 0;
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.25);
        }
        .benefit-modal-header h2 {
            margin: 0 0 8px;
            font-size: 1.15rem;
            font-weight: 800;
            color: #0f172a !important;
            line-height: 1.4;
            padding-right: 8px;
        }
        .benefit-modal-header p {
            margin: 0;
            font-size: 0.9rem;
            color: #64748b !important;
            line-height: 1.6;
        }
        .benefit-modal-body {
            padding: 20px 24px 24px;
            max-height: none;
            flex: 1;
            overflow-y: auto;
            background: #ffffff !important;
        }
        .benefit-detail-block {
            background: #f8fafc;
            border: 1px solid #d8e0ea;
            border-radius: 12px;
            padding: 16px 18px;
            margin-bottom: 14px;
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.04), 0 8px 20px rgba(15, 23, 42, 0.05);
        }
        .benefit-detail-block:last-of-type {
            margin-bottom: 0;
        }
        .benefit-detail-label {
            margin: 0 0 12px;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #475569;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .benefit-detail-label i {
            width: 26px;
            height: 26px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.72rem;
            flex-shrink: 0;
        }
        .benefit-detail-block--benefits .benefit-detail-label i {
            background: #dcfce7;
            color: #16a34a;
        }
        .benefit-detail-block--requirements .benefit-detail-label i,
        .benefit-detail-block--documents .benefit-detail-label i {
            background: #dbeafe;
            color: #2563eb;
        }
        .benefit-detail-list {
            margin: 0;
            padding: 0;
            list-style: none;
        }
        .benefit-detail-list li {
            position: relative;
            padding: 9px 0 9px 22px;
            font-size: 0.9rem;
            color: #334155 !important;
            line-height: 1.55;
            border-bottom: 1px solid #e8eef4;
        }
        .benefit-detail-list li:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        .benefit-detail-list li:first-child {
            padding-top: 0;
        }
        .benefit-detail-list li::before {
            content: '';
            position: absolute;
            left: 4px;
            top: 15px;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #94a3b8;
        }
        .benefit-detail-block--benefits .benefit-detail-list li::before {
            background: #22c55e;
        }
        .benefit-detail-block--requirements .benefit-detail-list li::before,
        .benefit-detail-block--documents .benefit-detail-list li::before {
            background: #3b82f6;
        }
        .benefit-detail-block--benefits .benefit-detail-list li:first-child::before {
            top: 6px;
        }
        .benefit-detail-block--requirements .benefit-detail-list li:first-child::before,
        .benefit-detail-block--documents .benefit-detail-list li:first-child::before {
            top: 6px;
        }
        .benefit-ack-row {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-top: 16px;
            padding: 14px 16px;
            background: #fffbeb;
            border: 1px solid #f0d78c;
            border-radius: 12px;
            font-size: 0.88rem;
            color: #92400e !important;
            line-height: 1.55;
            box-shadow: 0 2px 8px rgba(146, 64, 14, 0.06);
        }
        .benefit-ack-row input {
            margin-top: 4px;
            flex-shrink: 0;
            accent-color: #3b82f6;
        }
        .benefit-modal-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            padding: 16px 24px 20px;
            border-top: 1px solid #d8e0ea;
            background: #f8fafc !important;
            flex-shrink: 0;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
        }
        .btn-benefit-cancel {
            padding: 11px 20px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #475569;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s ease, border-color 0.2s ease;
        }
        .btn-benefit-cancel:hover {
            background: #f1f5f9;
            border-color: #94a3b8;
        }
        .btn-benefit-proceed {
            padding: 11px 22px;
            border-radius: 10px;
            border: none;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #fff;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .btn-benefit-proceed:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(37, 99, 235, 0.4);
        }
        .btn-benefit-proceed:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            box-shadow: none;
        }
        #benefitModal .close {
            position: absolute;
            right: 16px;
            top: 16px;
            z-index: 2;
            float: none;
            color: #64748b !important;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            font-size: 22px;
            line-height: 1;
            transition: background 0.2s ease, color 0.2s ease;
        }
        #benefitModal .close:hover {
            color: #0f172a !important;
            background: rgba(15, 23, 42, 0.06);
        }

        /* Claim stubs and handwritten signature areas are printed only after
           approval. They are not part of the online application form. */
        .non-submission-preview { display: none !important; }

        /* Off-screen official-form sections keep their measured space but do
           not participate in repeated layout/paint work. This is especially
           important while the shared sidebar animates the content width. */
        @supports (content-visibility: auto) {
            .guided-benefit-form .deferred-form-section {
                content-visibility: auto;
                contain-intrinsic-size: auto var(--section-intrinsic-height, 220px);
            }
        }

    </style>
    <link rel="stylesheet" href="../assets/css/seniorlink-ui.css?v=15">
    <script src="../assets/js/modal-hci.js?v=2" defer></script>
    <link rel="stylesheet" href="../assets/css/system-header.css?v=1">
    <link rel="stylesheet" href="../assets/css/system-sidebar.css?v=3">
</head>
<body>
    <div class="container">
        <?php include '../partials/barangay_sidebar.php'; ?>
        <div class="main-content">

            <!-- Page Header -->
            <div class="page-header">
                <div class="page-header-left">
                    <div class="greeting" id="greetingMsg">Welcome back!</div>
                    <h1>New <span>Application</span></h1>
                </div>
                <div class="header-user">
                    <?php
                        $profilePic = isset($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : 'default.jpg';
                        $profilePicPath = '../images/profile_pictures/' . $profilePic;
                        if (!file_exists($profilePicPath) || is_dir($profilePicPath)) {
                            $profilePicPath = '../images/profile_pictures/default.jpg';
                        }
                        $barangayDisplay = htmlspecialchars($barangay);
                    ?>
                    <img src="<?php echo $profilePicPath; ?>" alt="Profile">
                    <div class="header-user-info">
                        <h3><?php echo htmlspecialchars($sessionDisplayName); ?></h3>
                        <p><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $_SESSION['role'] ?? 'barangay_staff'))); ?> · <?php echo $barangayDisplay; ?></p>
                    </div>
                </div>
            </div>

            <div class="application-form">
                <?php if (!empty($errorMessage)): ?>
                    <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo $errorMessage; ?></div>
                <?php endif; ?>
                <?php if (!empty($successMessage)): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $successMessage; ?></div>
                <?php endif; ?>

                <!-- Application Type Cards — directly visible on load -->
                <div id="cardSelectorSection">
                    <p class="card-section-hint"><i class="fas fa-hand-pointer"></i> Click a benefit below to view details and requirements before applying.</p>
                    <?php
                    $benefitDetails = getApplicationBenefitDetails();
                    $typeIcons = [
                        'senior'           => ['icon' => 'fas fa-id-card',       'color' => '#60a5fa', 'grad' => 'linear-gradient(135deg,#1e3a8a,#1d4ed8)', 'desc' => 'Senior Citizens ID registration',    'code' => '',           'accent' => '#3b82f6'],
                        'landbank'         => ['icon' => 'fas fa-credit-card',   'color' => '#34d399', 'grad' => 'linear-gradient(135deg,#064e3b,#059669)', 'desc' => 'Land Bank Cash Card enrollment',       'code' => '',           'accent' => '#10b981'],
                        'pension'          => ['icon' => 'fas fa-wallet',        'color' => '#fbbf24', 'grad' => 'linear-gradient(135deg,#78350f,#d97706)', 'desc' => 'Local social pension benefit',          'code' => 'Local',     'accent' => '#f59e0b'],
                        'national_pension' => ['icon' => 'fas fa-landmark',     'color' => '#a78bfa', 'grad' => 'linear-gradient(135deg,#4c1d95,#7c3aed)', 'desc' => 'National DSWD pension (RA 11916)',     'code' => 'National',  'accent' => '#8b5cf6'],
                        'milestone_gift'   => ['icon' => 'fas fa-gift',          'color' => '#f472b6', 'grad' => 'linear-gradient(135deg,#831843,#db2777)', 'desc' => 'Octogenarian / Centenarian cash gift',  'code' => '',           'accent' => '#ec4899'],
                        'burial'           => ['icon' => 'fas fa-ribbon',        'color' => '#94a3b8', 'grad' => 'linear-gradient(135deg,#1e293b,#475569)', 'desc' => 'Burial financial assistance claim',      'code' => '',           'accent' => '#64748b'],
                    ];
                    $newApplicationTypes = getApplicationTypeOptions();
                    unset($newApplicationTypes['home_visit']);
                    ?>
                    <div class="app-type-grid" id="appTypeGrid">
                        <?php foreach ($newApplicationTypes as $val => $label):
                            $meta = $typeIcons[$val] ?? ['icon' => 'fas fa-file', 'color' => '#94a3b8', 'grad' => 'linear-gradient(135deg,#1e293b,#334155)', 'desc' => '', 'code' => '', 'accent' => '#64748b'];
                        ?>
                            <div class="app-type-card"
                                style="--card-accent: <?php echo $meta['accent']; ?>; --card-bg: <?php echo $meta['grad']; ?>;"
                             data-value="<?php echo $val; ?>"
                             data-label="<?php echo htmlspecialchars($label); ?>"
                             data-icon="<?php echo $meta['icon']; ?>"
                             data-color="<?php echo $meta['color']; ?>"
                             data-bg="<?php echo $meta['grad']; ?>"
                             data-accent="<?php echo $meta['accent']; ?>"
                             onclick="openBenefitModal('<?php echo $val; ?>')"
                             role="button"
                             tabindex="0"
                             aria-pressed="false"
                             onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openBenefitModal('<?php echo $val; ?>');}"
                             title="<?php echo htmlspecialchars($label); ?>">
                            <div class="card-check"><i class="fas fa-check"></i></div>
                            <div class="card-arrow"><i class="fas fa-chevron-right"></i></div>
                            <div class="card-icon" style="background: <?php echo $meta['color']; ?>; color: #fff;">
                                <i class="<?php echo $meta['icon']; ?>" aria-hidden="true"></i>
                                <span class="sr-only"><?php echo htmlspecialchars($label); ?></span>
                            </div>
                            <div class="card-content">
                                <div class="card-title"><?php echo htmlspecialchars($label); ?></div>
                                <div class="card-desc"><?php echo $meta['desc']; ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- STEP 2: Full Form (hidden until a card is selected) -->
                <div id="formBody" class="guided-application">
                    <!-- Selected type banner -->
                    <div class="selected-type-banner" id="selectedTypeBanner" style="display:none;">
                        <div class="banner-icon" id="bannerIcon"></div>
                        <div class="banner-info">
                            <small><i class="fas fa-layer-group"></i> <span id="bannerTypeContext">Selected Application Form</span></small>
                            <strong id="bannerLabel"></strong>
                        </div>
                        <span class="banner-state"><i class="fas fa-check-circle"></i> Form in progress</span>
                    </div>

                    <div class="guided-form-intro" aria-label="Application instructions">
                        <span class="guided-form-intro-icon"><i class="fas fa-pen-to-square" aria-hidden="true"></i></span>
                        <div>
                            <h2>Complete the application</h2>
                            <p>Answer each question carefully. Fields marked with <span aria-hidden="true">*</span> are required before submission.</p>
                        </div>
                    </div>

                <form method="POST" action="new_application.php" enctype="multipart/form-data" id="mainAppForm">
                    <!-- Hidden Proxy Fields -->
                    <input type="hidden" name="isProxy" id="isProxy" value="<?php echo $loadedProxyData ? 1 : 0; ?>">
                    <input type="hidden" name="proxyToken" id="proxyToken" value="<?php echo htmlspecialchars($loadedProxyData['transactionId'] ?? ''); ?>">
                    <input type="hidden" name="proxyContactNumber" id="proxyContactNumber" value="<?php echo htmlspecialchars($loadedProxyData['proxyContactNumber'] ?? ''); ?>">
                    <input type="hidden" id="applicationType" name="applicationType" value="" required>

                    <!-- ================================================================
                         OSCA OFFICIAL FORM PREVIEW — Senior Citizens ID Application
                         Visible only when 'senior' application type is selected
                         ================================================================ -->
                    <!-- ================================================================
                         OSCA OFFICIAL FORM — Burial Assistance
                         Visible only when 'burial' application type is selected
                         ================================================================ -->
                    <template id="burialOfficialFormCardTemplate">
                    <div id="burialOfficialFormCard" class="guided-benefit-form" style="display:none; margin-bottom:28px;">
                        <div style="
                            background: #fff;
                            border: 2px solid #1e3a5f;
                            border-radius: 16px;
                            overflow: hidden;
                            box-shadow: 0 8px 32px rgba(15,23,42,0.12);
                            font-family: 'Segoe UI', Arial, sans-serif;
                        ">
                            <!-- LGU Letterhead Banner -->
                            <div style="
                                background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%);
                                padding: 18px 24px 16px;
                                display: flex;
                                align-items: center;
                                gap: 16px;
                            ">
                                <div style="width:56px;height:56px;background:rgba(255,255,255,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;border:2px solid rgba(255,255,255,0.3);">
                                    <i class="fas fa-landmark" style="color:#f0c060;font-size:1.4rem;"></i>
                                </div>
                                <div style="flex:1;">
                                    <div style="color:rgba(255,255,255,0.7);font-size:0.72rem;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;">Republic of the Philippines &bull; City Government of Pasig</div>
                                    <div style="color:#fff;font-size:1rem;font-weight:800;line-height:1.2;margin-top:2px;">Office for the Senior Citizens Affairs (OSCA)</div>
                                    <div style="color:#f0c060;font-size:0.8rem;font-weight:700;margin-top:3px;letter-spacing:0.04em;">SENIOR CITIZEN BURIAL ASSISTANCE FORM</div>
                                </div>
                                <div style="text-align:right;">
                                    <div style="color:rgba(255,255,255,0.5);font-size:0.68rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;margin-top:6px;">Date Filed:</div>
                                    <div style="color:#93c5fd;font-size:0.78rem;margin-top:1px;font-weight:600;"><?php echo date('m/d/Y'); ?></div>
                                </div>
                            </div>

                            <!-- Form Body -->
                            <div style="padding:20px 24px;">

                                <!-- Deceased Info -->
                                <div style="margin-bottom:14px;">
                                    <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:#475569;margin-bottom:8px;border-bottom:1px solid #e2e8f0;padding-bottom:4px;">
                                        <i class="fas fa-user-times" style="color:#64748b;margin-right:5px;"></i> Name of Deceased
                                    </div>
                                    <div style="display:grid;grid-template-columns:2fr 0.6fr 2fr 1.2fr;gap:10px;margin-bottom:10px;">
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Last Name</label>
                                            <input type="text" id="burialDeceasedLastName" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;transition:border-color 0.2s;" oninput="syncField(this,'lastName')" onfocus="this.style.borderColor='#3b82f6';" onblur="this.style.borderColor='#d0dae8';" placeholder="Dela Cruz">
                                        </div>
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Ext</label>
                                            <input type="text" id="burialDeceasedExt" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;transition:border-color 0.2s;" oninput="syncField(this,'suffix')" onfocus="this.style.borderColor='#3b82f6';" onblur="this.style.borderColor='#d0dae8';" placeholder="Jr.">
                                        </div>
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">First Name</label>
                                            <input type="text" id="burialDeceasedFirstName" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;transition:border-color 0.2s;" oninput="syncField(this,'firstName')" onfocus="this.style.borderColor='#3b82f6';" onblur="this.style.borderColor='#d0dae8';" placeholder="Juan">
                                        </div>
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Middle Name</label>
                                            <input type="text" id="burialDeceasedMiddleName" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;transition:border-color 0.2s;" oninput="syncField(this,'middleName')" onfocus="this.style.borderColor='#3b82f6';" onblur="this.style.borderColor='#d0dae8';" placeholder="Santos">
                                        </div>
                                    </div>
                                    <!-- Senior ID / Birth Date / Death Date / Landbank -->
                                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:10px;margin-bottom:10px;">
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Senior ID No.</label>
                                            <input type="text" id="burialScIdNo" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'seniorIdNo')" onfocus="this.style.borderColor='#3b82f6';" onblur="this.style.borderColor='#d0dae8';" placeholder="OSCA-YYYY-XXXXXX">
                                        </div>
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Birth Date of Deceased</label>
                                            <input type="date" id="burialDeceasedBirthDate" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.82rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'birthDate')" onfocus="this.style.borderColor='#3b82f6';" onblur="this.style.borderColor='#d0dae8';">
                                        </div>
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Date of Death</label>
                                            <input type="date" id="burialDateOfDeathCard" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.82rem;color:#0f172a;background:#fff;outline:none;" oninput="syncBurialDeath(this)" onfocus="this.style.borderColor='#3b82f6';" onblur="this.style.borderColor='#d0dae8';">
                                        </div>
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Landbank Cash Card No.</label>
                                            <input type="text" id="burialLandbankCard" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" onfocus="this.style.borderColor='#3b82f6';" onblur="this.style.borderColor='#d0dae8';" placeholder="XXXX-XXXX-XXXX">
                                        </div>
                                    </div>
                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Days Filed</label>
                                            <input type="text" id="burialDaysFiled" readonly style="width:100%;padding:7px 10px;border:1.5px solid #e2e8f0;border-radius:7px;font-size:0.88rem;color:#1e3a5f;background:#f8fafc;font-weight:700;" placeholder="Auto-calculated">
                                        </div>
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Contact No.</label>
                                            <input type="text" id="burialContactNo" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'contactNumber')" onfocus="this.style.borderColor='#3b82f6';" onblur="this.style.borderColor='#d0dae8';" placeholder="09XX-XXX-XXXX">
                                        </div>
                                    </div>
                                </div>

                                <!-- Claimant Info -->
                                <div style="margin-bottom:14px;">
                                    <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:#475569;margin-bottom:8px;border-bottom:1px solid #e2e8f0;padding-bottom:4px;">
                                        <i class="fas fa-user" style="color:#3b82f6;margin-right:5px;"></i> Claimant Information
                                    </div>
                                    <div style="display:grid;grid-template-columns:2fr 1.5fr;gap:10px;margin-bottom:10px;">
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Name of Applicant</label>
                                            <input type="text" id="burialClaimantName" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" onfocus="this.style.borderColor='#3b82f6';" onblur="this.style.borderColor='#d0dae8';" placeholder="Last Name, First Name, M.I.">
                                        </div>
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Relationship</label>
                                            <input type="text" id="burialRelationshipCard" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'relationshipToDeceased')" onfocus="this.style.borderColor='#3b82f6';" onblur="this.style.borderColor='#d0dae8';" placeholder="e.g. Spouse, Son, Daughter">
                                        </div>
                                    </div>
                                    <!-- Full Address -->
                                    <div>
                                        <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:5px;"><i class="fas fa-map-marker-alt" style="color:#3b82f6;"></i> Full Address</label>
                                        <div style="display:grid;grid-template-columns:0.8fr 1fr 0.8fr;gap:8px;">
                                            <div>
                                                <label style="font-size:0.64rem;color:#94a3b8;display:block;margin-bottom:2px;font-weight:600;">House/Lot/Block/Bldg. No.</label>
                                                <input type="text" id="burialAddrHouseNo" style="width:100%;padding:6px 8px;border:1.5px solid #d0dae8;border-radius:6px;font-size:0.82rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'houseNo'); syncAddressFromActiveCard();" onfocus="this.style.borderColor='#3b82f6';" onblur="this.style.borderColor='#d0dae8';" placeholder="e.g. 123 Blk 4">
                                            </div>
                                            <div>
                                                <label style="font-size:0.64rem;color:#94a3b8;display:block;margin-bottom:2px;font-weight:600;">Street/Road/Purok/Subd/Village</label>
                                                <input type="text" id="burialAddrStreet" style="width:100%;padding:6px 8px;border:1.5px solid #d0dae8;border-radius:6px;font-size:0.82rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'street'); syncAddressFromActiveCard();" onfocus="this.style.borderColor='#3b82f6';" onblur="this.style.borderColor='#d0dae8';" placeholder="e.g. Mabini St.">
                                            </div>
                                            <div>
                                                <label style="font-size:0.64rem;color:#94a3b8;display:block;margin-bottom:2px;font-weight:600;">Barangay</label>
                                                <input type="text" value="<?php echo htmlspecialchars($_SESSION['barangay'] ?? ''); ?>" readonly style="width:100%;padding:6px 8px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:0.82rem;color:#1e3a5f;background:#f8fafc;font-weight:700;">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Filing Notice -->
                                <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:10px 14px;margin-bottom:14px;">
                                    <p style="font-size:0.75rem;color:#856404;font-weight:600;margin:0;text-align:center;">
                                        <i class="fas fa-exclamation-triangle" style="margin-right:5px;"></i>
                                        PLEASE SUBMIT BURIAL APPLICATION WITHIN 30 WORKING DAYS UPON REGISTRATION OF DEATH CERTIFICATE WITH LOCAL CIVIL REGISTRY
                                    </p>
                                </div>

                                <!-- Requirements -->
                                <div style="background:#f0f7ff;border:1px solid #bfdbfe;border-radius:12px;padding:14px 16px;margin-bottom:14px;">
                                    <div style="font-size:0.72rem;font-weight:800;text-transform:uppercase;letter-spacing:0.07em;color:#1e3a8a;margin-bottom:10px;display:flex;align-items:center;gap:6px;">
                                        <i class="fas fa-clipboard-list" style="color:#2563eb;"></i> Requirements
                                    </div>
                                    <ol style="margin:0;padding-left:18px;font-size:0.78rem;color:#1e3a5f;line-height:1.7;">
                                        <li>Original and One (1) photocopy of Certified True Copy of Death Certificate (with registry no.);</li>
                                        <li>Original and Two (2) photocopies of Two (2) Valid ID of claimant (front &amp; back) w/ Three (3) signatures;</li>
                                        <li>Surrender the Original and Two (2) photocopies of Senior Citizen ID and Landbank Cash Card (Blue ATM) of deceased (front &amp; back);</li>
                                        <li>Original and Two (2) photocopies of proof of the relationship of deceased and claimant:
                                            <div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:4px;">
                                                <label style="display:flex;align-items:center;gap:5px;font-size:0.76rem;"><input type="checkbox" style="accent-color:#1e3a5f;"> Marriage contract</label>
                                                <label style="display:flex;align-items:center;gap:5px;font-size:0.76rem;"><input type="checkbox" style="accent-color:#1e3a5f;"> Birth certificate of</label>
                                                <label style="display:flex;align-items:center;gap:5px;font-size:0.76rem;"><input type="checkbox" style="accent-color:#1e3a5f;"> Others: <input type="text" style="border:none;border-bottom:1px solid #64748b;outline:none;font-size:0.76rem;width:80px;"></label>
                                            </div>
                                        </li>
                                        <li>Original and One (1) photocopy of Affidavit (if applicable):
                                            <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:4px;">
                                                <label style="display:flex;align-items:center;gap:5px;font-size:0.76rem;"><input type="checkbox" style="accent-color:#1e3a5f;"> Kinship</label>
                                                <label style="display:flex;align-items:center;gap:5px;font-size:0.76rem;"><input type="checkbox" style="accent-color:#1e3a5f;"> Discrepancy</label>
                                                <label style="display:flex;align-items:center;gap:5px;font-size:0.76rem;"><input type="checkbox" style="accent-color:#1e3a5f;"> Died single without a child</label>
                                                <label style="display:flex;align-items:center;gap:5px;font-size:0.76rem;"><input type="checkbox" style="accent-color:#1e3a5f;"> Cohabitation</label>
                                                <label style="display:flex;align-items:center;gap:5px;font-size:0.76rem;"><input type="checkbox" style="accent-color:#1e3a5f;"> Others: <input type="text" style="border:none;border-bottom:1px solid #64748b;outline:none;font-size:0.76rem;width:80px;"></label>
                                            </div>
                                        </li>
                                    </ol>
                                </div>

                                <!-- Certification / Remarks -->
                                <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:12px 14px;margin-bottom:14px;">
                                    <p style="font-size:0.74rem;color:#78350f;line-height:1.7;margin:0 0 8px;">
                                        I hereby certify that I am the legal heir of the deceased Senior Citizen and that upon receipt of the burial benefit with the amount of <strong>Five Thousand Pesos (Php 5,000.00)</strong>. I declare OSCA and the City Government of Pasig to be free from liability what so ever.
                                    </p>
                                    <div style="margin-bottom:8px;">
                                        <label style="font-size:0.68rem;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Remarks/Notes:</label>
                                        <textarea id="burialRemarks" rows="2" style="width:100%;padding:6px 10px;border:1.5px solid #fde68a;border-radius:7px;font-size:0.82rem;color:#0f172a;background:#fff;outline:none;resize:vertical;" onfocus="this.style.borderColor='#3b82f6';" onblur="this.style.borderColor='#fde68a';"></textarea>
                                    </div>
                                    <p style="font-size:0.74rem;color:#78350f;line-height:1.6;margin:0;">
                                        <i class="fas fa-gavel" style="color:#92400e;margin-right:5px;"></i>
                                        I hereby certify under law on perjury that the information provided in this form is <strong>complete, true, correct, and of my own knowledge</strong>. I further authorize the City Government of Pasig to process my data, validate, and confirm the answers herein with third parties such as the GSIS, SSS, DSWD and other Government/Private Agencies.
                                    </p>
                                </div>

                                <!-- Signature + Received by -->
                                <div class="non-submission-preview" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:10px;">
                                    <div style="border:1px solid #d0dae8;border-radius:8px;padding:10px 12px;">
                                        <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:18px;">Signature over printed name</label>
                                        <div style="border-top:1px solid #cbd5e1;padding-top:4px;font-size:0.7rem;color:#94a3b8;">Claimant's Signature</div>
                                    </div>
                                    <div style="border:1px solid #d0dae8;border-radius:8px;padding:10px 12px;">
                                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:6px;">
                                            <div>
                                                <label style="font-size:0.64rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;display:block;margin-bottom:3px;">Received by:</label>
                                                <div style="border-bottom:1px solid #d0dae8;min-height:20px;"></div>
                                            </div>
                                            <div>
                                                <label style="font-size:0.64rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;display:block;margin-bottom:3px;">Date Received:</label>
                                                <div style="border-bottom:1px solid #d0dae8;min-height:20px;"></div>
                                            </div>
                                        </div>
                                        <div>
                                            <label style="font-size:0.64rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;display:block;margin-bottom:3px;">Approved by:</label>
                                            <div style="border-bottom:1px solid #d0dae8;min-height:20px;"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Stub -->
                                <div class="non-submission-preview" style="border:2px dashed #94a3b8;border-radius:10px;padding:12px 16px;background:#f8fafc;">
                                    <div style="font-size:0.7rem;font-weight:800;color:#475569;text-transform:uppercase;letter-spacing:0.08em;margin-bottom:8px;">BURIAL ASSISTANCE STUB <span style="font-size:0.65rem;color:#94a3b8;font-weight:500;">(Present upon claiming — do not lose)</span></div>
                                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;font-size:0.75rem;color:#334155;">
                                        <div><span style="font-weight:700;">Name of claimant:</span><div style="border-bottom:1px solid #cbd5e1;min-height:16px;margin-top:2px;"></div></div>
                                        <div><span style="font-weight:700;">Barangay:</span><div style="border-bottom:1px solid #cbd5e1;min-height:16px;margin-top:2px;"><?php echo htmlspecialchars($_SESSION['barangay'] ?? ''); ?></div></div>
                                        <div><span style="font-weight:700;">Name of deceased:</span><div style="border-bottom:1px solid #cbd5e1;min-height:16px;margin-top:2px;"></div></div>
                                        <div><span style="font-weight:700;">Time:</span><div style="border-bottom:1px solid #cbd5e1;min-height:16px;margin-top:2px;"></div></div>
                                        <div><span style="font-weight:700;">Date of file:</span><div style="border-bottom:1px solid #cbd5e1;min-height:16px;margin-top:2px;"><?php echo date('m/d/Y'); ?></div></div>
                                        <div><span style="font-weight:700;">Received by:</span><div style="border-bottom:1px solid #cbd5e1;min-height:16px;margin-top:2px;"></div></div>
                                    </div>
                                </div>

                                <!-- Required Documents (Burial) -->
                                <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:14px 16px;margin-top:14px;margin-bottom:10px;">
                                    <div style="font-size:0.72rem;font-weight:800;text-transform:uppercase;letter-spacing:0.07em;color:#166534;margin-bottom:4px;display:flex;align-items:center;gap:6px;">
                                        <i class="fas fa-file-alt" style="color:#16a34a;"></i> Required Documents
                                        <span style="font-size:0.65rem;font-weight:600;color:#16a34a;background:#dcfce7;border-radius:20px;padding:2px 8px;margin-left:4px;">4 Required &nbsp;·&nbsp; 1 Optional</span>
                                    </div>
                                    <p style="font-size:0.72rem;color:#4b7c5e;margin-bottom:12px;display:flex;align-items:center;gap:5px;margin-top:6px;">
                                        <i class="fas fa-info-circle" style="color:#16a34a;"></i>
                                        Accepted formats: JPEG, PNG, PDF &mdash; Max 8MB per file.
                                    </p>
                                    <!-- Row 1 -->
                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                                        <div>
                                            <label for="burialDoc1" style="font-size:0.68rem;font-weight:700;color:#166534;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:5px;">
                                                <i class="fas fa-certificate" style="margin-right:4px;color:#16a34a;"></i> Death Certificate <span style="color:#e74c3c;">*</span>
                                            </label>
                                            <input type="file" id="burialDoc1" name="doc_death_certificate" required accept="image/jpeg,image/png,image/gif,application/pdf" onchange="checkFileSize(this)" style="width:100%;font-size:0.78rem;padding:6px 8px;border:1.5px solid #bbf7d0;border-radius:7px;background:#fff;color:#166534;cursor:pointer;">
                                            <div id="burialDoc1SizeWarn" style="display:none;color:#e74c3c;font-size:0.72rem;margin-top:3px;"><i class="fas fa-exclamation-triangle"></i> File exceeds 8MB.</div>
                                        </div>
                                        <div>
                                            <label for="burialDoc2" style="font-size:0.68rem;font-weight:700;color:#166534;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:5px;">
                                                <i class="fas fa-users" style="margin-right:4px;color:#16a34a;"></i> Proof of Relationship to Deceased <span style="color:#e74c3c;">*</span>
                                            </label>
                                            <input type="file" id="burialDoc2" name="doc_relationship_proof" required accept="image/jpeg,image/png,image/gif,application/pdf" onchange="checkFileSize(this)" style="width:100%;font-size:0.78rem;padding:6px 8px;border:1.5px solid #bbf7d0;border-radius:7px;background:#fff;color:#166534;cursor:pointer;">
                                            <div id="burialDoc2SizeWarn" style="display:none;color:#e74c3c;font-size:0.72rem;margin-top:3px;"><i class="fas fa-exclamation-triangle"></i> File exceeds 8MB.</div>
                                        </div>
                                    </div>
                                    <!-- Row 2 -->
                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                                        <div>
                                            <label for="burialDoc3" style="font-size:0.68rem;font-weight:700;color:#166534;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:5px;">
                                                <i class="fas fa-home" style="margin-right:4px;color:#16a34a;"></i> Barangay Residency Certificate <span style="color:#e74c3c;">*</span>
                                            </label>
                                            <input type="file" id="burialDoc3" name="doc_barangay_cert" required accept="image/jpeg,image/png,image/gif,application/pdf" onchange="checkFileSize(this)" style="width:100%;font-size:0.78rem;padding:6px 8px;border:1.5px solid #bbf7d0;border-radius:7px;background:#fff;color:#166534;cursor:pointer;">
                                            <div id="burialDoc3SizeWarn" style="display:none;color:#e74c3c;font-size:0.72rem;margin-top:3px;"><i class="fas fa-exclamation-triangle"></i> File exceeds 8MB.</div>
                                        </div>
                                        <div>
                                            <label for="burialDoc4" style="font-size:0.68rem;font-weight:700;color:#166534;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:5px;">
                                                <i class="fas fa-id-card" style="margin-right:4px;color:#16a34a;"></i> Valid ID of Claimant <span style="color:#e74c3c;">*</span>
                                            </label>
                                            <input type="file" id="burialDoc4" name="doc_claimant_id" required accept="image/jpeg,image/png,image/gif,application/pdf" onchange="checkFileSize(this)" style="width:100%;font-size:0.78rem;padding:6px 8px;border:1.5px solid #bbf7d0;border-radius:7px;background:#fff;color:#166534;cursor:pointer;">
                                            <div id="burialDoc4SizeWarn" style="display:none;color:#e74c3c;font-size:0.72rem;margin-top:3px;"><i class="fas fa-exclamation-triangle"></i> File exceeds 8MB.</div>
                                        </div>
                                    </div>
                                    <!-- Row 3 – Optional -->
                                    <div style="display:grid;grid-template-columns:1fr;gap:12px;">
                                        <div style="background:#fffbeb;border:1px dashed #fde68a;border-radius:8px;padding:10px 12px;">
                                            <label for="burialDoc5" style="font-size:0.68rem;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:5px;">
                                                <i class="fas fa-file-signature" style="margin-right:4px;color:#d97706;"></i> Affidavit of Loss <span style="font-size:0.65rem;font-weight:500;color:#92400e;background:#fef3c7;border-radius:12px;padding:1px 7px;margin-left:4px;">Optional — Lost ID Only</span>
                                            </label>
                                            <input type="file" id="burialDoc5" name="doc_affidavit_loss" accept="image/jpeg,image/png,image/gif,application/pdf" onchange="checkFileSize(this)" style="width:100%;font-size:0.78rem;padding:6px 8px;border:1.5px solid #fde68a;border-radius:7px;background:#fff;color:#92400e;cursor:pointer;">
                                            <div id="burialDoc5SizeWarn" style="display:none;color:#e74c3c;font-size:0.72rem;margin-top:3px;"><i class="fas fa-exclamation-triangle"></i> File exceeds 8MB.</div>
                                        </div>
                                    </div>
                                </div>

                                <p style="font-size:0.67rem;color:#94a3b8;text-align:center;margin-top:10px;margin-bottom:0;">
                                    <i class="fas fa-info-circle" style="margin-right:4px;"></i>
                                    This official form preview mirrors the physical OSCA Burial Assistance form. All fields entered here are automatically carried over to the main form below.
                                </p>
                            </div><!-- /form body -->
                        </div>
                    </div><!-- /#burialOfficialFormCard -->
                    </template>

                    <!-- ================================================================
                         OSCA OFFICIAL FORM — Home Visitation / Confirmation
                         Visible only when 'home_visit' application type is selected
                         ================================================================ -->
                    <template id="homeVisitOfficialFormCardTemplate">
                    <div id="homeVisitOfficialFormCard" class="guided-benefit-form" style="display:none; margin-bottom:28px;">
                        <div style="
                            background: #fff;
                            border: 2px solid #0891b2;
                            border-radius: 16px;
                            overflow: hidden;
                            box-shadow: 0 8px 32px rgba(8,145,178,0.15);
                            font-family: 'Segoe UI', Arial, sans-serif;
                        ">
                            <!-- LGU Letterhead Banner -->
                            <div style="
                                background: linear-gradient(135deg, #083344 0%, #0e7490 100%);
                                padding: 18px 24px 16px;
                                display: flex;
                                align-items: center;
                                gap: 16px;
                            ">
                                <div style="width:56px;height:56px;background:rgba(255,255,255,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;border:2px solid rgba(255,255,255,0.3);">
                                    <i class="fas fa-house-medical" style="color:#67e8f9;font-size:1.4rem;"></i>
                                </div>
                                <div style="flex:1;">
                                    <div style="color:rgba(255,255,255,0.7);font-size:0.72rem;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;">Republic of the Philippines &bull; City Government of Pasig</div>
                                    <div style="color:#fff;font-size:1rem;font-weight:800;line-height:1.2;margin-top:2px;">Office for the Senior Citizens Affairs (OSCA)</div>
                                    <div style="color:#67e8f9;font-size:0.8rem;font-weight:700;margin-top:3px;letter-spacing:0.04em;">HOME VISITATION / CONFIRMATION FORM</div>
                                </div>
                                <div style="text-align:right;">
                                    <div style="color:rgba(255,255,255,0.5);font-size:0.68rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;">Date Filed:</div>
                                    <div style="color:#67e8f9;font-size:0.78rem;margin-top:1px;font-weight:600;"><?php echo date('m/d/Y'); ?></div>
                                </div>
                            </div>

                            <!-- Type Selection Checkboxes -->
                            <div style="background:#f0fdff;border-bottom:1px solid #a5f3fc;padding:12px 24px;">
                                <div style="font-size:0.68rem;font-weight:700;color:#0e7490;text-transform:uppercase;letter-spacing:0.07em;margin-bottom:8px;">Purpose / Type of Visit:</div>
                                <div style="display:flex;flex-wrap:wrap;gap:10px;">
                                    <?php
                                    $visitTypes = ['Local Pension','Burial Assistance','Senior ID','Cash Gift','Octogenarian','Nonagenarian','Centenarian','Others'];
                                    foreach($visitTypes as $vt): ?>
                                    <label style="display:flex;align-items:center;gap:5px;cursor:pointer;padding:5px 11px;border-radius:7px;border:1.5px solid #a5f3fc;background:#fff;font-size:0.78rem;font-weight:600;color:#0e7490;" onmouseover="this.style.background='#cffafe';" onmouseout="this.style.background='#fff';">
                                        <input type="checkbox" style="accent-color:#0891b2;"> <?php echo $vt; ?>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Form Body -->
                            <div style="padding:20px 24px;">

                                <!-- Personal Info -->
                                <div style="margin-bottom:14px;">
                                    <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:#475569;margin-bottom:8px;border-bottom:1px solid #e2e8f0;padding-bottom:4px;">
                                        <i class="fas fa-user" style="color:#0891b2;margin-right:5px;"></i> Personal Information
                                    </div>
                                    <div style="display:grid;grid-template-columns:2fr 0.6fr 2fr;gap:10px;margin-bottom:10px;">
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Last Name</label>
                                            <input type="text" id="hvLastName" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'lastName')" onfocus="this.style.borderColor='#0891b2';" onblur="this.style.borderColor='#d0dae8';" placeholder="Dela Cruz">
                                        </div>
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Ext</label>
                                            <input type="text" id="hvExt" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'suffix')" onfocus="this.style.borderColor='#0891b2';" onblur="this.style.borderColor='#d0dae8';" placeholder="Jr.">
                                        </div>
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">First Name, Middle Name</label>
                                            <input type="text" id="hvFirstName" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'firstName')" onfocus="this.style.borderColor='#0891b2';" onblur="this.style.borderColor='#d0dae8';" placeholder="Juan Santos">
                                        </div>
                                    </div>
                                    <div style="display:grid;grid-template-columns:0.6fr 1fr 1fr 1fr;gap:10px;margin-bottom:10px;">
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Age</label>
                                            <input type="number" id="hvAge" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" onfocus="this.style.borderColor='#0891b2';" onblur="this.style.borderColor='#d0dae8';" placeholder="70">
                                        </div>
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">SC ID No.</label>
                                            <input type="text" id="hvScIdNo" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'seniorIdNo')" onfocus="this.style.borderColor='#0891b2';" onblur="this.style.borderColor='#d0dae8';" placeholder="OSCA-YYYY-XXXXXX">
                                        </div>
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Birth Date (MM/DD/YYYY)</label>
                                            <input type="date" id="hvBirthDate" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.82rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'birthDate')" onfocus="this.style.borderColor='#0891b2';" onblur="this.style.borderColor='#d0dae8';">
                                        </div>
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:5px;">Gender</label>
                                            <div style="display:flex;gap:12px;padding-top:2px;">
                                                <label style="display:flex;align-items:center;gap:5px;font-size:0.82rem;font-weight:600;color:#0f172a;cursor:pointer;"><input type="radio" name="hvGender" value="Male" style="accent-color:#0891b2;"> Male</label>
                                                <label style="display:flex;align-items:center;gap:5px;font-size:0.82rem;font-weight:600;color:#0f172a;cursor:pointer;"><input type="radio" name="hvGender" value="Female" style="accent-color:#0891b2;"> Female</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="display:grid;grid-template-columns:1fr;gap:10px;margin-bottom:10px;">
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Contact Number</label>
                                            <input type="text" id="hvContact" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'contactNumber')" onfocus="this.style.borderColor='#0891b2';" onblur="this.style.borderColor='#d0dae8';" placeholder="09XX-XXX-XXXX">
                                        </div>
                                    </div>
                                </div>

                                <!-- Present Address -->
                                <div style="margin-bottom:14px;">
                                    <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:#475569;margin-bottom:8px;border-bottom:1px solid #e2e8f0;padding-bottom:4px;">
                                        <i class="fas fa-map-marker-alt" style="color:#0891b2;margin-right:5px;"></i> Present Address
                                    </div>
                                    <div style="display:grid;grid-template-columns:0.7fr 1fr 0.7fr 0.5fr 0.5fr;gap:8px;">
                                        <div>
                                            <label style="font-size:0.64rem;color:#94a3b8;display:block;margin-bottom:2px;font-weight:600;">House No.</label>
                                            <input type="text" id="hvHouseNo" style="width:100%;padding:6px 8px;border:1.5px solid #d0dae8;border-radius:6px;font-size:0.82rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'houseNo'); syncAddressFromActiveCard();" onfocus="this.style.borderColor='#0891b2';" onblur="this.style.borderColor='#d0dae8';" placeholder="e.g. 123 Blk 4">
                                        </div>
                                        <div>
                                            <label style="font-size:0.64rem;color:#94a3b8;display:block;margin-bottom:2px;font-weight:600;">Street</label>
                                            <input type="text" id="hvStreet" style="width:100%;padding:6px 8px;border:1.5px solid #d0dae8;border-radius:6px;font-size:0.82rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'street'); syncAddressFromActiveCard();" onfocus="this.style.borderColor='#0891b2';" onblur="this.style.borderColor='#d0dae8';" placeholder="e.g. Mabini St.">
                                        </div>
                                        <div>
                                            <label style="font-size:0.64rem;color:#94a3b8;display:block;margin-bottom:2px;font-weight:600;">Barangay</label>
                                            <input type="text" value="<?php echo htmlspecialchars($_SESSION['barangay'] ?? ''); ?>" readonly style="width:100%;padding:6px 8px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:0.82rem;color:#0e7490;background:#f0fdff;font-weight:700;">
                                        </div>
                                        <div>
                                            <label style="font-size:0.64rem;color:#94a3b8;display:block;margin-bottom:2px;font-weight:600;">City/Municipality</label>
                                            <input type="text" value="Pasig City" readonly style="width:100%;padding:6px 8px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:0.82rem;color:#0e7490;background:#f0fdff;font-weight:700;">
                                        </div>
                                        <div>
                                            <label style="font-size:0.64rem;color:#94a3b8;display:block;margin-bottom:2px;font-weight:600;">Province / ZIP</label>
                                            <input type="text" id="hvZipCode" placeholder="1600" style="width:100%;padding:6px 8px;border:1.5px solid #d0dae8;border-radius:6px;font-size:0.82rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'zipCode'); syncAddressFromActiveCard();" onfocus="this.style.borderColor='#0891b2';" onblur="this.style.borderColor='#d0dae8';">
                                        </div>
                                    </div>
                                </div>

                                <!-- Confirmed Sections -->
                                <div style="margin-bottom:14px;">
                                    <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:#475569;margin-bottom:10px;border-bottom:1px solid #e2e8f0;padding-bottom:4px;">
                                        <i class="fas fa-clipboard-check" style="color:#0891b2;margin-right:5px;"></i> Confirmed the Following
                                    </div>

                                    <!-- Living Arrangement -->
                                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px 14px;margin-bottom:10px;">
                                        <div style="font-size:0.7rem;font-weight:700;color:#0e7490;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:6px;">Living Arrangement:</div>
                                        <div style="display:flex;flex-wrap:wrap;gap:10px;">
                                            <label style="display:flex;align-items:center;gap:5px;font-size:0.82rem;font-weight:600;color:#334155;cursor:pointer;"><input type="radio" name="hvLiving" value="Owned" style="accent-color:#0891b2;"> Owned</label>
                                            <label style="display:flex;align-items:center;gap:5px;font-size:0.82rem;font-weight:600;color:#334155;cursor:pointer;"><input type="radio" name="hvLiving" value="Living Alone" style="accent-color:#0891b2;"> Living Alone</label>
                                            <label style="display:flex;align-items:center;gap:5px;font-size:0.82rem;font-weight:600;color:#334155;cursor:pointer;"><input type="radio" name="hvLiving" value="Living with Relatives" style="accent-color:#0891b2;"> Living with Relatives</label>
                                            <label style="display:flex;align-items:center;gap:5px;font-size:0.82rem;font-weight:600;color:#334155;cursor:pointer;"><input type="radio" name="hvLiving" value="Rent" style="accent-color:#0891b2;"> Rent</label>
                                            <label style="display:flex;align-items:center;gap:5px;font-size:0.82rem;font-weight:600;color:#334155;cursor:pointer;"><input type="radio" name="hvLiving" value="Others" style="accent-color:#0891b2;"> Others: <input type="text" style="border:none;border-bottom:1px solid #64748b;outline:none;font-size:0.8rem;width:80px;margin-left:4px;"></label>
                                        </div>
                                    </div>

                                    <!-- Economic Status -->
                                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px 14px;margin-bottom:10px;">
                                        <div style="font-size:0.7rem;font-weight:700;color:#0e7490;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:8px;">Economic Status:</div>
                                        <div style="display:flex;flex-direction:column;gap:8px;">
                                            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                                                <span style="font-size:0.82rem;font-weight:600;color:#334155;min-width:160px;">Pensioner:</span>
                                                <label style="display:flex;align-items:center;gap:5px;font-size:0.82rem;font-weight:600;cursor:pointer;"><input type="radio" name="hvPensioner" value="Yes" style="accent-color:#0891b2;"> Yes</label>
                                                <label style="display:flex;align-items:center;gap:5px;font-size:0.82rem;font-weight:600;cursor:pointer;"><input type="radio" name="hvPensioner" value="No" style="accent-color:#0891b2;"> No</label>
                                                <span style="font-size:0.78rem;color:#64748b;">If yes, from what source and how much?</span>
                                                <input type="text" style="border:none;border-bottom:1px solid #64748b;outline:none;font-size:0.8rem;flex:1;min-width:100px;" placeholder="e.g. SSS / P2,000">
                                            </div>
                                            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                                                <span style="font-size:0.82rem;font-weight:600;color:#334155;min-width:160px;">Regular Support from Family:</span>
                                                <label style="display:flex;align-items:center;gap:5px;font-size:0.82rem;font-weight:600;cursor:pointer;"><input type="radio" name="hvFamily" value="Yes" style="accent-color:#0891b2;"> Yes</label>
                                                <label style="display:flex;align-items:center;gap:5px;font-size:0.82rem;font-weight:600;cursor:pointer;"><input type="radio" name="hvFamily" value="No" style="accent-color:#0891b2;"> No</label>
                                                <span style="font-size:0.78rem;color:#64748b;">If yes, how much?</span>
                                                <input type="text" style="border:none;border-bottom:1px solid #64748b;outline:none;font-size:0.8rem;flex:1;min-width:100px;" placeholder="e.g. P1,500/month">
                                            </div>
                                            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                                                <span style="font-size:0.82rem;font-weight:600;color:#334155;min-width:160px;">Personal Income:</span>
                                                <label style="display:flex;align-items:center;gap:5px;font-size:0.82rem;font-weight:600;cursor:pointer;"><input type="radio" name="hvIncome" value="Yes" style="accent-color:#0891b2;"> Yes</label>
                                                <label style="display:flex;align-items:center;gap:5px;font-size:0.82rem;font-weight:600;cursor:pointer;"><input type="radio" name="hvIncome" value="No" style="accent-color:#0891b2;"> No</label>
                                                <span style="font-size:0.78rem;color:#64748b;">If yes, how much?</span>
                                                <input type="text" style="border:none;border-bottom:1px solid #64748b;outline:none;font-size:0.8rem;flex:1;min-width:100px;" placeholder="e.g. P3,000/month">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Health Condition -->
                                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px 14px;margin-bottom:10px;">
                                        <div style="font-size:0.7rem;font-weight:700;color:#0e7490;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:8px;">Health Condition / Illness:</div>
                                        <div style="display:flex;flex-direction:column;gap:8px;">
                                            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                                                <span style="font-size:0.82rem;font-weight:600;color:#334155;min-width:160px;">With Maintenance:</span>
                                                <label style="display:flex;align-items:center;gap:5px;font-size:0.82rem;font-weight:600;cursor:pointer;"><input type="radio" name="hvMaintenance" value="Yes" style="accent-color:#0891b2;"> Yes</label>
                                                <label style="display:flex;align-items:center;gap:5px;font-size:0.82rem;font-weight:600;cursor:pointer;"><input type="radio" name="hvMaintenance" value="No" style="accent-color:#0891b2;"> No</label>
                                                <span style="font-size:0.78rem;color:#64748b;">If yes, please specify:</span>
                                                <input type="text" style="border:none;border-bottom:1px solid #64748b;outline:none;font-size:0.8rem;flex:1;min-width:120px;" placeholder="Condition/Illness">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Visit Summary -->
                                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px 14px;margin-bottom:10px;">
                                        <div style="font-size:0.7rem;font-weight:700;color:#0e7490;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:8px;">Visit Summary:</div>
                                        <textarea rows="3" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.82rem;color:#0f172a;background:#fff;outline:none;resize:vertical;" onfocus="this.style.borderColor='#0891b2';" onblur="this.style.borderColor='#d0dae8';" placeholder="Summary of visit and confirmation details..."></textarea>
                                        <div style="margin-top:10px;">
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:5px;">Confirmation Made With:</label>
                                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                                                <div style="border:1px solid #d0dae8;border-radius:8px;padding:10px 12px;">
                                                    <label style="font-size:0.64rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;display:block;margin-bottom:18px;">Signature over Printed Name</label>
                                                    <div style="border-top:1px solid #cbd5e1;padding-top:4px;">
                                                        <input type="text" style="width:100%;border:none;border-bottom:1px solid #cbd5e1;outline:none;font-size:0.8rem;" placeholder="Name">
                                                        <div style="font-size:0.68rem;color:#94a3b8;margin-top:2px;">Contact No.: <input type="text" style="border:none;border-bottom:1px solid #cbd5e1;outline:none;font-size:0.8rem;width:100px;"></div>
                                                    </div>
                                                </div>
                                                <div style="border:1px solid #d0dae8;border-radius:8px;padding:10px 12px;">
                                                    <label style="font-size:0.64rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;display:block;margin-bottom:18px;">Visited by (Signature over Printed Name)</label>
                                                    <div style="border-top:1px solid #cbd5e1;padding-top:4px;">
                                                        <div style="font-size:0.68rem;color:#94a3b8;">Date and time of interview: <input type="datetime-local" style="border:none;border-bottom:1px solid #cbd5e1;outline:none;font-size:0.78rem;"></div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div style="border:1px solid #d0dae8;border-radius:8px;padding:10px 12px;margin-top:10px;">
                                                <label style="font-size:0.64rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;display:block;margin-bottom:18px;">Checked by (Signature over Printed Name)</label>
                                                <div style="border-top:1px solid #cbd5e1;padding-top:4px;display:flex;justify-content:space-between;align-items:center;">
                                                    <div style="font-size:0.68rem;color:#94a3b8;">Date and time: <input type="datetime-local" style="border:none;border-bottom:1px solid #cbd5e1;outline:none;font-size:0.78rem;"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Final Evaluation -->
                                    <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:12px 14px;">
                                        <div style="font-size:0.7rem;font-weight:800;color:#92400e;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:8px;"><i class="fas fa-gavel" style="margin-right:5px;"></i> Final Evaluation</div>
                                        <div style="display:flex;gap:20px;margin-bottom:10px;">
                                            <label style="display:flex;align-items:center;gap:7px;cursor:pointer;padding:8px 18px;border-radius:8px;border:2px solid #22c55e;background:#f0fdf4;font-size:0.88rem;font-weight:700;color:#16a34a;">
                                                <input type="radio" name="hvEvaluation" value="Eligible" style="accent-color:#16a34a;"> ELIGIBLE
                                            </label>
                                            <label style="display:flex;align-items:center;gap:7px;cursor:pointer;padding:8px 18px;border-radius:8px;border:2px solid #ef4444;background:#fff1f2;font-size:0.88rem;font-weight:700;color:#dc2626;">
                                                <input type="radio" name="hvEvaluation" value="Not Eligible" style="accent-color:#dc2626;"> NOT ELIGIBLE
                                            </label>
                                        </div>
                                        <div style="margin-bottom:8px;">
                                            <label style="font-size:0.68rem;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Reason for Decision:</label>
                                            <textarea rows="2" style="width:100%;padding:6px 10px;border:1.5px solid #fde68a;border-radius:7px;font-size:0.82rem;color:#0f172a;background:#fff;outline:none;resize:vertical;" onfocus="this.style.borderColor='#0891b2';" onblur="this.style.borderColor='#fde68a';"></textarea>
                                        </div>
                                        <div class="non-submission-preview" style="border:1px solid #d0dae8;border-radius:8px;padding:10px 12px;">
                                            <label style="font-size:0.64rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;display:block;margin-bottom:18px;">Evaluated by (Signature over Printed Name)</label>
                                            <div style="border-top:1px solid #cbd5e1;padding-top:4px;display:flex;justify-content:space-between;align-items:center;">
                                                <div style="font-size:0.68rem;color:#94a3b8;">Date and time: <input type="datetime-local" style="border:none;border-bottom:1px solid #cbd5e1;outline:none;font-size:0.78rem;"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Required Documents (Home Visit) -->
                                <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:14px 16px;margin-top:14px;margin-bottom:10px;">
                                    <div style="font-size:0.72rem;font-weight:800;text-transform:uppercase;letter-spacing:0.07em;color:#166534;margin-bottom:8px;display:flex;align-items:center;gap:6px;">
                                        <i class="fas fa-file-alt" style="color:#16a34a;"></i> Required Documents
                                    </div>
                                    <p style="font-size:0.72rem;color:#4b7c5e;margin-bottom:10px;display:flex;align-items:center;gap:5px;">
                                        <i class="fas fa-info-circle" style="color:#16a34a;"></i>
                                        Accepted formats: JPEG, PNG, PDF &mdash; Max 8MB per file.
                                    </p>
                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                        <div>
                                            <label for="proofOfAddressHomeVisit" style="font-size:0.68rem;font-weight:700;color:#166534;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:5px;">
                                                <i class="fas fa-home" style="margin-right:4px;color:#16a34a;"></i> Proof of Address
                                            </label>
                                            <input type="file" id="proofOfAddressHomeVisit" name="proofOfAddress" required accept="image/jpeg,image/png,image/gif,application/pdf" onchange="checkFileSize(this)"
                                                style="width:100%;font-size:0.78rem;padding:6px 8px;border:1.5px solid #bbf7d0;border-radius:7px;background:#fff;color:#166534;cursor:pointer;">
                                            <div id="proofOfAddressHomeVisitSizeWarn" style="display:none;color:#e74c3c;font-size:0.72rem;margin-top:3px;">
                                                <i class="fas fa-exclamation-triangle"></i> File is large (&gt;8MB). Consider compressing it first.
                                            </div>
                                        </div>
                                        <div>
                                            <label for="idImageHomeVisit" style="font-size:0.68rem;font-weight:700;color:#166534;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:5px;">
                                                <i class="fas fa-id-card" style="margin-right:4px;color:#16a34a;"></i> ID Image / Supporting Document Photo
                                            </label>
                                            <input type="file" id="idImageHomeVisit" name="idImage" required accept="image/jpeg,image/png,image/gif,application/pdf" onchange="checkFileSize(this)"
                                                style="width:100%;font-size:0.78rem;padding:6px 8px;border:1.5px solid #bbf7d0;border-radius:7px;background:#fff;color:#166534;cursor:pointer;">
                                            <div id="idImageHomeVisitSizeWarn" style="display:none;color:#e74c3c;font-size:0.72rem;margin-top:3px;">
                                                <i class="fas fa-exclamation-triangle"></i> File is large (&gt;8MB). Consider compressing it first.
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <p style="font-size:0.67rem;color:#94a3b8;text-align:center;margin-top:10px;margin-bottom:0;">
                                    <i class="fas fa-info-circle" style="margin-right:4px;"></i>
                                    This official form preview mirrors the physical OSCA Home Visitation/Confirmation form. All fields entered here are automatically carried over to the main form below.
                                </p>
                            </div><!-- /form body -->
                        </div>
                    </div><!-- /#homeVisitOfficialFormCard -->
                    </template>

                    <!-- ================================================================
                         OSCA OFFICIAL FORM — Octogenarian / Nonagenarian / Centenarian
                         Visible only when 'milestone_gift' application type is selected
                         ================================================================ -->
                    <template id="milestoneOfficialFormCardTemplate">
                    <div id="milestoneOfficialFormCard" class="guided-benefit-form" style="display:none; margin-bottom:28px;">
                        <div style="
                            background: #fff;
                            border: 2px solid #be185d;
                            border-radius: 16px;
                            overflow: hidden;
                            box-shadow: 0 8px 32px rgba(190,24,93,0.13);
                            font-family: 'Segoe UI', Arial, sans-serif;
                        ">
                            <!-- LGU Letterhead Banner -->
                            <div style="
                                background: linear-gradient(135deg, #4a0728 0%, #9d174d 100%);
                                padding: 18px 24px 16px;
                                display: flex;
                                align-items: center;
                                gap: 16px;
                            ">
                                <div style="width:56px;height:56px;background:rgba(255,255,255,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;border:2px solid rgba(255,255,255,0.3);">
                                    <i class="fas fa-gift" style="color:#fbcfe8;font-size:1.4rem;"></i>
                                </div>
                                <div style="flex:1;">
                                    <div style="color:rgba(255,255,255,0.7);font-size:0.72rem;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;">Republic of the Philippines &bull; City Government of Pasig</div>
                                    <div style="color:#fff;font-size:1rem;font-weight:800;line-height:1.2;margin-top:2px;">Office for the Senior Citizens Affairs (OSCA)</div>
                                    <div style="color:#fbcfe8;font-size:0.8rem;font-weight:700;margin-top:3px;letter-spacing:0.04em;">OCTOGENARIAN, NONAGENARIAN &amp; CENTENARIAN APPLICATION FORM</div>
                                </div>
                                <div style="text-align:right;">
                                    <div style="color:rgba(255,255,255,0.5);font-size:0.68rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;margin-top:6px;">Date:</div>
                                    <div style="color:#fbcfe8;font-size:0.78rem;margin-top:1px;font-weight:600;"><?php echo date('m/d/Y'); ?></div>
                                </div>
                            </div>

                            <!-- Milestone Age Selector -->
                            <div style="background:#fdf2f8;border-bottom:1px solid #fbcfe8;padding:12px 24px;">
                                <div style="font-size:0.68rem;font-weight:700;color:#9d174d;text-transform:uppercase;letter-spacing:0.07em;margin-bottom:8px;">Milestone Age:</div>
                                <div style="display:flex;flex-wrap:wrap;gap:10px;">
                                    <?php foreach([80,85,90,95,100] as $ma): ?>
                                    <label style="display:flex;align-items:center;gap:5px;cursor:pointer;padding:6px 16px;border-radius:8px;border:1.5px solid #fbcfe8;background:#fff;font-size:0.88rem;font-weight:700;color:#9d174d;" onmouseover="this.style.background='#fce7f3';" onmouseout="this.style.background='#fff';">
                                        <input type="radio" name="milestoneAge" value="<?php echo $ma; ?>" style="accent-color:#be185d;"> <?php echo $ma; ?>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Form Body -->
                            <div style="padding:20px 24px;">

                                <!-- Name Row -->
                                <div style="margin-bottom:14px;">
                                    <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:#475569;margin-bottom:8px;border-bottom:1px solid #e2e8f0;padding-bottom:4px;">
                                        <i class="fas fa-user" style="color:#be185d;margin-right:5px;"></i> Full Name
                                    </div>
                                    <div style="display:grid;grid-template-columns:2fr 0.6fr 2fr 1.2fr;gap:10px;">
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Last Name</label>
                                            <input type="text" id="msLastName" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'lastName')" onfocus="this.style.borderColor='#be185d';" onblur="this.style.borderColor='#d0dae8';" placeholder="Dela Cruz">
                                        </div>
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Ext</label>
                                            <input type="text" id="msExt" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'suffix')" onfocus="this.style.borderColor='#be185d';" onblur="this.style.borderColor='#d0dae8';" placeholder="Jr.">
                                        </div>
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">First Name</label>
                                            <input type="text" id="msFirstName" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'firstName')" onfocus="this.style.borderColor='#be185d';" onblur="this.style.borderColor='#d0dae8';" placeholder="Juan">
                                        </div>
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Middle Name</label>
                                            <input type="text" id="msMiddleName" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'middleName')" onfocus="this.style.borderColor='#be185d';" onblur="this.style.borderColor='#d0dae8';" placeholder="Santos">
                                        </div>
                                    </div>
                                </div>

                                <!-- DOB / Place of Birth / Age / Gender / Civil Status / Contact -->
                                <div style="display:grid;grid-template-columns:1fr 1fr 0.5fr 0.8fr 1fr 1fr;gap:10px;margin-bottom:14px;">
                                    <div>
                                        <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Date of Birth (MM/DD/YYYY)</label>
                                        <input type="date" id="msBirthDate" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.82rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'birthDate');updateMsAge();" onfocus="this.style.borderColor='#be185d';" onblur="this.style.borderColor='#d0dae8';">
                                    </div>
                                    <div>
                                        <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Place of Birth</label>
                                        <input type="text" id="msPlaceOfBirth" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" onfocus="this.style.borderColor='#be185d';" onblur="this.style.borderColor='#d0dae8';" placeholder="Pasig City, MM">
                                    </div>
                                    <div>
                                        <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Age</label>
                                        <input type="text" id="msAge" readonly style="width:100%;padding:7px 10px;border:1.5px solid #e2e8f0;border-radius:7px;font-size:0.88rem;color:#9d174d;background:#fdf2f8;font-weight:700;text-align:center;" placeholder="—">
                                    </div>
                                    <div>
                                        <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:5px;">Gender</label>
                                        <div style="display:flex;gap:10px;padding-top:4px;">
                                            <label style="display:flex;align-items:center;gap:4px;font-size:0.82rem;font-weight:600;color:#0f172a;cursor:pointer;"><input type="radio" name="msGender" value="Male" style="accent-color:#be185d;"> Male</label>
                                            <label style="display:flex;align-items:center;gap:4px;font-size:0.82rem;font-weight:600;color:#0f172a;cursor:pointer;"><input type="radio" name="msGender" value="Female" style="accent-color:#be185d;"> Female</label>
                                        </div>
                                    </div>
                                    <div>
                                        <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Civil Status</label>
                                        <select id="msCivilStatus" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" onfocus="this.style.borderColor='#be185d';" onblur="this.style.borderColor='#d0dae8';">
                                            <option value="">Select</option>
                                            <option>Single</option><option>Married</option><option>Widow/er</option><option>Separated</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Contact No.</label>
                                        <input type="text" id="msContact" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'contactNumber')" onfocus="this.style.borderColor='#be185d';" onblur="this.style.borderColor='#d0dae8';" placeholder="09XX-XXX-XXXX">
                                    </div>
                                </div>

                                <!-- Complete Address -->
                                <div style="margin-bottom:14px;">
                                    <label style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:#475569;margin-bottom:8px;border-bottom:1px solid #e2e8f0;padding-bottom:4px;display:flex;align-items:center;gap:5px;">
                                        <i class="fas fa-map-marker-alt" style="color:#be185d;"></i> Complete Address
                                    </label>
                                    <div style="display:grid;grid-template-columns:0.7fr 1fr 0.7fr 0.5fr 0.5fr;gap:8px;">
                                        <div>
                                            <label style="font-size:0.64rem;color:#94a3b8;display:block;margin-bottom:2px;font-weight:600;">House No.</label>
                                            <input type="text" id="msHouseNo" style="width:100%;padding:6px 8px;border:1.5px solid #d0dae8;border-radius:6px;font-size:0.82rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'houseNo'); syncAddressFromActiveCard();" onfocus="this.style.borderColor='#be185d';" onblur="this.style.borderColor='#d0dae8';" placeholder="e.g. 123 Blk 4">
                                        </div>
                                        <div>
                                            <label style="font-size:0.64rem;color:#94a3b8;display:block;margin-bottom:2px;font-weight:600;">Street</label>
                                            <input type="text" id="msStreet" style="width:100%;padding:6px 8px;border:1.5px solid #d0dae8;border-radius:6px;font-size:0.82rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'street'); syncAddressFromActiveCard();" onfocus="this.style.borderColor='#be185d';" onblur="this.style.borderColor='#d0dae8';" placeholder="e.g. Mabini St.">
                                        </div>
                                        <div>
                                            <label style="font-size:0.64rem;color:#94a3b8;display:block;margin-bottom:2px;font-weight:600;">Barangay</label>
                                            <input type="text" value="<?php echo htmlspecialchars($_SESSION['barangay'] ?? ''); ?>" readonly style="width:100%;padding:6px 8px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:0.82rem;color:#9d174d;background:#fdf2f8;font-weight:700;">
                                        </div>
                                        <div>
                                            <label style="font-size:0.64rem;color:#94a3b8;display:block;margin-bottom:2px;font-weight:600;">City/Municipality</label>
                                            <input type="text" value="Pasig City" readonly style="width:100%;padding:6px 8px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:0.82rem;color:#9d174d;background:#fdf2f8;font-weight:700;">
                                        </div>
                                        <div>
                                            <label style="font-size:0.64rem;color:#94a3b8;display:block;margin-bottom:2px;font-weight:600;">Province / ZIP</label>
                                            <input type="text" id="msZipCode" placeholder="1600" style="width:100%;padding:6px 8px;border:1.5px solid #d0dae8;border-radius:6px;font-size:0.82rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'zipCode'); syncAddressFromActiveCard();" onfocus="this.style.borderColor='#be185d';" onblur="this.style.borderColor='#d0dae8';">
                                        </div>
                                    </div>
                                </div>

                                <!-- Qualifications + Staggered Scheme Table -->
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
                                    <!-- Qualifications -->
                                    <div style="background:#fdf2f8;border:1px solid #fbcfe8;border-radius:12px;padding:14px 16px;">
                                        <div style="font-size:0.7rem;font-weight:800;text-transform:uppercase;letter-spacing:0.07em;color:#9d174d;margin-bottom:10px;display:flex;align-items:center;gap:6px;">
                                            <i class="fas fa-clipboard-check" style="color:#be185d;"></i> Qualifications
                                        </div>
                                        <ol style="margin:0;padding-left:18px;font-size:0.78rem;color:#4a0728;line-height:1.8;">
                                            <li>Must have Pasig City Senior Citizen's ID.</li>
                                            <li>Must have at least 2 years actual residency in Pasig City.</li>
                                            <li>Must have reached age 80 years old and above.</li>
                                        </ol>
                                    </div>
                                    <!-- Staggered Scheme Table -->
                                    <div style="background:#fff;border:1px solid #fbcfe8;border-radius:12px;padding:14px 16px;">
                                        <div style="font-size:0.7rem;font-weight:800;text-transform:uppercase;letter-spacing:0.07em;color:#9d174d;margin-bottom:10px;text-align:center;">Staggered Scheme Financial Assistance</div>
                                        <table style="width:100%;border-collapse:collapse;font-size:0.8rem;">
                                            <thead>
                                                <tr style="background:#fdf2f8;">
                                                    <th style="border:1px solid #fbcfe8;padding:6px 10px;text-align:left;color:#9d174d;font-weight:700;">Age</th>
                                                    <th style="border:1px solid #fbcfe8;padding:6px 10px;text-align:right;color:#9d174d;font-weight:700;">Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $scheme = [
                                                    '80 years old' => 'P10,000.00',
                                                    '85 years old' => 'P15,000.00',
                                                    '90 years old' => 'P25,000.00',
                                                    '95 years old' => 'P25,000.00',
                                                    '100 years old' => 'P25,000.00',
                                                ];
                                                foreach($scheme as $age => $amount): ?>
                                                <tr>
                                                    <td style="border:1px solid #fbcfe8;padding:5px 10px;color:#4a0728;"><?php echo $age; ?></td>
                                                    <td style="border:1px solid #fbcfe8;padding:5px 10px;text-align:right;font-weight:700;color:#be185d;"><?php echo $amount; ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                                <tr style="background:#fdf2f8;">
                                                    <td style="border:1px solid #fbcfe8;padding:6px 10px;font-weight:800;color:#9d174d;">Total</td>
                                                    <td style="border:1px solid #fbcfe8;padding:6px 10px;text-align:right;font-weight:800;color:#9d174d;">P100,000.00</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Certification / Oath (completed during the in-person claim process) -->
                                <div class="non-submission-preview" style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:12px 14px;margin-bottom:14px;">
                                    <p style="font-size:0.74rem;color:#78350f;line-height:1.7;margin:0 0 12px;">
                                        <i class="fas fa-gavel" style="color:#92400e;margin-right:5px;"></i>
                                        I hereby certify under law on perjury that the information provided in this form is <strong>complete, true, correct, and of my knowledge</strong>. I further authorize the City Government of Pasig to process my data, validate, and confirm the answers herein with third parties such as the GSIS, SSS, DSWD and other Government/Private Agencies.
                                    </p>
                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                        <div style="border:1px solid #d0dae8;border-radius:8px;padding:10px 12px;">
                                            <label style="font-size:0.64rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;display:block;margin-bottom:18px;">Signature/Thumbmark over printed name of the Senior Citizen</label>
                                            <div style="border-top:1px solid #cbd5e1;padding-top:4px;font-size:0.7rem;color:#94a3b8;">Senior Citizen</div>
                                        </div>
                                        <div>
                                            <div style="margin-bottom:10px;">
                                                <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:5px;">Family Representative / Claimant</label>
                                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                                                    <div>
                                                        <label style="font-size:0.64rem;color:#94a3b8;display:block;margin-bottom:2px;font-weight:600;">Name:</label>
                                                        <input type="text" style="width:100%;padding:5px 8px;border:1.5px solid #d0dae8;border-radius:6px;font-size:0.82rem;color:#0f172a;background:#fff;outline:none;" onfocus="this.style.borderColor='#be185d';" onblur="this.style.borderColor='#d0dae8';">
                                                    </div>
                                                    <div>
                                                        <label style="font-size:0.64rem;color:#94a3b8;display:block;margin-bottom:2px;font-weight:600;">Relationship:</label>
                                                        <input type="text" style="width:100%;padding:5px 8px;border:1.5px solid #d0dae8;border-radius:6px;font-size:0.82rem;color:#0f172a;background:#fff;outline:none;" onfocus="this.style.borderColor='#be185d';" onblur="this.style.borderColor='#d0dae8';">
                                                    </div>
                                                    <div style="grid-column:span 2;">
                                                        <label style="font-size:0.64rem;color:#94a3b8;display:block;margin-bottom:2px;font-weight:600;">Contact No.:</label>
                                                        <input type="text" style="width:100%;padding:5px 8px;border:1.5px solid #d0dae8;border-radius:6px;font-size:0.82rem;color:#0f172a;background:#fff;outline:none;" onfocus="this.style.borderColor='#be185d';" onblur="this.style.borderColor='#d0dae8';">
                                                    </div>
                                                </div>
                                            </div>
                                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                                                <div>
                                                    <label style="font-size:0.64rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;display:block;margin-bottom:3px;">Received by:</label>
                                                    <div style="border-bottom:1px solid #d0dae8;min-height:20px;"></div>
                                                </div>
                                                <div>
                                                    <label style="font-size:0.64rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;display:block;margin-bottom:3px;">Approved by:</label>
                                                    <div style="border-bottom:1px solid #d0dae8;min-height:20px;"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Requirements Panel -->
                                <div style="background:#f0f7ff;border:1px solid #bfdbfe;border-radius:12px;padding:14px 16px;margin-bottom:14px;">
                                    <div style="font-size:0.72rem;font-weight:800;text-transform:uppercase;letter-spacing:0.07em;color:#1e3a8a;margin-bottom:10px;display:flex;align-items:center;gap:6px;">
                                        <i class="fas fa-clipboard-list" style="color:#2563eb;"></i> Primary Requirements
                                    </div>
                                    <ol style="margin:0;padding-left:18px;font-size:0.78rem;color:#1e3a5f;line-height:1.8;">
                                        <li>Certificate of live birth duly issued or authenticated by the Philippine Statistics Authority (PSA)</li>
                                        <li>Photocopy of Senior identification card (OSCA I.D.) (front &amp; back)</li>
                                        <li>Latest A4 size whole body picture</li>
                                    </ol>
                                    <div style="margin-top:10px;font-size:0.75rem;color:#1e3a5f;font-style:italic;">
                                        <strong>In the absence of primary documents</strong>, any two (2) of the following secondary ID cards/documents may be accepted:
                                        <ul style="margin-top:6px;padding-left:18px;line-height:1.8;">
                                            <li>Original Certificate of Late Registration of Birth (PSA)</li>
                                            <li>Photocopy of government-issued ID (LTO, GSIS, SSS, PRC, Postal ID, COMELEC)</li>
                                            <li>Original Certificate of Live Birth of Eldest Child (PSA/Local Civil Register)</li>
                                            <li>Photocopy of Valid Philippine Passport</li>
                                            <li>Original Baptismal Certificate or church record showing date of birth</li>
                                            <li>For Indigenous Peoples: NCIP certification; For Muslims: NCMF certification</li>
                                        </ul>
                                    </div>
                                </div>

                                <!-- Stub -->
                                <div class="non-submission-preview" style="border:2px dashed #be185d;border-radius:10px;padding:12px 16px;background:#fdf2f8;">
                                    <div style="font-size:0.7rem;font-weight:800;color:#9d174d;text-transform:uppercase;letter-spacing:0.08em;margin-bottom:8px;">MILESTONE CASH GIFT STUB <span style="font-size:0.65rem;color:#f9a8d4;font-weight:500;">(Present upon claiming &mdash; do not lose)</span></div>
                                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;font-size:0.75rem;color:#4a0728;">
                                        <div><span style="font-weight:700;">Name of Senior Citizen:</span><div style="border-bottom:1px solid #fbcfe8;min-height:16px;margin-top:2px;"></div></div>
                                        <div><span style="font-weight:700;">Name of Claimant:</span><div style="border-bottom:1px solid #fbcfe8;min-height:16px;margin-top:2px;"></div></div>
                                        <div><span style="font-weight:700;">Relationship:</span><div style="border-bottom:1px solid #fbcfe8;min-height:16px;margin-top:2px;"></div></div>
                                        <div><span style="font-weight:700;">Barangay:</span><div style="border-bottom:1px solid #fbcfe8;min-height:16px;margin-top:2px;"><?php echo htmlspecialchars($_SESSION['barangay'] ?? ''); ?></div></div>
                                        <div><span style="font-weight:700;">Date &amp; Time:</span><div style="border-bottom:1px solid #fbcfe8;min-height:16px;margin-top:2px;"><?php echo date('m/d/Y'); ?></div></div>
                                        <div><span style="font-weight:700;">Received by:</span><div style="border-bottom:1px solid #fbcfe8;min-height:16px;margin-top:2px;"></div></div>
                                    </div>
                                </div>

                                <!-- Required Documents (Milestone) -->
                                <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:14px 16px;margin-top:14px;margin-bottom:10px;">
                                    <div style="font-size:0.72rem;font-weight:800;text-transform:uppercase;letter-spacing:0.07em;color:#166534;margin-bottom:4px;display:flex;align-items:center;gap:6px;">
                                        <i class="fas fa-file-alt" style="color:#16a34a;"></i> Required Documents
                                        <span style="font-size:0.65rem;font-weight:600;color:#16a34a;background:#dcfce7;border-radius:20px;padding:2px 8px;margin-left:4px;">3 Required</span>
                                    </div>
                                    <p style="font-size:0.72rem;color:#4b7c5e;margin-bottom:12px;display:flex;align-items:center;gap:5px;margin-top:6px;">
                                        <i class="fas fa-info-circle" style="color:#16a34a;"></i>
                                        Accepted formats: JPEG, PNG, PDF &mdash; Max 8MB per file.
                                    </p>
                                    <!-- Row 1 -->
                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                                        <div>
                                            <label for="msDoc1" style="font-size:0.68rem;font-weight:700;color:#166534;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:5px;">
                                                <i class="fas fa-scroll" style="margin-right:4px;color:#16a34a;"></i> Birth Certificate (PSA / NSO) <span style="color:#e74c3c;">*</span>
                                            </label>
                                            <input type="file" id="msDoc1" name="doc_birth_certificate" required accept="image/jpeg,image/png,image/gif,application/pdf" onchange="checkFileSize(this)" style="width:100%;font-size:0.78rem;padding:6px 8px;border:1.5px solid #bbf7d0;border-radius:7px;background:#fff;color:#166534;cursor:pointer;">
                                            <div id="msDoc1SizeWarn" style="display:none;color:#e74c3c;font-size:0.72rem;margin-top:3px;"><i class="fas fa-exclamation-triangle"></i> File exceeds 8MB.</div>
                                        </div>
                                        <div>
                                            <label for="msDoc2" style="font-size:0.68rem;font-weight:700;color:#166534;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:5px;">
                                                <i class="fas fa-home" style="margin-right:4px;color:#16a34a;"></i> Barangay Residency Certificate <span style="color:#e74c3c;">*</span>
                                            </label>
                                            <input type="file" id="msDoc2" name="doc_barangay_cert" required accept="image/jpeg,image/png,image/gif,application/pdf" onchange="checkFileSize(this)" style="width:100%;font-size:0.78rem;padding:6px 8px;border:1.5px solid #bbf7d0;border-radius:7px;background:#fff;color:#166534;cursor:pointer;">
                                            <div id="msDoc2SizeWarn" style="display:none;color:#e74c3c;font-size:0.72rem;margin-top:3px;"><i class="fas fa-exclamation-triangle"></i> File exceeds 8MB.</div>
                                        </div>
                                    </div>
                                    <!-- Row 2 -->
                                    <div style="display:grid;grid-template-columns:1fr;gap:12px;">
                                        <div>
                                            <label for="msDoc3" style="font-size:0.68rem;font-weight:700;color:#166534;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:5px;">
                                                <i class="fas fa-id-card" style="margin-right:4px;color:#16a34a;"></i> Valid ID / Senior Citizen ID <span style="color:#e74c3c;">*</span>
                                            </label>
                                            <input type="file" id="msDoc3" name="doc_valid_id" required accept="image/jpeg,image/png,image/gif,application/pdf" onchange="checkFileSize(this)" style="width:100%;font-size:0.78rem;padding:6px 8px;border:1.5px solid #bbf7d0;border-radius:7px;background:#fff;color:#166534;cursor:pointer;">
                                            <div id="msDoc3SizeWarn" style="display:none;color:#e74c3c;font-size:0.72rem;margin-top:3px;"><i class="fas fa-exclamation-triangle"></i> File exceeds 8MB.</div>
                                        </div>
                                    </div>
                                </div>

                                <p style="font-size:0.67rem;color:#94a3b8;text-align:center;margin-top:10px;margin-bottom:0;">
                                    <i class="fas fa-info-circle" style="margin-right:4px;"></i>
                                    This official form preview mirrors the Milestone Cash Gift form. All fields entered here are automatically carried over to the main form below.
                                </p>
                            </div><!-- /form body -->
                        </div>
                    </div><!-- /#milestoneOfficialFormCard -->
                    </template>

                    <!-- ================================================================
                         OSCA OFFICIAL FORM — Local Senior Pension
                         Visible for 'pension' and 'national_pension' types
                         ================================================================ -->
                    <template id="pensionOfficialFormCardTemplate">
                    <div id="pensionOfficialFormCard" class="guided-benefit-form" style="display:none; margin-bottom:28px;">
                        <div style="
                            background: #fff;
                            border: 2px solid #b45309;
                            border-radius: 16px;
                            overflow: hidden;
                            box-shadow: 0 8px 32px rgba(180,83,9,0.13);
                            font-family: 'Segoe UI', Arial, sans-serif;
                        ">
                            <!-- LGU Letterhead Banner -->
                            <div style="
                                background: linear-gradient(135deg, #431407 0%, #b45309 100%);
                                padding: 18px 24px 16px;
                                display: flex;
                                align-items: center;
                                gap: 16px;
                            ">
                                <div style="width:56px;height:56px;background:rgba(255,255,255,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;border:2px solid rgba(255,255,255,0.3);">
                                    <i class="fas fa-wallet" style="color:#fcd34d;font-size:1.4rem;"></i>
                                </div>
                                <div style="flex:1;">
                                    <div style="color:rgba(255,255,255,0.7);font-size:0.72rem;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;">Republic of the Philippines &bull; City Government of Pasig</div>
                                    <div style="color:#fff;font-size:1rem;font-weight:800;line-height:1.2;margin-top:2px;">Office for the Senior Citizens Affairs (OSCA)</div>
                                    <div style="color:#fcd34d;font-size:0.8rem;font-weight:700;margin-top:3px;letter-spacing:0.04em;" id="pensionFormTitle">LOCAL SENIOR PENSION FORM</div>
                                </div>
                                <div style="text-align:right;">
                                    <div style="color:rgba(255,255,255,0.5);font-size:0.68rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;">Control No.</div>
                                    <div style="color:#fcd34d;font-size:0.78rem;margin-top:2px;font-weight:700;" id="pensionControlNoDisplay"><?php echo date('Y') . '-' . rand(1000,9999); ?></div>
                                    <div style="color:rgba(255,255,255,0.5);font-size:0.68rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;margin-top:5px;">Date:</div>
                                    <div style="color:#fcd34d;font-size:0.78rem;margin-top:1px;font-weight:600;"><?php echo date('m/d/Y'); ?></div>
                                </div>
                            </div>

                            <!-- Form Body -->
                            <div style="padding:20px 24px;">

                                <!-- Name Row -->
                                <div style="margin-bottom:14px;">
                                    <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:#475569;margin-bottom:8px;border-bottom:1px solid #e2e8f0;padding-bottom:4px;">
                                        <i class="fas fa-user" style="color:#b45309;margin-right:5px;"></i> Personal Information
                                    </div>
                                    <div style="display:grid;grid-template-columns:2fr 0.6fr 2fr 1.2fr;gap:10px;margin-bottom:10px;">
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Last Name</label>
                                            <input type="text" id="penLastName" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'lastName')" onfocus="this.style.borderColor='#b45309';" onblur="this.style.borderColor='#d0dae8';" placeholder="Dela Cruz">
                                        </div>
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Suffix</label>
                                            <input type="text" id="penSuffix" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'suffix')" onfocus="this.style.borderColor='#b45309';" onblur="this.style.borderColor='#d0dae8';" placeholder="Jr.">
                                        </div>
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">First Name</label>
                                            <input type="text" id="penFirstName" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'firstName')" onfocus="this.style.borderColor='#b45309';" onblur="this.style.borderColor='#d0dae8';" placeholder="Juan">
                                        </div>
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Middle Name</label>
                                            <input type="text" id="penMiddleName" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'middleName')" onfocus="this.style.borderColor='#b45309';" onblur="this.style.borderColor='#d0dae8';" placeholder="Santos">
                                        </div>
                                    </div>
                                    <div style="display:grid;grid-template-columns:0.5fr 0.5fr 0.5fr 1fr;gap:10px;margin-bottom:10px;">
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Birthdate</label>
                                            <input type="date" id="penBirthDate" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.82rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'birthDate');updatePenAge();" onfocus="this.style.borderColor='#b45309';" onblur="this.style.borderColor='#d0dae8';">
                                        </div>
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Age</label>
                                            <input type="text" id="penAge" readonly style="width:100%;padding:7px 10px;border:1.5px solid #e2e8f0;border-radius:7px;font-size:0.88rem;color:#b45309;background:#fefce8;font-weight:700;text-align:center;" placeholder="—">
                                        </div>
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:5px;">Sex</label>
                                            <div style="display:flex;gap:8px;padding-top:4px;">
                                                <label style="display:flex;align-items:center;gap:4px;font-size:0.82rem;font-weight:600;color:#0f172a;cursor:pointer;"><input type="radio" name="penGender" value="Male" style="accent-color:#b45309;"> M</label>
                                                <label style="display:flex;align-items:center;gap:4px;font-size:0.82rem;font-weight:600;color:#0f172a;cursor:pointer;"><input type="radio" name="penGender" value="Female" style="accent-color:#b45309;"> F</label>
                                            </div>
                                        </div>
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Contact No.</label>
                                            <input type="text" id="penContact" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'contactNumber')" onfocus="this.style.borderColor='#b45309';" onblur="this.style.borderColor='#d0dae8';" placeholder="09XX-XXX-XXXX">
                                        </div>
                                    </div>
                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Senior ID No.</label>
                                            <input type="text" id="penScIdNo" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'seniorIdNo')" onfocus="this.style.borderColor='#b45309';" onblur="this.style.borderColor='#d0dae8';" placeholder="OSCA-YYYY-XXXXXX">
                                        </div>
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">ATM No. or Temporary Cash Card Stub No.</label>
                                            <input type="text" id="penAtmNo" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" onfocus="this.style.borderColor='#b45309';" onblur="this.style.borderColor='#d0dae8';" placeholder="ATM / Temp Card No.">
                                        </div>
                                    </div>
                                    <div>
                                        <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Mother's Maiden Name</label>
                                        <input type="text" id="penMother" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" onfocus="this.style.borderColor='#b45309';" onblur="this.style.borderColor='#d0dae8';" placeholder="Santos, Maria A.">
                                    </div>
                                </div>

                                <!-- Address -->
                                <div style="margin-bottom:14px;">
                                    <label style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:#475569;margin-bottom:8px;border-bottom:1px solid #e2e8f0;padding-bottom:4px;display:flex;align-items:center;gap:5px;">
                                        <i class="fas fa-map-marker-alt" style="color:#b45309;"></i> Address
                                    </label>
                                    <div style="display:grid;grid-template-columns:0.7fr 1fr 0.7fr 0.5fr;gap:8px;">
                                        <div><label style="font-size:0.64rem;color:#94a3b8;display:block;margin-bottom:2px;font-weight:600;">House/Blk/Lot No.</label><input type="text" id="penHouseNo" style="width:100%;padding:6px 8px;border:1.5px solid #d0dae8;border-radius:6px;font-size:0.82rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'houseNo'); syncAddressFromActiveCard();" onfocus="this.style.borderColor='#b45309';" onblur="this.style.borderColor='#d0dae8';" placeholder="e.g. 123 Blk 4"></div>
                                        <div><label style="font-size:0.64rem;color:#94a3b8;display:block;margin-bottom:2px;font-weight:600;">Street/Purok/Village</label><input type="text" id="penStreet" style="width:100%;padding:6px 8px;border:1.5px solid #d0dae8;border-radius:6px;font-size:0.82rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'street'); syncAddressFromActiveCard();" onfocus="this.style.borderColor='#b45309';" onblur="this.style.borderColor='#d0dae8';" placeholder="e.g. Mabini St."></div>
                                        <div><label style="font-size:0.64rem;color:#94a3b8;display:block;margin-bottom:2px;font-weight:600;">Barangay</label><input type="text" value="<?php echo htmlspecialchars($_SESSION['barangay'] ?? ''); ?>" readonly style="width:100%;padding:6px 8px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:0.82rem;color:#b45309;background:#fefce8;font-weight:700;"></div>
                                        <div><label style="font-size:0.64rem;color:#94a3b8;display:block;margin-bottom:2px;font-weight:600;">ZIP Code</label><input type="text" id="penZipCode" placeholder="1600" style="width:100%;padding:6px 8px;border:1.5px solid #d0dae8;border-radius:6px;font-size:0.82rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'zipCode'); syncAddressFromActiveCard();" onfocus="this.style.borderColor='#b45309';" onblur="this.style.borderColor='#d0dae8';"></div>
                                    </div>
                                </div>

                                <!-- Pension details (reference only; no external verification) -->
                                <div style="background:#fff7ed;border:1.5px solid #fdba74;border-radius:12px;padding:14px 16px;margin-bottom:14px;">
                                    <div style="font-size:0.72rem;font-weight:800;text-transform:uppercase;letter-spacing:0.07em;color:#9a3412;margin-bottom:6px;display:flex;align-items:center;gap:6px;">
                                        <i class="fas fa-file-invoice-dollar" style="color:#ea580c;"></i> Pension Details <span style="font-weight:600;text-transform:none;letter-spacing:0;">(Optional)</span>
                                    </div>
                                    <p style="font-size:0.78rem;color:#9a3412;line-height:1.5;margin:0 0 10px;">Record the applicant's SSS information if it is available. This is not verified by the system and will not affect submission.</p>
                                    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;align-items:end;">
                                        <div>
                                            <label style="font-size:0.66rem;font-weight:700;color:#9a3412;text-transform:uppercase;letter-spacing:0.05em;display:block;margin-bottom:3px;">SSS Number</label>
                                            <input type="text" id="penSssNumber" inputmode="numeric" autocomplete="off" style="width:100%;padding:8px 10px;border:1.5px solid #fdba74;border-radius:7px;font-size:0.88rem;color:#431407;background:#fff;outline:none;" oninput="syncField(this,'sssNumber');" placeholder="Enter SSS number">
                                        </div>
                                        <div>
                                            <label style="font-size:0.66rem;font-weight:700;color:#9a3412;text-transform:uppercase;letter-spacing:0.05em;display:block;margin-bottom:3px;">Monthly Pension Amount</label>
                                            <input type="number" id="penPensionAmount" min="0" step="0.01" inputmode="decimal" style="width:100%;padding:8px 10px;border:1.5px solid #fdba74;border-radius:7px;font-size:0.88rem;color:#431407;background:#fff;outline:none;" oninput="syncField(this,'pensionAmount');" placeholder="e.g. 3500.00">
                                        </div>
                                    </div>
                                </div>

                                <!-- Home visitation schedule (Local Pension only) -->
                                <div id="pensionHomeVisitSchedule" style="display:none;background:#eff6ff;border:1.5px solid #93c5fd;border-radius:12px;padding:14px 16px;margin-bottom:14px;">
                                    <div style="font-size:0.72rem;font-weight:800;text-transform:uppercase;letter-spacing:0.07em;color:#1e3a8a;margin-bottom:6px;display:flex;align-items:center;gap:6px;">
                                        <i class="fas fa-house-medical" style="color:#2563eb;"></i> Home Visitation Schedule
                                    </div>
                                    <p style="font-size:0.78rem;color:#1e40af;line-height:1.5;margin:0 0 10px;">
                                        Complete the eligibility decision first. Scheduling and personnel assignment are required only for eligible seniors.
                                    </p>
                                    <fieldset style="border:0;margin:0 0 10px;padding:0;">
                                        <legend style="font-size:0.68rem;font-weight:700;color:#1e3a8a;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:5px;">Eligible for home visit? <span style="color:#dc2626;">*</span></legend>
                                        <label style="display:inline-flex;align-items:center;gap:6px;margin-right:18px;font-size:0.84rem;font-weight:600;"><input type="radio" name="homeVisitEligibility" value="eligible" onchange="toggleHomeVisitEligibility()"> Yes</label>
                                        <label style="display:inline-flex;align-items:center;gap:6px;font-size:0.84rem;font-weight:600;"><input type="radio" name="homeVisitEligibility" value="not_eligible" onchange="toggleHomeVisitEligibility()"> No</label>
                                    </fieldset>
                                    <label for="homeVisitEligibilityReason" style="font-size:0.68rem;font-weight:700;color:#1e3a8a;text-transform:uppercase;letter-spacing:0.05em;display:block;margin-bottom:4px;">Assessment reason</label>
                                    <input type="text" id="homeVisitEligibilityReason" name="homeVisitEligibilityReason" maxlength="255" placeholder="e.g. Bedridden or unable to visit the office"
                                        style="width:100%;padding:9px 10px;border:1.5px solid #93c5fd;border-radius:7px;font-size:0.88rem;color:#172554;background:#fff;outline:none;margin-bottom:10px;">
                                    <div id="homeVisitScheduleHelp" style="font-size:0.75rem;color:#1d4ed8;background:#dbeafe;border-radius:7px;padding:8px 10px;margin-bottom:8px;"><i class="fas fa-info-circle"></i> Choose <strong>Yes</strong> above to enable the calendar and personnel assignment.</div>
                                    <div id="homeVisitAssignmentFields" style="display:block;opacity:0.55;transition:opacity .2s;">
                                    <label for="homeVisitScheduledAt" style="font-size:0.68rem;font-weight:700;color:#1e3a8a;text-transform:uppercase;letter-spacing:0.05em;display:block;margin-bottom:4px;">Visit date and time <span style="color:#dc2626;">*</span></label>
                                    <input type="datetime-local" id="homeVisitScheduledAt" name="homeVisitScheduledAt" step="1800"
                                        onclick="if (this.showPicker && !this.disabled) this.showPicker()"
                                        style="width:100%;max-width:360px;padding:9px 10px;border:1.5px solid #93c5fd;border-radius:7px;font-size:0.88rem;color:#172554;background:#fff;outline:none;">
                                    <label for="homeVisitPersonnelId" style="font-size:0.68rem;font-weight:700;color:#1e3a8a;text-transform:uppercase;letter-spacing:0.05em;display:block;margin:8px 0 4px;">Assigned personnel <span style="color:#dc2626;">*</span></label>
                                    <select id="homeVisitPersonnelId" name="homeVisitPersonnelId" style="width:100%;max-width:360px;padding:9px 10px;border:1.5px solid #93c5fd;border-radius:7px;font-size:0.88rem;color:#172554;background:#fff;outline:none;">
                                        <option value="">Select personnel</option>
                                        <?php foreach ($homeVisitPersonnel as $person): ?>
                                            <option value="<?= (int)$person['id'] ?>"><?= htmlspecialchars($person['full_name'] . (!empty($person['position']) ? ' - ' . $person['position'] : '')) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    </div>
                                    <div style="font-size:0.72rem;color:#64748b;margin-top:6px;"><i class="fas fa-clock" style="margin-right:4px;"></i>Monday–Friday, 8:00 AM–4:30 PM; 30-minute slots.</div>
                                </div>

                                <!-- Economic Status -->
                                <div style="background:#fefce8;border:1px solid #fde68a;border-radius:12px;padding:14px 16px;margin-bottom:14px;">
                                    <div style="font-size:0.72rem;font-weight:800;text-transform:uppercase;letter-spacing:0.07em;color:#92400e;margin-bottom:12px;display:flex;align-items:center;gap:6px;">
                                        <i class="fas fa-chart-bar" style="color:#b45309;"></i> Economic Status
                                    </div>
                                    <div style="display:flex;flex-direction:column;gap:10px;">
                                        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                                            <span style="font-size:0.82rem;font-weight:700;color:#431407;min-width:200px;">1. Pensioner?</span>
                                            <label style="display:flex;align-items:center;gap:5px;font-size:0.82rem;font-weight:600;cursor:pointer;"><input type="radio" name="penPensioner" value="Yes" style="accent-color:#b45309;"> Yes</label>
                                            <label style="display:flex;align-items:center;gap:5px;font-size:0.82rem;font-weight:600;cursor:pointer;"><input type="radio" name="penPensioner" value="No" style="accent-color:#b45309;"> No</label>
                                            <span style="font-size:0.78rem;color:#78350f;">If yes, what source and how much?</span>
                                            <input type="text" style="border:none;border-bottom:1px solid #b45309;outline:none;font-size:0.8rem;flex:1;min-width:120px;background:transparent;" placeholder="e.g. SSS / P2,000">
                                        </div>
                                        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                                            <span style="font-size:0.82rem;font-weight:700;color:#431407;min-width:200px;">2. Permanent source of income?</span>
                                            <label style="display:flex;align-items:center;gap:5px;font-size:0.82rem;font-weight:600;cursor:pointer;"><input type="radio" name="penPermIncome" value="Yes" style="accent-color:#b45309;"> Yes</label>
                                            <label style="display:flex;align-items:center;gap:5px;font-size:0.82rem;font-weight:600;cursor:pointer;"><input type="radio" name="penPermIncome" value="No" style="accent-color:#b45309;"> No</label>
                                            <span style="font-size:0.78rem;color:#78350f;">If yes, from what source?</span>
                                            <input type="text" style="border:none;border-bottom:1px solid #b45309;outline:none;font-size:0.8rem;flex:1;min-width:120px;background:transparent;" placeholder="Source of income">
                                        </div>
                                        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                                            <span style="font-size:0.82rem;font-weight:700;color:#431407;min-width:200px;">3. Regular support from family?</span>
                                            <label style="display:flex;align-items:center;gap:5px;font-size:0.82rem;font-weight:600;cursor:pointer;"><input type="radio" name="penFamilySupport" value="Yes" style="accent-color:#b45309;"> Yes</label>
                                            <label style="display:flex;align-items:center;gap:5px;font-size:0.82rem;font-weight:600;cursor:pointer;"><input type="radio" name="penFamilySupport" value="No" style="accent-color:#b45309;"> No</label>
                                            <span style="font-size:0.78rem;color:#78350f;">If yes, type of support:</span>
                                            <label style="display:flex;align-items:center;gap:5px;font-size:0.8rem;cursor:pointer;"><input type="checkbox" style="accent-color:#b45309;"> Cash (How much? <input type="text" style="border:none;border-bottom:1px solid #b45309;outline:none;font-size:0.78rem;width:70px;background:transparent;">)</label>
                                        </div>
                                        <div style="display:flex;align-items:flex-start;gap:12px;flex-wrap:wrap;">
                                            <span style="font-size:0.82rem;font-weight:700;color:#431407;min-width:200px;">4. Condition / Illness:</span>
                                            <input type="text" style="border:none;border-bottom:1px solid #b45309;outline:none;font-size:0.8rem;flex:1;min-width:200px;background:transparent;" placeholder="Describe condition or illness">
                                        </div>
                                        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                                            <span style="font-size:0.82rem;font-weight:700;color:#431407;min-width:200px;">5. Own house?</span>
                                            <label style="display:flex;align-items:center;gap:5px;font-size:0.82rem;font-weight:600;cursor:pointer;"><input type="radio" name="penOwnHouse" value="Yes" style="accent-color:#b45309;"> Yes</label>
                                            <label style="display:flex;align-items:center;gap:5px;font-size:0.82rem;font-weight:600;cursor:pointer;"><input type="radio" name="penOwnHouse" value="No" style="accent-color:#b45309;"> No</label>
                                            <span style="font-size:0.82rem;font-weight:700;color:#431407;margin-left:10px;">Renter:</span>
                                            <label style="display:flex;align-items:center;gap:5px;font-size:0.82rem;font-weight:600;cursor:pointer;"><input type="radio" name="penRenter" value="Yes" style="accent-color:#b45309;"> Yes</label>
                                            <label style="display:flex;align-items:center;gap:5px;font-size:0.82rem;font-weight:600;cursor:pointer;"><input type="radio" name="penRenter" value="No" style="accent-color:#b45309;"> No</label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Certification / Oath -->
                                <div class="non-submission-preview" style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:12px 14px;margin-bottom:14px;">
                                    <p style="font-size:0.74rem;color:#78350f;line-height:1.7;margin:0 0 12px;">
                                        <i class="fas fa-gavel" style="color:#92400e;margin-right:5px;"></i>
                                        I hereby certify under law on perjury that the information provided in this form is <strong>complete, true and correct to the best of my knowledge</strong>. I further authorize the City Government of Pasig to process my data, validate, and confirm the answers herein with third parties such as the GSIS, SSS, DSWD and other Government/Private Agencies.
                                    </p>
                                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                                        <div style="border:1px solid #d0dae8;border-radius:8px;padding:10px 12px;">
                                            <label style="font-size:0.64rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;display:block;margin-bottom:18px;">Signature/Thumbmark over printed name of the Senior Citizen</label>
                                            <div style="border-top:1px solid #cbd5e1;padding-top:4px;font-size:0.7rem;color:#94a3b8;">Senior Citizen</div>
                                        </div>
                                        <div style="border:1px solid #d0dae8;border-radius:8px;padding:10px 12px;">
                                            <label style="font-size:0.64rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;display:block;margin-bottom:18px;">OSCA Personnel's Name &amp; Signature</label>
                                            <div style="border-top:1px solid #cbd5e1;padding-top:4px;">
                                                <div style="font-size:0.68rem;color:#94a3b8;">Date: <input type="date" style="border:none;border-bottom:1px solid #cbd5e1;outline:none;font-size:0.78rem;"></div>
                                            </div>
                                        </div>
                                        <div style="border:1px solid #d0dae8;border-radius:8px;padding:10px 12px;">
                                            <label style="font-size:0.64rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;display:block;margin-bottom:3px;">* to be filled-up by OSCA Encoder</label>
                                            <div style="font-size:0.72rem;color:#334155;margin-bottom:4px;">Name of encoder:<input type="text" style="border:none;border-bottom:1px solid #cbd5e1;outline:none;font-size:0.78rem;width:100%;" ></div>
                                            <div style="font-size:0.72rem;color:#334155;">Date encoded:<input type="date" style="border:none;border-bottom:1px solid #cbd5e1;outline:none;font-size:0.78rem;" ></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Requirements Reference -->
                                <div style="background:#f0f7ff;border:1px solid #bfdbfe;border-radius:12px;padding:14px 16px;margin-bottom:14px;">
                                    <div style="font-size:0.72rem;font-weight:800;text-transform:uppercase;letter-spacing:0.07em;color:#1e3a8a;margin-bottom:10px;display:flex;align-items:center;gap:6px;">
                                        <i class="fas fa-clipboard-list" style="color:#2563eb;"></i> Requirements
                                    </div>
                                    <ul style="margin:0;padding-left:18px;font-size:0.78rem;color:#1e3a5f;line-height:1.8;">
                                        <li>Original and One (1) Photocopy of Senior ID (front &amp; back)</li>
                                        <li>One (1) Photocopy of Landbank Cash Card (ATM) or Temporary Cash Card</li>
                                        <li>Barangay Certificate of Indigency</li>
                                        <li>One (1) latest 1&times;1 ID picture</li>
                                    </ul>
                                    <div style="margin-top:8px;font-size:0.74rem;color:#1e3a5f;font-style:italic;">Assessment: <input type="text" style="border:none;border-bottom:1px solid #bfdbfe;outline:none;font-size:0.78rem;width:60%;background:transparent;" placeholder="(to be filled-up by SHDO)"></div>
                                </div>

                                <!-- Pension Stub -->
                                <div class="non-submission-preview" style="border:2px dashed #b45309;border-radius:10px;padding:12px 16px;background:#fefce8;">
                                    <div style="font-size:0.7rem;font-weight:800;color:#92400e;text-transform:uppercase;letter-spacing:0.08em;margin-bottom:8px;">OSCA &mdash; PASIG CITY LOCAL SENIOR PENSION STUB <span style="font-size:0.65rem;color:#d97706;font-weight:500;">(Present upon claiming)</span></div>
                                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;font-size:0.75rem;color:#431407;">
                                        <div><span style="font-weight:700;">Name:</span><div style="border-bottom:1px solid #fde68a;min-height:16px;margin-top:2px;"></div></div>
                                        <div><span style="font-weight:700;">Control No.:</span><div style="border-bottom:1px solid #fde68a;min-height:16px;margin-top:2px;"></div></div>
                                        <div><span style="font-weight:700;">Date:</span><div style="border-bottom:1px solid #fde68a;min-height:16px;margin-top:2px;"><?php echo date('m/d/Y'); ?></div></div>
                                    </div>
                                </div>

                                <!-- Required Documents (Local Pension) -->
                                <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:14px 16px;margin-top:14px;margin-bottom:10px;">
                                    <div style="font-size:0.72rem;font-weight:800;text-transform:uppercase;letter-spacing:0.07em;color:#166534;margin-bottom:4px;display:flex;align-items:center;gap:6px;">
                                        <i class="fas fa-file-alt" style="color:#16a34a;"></i> Required Documents
                                        <span style="font-size:0.65rem;font-weight:600;color:#16a34a;background:#dcfce7;border-radius:20px;padding:2px 8px;margin-left:4px;">4 Required</span>
                                    </div>
                                    <p style="font-size:0.72rem;color:#4b7c5e;margin-bottom:12px;display:flex;align-items:center;gap:5px;margin-top:6px;">
                                        <i class="fas fa-info-circle" style="color:#16a34a;"></i>
                                        Accepted formats: JPEG, PNG, PDF &mdash; Max 8MB per file.
                                    </p>
                                    <!-- Row 1 -->
                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                                        <div>
                                            <label for="penDoc1" style="font-size:0.68rem;font-weight:700;color:#166534;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:5px;">
                                                <i class="fas fa-scroll" style="margin-right:4px;color:#16a34a;"></i> Birth Certificate (PSA / NSO) <span style="color:#e74c3c;">*</span>
                                            </label>
                                            <input type="file" id="penDoc1" name="doc_birth_certificate" required accept="image/jpeg,image/png,image/gif,application/pdf" onchange="checkFileSize(this)" style="width:100%;font-size:0.78rem;padding:6px 8px;border:1.5px solid #bbf7d0;border-radius:7px;background:#fff;color:#166534;cursor:pointer;">
                                            <div id="penDoc1SizeWarn" style="display:none;color:#e74c3c;font-size:0.72rem;margin-top:3px;"><i class="fas fa-exclamation-triangle"></i> File exceeds 8MB.</div>
                                        </div>
                                        <div>
                                            <label for="penDoc2" style="font-size:0.68rem;font-weight:700;color:#166534;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:5px;">
                                                <i class="fas fa-home" style="margin-right:4px;color:#16a34a;"></i> Barangay Residency Certificate <span style="color:#e74c3c;">*</span>
                                            </label>
                                            <input type="file" id="penDoc2" name="doc_barangay_cert" required accept="image/jpeg,image/png,image/gif,application/pdf" onchange="checkFileSize(this)" style="width:100%;font-size:0.78rem;padding:6px 8px;border:1.5px solid #bbf7d0;border-radius:7px;background:#fff;color:#166534;cursor:pointer;">
                                            <div id="penDoc2SizeWarn" style="display:none;color:#e74c3c;font-size:0.72rem;margin-top:3px;"><i class="fas fa-exclamation-triangle"></i> File exceeds 8MB.</div>
                                        </div>
                                    </div>
                                    <!-- Row 2 -->
                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                        <div>
                                            <label for="penDoc3" style="font-size:0.68rem;font-weight:700;color:#166534;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:5px;">
                                                <i class="fas fa-ban" style="margin-right:4px;color:#16a34a;"></i> Certificate of Indigency / No Pension Cert. <span style="color:#e74c3c;">*</span>
                                            </label>
                                            <input type="file" id="penDoc3" name="doc_indigency_cert" required accept="image/jpeg,image/png,image/gif,application/pdf" onchange="checkFileSize(this)" style="width:100%;font-size:0.78rem;padding:6px 8px;border:1.5px solid #bbf7d0;border-radius:7px;background:#fff;color:#166534;cursor:pointer;">
                                            <div id="penDoc3SizeWarn" style="display:none;color:#e74c3c;font-size:0.72rem;margin-top:3px;"><i class="fas fa-exclamation-triangle"></i> File exceeds 8MB.</div>
                                        </div>
                                        <div>
                                            <label for="penDoc4" style="font-size:0.68rem;font-weight:700;color:#166534;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:5px;">
                                                <i class="fas fa-id-card" style="margin-right:4px;color:#16a34a;"></i> Valid ID / Senior Citizen ID <span style="color:#e74c3c;">*</span>
                                            </label>
                                            <input type="file" id="penDoc4" name="doc_valid_id" required accept="image/jpeg,image/png,image/gif,application/pdf" onchange="checkFileSize(this)" style="width:100%;font-size:0.78rem;padding:6px 8px;border:1.5px solid #bbf7d0;border-radius:7px;background:#fff;color:#166534;cursor:pointer;">
                                            <div id="penDoc4SizeWarn" style="display:none;color:#e74c3c;font-size:0.72rem;margin-top:3px;"><i class="fas fa-exclamation-triangle"></i> File exceeds 8MB.</div>
                                        </div>
                                    </div>
                                </div>

                                <p style="font-size:0.67rem;color:#94a3b8;text-align:center;margin-top:10px;margin-bottom:0;">
                                    <i class="fas fa-info-circle" style="margin-right:4px;"></i>
                                    This official form preview mirrors the physical OSCA Local Senior Pension form. All fields entered here are automatically carried over to the main form below.
                                </p>
                            </div><!-- /form body -->
                        </div>
                    </div><!-- /#pensionOfficialFormCard -->
                    </template>

                    <!-- ================================================================
                         OSCA OFFICIAL FORM — Land Bank Cash Card Enrollment
                         Visible only when 'landbank' application type is selected
                         ================================================================ -->
                    <template id="landbankOfficialFormCardTemplate">
                    <div id="landbankOfficialFormCard" class="guided-benefit-form" style="display:none; margin-bottom:28px;">
                        <div style="
                            background: #fff;
                            border: 2px solid #059669;
                            border-radius: 16px;
                            overflow: hidden;
                            box-shadow: 0 8px 32px rgba(5, 150, 105, 0.13);
                            font-family: 'Segoe UI', Arial, sans-serif;
                        ">
                            <!-- LGU Letterhead Banner -->
                            <div style="
                                background: linear-gradient(135deg, #064e3b 0%, #059669 100%);
                                padding: 18px 24px 16px;
                                display: flex;
                                align-items: center;
                                gap: 16px;
                            ">
                                <div style="width:56px;height:56px;background:rgba(255,255,255,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;border:2px solid rgba(255,255,255,0.3);">
                                    <i class="fas fa-credit-card" style="color:#6ee7b7;font-size:1.4rem;"></i>
                                </div>
                                <div style="flex:1;">
                                    <div style="color:rgba(255,255,255,0.7);font-size:0.72rem;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;">Republic of the Philippines &bull; City Government of Pasig</div>
                                    <div style="color:#fff;font-size:1rem;font-weight:800;line-height:1.2;margin-top:2px;">Office for the Senior Citizens Affairs (OSCA)</div>
                                    <div style="color:#6ee7b7;font-size:0.8rem;font-weight:700;margin-top:3px;letter-spacing:0.04em;">LAND BANK CASH CARD ENROLLMENT FORM</div>
                                </div>
                                <div style="text-align:right;">
                                    <div style="color:rgba(255,255,255,0.5);font-size:0.68rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;">Control No.</div>
                                    <div style="color:#6ee7b7;font-size:0.78rem;margin-top:2px;font-weight:700;" id="lbControlNoDisplay">PENDING</div>
                                    <div style="color:rgba(255,255,255,0.5);font-size:0.68rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;margin-top:5px;">Date Filed:</div>
                                    <div style="color:#6ee7b7;font-size:0.78rem;margin-top:1px;font-weight:600;"><?php echo date('m/d/Y'); ?></div>
                                </div>
                            </div>

                            <!-- Form Body -->
                            <div style="padding:20px 24px;">

                                <!-- Name Row + Photo Box -->
                                <div style="display:flex; gap:20px; align-items:flex-start; margin-bottom:14px;">
                                    <div style="flex:1;">
                                        <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:#475569;margin-bottom:8px;border-bottom:1px solid #e2e8f0;padding-bottom:4px;">
                                            <i class="fas fa-user" style="color:#059669;margin-right:5px;"></i> Cardholder Personal Information
                                        </div>
                                        <div style="display:grid;grid-template-columns:2fr 0.6fr 2fr 1.2fr;gap:10px;margin-bottom:10px;">
                                            <div>
                                                <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Last Name</label>
                                                <input type="text" id="lbLastName" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'lastName')" onfocus="this.style.borderColor='#059669';" onblur="this.style.borderColor='#d0dae8';" placeholder="Dela Cruz">
                                            </div>
                                            <div>
                                                <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Suffix</label>
                                                <input type="text" id="lbSuffix" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'suffix')" onfocus="this.style.borderColor='#059669';" onblur="this.style.borderColor='#d0dae8';" placeholder="Jr.">
                                            </div>
                                            <div>
                                                <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">First Name</label>
                                                <input type="text" id="lbFirstName" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'firstName')" onfocus="this.style.borderColor='#059669';" onblur="this.style.borderColor='#d0dae8';" placeholder="Juan">
                                            </div>
                                            <div>
                                                <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Middle Name</label>
                                                <input type="text" id="lbMiddleName" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'middleName')" onfocus="this.style.borderColor='#059669';" onblur="this.style.borderColor='#d0dae8';" placeholder="Santos">
                                            </div>
                                        </div>
                                        <div style="display:grid;grid-template-columns:1.2fr 0.6fr 1.2fr 1fr;gap:10px;">
                                            <div>
                                                <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Date of Birth</label>
                                                <input type="date" id="lbBirthDate" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.82rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'birthDate');updateLbAge();" onfocus="this.style.borderColor='#059669';" onblur="this.style.borderColor='#d0dae8';">
                                            </div>
                                            <div>
                                                <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Age</label>
                                                <input type="text" id="lbAge" readonly style="width:100%;padding:7px 10px;border:1.5px solid #e2e8f0;border-radius:7px;font-size:0.88rem;color:#064e3b;background:#ecfdf5;font-weight:700;text-align:center;" placeholder="—">
                                            </div>
                                            <div>
                                                <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Place of Birth</label>
                                                <input type="text" id="lbPlaceOfBirth" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'placeOfBirth')" onfocus="this.style.borderColor='#059669';" onblur="this.style.borderColor='#d0dae8';" placeholder="Pasig City, MM">
                                            </div>
                                            <div>
                                                <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Nationality</label>
                                                <input type="text" id="lbNationality" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'nationality')" onfocus="this.style.borderColor='#059669';" onblur="this.style.borderColor='#d0dae8';" value="Filipino">
                                            </div>
                                        </div>
                                    </div>
                                    <!-- 2x2 ID Photo Box -->
                                    <div style="flex-shrink:0;text-align:center;">
                                        <div style="
                                            width:108px;height:108px;border:2px dashed #94a3b8;border-radius:8px;
                                            background:#f1f5f9;display:flex;flex-direction:column;align-items:center;
                                            justify-content:center;gap:6px;cursor:pointer;
                                            transition:border-color 0.2s,background 0.2s;
                                            position:relative;overflow:hidden;
                                        " id="lbPhotoBox" onclick="document.getElementById('lbIdPhoto').click();"
                                           onmouseover="this.style.borderColor='#059669';this.style.background='#ecfdf5';"
                                           onmouseout="this.style.borderColor='#94a3b8';this.style.background='#f1f5f9';"
                                           title="Click to upload 2x2 ID photo">
                                            <img id="lbPhotoPreview" src="" alt="" style="display:none;position:absolute;inset:0;width:100%;height:100%;object-fit:cover;border-radius:6px;">
                                            <i class="fas fa-camera" style="color:#94a3b8;font-size:1.4rem;"></i>
                                            <span style="font-size:0.62rem;color:#64748b;font-weight:600;text-align:center;line-height:1.3;">2×2 ID Photo<br>White Background</span>
                                        </div>
                                        <input type="file" id="lbIdPhoto" name="lbIdPhoto" accept="image/*" style="display:none;" onchange="previewLbPhoto(this)">
                                        <div style="font-size:0.6rem;color:#94a3b8;margin-top:4px;">Latest Photo</div>
                                    </div>
                                </div>

                                <!-- Card-specific Details Row -->
                                <div style="margin-bottom:14px;">
                                    <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:#475569;margin-bottom:8px;border-bottom:1px solid #e2e8f0;padding-bottom:4px;">
                                        <i class="fas fa-credit-card" style="color:#059669;margin-right:5px;"></i> Land Bank Cash Card Details
                                    </div>
                                    <div style="display:grid;grid-template-columns:1.5fr 1fr 1fr;gap:10px;margin-bottom:10px;">
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Name to Appear on Card (max 23 characters)</label>
                                            <input type="text" id="lbNameOnCard" maxlength="23" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'nameOnCard')" onfocus="this.style.borderColor='#059669';" onblur="this.style.borderColor='#d0dae8';" placeholder="JUAN S. DELA CRUZ">
                                        </div>
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">TIN</label>
                                            <input type="text" id="lbTin" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'tin')" onfocus="this.style.borderColor='#059669';" onblur="this.style.borderColor='#d0dae8';" placeholder="XXX-XXX-XXX-000">
                                        </div>
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Source of Funds</label>
                                            <input type="text" id="lbSourceOfFunds" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'sourceOfFunds')" onfocus="this.style.borderColor='#059669';" onblur="this.style.borderColor='#d0dae8';" placeholder="e.g. Senior Pension, Savings">
                                        </div>
                                    </div>
                                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;">
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Type of ID Presented</label>
                                            <input type="text" id="lbIdTypePresented" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'idTypePresented')" onfocus="this.style.borderColor='#059669';" onblur="this.style.borderColor='#d0dae8';" value="OSCA">
                                        </div>
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Senior ID No.</label>
                                            <input type="text" id="lbSeniorIdNo" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'seniorIdNo')" onfocus="this.style.borderColor='#059669';" onblur="this.style.borderColor='#d0dae8';" placeholder="OSCA-YYYY-XXXXXX">
                                        </div>
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Mother's Maiden Name</label>
                                            <input type="text" id="lbMothersMaidenName" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'mothersMaidenName')" onfocus="this.style.borderColor='#059669';" onblur="this.style.borderColor='#d0dae8';" placeholder="Santos, Maria A.">
                                        </div>
                                    </div>
                                </div>

                                <!-- Contact & Address Section -->
                                <div style="margin-bottom:14px;">
                                    <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:#475569;margin-bottom:8px;border-bottom:1px solid #e2e8f0;padding-bottom:4px;">
                                        <i class="fas fa-home" style="color:#059669;margin-right:5px;"></i> Contact &amp; Address Information
                                    </div>
                                    <div style="display:grid;grid-template-columns:2fr 0.8fr 1.2fr;gap:10px;">
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Home Address</label>
                                            <input type="text" id="lbCompleteAddress" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'completeAddress')" onfocus="this.style.borderColor='#059669';" onblur="this.style.borderColor='#d0dae8';" placeholder="House No., Street Name, Barangay, City">
                                        </div>
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">ZIP Code</label>
                                            <input type="text" id="lbZipCode" maxlength="10" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'zipCode'); syncAddressFromActiveCard();" onfocus="this.style.borderColor='#059669';" onblur="this.style.borderColor='#d0dae8';" placeholder="1600">
                                        </div>
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Contact Number</label>
                                            <input type="text" id="lbContactNumber" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'contactNumber')" onfocus="this.style.borderColor='#059669';" onblur="this.style.borderColor='#d0dae8';" placeholder="09XX-XXX-XXXX">
                                        </div>
                                    </div>
                                </div>

                                <!-- Bottom certification stub -->
                                <div class="non-submission-preview" style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:12px 14px;margin-bottom:4px;">
                                    <p style="font-size:0.75rem;color:#166534;line-height:1.6;margin:0 0 10px;">
                                        <i class="fas fa-info-circle" style="color:#15803d;margin-right:5px;"></i>
                                        I hereby authorize Land Bank of the Philippines and OSCA City Government of Pasig to process my enrollment details. I acknowledge and agree to the Terms and Conditions of the LANDBANK Cash Card and verify the authenticity of all documents presented.
                                    </p>
                                    <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px dashed #bbf7d0; padding-top:10px; font-size:0.7rem; color:#15803d; font-weight:700; text-transform:uppercase;">
                                        <div>Applicant Signature: <span style="font-weight:400; font-family:'Courier New', monospace; font-size:0.85rem; text-transform:none;">/s/ Signed Digitally</span></div>
                                        <div>Barangay: <span style="color:#0f172a;"><?php echo htmlspecialchars($barangay); ?></span></div>
                                    </div>
                                </div>

                                <!-- Required Documents (Land Bank) -->
                                <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:14px 16px;margin-top:14px;margin-bottom:10px;">
                                    <div style="font-size:0.72rem;font-weight:800;text-transform:uppercase;letter-spacing:0.07em;color:#166534;margin-bottom:4px;display:flex;align-items:center;gap:6px;">
                                        <i class="fas fa-file-alt" style="color:#16a34a;"></i> Required Documents
                                        <span style="font-size:0.65rem;font-weight:600;color:#16a34a;background:#dcfce7;border-radius:20px;padding:2px 8px;margin-left:4px;">3 Required</span>
                                    </div>
                                    <p style="font-size:0.72rem;color:#4b7c5e;margin-bottom:12px;display:flex;align-items:center;gap:5px;margin-top:6px;">
                                        <i class="fas fa-info-circle" style="color:#16a34a;"></i>
                                        Accepted formats: JPEG, PNG, PDF &mdash; Max 8MB per file.
                                    </p>
                                    <!-- Row 1 -->
                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                                        <div>
                                            <label for="lbDoc1" style="font-size:0.68rem;font-weight:700;color:#166534;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:5px;">
                                                <i class="fas fa-home" style="margin-right:4px;color:#16a34a;"></i> Barangay Residency Certificate <span style="color:#e74c3c;">*</span>
                                            </label>
                                            <input type="file" id="lbDoc1" name="doc_barangay_cert" required accept="image/jpeg,image/png,image/gif,application/pdf" onchange="checkFileSize(this)" style="width:100%;font-size:0.78rem;padding:6px 8px;border:1.5px solid #bbf7d0;border-radius:7px;background:#fff;color:#166534;cursor:pointer;">
                                            <div id="lbDoc1SizeWarn" style="display:none;color:#e74c3c;font-size:0.72rem;margin-top:3px;"><i class="fas fa-exclamation-triangle"></i> File exceeds 8MB.</div>
                                        </div>
                                        <div>
                                            <label for="lbDoc2" style="font-size:0.68rem;font-weight:700;color:#166534;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:5px;">
                                                <i class="fas fa-id-card" style="margin-right:4px;color:#16a34a;"></i> Valid ID / Senior Citizen ID <span style="color:#e74c3c;">*</span>
                                            </label>
                                            <input type="file" id="lbDoc2" name="doc_valid_id" required accept="image/jpeg,image/png,image/gif,application/pdf" onchange="checkFileSize(this)" style="width:100%;font-size:0.78rem;padding:6px 8px;border:1.5px solid #bbf7d0;border-radius:7px;background:#fff;color:#166534;cursor:pointer;">
                                            <div id="lbDoc2SizeWarn" style="display:none;color:#e74c3c;font-size:0.72rem;margin-top:3px;"><i class="fas fa-exclamation-triangle"></i> File exceeds 8MB.</div>
                                        </div>
                                    </div>
                                    <!-- Row 2 -->
                                    <div style="display:grid;grid-template-columns:1fr;gap:12px;">
                                        <div>
                                            <label for="lbDoc3" style="font-size:0.68rem;font-weight:700;color:#166534;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:5px;">
                                                <i class="fas fa-credit-card" style="margin-right:4px;color:#16a34a;"></i> OSCA / Senior Citizen ID (for card enrollment) <span style="color:#e74c3c;">*</span>
                                            </label>
                                            <input type="file" id="lbDoc3" name="doc_osca_id" required accept="image/jpeg,image/png,image/gif,application/pdf" onchange="checkFileSize(this)" style="width:100%;font-size:0.78rem;padding:6px 8px;border:1.5px solid #bbf7d0;border-radius:7px;background:#fff;color:#166534;cursor:pointer;">
                                            <div id="lbDoc3SizeWarn" style="display:none;color:#e74c3c;font-size:0.72rem;margin-top:3px;"><i class="fas fa-exclamation-triangle"></i> File exceeds 8MB.</div>
                                        </div>
                                    </div>
                                </div>

                                <p style="font-size:0.67rem;color:#94a3b8;text-align:center;margin-top:8px;margin-bottom:0;">
                                    <i class="fas fa-info-circle" style="margin-right:4px;"></i>
                                    This official form preview mirrors the physical Land Bank Cash Card Enrollment Form. All fields entered here are automatically carried over to the main form below.
                                </p>
                            </div><!-- /form body -->
                        </div>
                    </div><!-- /#landbankOfficialFormCard -->
                    </template>

                    <template id="oscaOfficialFormCardTemplate">
                    <div id="oscaOfficialFormCard" class="guided-benefit-form" style="display:none; margin-bottom:28px;">
                        <div style="
                            background: #fff;
                            border: 2px solid #1e3a5f;
                            border-radius: 16px;
                            overflow: hidden;
                            box-shadow: 0 8px 32px rgba(15,23,42,0.12);
                            font-family: 'Segoe UI', Arial, sans-serif;
                        ">
                            <!-- LGU Letterhead Banner -->
                            <div style="
                                background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%);
                                padding: 18px 24px 16px;
                                display: flex;
                                align-items: center;
                                gap: 16px;
                            ">
                                <div style="width:56px;height:56px;background:rgba(255,255,255,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;border:2px solid rgba(255,255,255,0.3);">
                                    <i class="fas fa-landmark" style="color:#f0c060;font-size:1.4rem;"></i>
                                </div>
                                <div style="flex:1;">
                                    <div style="color:rgba(255,255,255,0.7);font-size:0.72rem;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;">Republic of the Philippines &bull; City Government of Pasig</div>
                                    <div style="color:#fff;font-size:1rem;font-weight:800;line-height:1.2;margin-top:2px;">Office for the Senior Citizens Affairs (OSCA)</div>
                                    <div style="color:#f0c060;font-size:0.8rem;font-weight:700;margin-top:3px;letter-spacing:0.04em;">SENIOR CITIZENS ID APPLICATION FORM</div>
                                </div>
                                <div style="text-align:right;">
                                    <div style="color:rgba(255,255,255,0.5);font-size:0.68rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;">OSCA ID</div>
                                    <div style="color:#fff;font-size:0.78rem;font-weight:700;margin-top:2px;">Date Filed:</div>
                                    <div style="color:#93c5fd;font-size:0.78rem;margin-top:1px;font-weight:600;"><?php echo date('m/d/Y'); ?></div>
                                </div>
                            </div>

                            <!-- Form Body -->
                            <div style="padding:20px 24px;">

                                <!-- Purpose Row + ID Photo -->
                                <div style="display:flex;gap:20px;align-items:flex-start;margin-bottom:18px;">
                                    <!-- Purpose checkboxes -->
                                    <div style="flex:1;">
                                        <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:#475569;margin-bottom:10px;">
                                            <i class="fas fa-bullseye" style="color:#3b82f6;margin-right:5px;"></i> Purpose
                                        </div>
                                        <div style="display:flex;flex-wrap:wrap;gap:8px;">
                                            <?php
                                            $purposes = ['new'=>'New','lost'=>'Lost','change'=>'Change','transfer'=>'Transfer'];
                                            foreach($purposes as $pval=>$plabel): ?>
                                            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;padding:6px 12px;border-radius:8px;border:1.5px solid #d0dae8;background:#f8fafc;font-size:0.82rem;font-weight:600;color:#334155;transition:all 0.15s;" onmouseover="this.style.borderColor='#3b82f6';this.style.background='#eff6ff';" onmouseout="this.style.borderColor='#d0dae8';this.style.background='#f8fafc';">
                                                <input type="radio" name="idPurposeOsca" value="<?php echo $pval; ?>" style="accent-color:#1e3a5f;" <?php echo $pval==='new'?'checked':''; ?>>
                                                <?php echo $plabel; ?>
                                            </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <!-- ID Photo Box -->
                                    <div style="flex-shrink:0;text-align:center;">
                                        <div style="
                                            width:96px;height:112px;border:2px dashed #94a3b8;border-radius:8px;
                                            background:#f1f5f9;display:flex;flex-direction:column;align-items:center;
                                            justify-content:center;gap:6px;cursor:pointer;
                                            transition:border-color 0.2s,background 0.2s;
                                            position:relative;overflow:hidden;
                                        " id="oscaPhotoBox" onclick="document.getElementById('oscaIdPhoto').click();"
                                           onmouseover="this.style.borderColor='#3b82f6';this.style.background='#eff6ff';"
                                           onmouseout="this.style.borderColor='#94a3b8';this.style.background='#f1f5f9';"
                                           title="Click to upload 1x1 ID photo">
                                            <img id="oscaPhotoPreview" src="" alt="" style="display:none;position:absolute;inset:0;width:100%;height:100%;object-fit:cover;border-radius:6px;">
                                            <i class="fas fa-camera" style="color:#94a3b8;font-size:1.4rem;"></i>
                                            <span style="font-size:0.62rem;color:#64748b;font-weight:600;text-align:center;line-height:1.3;">1×1 ID Photo<br>White Background</span>
                                        </div>
                                        <input type="file" id="oscaIdPhoto" name="oscaIdPhoto" accept="image/*" style="display:none;" onchange="previewOscaPhoto(this)">
                                        <div style="font-size:0.6rem;color:#94a3b8;margin-top:4px;">Latest Photo</div>
                                    </div>
                                </div>

                                <!-- Name Row -->
                                <div style="margin-bottom:14px;">
                                    <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:#475569;margin-bottom:8px;border-bottom:1px solid #e2e8f0;padding-bottom:4px;">
                                        <i class="fas fa-user" style="color:#3b82f6;margin-right:5px;"></i> Full Name
                                    </div>
                                    <div style="display:grid;grid-template-columns:2fr 2fr 2fr 1fr;gap:10px;">
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Last Name</label>
                                            <input type="text" id="oscaLastName" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;transition:border-color 0.2s;" oninput="syncField(this,'lastName')" onfocus="this.style.borderColor='#3b82f6';" onblur="this.style.borderColor='#d0dae8';" placeholder="Dela Cruz">
                                        </div>
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">First Name</label>
                                            <input type="text" id="oscaFirstName" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;transition:border-color 0.2s;" oninput="syncField(this,'firstName')" onfocus="this.style.borderColor='#3b82f6';" onblur="this.style.borderColor='#d0dae8';" placeholder="Juan">
                                        </div>
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Middle Name</label>
                                            <input type="text" id="oscaMiddleName" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;transition:border-color 0.2s;" oninput="syncField(this,'middleName')" onfocus="this.style.borderColor='#3b82f6';" onblur="this.style.borderColor='#d0dae8';" placeholder="Santos">
                                        </div>
                                        <div>
                                            <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Ext.</label>
                                            <input type="text" id="oscaSuffix" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;transition:border-color 0.2s;" oninput="syncField(this,'suffix')" onfocus="this.style.borderColor='#3b82f6';" onblur="this.style.borderColor='#d0dae8';" placeholder="Jr.">
                                        </div>
                                    </div>

                                    <!-- The official OSCA ID is issued by the approval workflow, not by the applicant. -->
                                    <div style="margin-top:10px;">
                                        <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">OSCA ID #</label>
                                        <input type="text" id="oscaSeniorId" readonly value="Pending approval" style="width:100%;padding:8px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.9rem;color:#64748b;background:#f8fafc;" aria-describedby="oscaSeniorIdHelp" />
                                        <div id="oscaSeniorIdHelp" style="font-size:0.68rem;color:#64748b;margin-top:4px;">Assigned automatically by OSCA when this Senior Citizens ID application is approved.</div>
                                    </div>
                                </div>

                                <!-- DOB / Age / Place of Birth -->
                                <div style="display:grid;grid-template-columns:1.2fr 0.6fr 1.2fr;gap:10px;margin-bottom:14px;">
                                    <div>
                                        <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Date of Birth</label>
                                        <input type="date" id="oscaBirthDate" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.82rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'birthDate');updateOscaAge();" onfocus="this.style.borderColor='#3b82f6';" onblur="this.style.borderColor='#d0dae8';">
                                    </div>
                                    <div>
                                        <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Age</label>
                                        <input type="text" id="oscaAge" readonly style="width:100%;padding:7px 10px;border:1.5px solid #e2e8f0;border-radius:7px;font-size:0.88rem;color:#1e3a5f;background:#f8fafc;font-weight:700;text-align:center;" placeholder="—">
                                    </div>
                                    <div>
                                        <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Place of Birth</label>
                                        <input type="text" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" onfocus="this.style.borderColor='#3b82f6';" onblur="this.style.borderColor='#d0dae8';" placeholder="Pasig City, MM">
                                    </div>
                                </div>

                                <!-- Gender / Civil Status / Cellphone -->
                                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:14px;">
                                    <div>
                                        <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Gender</label>
                                        <select style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" onchange="syncSelectField(this,'gender')" onfocus="this.style.borderColor='#3b82f6';" onblur="this.style.borderColor='#d0dae8';">
                                            <option value="">Select</option>
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Civil Status</label>
                                        <select style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" onchange="syncSelectField(this,'civilStatus')" onfocus="this.style.borderColor='#3b82f6';" onblur="this.style.borderColor='#d0dae8';">
                                            <option value="">Select</option>
                                            <option value="Single">Single</option>
                                            <option value="Married">Married</option>
                                            <option value="Widow/er">Widow/er</option>
                                            <option value="Separated">Separated</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;">Cellphone No.</label>
                                        <input type="text" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'contactNumber')" onfocus="this.style.borderColor='#3b82f6';" onblur="this.style.borderColor='#d0dae8';" placeholder="09XX-XXX-XXXX">
                                    </div>
                                </div>

                                <!-- Full Address -->
                                <div style="margin-bottom:14px;">
                                    <label style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:#475569;margin-bottom:8px;border-bottom:1px solid #e2e8f0;padding-bottom:4px;display:flex;align-items:center;gap:5px;">
                                        <i class="fas fa-map-marker-alt" style="color:#3b82f6;"></i> Full Address
                                    </label>
                                    <div style="display:grid;grid-template-columns:0.7fr 1fr 0.7fr 0.5fr 0.5fr;gap:8px;margin-bottom:8px;">
                                        <div>
                                            <label style="font-size:0.64rem;color:#94a3b8;display:block;margin-bottom:2px;font-weight:600;">House/Lot/Blk/Bldg. No.</label>
                                            <input type="text" id="seniorHouseNo" style="width:100%;padding:6px 8px;border:1.5px solid #d0dae8;border-radius:6px;font-size:0.82rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'houseNo'); syncAddressFromActiveCard();" onfocus="this.style.borderColor='#3b82f6';" onblur="this.style.borderColor='#d0dae8';" placeholder="e.g. 123 Blk 4">
                                        </div>
                                        <div>
                                            <label style="font-size:0.64rem;color:#94a3b8;display:block;margin-bottom:2px;font-weight:600;">Street/Road/Purok/Subd./Village</label>
                                            <input type="text" id="seniorStreet" style="width:100%;padding:6px 8px;border:1.5px solid #d0dae8;border-radius:6px;font-size:0.82rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'street'); syncAddressFromActiveCard();" onfocus="this.style.borderColor='#3b82f6';" onblur="this.style.borderColor='#d0dae8';" placeholder="e.g. Mabini St.">
                                        </div>
                                        <div>
                                            <label style="font-size:0.64rem;color:#94a3b8;display:block;margin-bottom:2px;font-weight:600;">Barangay</label>
                                            <input type="text" value="<?php echo htmlspecialchars($_SESSION['barangay'] ?? ''); ?>" readonly style="width:100%;padding:6px 8px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:0.82rem;color:#1e3a5f;background:#f8fafc;font-weight:700;">
                                        </div>
                                        <div>
                                            <label style="font-size:0.64rem;color:#94a3b8;display:block;margin-bottom:2px;font-weight:600;">ZIP Code</label>
                                            <input type="text" id="seniorZipCode" style="width:100%;padding:6px 8px;border:1.5px solid #d0dae8;border-radius:6px;font-size:0.82rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'zipCode'); syncAddressFromActiveCard();" onfocus="this.style.borderColor='#3b82f6';" onblur="this.style.borderColor='#d0dae8';" placeholder="1600">
                                        </div>
                                        <div>
                                            <label style="font-size:0.64rem;color:#94a3b8;display:block;margin-bottom:2px;font-weight:600;">Landmark</label>
                                            <input type="text" id="seniorLandmark" style="width:100%;padding:6px 8px;border:1.5px solid #d0dae8;border-radius:6px;font-size:0.82rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'landmark')" onfocus="this.style.borderColor='#3b82f6';" onblur="this.style.borderColor='#d0dae8';" placeholder="Near...">
                                        </div>
                                    </div>
                                </div>

                                <!-- Mother's Maiden Name + Health Status -->
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px;">
                                    <div>
                                        <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:5px;"><i class="fas fa-female" style="color:#ec4899;margin-right:4px;"></i>Mother's Maiden Name</label>
                                        <input type="text" style="width:100%;padding:7px 10px;border:1.5px solid #d0dae8;border-radius:7px;font-size:0.88rem;color:#0f172a;background:#fff;outline:none;" onfocus="this.style.borderColor='#3b82f6';" onblur="this.style.borderColor='#d0dae8';" placeholder="Santos, Maria A.">
                                    </div>
                                    <div>
                                        <label style="font-size:0.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:5px;"><i class="fas fa-heartbeat" style="color:#ef4444;margin-right:4px;"></i>Health Status</label>
                                        <div style="display:flex;gap:8px;flex-wrap:wrap;padding-top:2px;">
                                            <?php
                                            $healthOpts = ['Physically Fit'=>'Physically Fit','Bedridden'=>'Bedridden','Frail/Sickly'=>'Frail/Sickly','PWD'=>'PWD'];
                                            $healthColors = ['Physically Fit'=>'#22c55e','Bedridden'=>'#f59e0b','Frail/Sickly'=>'#f97316','PWD'=>'#6366f1'];
                                            foreach($healthOpts as $hval=>$hlabel): ?>
                                            <label style="display:flex;align-items:center;gap:4px;cursor:pointer;padding:5px 10px;border-radius:7px;border:1.5px solid #d0dae8;background:#f8fafc;font-size:0.78rem;font-weight:600;color:#334155;" onmouseover="this.style.borderColor='<?php echo $healthColors[$hval]; ?>';this.style.background='#f0fdf4';" onmouseout="this.style.borderColor='#d0dae8';this.style.background='#f8fafc';">
                                                <input type="radio" name="oscaHealthStatus" value="<?php echo $hval; ?>" style="accent-color:<?php echo $healthColors[$hval]; ?>;">
                                                <?php echo $hlabel; ?>
                                            </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Requirements Reference Panel -->
                                <div style="background:#f0f7ff;border:1px solid #bfdbfe;border-radius:12px;padding:14px 16px;margin-bottom:16px;">
                                    <div style="font-size:0.72rem;font-weight:800;text-transform:uppercase;letter-spacing:0.07em;color:#1e3a8a;margin-bottom:10px;display:flex;align-items:center;gap:6px;">
                                        <i class="fas fa-clipboard-list" style="color:#2563eb;"></i> Requirements Reference Guide
                                    </div>
                                    <div id="requirementsGuideGrid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:0.78rem;color:#1e3a5f;">
                                        <div>
                                            <div style="font-weight:700;color:#1d4ed8;margin-bottom:5px;display:flex;align-items:center;gap:5px;"><span style="width:20px;height:20px;background:#2563eb;border-radius:4px;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:0.6rem;flex-shrink:0;"><i class="fas fa-id-card"></i></span> New Applicant (Filipino Citizen)</div>
                                            <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:3px;">
                                                <li style="display:flex;gap:5px;align-items:flex-start;"><i class="fas fa-circle" style="color:#3b82f6;font-size:0.4rem;margin-top:5px;flex-shrink:0;"></i><span>2 pcs 1×1 ID Photo (latest, white background)</span></li>
                                                <li style="display:flex;gap:5px;align-items:flex-start;"><i class="fas fa-circle" style="color:#3b82f6;font-size:0.4rem;margin-top:5px;flex-shrink:0;"></i><span>Birth Certificate (Original & Photocopy)</span></li>
                                                <li style="display:flex;gap:5px;align-items:flex-start;"><i class="fas fa-circle" style="color:#3b82f6;font-size:0.4rem;margin-top:5px;flex-shrink:0;"></i><span>Original Barangay Residency Certificate</span></li>
                                                <li style="display:flex;gap:5px;align-items:flex-start;"><i class="fas fa-circle" style="color:#3b82f6;font-size:0.4rem;margin-top:5px;flex-shrink:0;"></i><span>2 valid IDs (with date of birth & Pasig City address)</span></li>
                                            </ul>
                                        </div>
                                        <div style="display:flex;flex-direction:column;gap:8px;">
                                            <div>
                                                <div style="font-weight:700;color:#ca8a04;margin-bottom:4px;display:flex;align-items:center;gap:5px;"><span style="width:20px;height:20px;background:#ca8a04;border-radius:4px;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:0.6rem;flex-shrink:0;"><i class="fas fa-sync"></i></span> Replacement / Lost</div>
                                                <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:2px;">
                                                    <li style="display:flex;gap:5px;align-items:flex-start;"><i class="fas fa-circle" style="color:#ca8a04;font-size:0.4rem;margin-top:5px;flex-shrink:0;"></i><span>2 pcs 1×1 ID Photo (latest)</span></li>
                                                    <li style="display:flex;gap:5px;align-items:flex-start;"><i class="fas fa-circle" style="color:#ca8a04;font-size:0.4rem;margin-top:5px;flex-shrink:0;"></i><span>Original Senior Citizen ID</span></li>
                                                    <li style="display:flex;gap:5px;align-items:flex-start;"><i class="fas fa-circle" style="color:#ef4444;font-size:0.4rem;margin-top:5px;flex-shrink:0;"></i><span><em>Lost only:</em> Original Affidavit of Loss</span></li>
                                                </ul>
                                            </div>
                                            <div>
                                                <div style="font-weight:700;color:#16a34a;margin-bottom:4px;display:flex;align-items:center;gap:5px;"><span style="width:20px;height:20px;background:#16a34a;border-radius:4px;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:0.6rem;flex-shrink:0;"><i class="fas fa-exchange-alt"></i></span> Transfer</div>
                                                <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:2px;">
                                                    <li style="display:flex;gap:5px;align-items:flex-start;"><i class="fas fa-circle" style="color:#16a34a;font-size:0.4rem;margin-top:5px;flex-shrink:0;"></i><span>Certificate of Cancellation of SC ID from previous OSCA</span></li>
                                                    <li style="display:flex;gap:5px;align-items:flex-start;"><i class="fas fa-circle" style="color:#16a34a;font-size:0.4rem;margin-top:5px;flex-shrink:0;"></i><span>Original Barangay Residency Certificate</span></li>
                                                </ul>
                                            </div>
                                            </div>
                                    </div>
                                </div>

                                <!-- Certification / Signature -->
                                <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:12px 14px;margin-bottom:4px;">
                                    <p style="font-size:0.75rem;color:#78350f;line-height:1.6;margin:0 0 10px;">
                                        <i class="fas fa-gavel" style="color:#92400e;margin-right:5px;"></i>
                                        I hereby certify under law on perjury that the information provided in this form is <strong>complete, true, correct, and of my own knowledge</strong>. I further authorize the City Government of Pasig to process my data, validate, and confirm the answers herein with third parties such as the GSIS, SSS, DSWD and other Government/Private Agencies.
                                    </p>
                                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                                        <div>
                                            <label style="font-size:0.67rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;"><i class="fas fa-user-check" style="color:#1e3a5f;margin-right:4px;"></i>Emergency Contact Name</label>
                                            <input type="text" style="width:100%;padding:6px 10px;border:1.5px solid #fde68a;border-radius:7px;font-size:0.85rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'emergencyContactName')" onfocus="this.style.borderColor='#3b82f6';" onblur="this.style.borderColor='#fde68a';" placeholder="Full Name">
                                        </div>
                                        <div>
                                            <label style="font-size:0.67rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;"><i class="fas fa-phone" style="color:#1e3a5f;margin-right:4px;"></i>Emergency Contact No.</label>
                                            <input type="tel" style="width:100%;padding:6px 10px;border:1.5px solid #fde68a;border-radius:7px;font-size:0.85rem;color:#0f172a;background:#fff;outline:none;" oninput="syncField(this,'emergencyContact')" onfocus="this.style.borderColor='#3b82f6';" onblur="this.style.borderColor='#fde68a';" placeholder="09XX-XXX-XXXX">
                                        </div>
                                        <div>
                                            <label style="font-size:0.67rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:3px;"><i class="fas fa-people-arrows" style="color:#1e3a5f;margin-right:4px;"></i>Relationship to Applicant</label>
                                            <input type="text" style="width:100%;padding:6px 10px;border:1.5px solid #fde68a;border-radius:7px;font-size:0.85rem;color:#0f172a;background:#fff;outline:none;" onfocus="this.style.borderColor='#3b82f6';" onblur="this.style.borderColor='#fde68a';" placeholder="e.g. Son, Daughter, Spouse">
                                        </div>
                                    </div>
                                </div>

                                <!-- Required Documents (inside form card) -->
                                <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:14px 16px;margin-top:14px;margin-bottom:4px;">
                                    <div style="font-size:0.72rem;font-weight:800;text-transform:uppercase;letter-spacing:0.07em;color:#166534;margin-bottom:8px;display:flex;align-items:center;gap:6px;">
                                        <i class="fas fa-file-alt" style="color:#16a34a;"></i> Required Documents
                                    </div>
                                    <p style="font-size:0.72rem;color:#4b7c5e;margin-bottom:10px;display:flex;align-items:center;gap:5px;">
                                        <i class="fas fa-info-circle" style="color:#16a34a;"></i>
                                        Accepted formats: JPEG, PNG, PDF &mdash; Max 8MB per file.
                                    </p>
                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                        <div>
                                            <label for="proofOfAddress" id="labelProofOfAddress" style="font-size:0.68rem;font-weight:700;color:#166534;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:5px;">
                                                <i class="fas fa-home" style="margin-right:4px;color:#16a34a;"></i> Original Barangay Residency Certificate
                                            </label>
                                            <input type="file" id="proofOfAddress" name="proofOfAddress" required accept="image/jpeg,image/png,image/gif,application/pdf" onchange="checkFileSize(this)"
                                                style="width:100%;font-size:0.78rem;padding:6px 8px;border:1.5px solid #bbf7d0;border-radius:7px;background:#fff;color:#166534;cursor:pointer;">
                                            <div id="proofOfAddressSizeWarn" style="display:none;color:#e74c3c;font-size:0.72rem;margin-top:3px;">
                                                <i class="fas fa-exclamation-triangle"></i> File is large (&gt;8MB). Consider compressing it first.
                                            </div>
                                        </div>

                                        <div id="validId1Wrap" style="display:none;">
                                            <label for="validId1" id="labelValidId1" style="font-size:0.68rem;font-weight:700;color:#166534;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:5px;">
                                                <i class="fas fa-id-badge" style="margin-right:4px;color:#16a34a;"></i> Valid ID #1 (with DOB & Pasig City address)
                                            </label>
                                            <input type="file" id="validId1" name="validId1" accept="image/jpeg,image/png,image/gif,application/pdf" onchange="checkFileSize(this)"
                                                style="width:100%;font-size:0.78rem;padding:6px 8px;border:1.5px solid #bbf7d0;border-radius:7px;background:#fff;color:#166534;cursor:pointer;">
                                            <div id="validId1SizeWarn" style="display:none;color:#e74c3c;font-size:0.72rem;margin-top:3px;">
                                                <i class="fas fa-exclamation-triangle"></i> File is large (&gt;8MB). Consider compressing it first.
                                            </div>
                                        </div>

                                        <div id="validId2Wrap" style="display:none;">
                                            <label for="validId2" id="labelValidId2" style="font-size:0.68rem;font-weight:700;color:#166534;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:5px;">
                                                <i class="fas fa-id-badge" style="margin-right:4px;color:#16a34a;"></i> Valid ID #2 (with DOB & Pasig City address)
                                            </label>
                                            <input type="file" id="validId2" name="validId2" accept="image/jpeg,image/png,image/gif,application/pdf" onchange="checkFileSize(this)"
                                                style="width:100%;font-size:0.78rem;padding:6px 8px;border:1.5px solid #bbf7d0;border-radius:7px;background:#fff;color:#166534;cursor:pointer;">
                                            <div id="validId2SizeWarn" style="display:none;color:#e74c3c;font-size:0.72rem;margin-top:3px;">
                                                <i class="fas fa-exclamation-triangle"></i> File is large (&gt;8MB). Consider compressing it first.
                                            </div>
                                        </div>

                                        <div id="birthOriginalWrap" style="display:none;">
                                            <label for="birthOriginal" id="labelBirthOriginal" style="font-size:0.68rem;font-weight:700;color:#166534;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:5px;">
                                                <i class="fas fa-file-alt" style="margin-right:4px;color:#16a34a;"></i> Birth Certificate (Original)
                                            </label>
                                            <input type="file" id="birthOriginal" name="birthOriginal" accept="image/jpeg,image/png,image/gif,application/pdf" onchange="checkFileSize(this)"
                                                style="width:100%;font-size:0.78rem;padding:6px 8px;border:1.5px solid #bbf7d0;border-radius:7px;background:#fff;color:#166534;cursor:pointer;">
                                            <div id="birthOriginalSizeWarn" style="display:none;color:#e74c3c;font-size:0.72rem;margin-top:3px;">
                                                <i class="fas fa-exclamation-triangle"></i> File is large (&gt;8MB). Consider compressing it first.
                                            </div>
                                        </div>

                                        <div id="birthPhotocopyWrap" style="display:none;">
                                            <label for="birthPhotocopy" id="labelBirthPhotocopy" style="font-size:0.68rem;font-weight:700;color:#166534;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:5px;">
                                                <i class="fas fa-copy" style="margin-right:4px;color:#16a34a;"></i> Birth Certificate (Photocopy)
                                            </label>
                                            <input type="file" id="birthPhotocopy" name="birthPhotocopy" accept="image/jpeg,image/png,image/gif,application/pdf" onchange="checkFileSize(this)"
                                                style="width:100%;font-size:0.78rem;padding:6px 8px;border:1.5px solid #bbf7d0;border-radius:7px;background:#fff;color:#166534;cursor:pointer;">
                                            <div id="birthPhotocopySizeWarn" style="display:none;color:#e74c3c;font-size:0.72rem;margin-top:3px;">
                                                <i class="fas fa-exclamation-triangle"></i> File is large (&gt;8MB). Consider compressing it first.
                                            </div>
                                        </div>

                                        <div id="originalSeniorIdWrap" style="display:none;">
                                            <label for="originalSeniorId" id="labelOriginalSeniorId" style="font-size:0.68rem;font-weight:700;color:#166534;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:5px;">
                                                <i class="fas fa-id-card" style="margin-right:4px;color:#16a34a;"></i> Original Senior Citizen ID
                                            </label>
                                            <input type="file" id="originalSeniorId" name="originalSeniorId" accept="image/jpeg,image/png,image/gif,application/pdf" onchange="checkFileSize(this)"
                                                style="width:100%;font-size:0.78rem;padding:6px 8px;border:1.5px solid #bbf7d0;border-radius:7px;background:#fff;color:#166534;cursor:pointer;">
                                            <div id="originalSeniorIdSizeWarn" style="display:none;color:#e74c3c;font-size:0.72rem;margin-top:3px;">
                                                <i class="fas fa-exclamation-triangle"></i> File is large (&gt;8MB). Consider compressing it first.
                                            </div>
                                        </div>

                                        <div id="affidavitOfLossWrap" style="display:none;">
                                            <label for="affidavitOfLoss" id="labelAffidavitOfLoss" style="font-size:0.68rem;font-weight:700;color:#166534;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:5px;">
                                                <i class="fas fa-file-signature" style="margin-right:4px;color:#16a34a;"></i> Original Affidavit of Loss
                                            </label>
                                            <input type="file" id="affidavitOfLoss" name="affidavitOfLoss" accept="image/jpeg,image/png,image/gif,application/pdf" onchange="checkFileSize(this)"
                                                style="width:100%;font-size:0.78rem;padding:6px 8px;border:1.5px solid #bbf7d0;border-radius:7px;background:#fff;color:#166534;cursor:pointer;">
                                            <div id="affidavitOfLossSizeWarn" style="display:none;color:#e74c3c;font-size:0.72rem;margin-top:3px;">
                                                <i class="fas fa-exclamation-triangle"></i> File is large (&gt;8MB). Consider compressing it first.
                                            </div>
                                        </div>

                                        <div id="cancellationCertWrap" style="display:none;">
                                            <label for="cancellationCert" id="labelCancellationCert" style="font-size:0.68rem;font-weight:700;color:#166534;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:5px;">
                                                <i class="fas fa-file-alt" style="margin-right:4px;color:#16a34a;"></i> Certificate of Cancellation of SC ID
                                            </label>
                                            <input type="file" id="cancellationCert" name="cancellationCert" accept="image/jpeg,image/png,image/gif,application/pdf" onchange="checkFileSize(this)"
                                                style="width:100%;font-size:0.78rem;padding:6px 8px;border:1.5px solid #bbf7d0;border-radius:7px;background:#fff;color:#166534;cursor:pointer;">
                                            <div id="cancellationCertSizeWarn" style="display:none;color:#e74c3c;font-size:0.72rem;margin-top:3px;">
                                                <i class="fas fa-exclamation-triangle"></i> File is large (&gt;8MB). Consider compressing it first.
                                            </div>
                                        </div>

                                        <div id="oscaAdditionalDocWrap" style="display:none;">
                                            <label for="oscaAdditionalDoc" id="labelAdditionalDoc" style="font-size:0.68rem;font-weight:700;color:#166534;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:5px;">
                                                <i class="fas fa-file" style="margin-right:4px;color:#16a34a;"></i> Additional Document
                                            </label>
                                            <input type="file" id="oscaAdditionalDoc" name="oscaAdditionalDoc" accept="image/jpeg,image/png,image/gif,application/pdf" onchange="checkFileSize(this)"
                                                style="width:100%;font-size:0.78rem;padding:6px 8px;border:1.5px solid #bbf7d0;border-radius:7px;background:#fff;color:#166534;cursor:pointer;">
                                            <div id="oscaAdditionalDocSizeWarn" style="display:none;color:#e74c3c;font-size:0.72rem;margin-top:3px;">
                                                <i class="fas fa-exclamation-triangle"></i> File is large (&gt;8MB). Consider compressing it first.
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <p style="font-size:0.67rem;color:#94a3b8;text-align:center;margin-top:8px;margin-bottom:0;">
                                    <i class="fas fa-info-circle" style="margin-right:4px;"></i>
                                    This official form preview mirrors the physical OSCA ID Application form. All fields entered here are automatically carried over to the main form below.
                                </p>
                            </div><!-- /form body -->
                        </div>
                    </div><!-- /#oscaOfficialFormCard -->
                    </template>

                    <!-- Hidden backing fields — values are synced from the official form preview cards above via syncField() -->
                    <input type="hidden" id="idNumber" name="idNumber" value="<?php echo htmlspecialchars($loadedProxyData['transactionId'] ?? uniqid('APP-')); ?>">
                    <input type="hidden" id="lastName"             name="lastName"             value="<?php echo htmlspecialchars($loadedProxyData['lastName'] ?? ''); ?>">
                    <input type="hidden" id="firstName"            name="firstName"            value="<?php echo htmlspecialchars($loadedProxyData['firstName'] ?? ''); ?>">
                    <input type="hidden" id="middleName"           name="middleName"           value="<?php echo htmlspecialchars($loadedProxyData['middleName'] ?? ''); ?>">
                    <input type="hidden" id="suffix"               name="suffix"               value="<?php echo htmlspecialchars($loadedProxyData['suffix'] ?? ''); ?>">
                    <input type="hidden" id="birthDate"            name="birthDate"            value="<?php echo htmlspecialchars($loadedProxyData['birthDate'] ?? ''); ?>">
                    <input type="hidden" id="contactNumber"        name="contactNumber"        value="<?php echo htmlspecialchars($loadedProxyData['contactNumber'] ?? ''); ?>">
                    <input type="hidden" id="completeAddress"      name="completeAddress"      value="<?php echo htmlspecialchars($loadedProxyData['completeAddress'] ?? ''); ?>">
                    <input type="hidden" id="houseNo"              name="houseNo"              value="">
                    <input type="hidden" id="street"               name="street"               value="">
                    <input type="hidden" id="city"                 name="city"                 value="Pasig City">
                    <input type="hidden" id="province"             name="province"             value="Metro Manila">
                    <input type="hidden" id="zipCode"              name="zipCode"              value="">
                    <input type="hidden" id="landmark"             name="landmark"             value="">
                    <input type="hidden" id="seniorIdNo"           name="seniorIdNo"           value="<?php echo htmlspecialchars($loadedProxyData['seniorIdNo'] ?? ''); ?>">
                    <input type="hidden" id="emergencyContactName" name="emergencyContactName" value="">
                    <input type="hidden" id="emergencyContact"     name="emergencyContact"     value="">
                    <!-- Pension/Burial hidden fields — synced from official form preview cards -->
                    <input type="hidden" id="sssNumber"              name="sssNumber"              value="<?php echo htmlspecialchars($loadedProxyData['sssNumber'] ?? ''); ?>">
                    <input type="hidden" id="pensionAmount"          name="pensionAmount"          value="<?php echo htmlspecialchars($loadedProxyData['pensionAmount'] ?? ''); ?>">
                    <input type="hidden" id="dateOfDeath"            name="dateOfDeath"            value="<?php echo htmlspecialchars($loadedProxyData['dateOfDeath'] ?? ''); ?>">
                    <input type="hidden" id="relationshipToDeceased" name="relationshipToDeceased" value="<?php echo htmlspecialchars($loadedProxyData['relationshipToDeceased'] ?? ''); ?>">
                    <div id="ageComplianceResult" style="display:none;"></div>
                    <div id="burialComplianceResult" style="display:none;"></div>


                    <!-- Proxy Representative Information (Visual validation block) -->
                    <div id="proxy-details-section" style="<?php echo $loadedProxyData ? 'display: block;' : 'display: none;'; ?>">
                        <div class="form-section" style="border: 1px dashed #2980b9; padding: 15px; border-radius: 8px; background-color: rgba(41, 128, 185, 0.05);">
                            <h3 style="color: #2980b9;"><i class="fas fa-id-card-alt"></i> Representative Registration Active (High-Priority Line)</h3>
                            <div class="form-row" style="grid-template-columns: 1fr 1fr 1fr;">
                                <div class="form-group">
                                    <label for="proxyName">Representative Name</label>
                                    <input type="text" id="proxyName" name="proxyName" value="<?php echo htmlspecialchars($loadedProxyData['proxyName'] ?? ''); ?>" readonly>
                                </div>
                                <div class="form-group">
                                    <label for="proxyRelationship">Representative Relationship</label>
                                    <input type="text" id="proxyRelationship" name="proxyRelationship" value="<?php echo htmlspecialchars($loadedProxyData['proxyRelationship'] ?? ''); ?>" readonly>
                                </div>
                                <div class="form-group">
                                    <label for="proxyDisplayContact">Representative Contact Number</label>
                                    <input type="text" id="proxyDisplayContact" value="<?php echo htmlspecialchars($loadedProxyData['proxyContactNumber'] ?? ''); ?>" readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-form-back" onclick="goBackFromApplication()">
                            <i class="fas fa-arrow-left"></i> Back
                        </button>
                        <button type="submit" class="btn" style="background-color: var(--primary);"><i class="fas fa-save"></i> Submit Application</button>
                    </div>
                </form>
                </div><!-- /#formBody -->
            </div>
        </div>
    </div>

    <!-- Benefit Details Modal -->
    <div id="benefitModal" class="modal" role="dialog" aria-labelledby="benefitModalTitle" aria-modal="true" aria-hidden="true">
        <div class="modal-content">
            <button type="button" class="close" onclick="closeBenefitModal()" aria-label="Close benefit details">&times;</button>
            <div class="benefit-modal-header">
                <div class="benefit-modal-icon" id="benefitModalIcon"></div>
                <div>
                    <h2 id="benefitModalTitle"></h2>
                    <p id="benefitModalSummary"></p>
                </div>
            </div>
            <div class="benefit-modal-body">
                <div class="benefit-detail-block benefit-detail-block--benefits">
                    <h3 class="benefit-detail-label"><i class="fas fa-star"></i> What You Get</h3>
                    <ul class="benefit-detail-list" id="benefitModalBenefits"></ul>
                </div>
                <div class="benefit-detail-block benefit-detail-block--requirements">
                    <h3 class="benefit-detail-label"><i class="fas fa-clipboard-check"></i> Requirements to Apply</h3>
                    <ul class="benefit-detail-list" id="benefitModalRequirements"></ul>
                </div>
                <div class="benefit-detail-block benefit-detail-block--documents">
                    <h3 class="benefit-detail-label"><i class="fas fa-folder-open"></i> Documents Needed</h3>
                    <ul class="benefit-detail-list" id="benefitModalDocuments"></ul>
                </div>
                <label class="benefit-ack-row">
                    <input type="checkbox" id="benefitAckCheckbox" onchange="toggleProceedButton()">
                    <span>I have read and understand the benefit details and requirements above. I confirm that I have the necessary documents ready to proceed.</span>
                </label>
            </div>
            <div class="benefit-modal-actions">
                <button type="button" class="btn-benefit-cancel" onclick="closeBenefitModal()">Cancel</button>
                <button type="button" class="btn-benefit-proceed" id="btnProceedApplication" onclick="proceedWithApplication()" disabled>
                    <i class="fas fa-arrow-right"></i> Proceed with Application
                </button>
            </div>
        </div>
    </div>

    <!-- Scan Representative QR Modal -->
    <div id="proxyModal" class="modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="proxyModalTitle">
        <div class="modal-content">
            <button type="button" class="close" onclick="closeProxyModal()" aria-label="Close QR scanner dialog">&times;</button>
            <h2 id="proxyModalTitle" style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px; color: #3498db;"><i class="fas fa-qrcode"></i> Scan Representative QR Token</h2>
            <p style="margin-bottom: 15px; font-size: 0.9rem; color: #94a3b8;">
                Paste or scan the encrypted representative QR token URL/payload generated by the relatives portal to retrieve details and place the senior in the High-Priority Queue.
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

    <script src="../assets/js/sidebar-toggle.js?v=3"></script>
    <script src="../assets/js/osca-form-fields.js"></script>
    <script>
        /* ─── Greeting ──────────────────────────────────────────── */
        (function(){
            const el = document.getElementById('greetingMsg');
            if (!el) return;
            const h = new Date().getHours();
            const g = h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening';
            el.innerHTML = `${g}, <strong><?php echo htmlspecialchars($sessionDisplayName); ?></strong>!`;
        })();

        const BENEFIT_DETAILS = <?php echo json_encode($benefitDetails, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        const TYPE_META = {};
        const OFFICIAL_FORM_CARDS = Object.freeze({
            senior: 'oscaOfficialFormCard',
            burial: 'burialOfficialFormCard',
            home_visit: 'homeVisitOfficialFormCard',
            milestone_gift: 'milestoneOfficialFormCard',
            pension: 'pensionOfficialFormCard',
            national_pension: 'pensionOfficialFormCard',
            landbank: 'landbankOfficialFormCard'
        });
        document.querySelectorAll('#appTypeGrid .app-type-card').forEach(card => {
            TYPE_META[card.dataset.value] = {
                label:  card.dataset.label,
                icon:   card.dataset.icon,
                color:  card.dataset.color,
                bg:     card.dataset.bg,
                accent: card.dataset.accent,
                code:   card.dataset.code,
            };
        });
        let pendingBenefitType = '';

        const DOCUMENT_PREVIEW_SKIP_IDS = new Set(['lbIdPhoto', 'oscaIdPhoto']);

        function formatDocumentFileSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        }

        function createDocumentPreviewHolder(input) {
            if (!input || DOCUMENT_PREVIEW_SKIP_IDS.has(input.id) || input.dataset.previewReady === '1') return null;

            const holder = document.createElement('div');
            holder.className = 'document-upload-preview';
            holder.dataset.previewFor = input.id || input.name;
            holder.setAttribute('aria-live', 'polite');
            input.insertAdjacentElement('afterend', holder);
            input.dataset.previewReady = '1';
            renderDocumentUploadPreview(input);
            return holder;
        }

        function renderDocumentUploadPreview(input) {
            const holder = input.nextElementSibling?.classList.contains('document-upload-preview')
                ? input.nextElementSibling
                : createDocumentPreviewHolder(input);
            if (!holder) return;

            if (input._documentPreviewUrl) {
                URL.revokeObjectURL(input._documentPreviewUrl);
                input._documentPreviewUrl = '';
            }

            holder.replaceChildren();
            holder.classList.remove('has-file');
            const file = input.files && input.files[0];

            const visual = document.createElement('div');
            visual.className = 'document-preview-visual';
            const details = document.createElement('div');
            details.className = 'document-preview-details';

            if (!file) {
                visual.innerHTML = '<i class="fas fa-image" aria-hidden="true"></i>';
                details.innerHTML = '<div class="document-preview-title">Document preview</div><div class="document-preview-meta">The selected image or PDF will appear here.</div>';
                holder.append(visual, details);
                return;
            }

            const objectUrl = URL.createObjectURL(file);
            input._documentPreviewUrl = objectUrl;
            holder.classList.add('has-file');

            if (file.type.startsWith('image/')) {
                const image = document.createElement('img');
                image.src = objectUrl;
                image.alt = 'Preview of ' + file.name;
                visual.appendChild(image);
            } else {
                visual.innerHTML = '<i class="fas fa-file-pdf" aria-hidden="true"></i>';
            }

            const title = document.createElement('div');
            title.className = 'document-preview-title';
            title.textContent = file.type.startsWith('image/') ? 'Image ready to upload' : 'PDF ready to upload';
            const fileName = document.createElement('div');
            fileName.className = 'document-preview-file-name';
            fileName.title = file.name;
            fileName.textContent = file.name;
            const meta = document.createElement('div');
            meta.className = 'document-preview-meta';
            meta.textContent = formatDocumentFileSize(file.size);
            const actions = document.createElement('div');
            actions.className = 'document-preview-actions';

            const viewLink = document.createElement('a');
            viewLink.className = 'document-preview-action';
            viewLink.href = objectUrl;
            viewLink.target = '_blank';
            viewLink.rel = 'noopener';
            viewLink.innerHTML = '<i class="fas fa-up-right-from-square" aria-hidden="true"></i> View';

            const clearButton = document.createElement('button');
            clearButton.type = 'button';
            clearButton.className = 'document-preview-action';
            clearButton.innerHTML = '<i class="fas fa-xmark" aria-hidden="true"></i> Remove';
            clearButton.addEventListener('click', function() {
                input.value = '';
                checkFileSize(input);
                renderDocumentUploadPreview(input);
            });

            actions.append(viewLink, clearButton);
            details.append(title, fileName, meta, actions);
            holder.append(visual, details);
        }

        function initializeDocumentUploadPreviews(root = document) {
            root.querySelectorAll('input[type="file"]').forEach(createDocumentPreviewHolder);
        }

        document.addEventListener('change', function(event) {
            const input = event.target;
            if (input instanceof HTMLInputElement
                && input.type === 'file'
                && !DOCUMENT_PREVIEW_SKIP_IDS.has(input.id)) {
                renderDocumentUploadPreview(input);
            }
        });

        document.getElementById('mainAppForm').addEventListener('reset', function() {
            window.setTimeout(() => initializeDocumentUploadPreviews(this), 0);
            window.setTimeout(() => {
                this.querySelectorAll('input[type="file"]').forEach(input => {
                    if (!DOCUMENT_PREVIEW_SKIP_IDS.has(input.id)) renderDocumentUploadPreview(input);
                });
            }, 0);
        });

        window.addEventListener('beforeunload', function() {
            document.querySelectorAll('input[type="file"]').forEach(input => {
                if (input._documentPreviewUrl) URL.revokeObjectURL(input._documentPreviewUrl);
            });
        });

        function ensureOfficialFormCard(type) {
            const cardId = OFFICIAL_FORM_CARDS[type];
            if (!cardId) return null;

            const existingCard = document.getElementById(cardId);
            if (existingCard) {
                initializeDocumentUploadPreviews(existingCard);
                return existingCard;
            }

            const template = document.getElementById(cardId + 'Template');
            if (!template) return null;

            template.parentNode.insertBefore(template.content.cloneNode(true), template);
            const card = document.getElementById(cardId);
            if (card) initializeDocumentUploadPreviews(card);
            return card;
        }

        function optimizeOfficialFormSections(card) {
            if (!card || card.dataset.layoutOptimized === '1') return;

            const cardShell = card.firstElementChild;
            const shellSections = cardShell ? Array.from(cardShell.children) : [];
            const formBody = shellSections.reduce((largest, section) => {
                if (!largest) return section;
                return section.querySelectorAll('*').length > largest.querySelectorAll('*').length
                    ? section
                    : largest;
            }, null);
            if (!formBody) return;

            // Measure once before applying containment so every deferred
            // section preserves its exact initial space without layout jumps.
            const sections = Array.from(formBody.children);
            const sectionHeights = sections.map(section =>
                Math.ceil(section.getBoundingClientRect().height)
            );

            sections.forEach((section, index) => {
                const height = sectionHeights[index];
                if (height > 0) {
                    section.style.setProperty('--section-intrinsic-height', height + 'px');
                    section.classList.add('deferred-form-section');
                }
            });
            card.dataset.layoutOptimized = '1';
        }

        function openBenefitModal(value) {
            pendingBenefitType = value;
            const meta = TYPE_META[value] || {};
            const details = BENEFIT_DETAILS[value] || {};

            document.getElementById('benefitModalTitle').textContent = meta.label || value;
            document.getElementById('benefitModalSummary').textContent = details.summary || '';
            document.getElementById('benefitModalIcon').innerHTML = `<i class="${meta.icon || 'fas fa-file'}"></i>`;
            document.getElementById('benefitModalIcon').style.background = meta.bg || 'linear-gradient(135deg,#3b82f6,#2563eb)';
            document.querySelector('#benefitModal .modal-content').style.setProperty('--benefit-accent', meta.accent || '#3b82f6');

            const fillList = (id, items) => {
                const el = document.getElementById(id);
                el.innerHTML = '';
                (items || []).forEach(text => {
                    const li = document.createElement('li');
                    li.textContent = text;
                    el.appendChild(li);
                });
            };
            fillList('benefitModalBenefits', details.benefits);
            fillList('benefitModalRequirements', details.requirements);
            fillList('benefitModalDocuments', details.documents);

            const ack = document.getElementById('benefitAckCheckbox');
            ack.checked = false;
            toggleProceedButton();

            const modal = document.getElementById('benefitModal');
            modal._returnFocus = document.activeElement;
            modal.style.display = 'flex';
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            modal.querySelector('.close')?.focus();
        }

        function closeBenefitModal() {
            const modal = document.getElementById('benefitModal');
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            pendingBenefitType = '';
            modal._returnFocus?.focus();
        }

        function toggleProceedButton() {
            const ack = document.getElementById('benefitAckCheckbox').checked;
            document.getElementById('btnProceedApplication').disabled = !ack;
        }

        function proceedWithApplication() {
            if (!pendingBenefitType || !document.getElementById('benefitAckCheckbox').checked) return;
            const type = pendingBenefitType;
            closeBenefitModal();
            selectAppType(type);
        }

        let proxyScannerAssetsPromise = null;

        function loadProxyScannerAssets() {
            if (window.Html5Qrcode && document.getElementById('simpleCodeScannerReader')) {
                return Promise.resolve();
            }
            if (proxyScannerAssetsPromise) return proxyScannerAssetsPromise;

            const loadScript = (src) => new Promise((resolve, reject) => {
                const existing = document.querySelector(`script[data-lazy-src="${src}"]`);
                if (existing) {
                    if (existing.dataset.loaded === '1') resolve();
                    else {
                        existing.addEventListener('load', resolve, { once: true });
                        existing.addEventListener('error', reject, { once: true });
                    }
                    return;
                }

                const script = document.createElement('script');
                script.src = src;
                script.dataset.lazySrc = src;
                script.addEventListener('load', () => {
                    script.dataset.loaded = '1';
                    resolve();
                }, { once: true });
                script.addEventListener('error', reject, { once: true });
                document.body.appendChild(script);
            });

            proxyScannerAssetsPromise = loadScript('../assets/js/vendor/html5-qrcode.min.js?v=2.3.8')
                .then(() => loadScript('../assets/js/simple-code-scanner.js?v=3'));
            return proxyScannerAssetsPromise;
        }

        function openProxyModal() {
            const modal = document.getElementById('proxyModal');
            modal._returnFocus = document.activeElement;
            modal.style.display = 'flex';
            modal.setAttribute('aria-hidden', 'false');
            document.getElementById('modalError').textContent = "";
            document.getElementById('modalToken').value = "";
            document.getElementById('modalToken').focus();
            loadProxyScannerAssets().catch(() => {
                document.getElementById('modalError').textContent =
                    'The camera scanner could not load. You can still paste the QR token manually.';
            });
        }

        function setCardSectionInputsState(cardId, enabled) {
            const card = document.getElementById(cardId);
            if (!card) return;
            card.querySelectorAll('input, select, textarea').forEach(el => {
                if (el.type === 'button' || el.type === 'submit' || el.type === 'reset' || el.type === 'image') return;
                if (!enabled) {
                    if (el.required) {
                        el.dataset.wasRequired = '1';
                    }
                    el.required = false;
                    el.disabled = true;
                } else {
                    if (el.dataset.wasRequired === '1') {
                        el.required = true;
                        delete el.dataset.wasRequired;
                    }
                    el.disabled = false;
                }
            });
        }

        function closeProxyModal() {
            const modal = document.getElementById('proxyModal');
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
            modal._returnFocus?.focus();
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const proxyModal = document.getElementById('proxyModal');
            const benefitModal = document.getElementById('benefitModal');
            if (event.target === proxyModal) {
                proxyModal.style.display = 'none';
            }
            if (event.target === benefitModal) {
                closeBenefitModal();
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
                    document.getElementById('seniorIdNo').value = data.seniorIdNo || '';
                    
                    // Application type
                    const loadedType = data.applicationType || 'senior';
                    selectAppType(loadedType);

                    // Proxy fields
                    document.getElementById('isProxy').value = 1;
                    document.getElementById('proxyToken').value = data.transactionId || '';
                    document.getElementById('proxyName').value = data.proxyName || '';
                    document.getElementById('proxyRelationship').value = data.proxyRelationship || '';
                    document.getElementById('proxyContactNumber').value = data.proxyContactNumber || '';
                    document.getElementById('proxyDisplayContact').value = data.proxyContactNumber || '';
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

                    // Refresh validation after the selected form is available.
                    checkAgeCompliance();
                    
                    if (data.applicationType === 'burial') {
                        checkBurialDeadlineCompliance();
                    }

                    closeProxyModal();
                    window.showCarelinkResult("Representative details loaded! Added senior to the HIGH PRIORITY queue.", true);
                } else {
                    document.getElementById('modalError').textContent = result.message || 'Decryption failed.';
                }
            } catch (error) {
                console.error("Failed to fetch proxy QR details:", error);
                document.getElementById('modalError').textContent = "Server communication failure.";
            }
        }

        function selectAppType(value) {
            // Update hidden input
            document.getElementById('applicationType').value = value;
            ensureOfficialFormCard(value);

            // Highlight selected card + aria
            document.querySelectorAll('#appTypeGrid .app-type-card').forEach(card => {
                const sel = card.dataset.value === value;
                card.classList.toggle('selected', sel);
                card.setAttribute('aria-pressed', sel ? 'true' : 'false');
            });

            // Show banner with accent-matched border
            const meta   = TYPE_META[value] || {};
            const banner = document.getElementById('selectedTypeBanner');
            document.getElementById('bannerLabel').textContent = meta.label || value;
            document.getElementById('bannerTypeContext').textContent = 'Selected Application Form';
            const bannerIcon = document.getElementById('bannerIcon');
            bannerIcon.innerHTML = `<i class="${meta.icon || 'fas fa-file'}" style="font-size:1.1rem;"></i>`;
            bannerIcon.style.background = meta.bg    || '';
            bannerIcon.style.color      = meta.color || '';
            if (meta.accent) {
                banner.style.borderColor = meta.accent + '80'; // 50% opacity
            }
            banner.style.display = 'flex';

            // Hide card selector and reveal application form
            document.getElementById('cardSelectorSection').classList.add('hidden');
            const formBody = document.getElementById('formBody');
            formBody.classList.add('visible');

            // Reveal immediately; animating a very tall form makes the page feel delayed.
            formBody.scrollIntoView({ behavior: 'auto', block: 'start' });

            const visitSchedule = document.getElementById('pensionHomeVisitSchedule');
            const visitScheduleInput = document.getElementById('homeVisitScheduledAt');
            if (visitSchedule && visitScheduleInput) {
                const isLocalPension = value === 'pension';
                visitSchedule.style.display = isLocalPension ? 'block' : 'none';
                visitScheduleInput.disabled = !isLocalPension;
                document.querySelectorAll('input[name="homeVisitEligibility"]').forEach((radio) => {
                    radio.disabled = !isLocalPension;
                    radio.required = isLocalPension;
                    if (!isLocalPension) radio.checked = false;
                });
                if (isLocalPension) {
                    const earliest = new Date();
                    earliest.setMinutes(earliest.getMinutes() < 30 ? 30 : 60, 0, 0);
                    const localIso = new Date(earliest.getTime() - earliest.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
                    visitScheduleInput.min = localIso;
                }
                toggleHomeVisitEligibility();
            }

            // Update pension form title for national pension
            const penTitle = document.getElementById('pensionFormTitle');
            if (penTitle) penTitle.textContent = (value === 'national_pension') ? 'NATIONAL DSWD SOCIAL PENSION FORM' : 'LOCAL SENIOR PENSION FORM';
            if (value === 'senior')         setTimeout(mirrorMainFieldsToOsca, 50);
            if (value === 'burial')         setTimeout(mirrorMainFieldsToBurial, 50);
            if (value === 'home_visit')     setTimeout(mirrorMainFieldsToHV, 50);
            if (value === 'milestone_gift') setTimeout(mirrorMainFieldsToMs, 50);
            if (value === 'pension' || value === 'national_pension') setTimeout(mirrorMainFieldsToPen, 50);
            if (value === 'landbank')       setTimeout(mirrorMainFieldsToLb, 50);

            // Trigger dependent logic
            toggleFields();
            checkAgeCompliance();

            // Update requirements guide and file inputs based on purpose if OSCA senior form is visible
            try {
                const purposeRadios = document.getElementsByName('idPurposeOsca');
                let selPurpose = null;
                for (const r of purposeRadios) if (r.checked) selPurpose = r.value;
                updateRequirementsByPurpose(selPurpose || 'new');
            } catch (e) { /* ignore if not present */ }
        }

        function toggleHomeVisitEligibility() {
            const selected = document.querySelector('input[name="homeVisitEligibility"]:checked');
            const assignmentFields = document.getElementById('homeVisitAssignmentFields');
            const scheduleInput = document.getElementById('homeVisitScheduledAt');
            const personnelInput = document.getElementById('homeVisitPersonnelId');
            const reasonInput = document.getElementById('homeVisitEligibilityReason');
            const eligible = selected && selected.value === 'eligible';

            const help = document.getElementById('homeVisitScheduleHelp');
            if (assignmentFields) assignmentFields.style.opacity = eligible ? '1' : '0.55';
            if (help) help.style.display = eligible ? 'none' : 'block';
            if (scheduleInput) {
                scheduleInput.required = !!eligible;
                scheduleInput.disabled = !eligible;
                if (!eligible) scheduleInput.value = '';
            }
            if (personnelInput) {
                personnelInput.required = !!eligible;
                personnelInput.disabled = !eligible;
                if (!eligible) personnelInput.value = '';
            }
            if (reasonInput) reasonInput.required = !!eligible;
        }

        // Update the Requirements Reference Guide and toggle required file inputs based on OSCA purpose
        function updateRequirementsByPurpose(purpose) {
            const grid = document.getElementById('requirementsGuideGrid');
            if (!grid) return;

            // references to inputs and wrappers
            const proof = document.getElementById('proofOfAddress');
            const valid1Wrap = document.getElementById('validId1Wrap');
            const valid2Wrap = document.getElementById('validId2Wrap');
            const birthOriginalWrap = document.getElementById('birthOriginalWrap');
            const birthPhotocopyWrap = document.getElementById('birthPhotocopyWrap');
            const originalSeniorIdWrap = document.getElementById('originalSeniorIdWrap');
            const affidavitOfLossWrap = document.getElementById('affidavitOfLossWrap');
            const cancellationCertWrap = document.getElementById('cancellationCertWrap');
            const oscaAddWrap = document.getElementById('oscaAdditionalDocWrap');

            // helper to hide and clear required
            function hide(id) { const e = document.getElementById(id); if (e) { e.style.display = 'none'; const inp = e.querySelector('input[type=file]'); if (inp) inp.required = false; } }
            function show(id, req) { const e = document.getElementById(id); if (e) { e.style.display = 'block'; const inp = e.querySelector('input[type=file]'); if (inp) inp.required = !!req; } }

            // hide all purpose-specific wrappers by default
            ['validId1Wrap','validId2Wrap','birthOriginalWrap','birthPhotocopyWrap','originalSeniorIdWrap','affidavitOfLossWrap','cancellationCertWrap','oscaAdditionalDocWrap'].forEach(hide);

            // reset proof and any common inputs
            if (proof) proof.required = false;
            const idimg = document.getElementById('idImage'); if (idimg) idimg.required = false;
            const photo = document.getElementById('oscaIdPhoto'); if (photo) photo.required = false;

            if (purpose === 'new' || purpose === 'change') {
                // show guide text (reuse existing new applicant block)
                grid.innerHTML = `
                    <div>
                        <div style="font-weight:700;color:#1d4ed8;margin-bottom:5px;display:flex;align-items:center;gap:5px;"><span style="width:20px;height:20px;background:#2563eb;border-radius:4px;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:0.6rem;flex-shrink:0;"><i class="fas fa-id-card"></i></span> New Applicant (Filipino Citizen)</div>
                        <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:3px;">
                            <li style="display:flex;gap:5px;align-items:flex-start;"><i class="fas fa-circle" style="color:#3b82f6;font-size:0.4rem;margin-top:5px;flex-shrink:0;"></i><span>Birth Certificate (Original & Photocopy)</span></li>
                            <li style="display:flex;gap:5px;align-items:flex-start;"><i class="fas fa-circle" style="color:#3b82f6;font-size:0.4rem;margin-top:5px;flex-shrink:0;"></i><span>Original Barangay Residency Certificate</span></li>
                            <li style="display:flex;gap:5px;align-items:flex-start;"><i class="fas fa-circle" style="color:#3b82f6;font-size:0.4rem;margin-top:5px;flex-shrink:0;"></i><span>2 valid IDs (with date of birth & Pasig City address)</span></li>
                        </ul>
                    </div>
                `;

                // Require birth certificate original + photocopy, barangay cert, two valid IDs
                if (proof) proof.required = true;
                show('birthOriginalWrap', true);
                show('birthPhotocopyWrap', true);
                show('validId1Wrap', true);
                show('validId2Wrap', true);

            } else if (purpose === 'lost') {
                grid.innerHTML = `
                    <div>
                        <div style="font-weight:700;color:#ca8a04;margin-bottom:4px;display:flex;align-items:center;gap:5px;"><span style="width:20px;height:20px;background:#ca8a04;border-radius:4px;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:0.6rem;flex-shrink:0;"><i class="fas fa-sync"></i></span> Replacement / Lost</div>
                        <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:2px;">
                            <li style="display:flex;gap:5px;align-items:flex-start;"><i class="fas fa-circle" style="color:#ca8a04;font-size:0.4rem;margin-top:5px;flex-shrink:0;"></i><span>Original Senior Citizen ID</span></li>
                            <li style="display:flex;gap:5px;align-items:flex-start;"><i class="fas fa-circle" style="color:#ef4444;font-size:0.4rem;margin-top:5px;flex-shrink:0;"></i><span><em>Lost only:</em> Original Affidavit of Loss</span></li>
                        </ul>
                    </div>
                `;

                show('originalSeniorIdWrap', true);
                show('affidavitOfLossWrap', true);

            } else if (purpose === 'transfer') {
                grid.innerHTML = `
                    <div>
                        <div style="font-weight:700;color:#16a34a;margin-bottom:4px;display:flex;align-items:center;gap:5px;"><span style="width:20px;height:20px;background:#16a34a;border-radius:4px;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:0.6rem;flex-shrink:0;"><i class="fas fa-exchange-alt"></i></span> Transfer</div>
                        <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:2px;">
                            <li style="display:flex;gap:5px;align-items:flex-start;"><i class="fas fa-circle" style="color:#16a34a;font-size:0.4rem;margin-top:5px;flex-shrink:0;"></i><span>Certificate of Cancellation of SC ID from previous OSCA</span></li>
                            <li style="display:flex;gap:5px;align-items:flex-start;"><i class="fas fa-circle" style="color:#16a34a;font-size:0.4rem;margin-top:5px;flex-shrink:0;"></i><span>Original Barangay Residency Certificate</span></li>
                        </ul>
                    </div>
                `;

                if (proof) proof.required = true;
                show('cancellationCertWrap', true);

            } else {
                // fallback to new
                updateRequirementsByPurpose('new');
            }
        }

        // Listen to purpose radio changes and update requirements
        document.addEventListener('change', function(e) {
            if (e.target && e.target.name === 'idPurposeOsca') {
                updateRequirementsByPurpose(e.target.value);
            }
        });

        // Initialize requirements guide on load based on selected purpose
        document.addEventListener('DOMContentLoaded', function() {
            const sel = document.querySelector('input[name="idPurposeOsca"]:checked');
            if (sel) updateRequirementsByPurpose(sel.value);
        });

        function goBackFromApplication() {
            document.getElementById('formBody').classList.remove('visible');
            document.getElementById('selectedTypeBanner').style.display = 'none';
            document.getElementById('cardSelectorSection').classList.remove('hidden');
            document.getElementById('cardSelectorSection').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function toggleFields() {
            const type = document.getElementById('applicationType').value;
            ensureOfficialFormCard(type);

            document.getElementById('sssNumber')?.removeAttribute('required');
            document.getElementById('dateOfDeath')?.removeAttribute('required');
            document.getElementById('relationshipToDeceased')?.removeAttribute('required');

            if (type === 'burial') {
                document.getElementById('dateOfDeath')?.setAttribute('required', 'required');
                document.getElementById('relationshipToDeceased')?.setAttribute('required', 'required');
            }

            new Set(Object.values(OFFICIAL_FORM_CARDS)).forEach(id => {
                const card = document.getElementById(id);
                if (card) card.style.display = 'none';
            });
            const activeCard = OFFICIAL_FORM_CARDS[type];
            if (activeCard) {
                const card = document.getElementById(activeCard);
                if (card) {
                    card.style.display = 'block';
                    optimizeOfficialFormSections(card);
                }
            }

            setCardSectionInputsState('oscaOfficialFormCard', type === 'senior');
            setCardSectionInputsState('burialOfficialFormCard', type === 'burial');
            setCardSectionInputsState('homeVisitOfficialFormCard', type === 'home_visit');
            setCardSectionInputsState('milestoneOfficialFormCard', type === 'milestone_gift');
            setCardSectionInputsState('pensionOfficialFormCard', type === 'pension' || type === 'national_pension');
            setCardSectionInputsState('landbankOfficialFormCard', type === 'landbank');

            toggleOscaFormFields(type, '');
            checkAgeCompliance();
        }

        /* ── OSCA Official Form Card Helpers ─────────────────────────── */

        // Mirror main form fields into Landbank Cash Card preview card
        function mirrorMainFieldsToLb() {
            const map = {
                'lastName':          'lbLastName',
                'firstName':         'lbFirstName',
                'middleName':        'lbMiddleName',
                'suffix':            'lbSuffix',
                'birthDate':         'lbBirthDate',
                'contactNumber':     'lbContactNumber',
                'completeAddress':   'lbCompleteAddress',
                'zipCode':           'lbZipCode',
                'placeOfBirth':      'lbPlaceOfBirth',
                'mothersMaidenName': 'lbMothersMaidenName',
                'nationality':       'lbNationality',
                'idTypePresented':   'lbIdTypePresented',
                'tin':               'lbTin',
                'sourceOfFunds':     'lbSourceOfFunds',
                'seniorIdNo':        'lbSeniorIdNo',
                'nameOnCard':        'lbNameOnCard'
            };
            for (const [mainId, lbId] of Object.entries(map)) {
                const mainEl = document.getElementById(mainId);
                const lbEl = document.getElementById(lbId);
                if (mainEl && lbEl && mainEl.value !== undefined) {
                    lbEl.value = mainEl.value;
                }
            }
            // Fallback for Control No display
            const idNum = document.getElementById('idNumber');
            const controlNoDisp = document.getElementById('lbControlNoDisplay');
            if (idNum && controlNoDisp) {
                controlNoDisp.textContent = idNum.value || 'PENDING';
            }
            updateLbAge();
        }

        // Calculate and display age in the Landbank Age field from the Landbank birth date
        function updateLbAge() {
            const dob = document.getElementById('lbBirthDate');
            const ageEl = document.getElementById('lbAge');
            if (!dob || !ageEl || !dob.value) { if (ageEl) ageEl.value = ''; return; }
            const today = new Date();
            const birth = new Date(dob.value);
            let age = today.getFullYear() - birth.getFullYear();
            const m = today.getMonth() - birth.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
            ageEl.value = age >= 0 ? age : '';
        }

        // Preview uploaded Landbank photo in the photo box and mirror to main form
        function previewLbPhoto(input) {
            const preview = document.getElementById('lbPhotoPreview');
            const icon = document.querySelector('#lbPhotoBox .fa-camera');
            const label = document.querySelector('#lbPhotoBox span');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (preview) {
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                        if (icon) icon.style.display = 'none';
                        if (label) label.style.display = 'none';
                    }
                };
                reader.readAsDataURL(input.files[0]);

                // Mirror the file to the main form idImage file input
                const mainFileInput = document.getElementById('idImage');
                if (mainFileInput) {
                    mainFileInput.files = input.files;
                    checkFileSize(mainFileInput);
                }
            }
        }

        // Mirror main form fields into OSCA Senior ID card display fields
        function mirrorMainFieldsToOsca() {
            const map = {
                'lastName':   'oscaLastName',
                'firstName':  'oscaFirstName',
                'middleName': 'oscaMiddleName',
                'suffix':     'oscaSuffix',
                'birthDate':  'oscaBirthDate',
            };
            for (const [mainId, oscaId] of Object.entries(map)) {
                const mainEl = document.getElementById(mainId);
                const oscaEl = document.getElementById(oscaId);
                if (mainEl && oscaEl && mainEl.value) {
                    oscaEl.value = mainEl.value;
                }
            }
            updateOscaAge();
        }

        // Mirror main form fields into Milestone Gift card
        function mirrorMainFieldsToMs() {
            const map = {
                'lastName':      'msLastName',
                'firstName':     'msFirstName',
                'middleName':    'msMiddleName',
                'suffix':        'msExt',
                'birthDate':     'msBirthDate',
                'contactNumber': 'msContact',
            };
            for (const [mainId, mId] of Object.entries(map)) {
                const mainEl = document.getElementById(mainId);
                const mEl = document.getElementById(mId);
                if (mainEl && mEl && mainEl.value) mEl.value = mainEl.value;
            }
            updateMsAge();
        }

        function updateMsAge() {
            const dob = document.getElementById('msBirthDate');
            const ageEl = document.getElementById('msAge');
            if (!dob || !ageEl || !dob.value) { if (ageEl) ageEl.value = ''; return; }
            const today = new Date();
            const birth = new Date(dob.value);
            let age = today.getFullYear() - birth.getFullYear();
            const m = today.getMonth() - birth.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
            ageEl.value = age >= 0 ? age : '';
        }

        // Mirror main form fields into Pension card
        function mirrorMainFieldsToPen() {
            const map = {
                'lastName':      'penLastName',
                'firstName':     'penFirstName',
                'middleName':    'penMiddleName',
                'suffix':        'penSuffix',
                'birthDate':     'penBirthDate',
                'contactNumber': 'penContact',
                'seniorIdNo':    'penScIdNo',
                'sssNumber':     'penSssNumber',
            };
            for (const [mainId, pId] of Object.entries(map)) {
                const mainEl = document.getElementById(mainId);
                const pEl = document.getElementById(pId);
                if (mainEl && pEl && mainEl.value) pEl.value = mainEl.value;
            }
            const pensionAmount = document.getElementById('pensionAmount');
            const pensionAmountDisplay = document.getElementById('penPensionAmount');
            if (pensionAmount && pensionAmountDisplay && pensionAmount.value !== '') {
                pensionAmountDisplay.value = pensionAmount.value;
            }
            updatePenAge();
        }

        function updatePenAge() {
            const dob = document.getElementById('penBirthDate');
            const ageEl = document.getElementById('penAge');
            if (!dob || !ageEl || !dob.value) { if (ageEl) ageEl.value = ''; return; }
            const today = new Date();
            const birth = new Date(dob.value);
            let age = today.getFullYear() - birth.getFullYear();
            const m = today.getMonth() - birth.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
            ageEl.value = age >= 0 ? age : '';
        }

        // Mirror main form fields into Burial card
        function mirrorMainFieldsToBurial() {
            const map = {
                'lastName':   'burialDeceasedLastName',
                'firstName':  'burialDeceasedFirstName',
                'middleName': 'burialDeceasedMiddleName',
                'suffix':     'burialDeceasedExt',
                'birthDate':  'burialDeceasedBirthDate',
                'contactNumber': 'burialContactNo',
                'seniorIdNo': 'burialScIdNo',
            };
            for (const [mainId, bId] of Object.entries(map)) {
                const mainEl = document.getElementById(mainId);
                const bEl = document.getElementById(bId);
                if (mainEl && bEl && mainEl.value) bEl.value = mainEl.value;
            }
            const dod = document.getElementById('dateOfDeath');
            const dodCard = document.getElementById('burialDateOfDeathCard');
            if (dod && dodCard && dod.value) {
                dodCard.value = dod.value;
                updateBurialDaysFiled(dod.value);
            }
            const rel = document.getElementById('relationshipToDeceased');
            const relCard = document.getElementById('burialRelationshipCard');
            if (rel && relCard && rel.value) relCard.value = rel.value;
        }

        // Mirror main form fields into Home Visit card
        function mirrorMainFieldsToHV() {
            const map = {
                'lastName':       'hvLastName',
                'firstName':      'hvFirstName',
                'contactNumber':  'hvContact',
                'birthDate':      'hvBirthDate',
                'seniorIdNo':     'hvScIdNo',
            };
            for (const [mainId, hvId] of Object.entries(map)) {
                const mainEl = document.getElementById(mainId);
                const hvEl = document.getElementById(hvId);
                if (mainEl && hvEl && mainEl.value) hvEl.value = mainEl.value;
            }
        }

        // Sync burial death date card → main form field and update days filed
        function syncBurialDeath(input) {
            const mainEl = document.getElementById('dateOfDeath');
            if (mainEl) mainEl.value = input.value;
            updateBurialDaysFiled(input.value);
            checkBurialDeadlineCompliance();
        }

        // Calculate and show days filed in Burial card
        function updateBurialDaysFiled(dateOfDeath) {
            const el = document.getElementById('burialDaysFiled');
            if (!el || !dateOfDeath) { if (el) el.value = ''; return; }
            const start = new Date(dateOfDeath);
            const end = new Date();
            if (start > end) { el.value = 'Invalid date'; return; }
            let wd = 0;
            let cur = new Date(start);
            while (cur < end) {
                const d = cur.getDay();
                if (d !== 0 && d !== 6) wd++;
                cur.setDate(cur.getDate() + 1);
            }
            el.value = wd + ' working day(s)';
        }

        // Sync an OSCA card text/date field → main form field (one-way: OSCA → main)
        function syncField(oscaInput, mainFieldId) {
            const mainEl = document.getElementById(mainFieldId);
            if (mainEl) mainEl.value = oscaInput.value;
            // Also call checkAgeCompliance if it's a date field
            if (mainFieldId === 'birthDate') checkAgeCompliance();
        }

        function syncAddressFromActiveCard() {
            const type = document.getElementById('applicationType').value;
            const barangay = <?php echo json_encode($_SESSION['barangay'] ?? ''); ?>;
            const city = 'Pasig City';
            const province = 'Metro Manila';
            const sources = {
                senior: { houseNo: 'seniorHouseNo', street: 'seniorStreet', zipCode: 'seniorZipCode', landmark: 'seniorLandmark' },
                burial: { houseNo: 'burialAddrHouseNo', street: 'burialAddrStreet' },
                home_visit: { houseNo: 'hvHouseNo', street: 'hvStreet', zipCode: 'hvZipCode' },
                milestone_gift: { houseNo: 'msHouseNo', street: 'msStreet', zipCode: 'msZipCode' },
                pension: { houseNo: 'penHouseNo', street: 'penStreet', zipCode: 'penZipCode' },
                national_pension: { houseNo: 'penHouseNo', street: 'penStreet', zipCode: 'penZipCode' },
                landbank: { completeAddress: 'lbCompleteAddress', zipCode: 'lbZipCode' }
            };
            const source = sources[type] || {};
            const read = id => {
                const el = id ? document.getElementById(id) : null;
                return el ? el.value.trim() : '';
            };
            const write = (id, value) => {
                const el = document.getElementById(id);
                if (el) el.value = value;
            };

            if (source.completeAddress) {
                const fullAddress = read(source.completeAddress);
                if (fullAddress) write('completeAddress', fullAddress);
                if (source.zipCode) write('zipCode', read(source.zipCode));
                write('city', city);
                write('province', province);
                return;
            }

            const houseNo = read(source.houseNo);
            const street = read(source.street);
            const zipCode = read(source.zipCode);
            const landmark = read(source.landmark);
            write('houseNo', houseNo);
            write('street', street);
            write('city', city);
            write('province', province);
            write('zipCode', zipCode);
            write('landmark', landmark);

            const addressParts = [houseNo, street, barangay, city, province, zipCode]
                .filter(part => part && part.trim() !== '');
            if (addressParts.length) {
                write('completeAddress', addressParts.join(', '));
            }
        }

        // Sync an OSCA card select → main form select (one-way: OSCA → main)
        function syncSelectField(oscaSelect, mainFieldId) {
            const mainEl = document.getElementById(mainFieldId);
            if (mainEl) mainEl.value = oscaSelect.value;
        }

        // Calculate and display age in the OSCA Age field from the OSCA birth date
        function updateOscaAge() {
            const dob = document.getElementById('oscaBirthDate');
            const ageEl = document.getElementById('oscaAge');
            if (!dob || !ageEl || !dob.value) { if (ageEl) ageEl.value = ''; return; }
            const today = new Date();
            const birth = new Date(dob.value);
            let age = today.getFullYear() - birth.getFullYear();
            const m = today.getMonth() - birth.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
            ageEl.value = age >= 0 ? age : '';
        }

        // Preview uploaded OSCA photo in the photo box
        function previewOscaPhoto(input) {
            const preview = document.getElementById('oscaPhotoPreview');
            const icon = document.querySelector('#oscaPhotoBox .fa-camera');
            const label = document.querySelector('#oscaPhotoBox span');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (preview) {
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                        if (icon) icon.style.display = 'none';
                        if (label) label.style.display = 'none';
                    }
                };
                reader.readAsDataURL(input.files[0]);

                // Mirror the file to the main form idImage file input
                const mainFileInput = document.getElementById('idImage');
                if (mainFileInput) {
                    mainFileInput.files = input.files;
                    checkFileSize(mainFileInput);
                }
            }
        }

        // Live Localized Compliance: Age check
        function checkAgeCompliance() {
            const birthDateVal = document.getElementById('birthDate').value;
            const type = document.getElementById('applicationType').value;
            const container = document.getElementById('ageComplianceResult');
            
            if (!birthDateVal) {
                container.innerHTML = "";
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

            if (type === 'pension' || type === 'national_pension') {
                if (age >= 65) {
                    container.innerHTML = `<span class="compliance-badge compliance-pass"><i class="fas fa-check"></i> Qualifies: Age matches Pension status (${age} years old)</span>`;
                } else {
                    container.innerHTML = `<span class="compliance-badge compliance-fail"><i class="fas fa-times"></i> Disqualified: Age ${age} is under 65 (Requires 65+ for social pension)</span>`;
                }
            } else if (type === 'milestone_gift') {
                if (age >= 80) {
                    container.innerHTML = `<span class="compliance-badge compliance-pass"><i class="fas fa-check"></i> Qualifies: Applicant is ${age} years old and eligible for the cash gift</span>`;
                } else {
                    container.innerHTML = `<span class="compliance-badge compliance-fail"><i class="fas fa-times"></i> Disqualified: Age ${age} is under 80 (Requires age 80 and above)</span>`;
                }
            } else { // senior, burial
                if (age >= 60) {
                    container.innerHTML = `<span class="compliance-badge compliance-pass"><i class="fas fa-check"></i> Qualifies: Age matches Senior status (${age} years old)</span>`;
                } else {
                    container.innerHTML = `<span class="compliance-badge compliance-fail"><i class="fas fa-times"></i> Disqualified: Age ${age} is under 60 (Requires 60+)</span>`;
                }
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
            syncAddressFromActiveCard();
            const type = document.getElementById('applicationType').value;
            
            // Age compliance block
            const birthDateVal = document.getElementById('birthDate').value;
            const today = new Date();
            const birthDate = new Date(birthDateVal);
            let age = today.getFullYear() - birthDate.getFullYear();
            const m = today.getMonth() - birthDate.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            
            if ((type === 'senior' || type === 'burial') && age < 60) {
                window.showCarelinkResult("Applicant is under 60 years old. Senior citizen benefits require age 60+.", false);
                e.preventDefault();
                return;
            } else if ((type === 'pension' || type === 'national_pension') && age < 65) {
                window.showCarelinkResult("Social pension applications require age 65+.", false);
                e.preventDefault();
                return;
            } else if (type === 'milestone_gift') {
                if (age < 80) {
                    window.showCarelinkResult("Octogenarian / Nonagenarian / Centenarian Cash Gift is available to applicants aged 80 and above.", false);
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
                window.showCarelinkResult("Burial assistance claims must be submitted within 30 working days from the date of death (current: " + workingDays + " working days). Submission is blocked.", false);
                    e.preventDefault();
                    return;
                }
            }

            if (type === 'pension') {
                const eligibility = document.querySelector('input[name="homeVisitEligibility"]:checked');
                if (!eligibility) {
                    window.showCarelinkResult('Please indicate whether the senior is eligible for a home visit.', false);
                    e.preventDefault();
                    return;
                }
                if (eligibility.value !== 'eligible') return;
                const visitInput = document.getElementById('homeVisitScheduledAt');
                const visitDate = visitInput && visitInput.value ? new Date(visitInput.value) : null;
                if (!visitDate || Number.isNaN(visitDate.getTime())) {
                    window.showCarelinkResult('Please choose a home visitation schedule.', false);
                    e.preventDefault();
                    return;
                }
                const day = visitDate.getDay();
                const hour = visitDate.getHours();
                const minute = visitDate.getMinutes();
                if (day === 0 || day === 6 || hour < 8 || hour >= 17 || ![0, 30].includes(minute)) {
                    window.showCarelinkResult('Home visits must be Monday to Friday, 8:00 AM to 4:30 PM, in 30-minute slots.', false);
                    e.preventDefault();
                    return;
                }
            }
        });

        // Restore a preset application without instantiating every other form.
        (function() {
            const presetType = document.getElementById('applicationType').value;
            if (presetType) {
                selectAppType(presetType);
            }
        })();

        // JS file size check — warn before upload (8MB threshold)
        function checkFileSize(input) {
            const maxBytes = 8 * 1024 * 1024; // 8MB
            const warnId   = input.id + 'SizeWarn';
            const warnEl   = document.getElementById(warnId);
            if (!warnEl) return;
            if (input.files && input.files[0] && input.files[0].size > maxBytes) {
                warnEl.style.display = 'block';
            } else {
                warnEl.style.display = 'none';
            }
        }
    </script>
    <script src="../assets/js/seniorlink-feedback.js?v=1"></script>
</body>
</html>
