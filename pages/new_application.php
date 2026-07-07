<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/crypto.php';
require_once '../includes/application_types.php';

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
    $oscaData = parseOscaFormPost($_POST);

    $idNumber = isset($_POST['idNumber']) ? trim(strip_tags($_POST['idNumber'])) : '';
    $disabilityType = null;

    // Rule-Based Compliance validation on backend
    if (!empty($birthDate)) {
        $birthDateObj = new DateTime($birthDate);
        $today = new DateTime();
        $age = $today->diff($birthDateObj)->y;
        
        if (($applicationType === 'senior' || $applicationType === 'burial') && $age < 60) {
            $errorMessage = "Localized Compliance Check Failed: Applicant must be at least 60 years old (current age: $age).";
        } elseif (($applicationType === 'pension' || $applicationType === 'national_pension') && $age < 65) {
            $errorMessage = "Localized Compliance Check Failed: Local/National Social Pension requires applicant to be at least 65 years old (current age: $age).";
        } elseif ($applicationType === 'milestone_gift') {
            $milestones = [80, 85, 90, 95];
            $isMilestone = in_array($age, $milestones) || ($age >= 100);
            if (!$isMilestone) {
                $errorMessage = "Localized Compliance Check Failed: Milestone Cash Gift is only available for ages 80, 85, 90, 95, or 100+ (current age: $age).";
            }
        }
    }

    // Social Pension validation (SSS cap <= P4,000 for local, 0 for national)
    $sssNumber = null;
    $pensionAmount = null;
    if ($applicationType === 'pension') {
        $sssNumber = isset($_POST['sssNumber']) ? trim(strip_tags($_POST['sssNumber'])) : '';
        $pensionAmount = isset($_POST['pensionAmount']) ? floatval($_POST['pensionAmount']) : 0;
        if (empty($sssNumber)) {
            $errorMessage = "Localized Compliance Check Failed: SSS Number is required for local social pension.";
        } else if ($pensionAmount > 4000) {
            $errorMessage = "Localized Compliance Check Failed: Monthly SSS Pension exceeds the local limit of P4,000 (current: P" . number_format($pensionAmount, 2) . ").";
        }
    } elseif ($applicationType === 'national_pension') {
        $sssNumber = isset($_POST['sssNumber']) ? trim(strip_tags($_POST['sssNumber'])) : '';
        $pensionAmount = isset($_POST['pensionAmount']) ? floatval($_POST['pensionAmount']) : 0;
        if (empty($sssNumber)) {
            $errorMessage = "Localized Compliance Check Failed: SSS Number is required for national social pension.";
        } else if ($pensionAmount > 0) {
            $errorMessage = "Localized Compliance Check Failed: National DSWD Social Pension is restricted to indigent seniors with no other pension benefits (current verified: P" . number_format($pensionAmount, 2) . ").";
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

            $proofOfAddress     = null;
            $proofOfAddressType = null;
            if (isset($_FILES['proofOfAddress']) && $_FILES['proofOfAddress']['error'] === UPLOAD_ERR_OK && $_FILES['proofOfAddress']['size'] > 0) {
                $mimeType = $finfo->file($_FILES['proofOfAddress']['tmp_name']);
                if (!in_array($mimeType, $allowedMimes)) {
                    $errorMessage = "Proof of Address: only JPEG, PNG, GIF, and PDF files are accepted. You uploaded: {$mimeType}";
                } else {
                    $proofOfAddress     = file_get_contents($_FILES['proofOfAddress']['tmp_name']);
                    $proofOfAddressType = $mimeType;
                }
            } elseif (isset($_FILES['proofOfAddress']) && $_FILES['proofOfAddress']['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploadErr = $_FILES['proofOfAddress']['error'];
                $errorMessage = "Proof of Address upload error (code {$uploadErr}). Please try a smaller file.";
            }

            $idImage     = null;
            $idImageType = null;
            if (empty($errorMessage)) {
                if (isset($_FILES['idImage']) && $_FILES['idImage']['error'] === UPLOAD_ERR_OK && $_FILES['idImage']['size'] > 0) {
                    $mimeType = $finfo->file($_FILES['idImage']['tmp_name']);
                    if (!in_array($mimeType, $allowedMimes)) {
                        $errorMessage = "ID Image: only JPEG, PNG, GIF, and PDF files are accepted. You uploaded: {$mimeType}";
                    } else {
                        $idImage     = file_get_contents($_FILES['idImage']['tmp_name']);
                        $idImageType = $mimeType;
                    }
                } elseif (isset($_FILES['idImage']) && $_FILES['idImage']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $uploadErr = $_FILES['idImage']['error'];
                    $errorMessage = "ID Image upload error (code {$uploadErr}). Please try a smaller file.";
                }
            }
        }

        if (empty($errorMessage)) {
            try {
                // Prepare and bind - Including new columns
                $oscaCols = getOscaExtraColumns();
                $colList = 'id_number, full_name, application_type, birth_date, contact_number, complete_address,
                            emergency_contact, emergency_contact_name, barangay,
                            proof_of_address, proof_of_address_type, id_image, id_image_type,
                            lastName, firstName, middleName, suffix, disability_type,
                            sss_number, pension_amount, date_of_death, relationship_to_deceased,
                            is_proxy_application, proxy_name, proxy_relationship, proxy_token,
                            priority_level, workflow_state, ' . implode(', ', $oscaCols);
                $placeholders = implode(', ', array_fill(0, 28 + count($oscaCols), '?'));

                $stmt = $conn->prepare("INSERT INTO applications ($colList) VALUES ($placeholders)");

                $params = [
                    $idNumber, $fullName, $applicationType, $birthDate, $contactNumber, $completeAddress,
                    $emergencyContact, $emergencyContactName, $barangay,
                    $proofOfAddress, $proofOfAddressType, $idImage, $idImageType,
                    $lastName, $firstName, $middleName, $suffix, $disabilityType,
                    $sssNumber, $pensionAmount, $dateOfDeath, $relationshipToDeceased,
                    $isProxy, $proxyName, $proxyRelationship, $proxyToken,
                    $priorityLevel, 'Received',
                ];
                foreach ($oscaCols as $col) {
                    $params[] = $oscaData[$col];
                }

                if ($stmt->execute($params)) {
                    // Log history
                    $stmtHist = $conn->prepare("INSERT INTO application_history (application_id, previous_state, new_state, changed_by, comments) VALUES (?, ?, ?, ?, ?)");
                    $userName = $_SESSION['username'] ?? 'barangay_staff';
                    $stmtHist->execute([$idNumber, 'None', 'Received', $userName, 'Application created and received at the counter.']);

                    header("Location: submit_application.php?success=1");
                    exit();
                } else {
                    $errorMessage = "Database error while saving the application. Please try again.";
                }
            } catch (PDOException $e) {
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

        /* ── Google Font ─────────────────────────────── */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        body, .main-content { font-family: 'Inter', sans-serif; }

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
        .btn-change-type {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.12);
            color: #94a3b8;
            padding: 9px 18px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 600;
            transition: all 0.2s;
            display: flex; align-items: center; gap: 7px;
            font-family: inherit;
        }
        .btn-change-type:hover {
            border-color: #6366f1;
            color: #a5b4fc;
            background: rgba(99,102,241,0.1);
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
        .btn-change-type {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            color: #94a3b8;
            padding: 8px 16px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.2s;
            display: flex; align-items: center; gap: 7px;
        }
        .btn-change-type:hover {
            border-color: #3b82f6;
            color: #60a5fa;
            background: rgba(59,130,246,0.08);
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
            transition: transform 260ms cubic-bezier(0.2,0.8,0.2,1), box-shadow 260ms ease, border-color 160ms ease;
            background: #ffffff !important;
            border-radius: 14px !important;
            border: 1px solid rgba(15,23,42,0.06) !important;
            box-shadow: 0 14px 40px rgba(2,6,23,0.06) !important;
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
            transform: translateY(-12px) scale(1.015);
            box-shadow: 0 36px 90px rgba(2,6,23,0.14);
            border-color: rgba(15,23,42,0.12) !important;
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
        .app-type-card .card-arrow { position: absolute; right: 18px; top: 50%; transform: translateY(-50%); color: #c7cdd4; font-size: 0.95rem; transition: transform 200ms ease, color 200ms ease; }
        .app-type-card:hover .card-arrow { transform: translateY(-50%) translateX(6px); color: #94a3b8; }

        /* container shadow around the whole application form area */
        .application-form {
            padding: 18px 20px 28px;
            border-radius: 12px;
            background: linear-gradient(180deg, rgba(255,255,255,0.99), rgba(250,250,250,0.98));
            box-shadow: 0 18px 60px rgba(2,6,23,0.06);
            border: 1px solid rgba(2,6,23,0.03);
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
        .app-type-card:hover { border-left-color: var(--card-accent, #60a5fa) !important; }

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

        #cardSelectorSection.hidden { display: none; }

        /* Benefit details modal */
        #benefitModal {
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px;
            box-sizing: border-box;
        }
        #benefitModal .modal-content {
            margin: 0;
            width: 100%;
            max-width: 720px;
            max-height: calc(100vh - 48px);
            border-radius: 16px;
            padding: 0;
            overflow: hidden;
            position: relative;
            display: flex;
            flex-direction: column;
        }
        .benefit-modal-header {
            display: flex;
            align-items: flex-start;
            gap: 18px;
            padding: 28px 28px 22px;
            background: linear-gradient(135deg, rgba(59,130,246,0.08), rgba(99,102,241,0.04));
            border-bottom: 1px solid rgba(15,23,42,0.08);
        }
        .benefit-modal-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.45rem;
            color: #fff;
            flex-shrink: 0;
            box-shadow: 0 12px 28px rgba(2,6,23,0.12);
        }
        .benefit-modal-header h2 {
            margin: 0 0 6px;
            font-size: 1.25rem;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.35;
        }
        .benefit-modal-header p {
            margin: 0;
            font-size: 0.92rem;
            color: #475569;
            line-height: 1.55;
        }
        .benefit-modal-body {
            padding: 24px 28px 28px;
            flex: 1;
            overflow-y: auto;
        }
        .benefit-modal-section {
            margin-bottom: 22px;
        }
        .benefit-modal-section:last-of-type { margin-bottom: 0; }
        .benefit-modal-section h3 {
            margin: 0 0 12px;
            font-size: 0.82rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .benefit-modal-section h3 i { color: #3b82f6; }
        .benefit-list {
            margin: 0;
            padding: 0;
            list-style: none;
        }
        .benefit-list li {
            position: relative;
            padding: 10px 12px 10px 36px;
            margin-bottom: 8px;
            background: #f8fafc;
            border: 1px solid rgba(15,23,42,0.06);
            border-radius: 10px;
            font-size: 0.9rem;
            color: #334155;
            line-height: 1.45;
        }
        .benefit-list li::before {
            content: '\f00c';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            left: 12px;
            top: 12px;
            color: #10b981;
            font-size: 0.75rem;
        }
        .benefit-list.requirements li::before {
            content: '\f15c';
            color: #3b82f6;
        }
        .benefit-ack-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-top: 20px;
            padding: 14px 16px;
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 10px;
            font-size: 0.88rem;
            color: #78350f;
            line-height: 1.45;
        }
        .benefit-ack-row input { margin-top: 3px; flex-shrink: 0; }
        .benefit-modal-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            padding: 18px 28px 24px;
            border-top: 1px solid rgba(15,23,42,0.08);
            background: #fafafa;
        }
        .btn-benefit-cancel {
            padding: 11px 20px;
            border-radius: 10px;
            border: 1px solid rgba(15,23,42,0.12);
            background: #fff;
            color: #475569;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-benefit-proceed {
            padding: 11px 22px;
            border-radius: 10px;
            border: none;
            background: linear-gradient(135deg, #3b82f6, #6366f1);
            color: #fff;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-benefit-proceed:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }
        #benefitModal .close {
            position: absolute;
            right: 18px;
            top: 18px;
            z-index: 2;
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

                <!-- Application Type Cards — directly visible on load -->
                <div id="cardSelectorSection">
                    <p class="card-section-hint"><i class="fas fa-hand-pointer"></i> Click a benefit below to view details and requirements before applying.</p>
                    <?php
                    $benefitDetails = getApplicationBenefitDetails();
                    $typeIcons = [
                        'senior'           => ['icon' => 'fas fa-id-card',       'color' => '#60a5fa', 'grad' => 'linear-gradient(135deg,#1e3a8a,#1d4ed8)', 'desc' => 'Senior Citizens ID registration',    'code' => 'Form 1',    'accent' => '#3b82f6'],
                        'landbank'         => ['icon' => 'fas fa-credit-card',   'color' => '#34d399', 'grad' => 'linear-gradient(135deg,#064e3b,#059669)', 'desc' => 'Land Bank Cash Card enrollment',       'code' => 'Form 2',    'accent' => '#10b981'],
                        'pension'          => ['icon' => 'fas fa-wallet',        'color' => '#fbbf24', 'grad' => 'linear-gradient(135deg,#78350f,#d97706)', 'desc' => 'Local social pension benefit',          'code' => 'Local',     'accent' => '#f59e0b'],
                        'national_pension' => ['icon' => 'fas fa-landmark',     'color' => '#a78bfa', 'grad' => 'linear-gradient(135deg,#4c1d95,#7c3aed)', 'desc' => 'National DSWD pension (RA 11916)',     'code' => 'National',  'accent' => '#8b5cf6'],
                        'milestone_gift'   => ['icon' => 'fas fa-gift',          'color' => '#f472b6', 'grad' => 'linear-gradient(135deg,#831843,#db2777)', 'desc' => 'Octogenarian / Centenarian cash gift',  'code' => 'Form 5',    'accent' => '#ec4899'],
                        'burial'           => ['icon' => 'fas fa-ribbon',        'color' => '#94a3b8', 'grad' => 'linear-gradient(135deg,#1e293b,#475569)', 'desc' => 'Burial financial assistance claim',      'code' => 'Form 7',    'accent' => '#64748b'],
                        'home_visit'       => ['icon' => 'fas fa-house-medical', 'color' => '#22d3ee', 'grad' => 'linear-gradient(135deg,#164e63,#0891b2)', 'desc' => 'Home visitation &amp; confirmation',     'code' => 'Form 8',    'accent' => '#06b6d4'],
                    ];
                    ?>
                    <div class="app-type-grid" id="appTypeGrid">
                        <?php foreach (getApplicationTypeOptions() as $val => $label):
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
                                <div class="card-code"><?php echo htmlspecialchars($meta['code']); ?></div>
                                <div class="card-title"><?php echo htmlspecialchars($label); ?></div>
                                <div class="card-desc"><?php echo $meta['desc']; ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- STEP 2: Full Form (hidden until a card is selected) -->
                <div id="formBody">
                    <!-- Selected type banner -->
                    <div class="selected-type-banner" id="selectedTypeBanner" style="display:none;">
                        <div class="banner-icon" id="bannerIcon"></div>
                        <div class="banner-info">
                            <small><i class="fas fa-layer-group"></i> Selected Application Type</small>
                            <strong id="bannerLabel"></strong>
                        </div>
                        <button type="button" class="btn-change-type" onclick="resetAppType()"><i class="fas fa-arrow-left"></i> Change</button>
                    </div>

                <form method="POST" action="new_application.php" enctype="multipart/form-data" id="mainAppForm">
                    <!-- Hidden Proxy Fields -->
                    <input type="hidden" name="isProxy" id="isProxy" value="<?php echo $loadedProxyData ? 1 : 0; ?>">
                    <input type="hidden" name="proxyToken" id="proxyToken" value="<?php echo htmlspecialchars($loadedProxyData['transactionId'] ?? ''); ?>">
                    <input type="hidden" id="applicationType" name="applicationType" value="" required>

                    <!-- Basic Information -->
                    <div class="form-section">
                        <div class="step-heading">
                            <div class="step-number">2</div>
                            <div>
                                <h3>Basic Information</h3>
                                <p>Fill in the applicant's personal details below.</p>
                            </div>
                        </div>

                        <div class="form-row">
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

                    <?php $formFieldPrefix = ''; include '../partials/osca_form_sections.php'; ?>

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
                        <p style="font-size:0.82rem; color:#94a3b8; margin-bottom:10px;"><i class="fas fa-info-circle"></i> Accepted formats: JPEG, PNG, PDF &mdash; Max 8MB per file.</p>
                        <div class="form-group">
                            <label for="proofOfAddress" id="labelProofOfAddress">Proof of Address</label>
                            <input type="file" id="proofOfAddress" name="proofOfAddress" required accept="image/jpeg,image/png,image/gif,application/pdf" onchange="checkFileSize(this)">
                            <div id="proofOfAddressSizeWarn" style="display:none; color:#e74c3c; font-size:0.8rem; margin-top:4px;"><i class="fas fa-exclamation-triangle"></i> File is large (&gt;8MB). It may fail to upload. Consider compressing it first.</div>
                        </div>
                        <div class="form-group">
                            <label for="idImage" id="labelIdImage">ID Image / Supporting Document Photo</label>
                            <input type="file" id="idImage" name="idImage" required accept="image/jpeg,image/png,image/gif,application/pdf" onchange="checkFileSize(this)">
                            <div id="idImageSizeWarn" style="display:none; color:#e74c3c; font-size:0.8rem; margin-top:4px;"><i class="fas fa-exclamation-triangle"></i> File is large (&gt;8MB). It may fail to upload. Consider compressing it first.</div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn" style="background-color: var(--primary);"><i class="fas fa-save"></i> Submit Application</button>
                    </div>
                </form>
                </div><!-- /#formBody -->
            </div>
        </div>
    </div>

    <!-- Benefit Details Modal -->
    <div id="benefitModal" class="modal" role="dialog" aria-labelledby="benefitModalTitle" aria-modal="true">
        <div class="modal-content">
            <span class="close" onclick="closeBenefitModal()" aria-label="Close">&times;</span>
            <div class="benefit-modal-header">
                <div class="benefit-modal-icon" id="benefitModalIcon"></div>
                <div>
                    <h2 id="benefitModalTitle"></h2>
                    <p id="benefitModalSummary"></p>
                </div>
            </div>
            <div class="benefit-modal-body">
                <div class="benefit-modal-section">
                    <h3><i class="fas fa-star"></i> What You Get</h3>
                    <ul class="benefit-list" id="benefitModalBenefits"></ul>
                </div>
                <div class="benefit-modal-section">
                    <h3><i class="fas fa-clipboard-check"></i> Requirements to Apply</h3>
                    <ul class="benefit-list requirements" id="benefitModalRequirements"></ul>
                </div>
                <div class="benefit-modal-section">
                    <h3><i class="fas fa-folder-open"></i> Documents Needed</h3>
                    <ul class="benefit-list requirements" id="benefitModalDocuments"></ul>
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
    <script src="../assets/js/osca-form-fields.js"></script>
    <script>
        const BENEFIT_DETAILS = <?php echo json_encode($benefitDetails, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        const TYPE_META = {};
        document.querySelectorAll('#appTypeGrid .app-type-card').forEach(card => {
            TYPE_META[card.dataset.value] = {
                label:  card.dataset.label,
                icon:   card.dataset.icon,
                color:  card.dataset.color,
                bg:     card.dataset.bg,
                accent: card.dataset.accent,
            };
        });
        let pendingBenefitType = '';

        function openBenefitModal(value) {
            pendingBenefitType = value;
            const meta = TYPE_META[value] || {};
            const details = BENEFIT_DETAILS[value] || {};

            document.getElementById('benefitModalTitle').textContent = meta.label || value;
            document.getElementById('benefitModalSummary').textContent = details.summary || '';
            document.getElementById('benefitModalIcon').innerHTML = `<i class="${meta.icon || 'fas fa-file'}"></i>`;
            document.getElementById('benefitModalIcon').style.background = meta.bg || 'linear-gradient(135deg,#3b82f6,#6366f1)';

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

            document.getElementById('benefitModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeBenefitModal() {
            document.getElementById('benefitModal').style.display = 'none';
            document.body.style.overflow = '';
            pendingBenefitType = '';
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

        function openProxyModal() {
            document.getElementById('proxyModal').style.display = "block";
            document.getElementById('modalError').textContent = "";
            document.getElementById('modalToken').value = "";
        }

        function closeProxyModal() {
            document.getElementById('proxyModal').style.display = "none";
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
                    
                    // Application type
                    const loadedType = data.applicationType || 'senior';
                    selectAppType(loadedType);

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

        function selectAppType(value) {
            // Update hidden input
            document.getElementById('applicationType').value = value;

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

            // Scroll smoothly to form
            setTimeout(() => formBody.scrollIntoView({ behavior: 'smooth', block: 'start' }), 80);

            // Trigger dependent logic
            toggleFields();
            checkAgeCompliance();
        }

        function resetAppType() {
            document.getElementById('applicationType').value = '';
            document.querySelectorAll('#appTypeGrid .app-type-card').forEach(c => {
                c.classList.remove('selected');
                c.setAttribute('aria-pressed', 'false');
            });

            const formBody = document.getElementById('formBody');
            formBody.classList.remove('visible');
            document.getElementById('selectedTypeBanner').style.display = 'none';
            document.getElementById('cardSelectorSection').classList.remove('hidden');

            document.getElementById('cardSelectorSection').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function toggleFields() {
            const type = document.getElementById('applicationType').value;

            document.getElementById('pension-fields').style.display = 'none';
            document.getElementById('burial-fields').style.display = 'none';

            document.getElementById('sssNumber').removeAttribute('required');
            document.getElementById('dateOfDeath').removeAttribute('required');
            document.getElementById('relationshipToDeceased').removeAttribute('required');

            if (type === 'pension' || type === 'national_pension') {
                document.getElementById('pension-fields').style.display = 'block';
                document.getElementById('sssNumber').setAttribute('required', 'required');
            } else if (type === 'burial') {
                document.getElementById('burial-fields').style.display = 'block';
                document.getElementById('dateOfDeath').setAttribute('required', 'required');
                document.getElementById('relationshipToDeceased').setAttribute('required', 'required');
            }

            toggleOscaFormFields(type, '');
            checkAgeCompliance();
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
                const milestones = [80, 85, 90, 95];
                const isMilestone = milestones.includes(age) || (age >= 100);
                if (isMilestone) {
                    container.innerHTML = `<span class="compliance-badge compliance-pass"><i class="fas fa-check"></i> Qualifies: Age ${age} matches Milestone Cash Gift bracket</span>`;
                } else {
                    container.innerHTML = `<span class="compliance-badge compliance-fail"><i class="fas fa-times"></i> Disqualified: Age ${age} is not a milestone age (Requires 80, 85, 90, 95, or 100+)</span>`;
                }
            } else { // senior, burial
                if (age >= 60) {
                    container.innerHTML = `<span class="compliance-badge compliance-pass"><i class="fas fa-check"></i> Qualifies: Age matches Senior status (${age} years old)</span>`;
                } else {
                    container.innerHTML = `<span class="compliance-badge compliance-fail"><i class="fas fa-times"></i> Disqualified: Age ${age} is under 60 (Requires 60+)</span>`;
                }
            }
        }

        // Live Localized Compliance: Pension check (SSS Cap P4,000 for local, 0 for national)
        async function verifySssPension() {
            const sssNum = document.getElementById('sssNumber').value.trim();
            const container = document.getElementById('pensionComplianceResult');
            const amountInput = document.getElementById('pensionAmount');
            const type = document.getElementById('applicationType').value;
            
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
                    
                    if (type === 'national_pension') {
                        if (amount === 0) {
                            container.innerHTML = `<span class="compliance-badge compliance-pass"><i class="fas fa-check"></i> Qualifies: Applicant has no active SSS pension</span>`;
                        } else {
                            container.innerHTML = `<span class="compliance-badge compliance-fail"><i class="fas fa-times"></i> Disqualified: National pension is restricted to indigent seniors with no SSS pension (current: P${amount.toFixed(2)})</span>`;
                        }
                    } else { // local pension
                        if (amount <= 4000) {
                            container.innerHTML = `<span class="compliance-badge compliance-pass"><i class="fas fa-check"></i> Qualifies: Pension (P${amount.toFixed(2)}) is <= P4,000 limit</span>`;
                        } else {
                            container.innerHTML = `<span class="compliance-badge compliance-fail"><i class="fas fa-times"></i> Disqualified: Pension (P${amount.toFixed(2)}) exceeds P4,000 cap</span>`;
                        }
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
            const birthDateVal = document.getElementById('birthDate').value;
            const today = new Date();
            const birthDate = new Date(birthDateVal);
            let age = today.getFullYear() - birthDate.getFullYear();
            const m = today.getMonth() - birthDate.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            
            if ((type === 'senior' || type === 'burial') && age < 60) {
                alert("Localized Compliance Error:\nApplicant is under 60 years old. Senior citizen benefits require age 60+.");
                e.preventDefault();
                return;
            } else if ((type === 'pension' || type === 'national_pension') && age < 65) {
                alert("Localized Compliance Error:\nSocial pension applications require age 65+.");
                e.preventDefault();
                return;
            } else if (type === 'milestone_gift') {
                const milestones = [80, 85, 90, 95];
                const isMilestone = milestones.includes(age) || (age >= 100);
                if (!isMilestone) {
                    alert("Localized Compliance Error:\nMilestone Cash Gift is only available for ages 80, 85, 90, 95, or 100+.");
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
            } else if (type === 'national_pension') {
                const pensionAmt = parseFloat(document.getElementById('pensionAmount').value);
                if (isNaN(pensionAmt)) {
                    alert("Localized Compliance Error:\nPlease verify SSS Pension before submitting.");
                    e.preventDefault();
                    return;
                } else if (pensionAmt > 0) {
                    alert("Localized Compliance Error:\nNational DSWD Social Pension is restricted to indigent seniors with NO other pension benefits.");
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

        // Auto-init: if an application type is already set (proxy load, POST error re-render), show form
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
</body>
</html>