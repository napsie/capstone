<?php
require_once '../includes/crypto.php';
require_once '../includes/barangays_list.php';

$qrCodeUrl = '';
$encryptedToken = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lastName = trim($_POST['lastName'] ?? '');
    $firstName = trim($_POST['firstName'] ?? '');
    $middleName = trim($_POST['middleName'] ?? '');
    $suffix = trim($_POST['suffix'] ?? '');
    $birthDate = trim($_POST['birthDate'] ?? '');
    $contactNumber = trim($_POST['contactNumber'] ?? '');
    $completeAddress = trim($_POST['completeAddress'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    $applicationType = trim($_POST['applicationType'] ?? 'senior');

    // Pension fields
    $sssNumber = trim($_POST['sssNumber'] ?? '');
    
    // Burial fields
    $dateOfDeath = trim($_POST['dateOfDeath'] ?? '');
    $relationshipToDeceased = trim($_POST['relationshipToDeceased'] ?? '');

    // Proxy fields
    $proxyName = trim($_POST['proxyName'] ?? '');
    $proxyRelationship = trim($_POST['proxyRelationship'] ?? '');

    // Unique random transaction ID
    $transactionId = 'PRX-' . strtoupper(bin2hex(random_bytes(4)));

    // Create payload
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
        'created_at' => date('Y-m-d H:i:s')
    ];

    // Encrypt payload using ProxyCrypto
    $encryptedToken = ProxyCrypto::encrypt($payload);

    // Create absolute URL that represents the decodable scanner endpoint
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $scanUrl = $protocol . $host . dirname($_SERVER['REQUEST_URI']) . "/scan_proxy_qr_redirect.php?token=" . urlencode($encryptedToken);

    // Generate QR API URL
    $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=" . urlencode($scanUrl);
    $success = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CARELINK — Bedridden Senior Pre-Registration</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #2c3e50 0%, #1a252f 100%);
            color: #333;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
        }

        .portal-card {
            background: white;
            border-radius: 15px;
            width: 100%;
            max-width: 800px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
            overflow: hidden;
        }

        .portal-header {
            background: #2980b9;
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
        }

        .portal-header h1 {
            font-size: 1.8rem;
            margin-bottom: 5px;
        }

        .portal-header p {
            font-size: 1rem;
            opacity: 0.9;
        }

        .portal-body {
            padding: 40px;
        }

        .form-section {
            margin-bottom: 30px;
            border-bottom: 1px solid #eee;
            padding-bottom: 20px;
        }

        .form-section:last-of-type {
            border-bottom: none;
            padding-bottom: 0;
        }

        .form-section h3 {
            color: #2c3e50;
            font-size: 1.2rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-section h3 i {
            color: #2980b9;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 15px;
        }

        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            color: #555;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            border-color: #2980b9;
            outline: none;
        }

        .btn {
            background: #27ae60;
            color: white;
            border: none;
            padding: 15px 30px;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
            transition: background 0.3s;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
        }

        .btn:hover {
            background: #219653;
        }

        .privacy-alert {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            font-size: 0.85rem;
            line-height: 1.5;
            margin-bottom: 25px;
            display: flex;
            gap: 10px;
            align-items: flex-start;
        }

        .privacy-alert i {
            font-size: 1.2rem;
            margin-top: 2px;
        }

        /* Success screen styles */
        .success-screen {
            text-align: center;
            padding: 40px;
        }

        .qr-wrapper {
            background: #f8f9fa;
            border: 2px dashed #ddd;
            padding: 20px;
            display: inline-block;
            margin: 20px 0;
            border-radius: 10px;
        }

        .qr-wrapper img {
            display: block;
        }

        .success-title {
            color: #27ae60;
            font-size: 1.8rem;
            margin-bottom: 10px;
        }

        .success-desc {
            color: #666;
            margin-bottom: 30px;
            line-height: 1.5;
        }

        .btn-print {
            background: #2980b9;
            margin-bottom: 15px;
        }

        .btn-print:hover {
            background: #20638f;
        }

        .btn-back {
            background: #7f8c8d;
        }

        .btn-back:hover {
            background: #6c7a7d;
        }
    </style>
</head>
<body>

    <div class="portal-card">
        <?php if (!$success): ?>
            <div class="portal-header">
                <h1>Proxy Registration Portal</h1>
                <p>Pre-register online for bedridden seniors to generate a priority queue token</p>
            </div>
            
            <form method="POST" class="portal-body">
                <div class="privacy-alert">
                    <i class="fas fa-shield-alt"></i>
                    <div>
                        <strong>RA 10173 Privacy Guard Activated:</strong> This portal processes sensitive personal details under the Philippine Data Privacy Act of 2012. 
                        Submitting this form encrypts all details into a secure token. Unauthorized devices scanning the resulting QR code will be blocked from viewing the contents. 
                        Only authenticated Carelink terminals can decode and import this data.
                    </div>
                </div>

                <!-- Senior Details -->
                <div class="form-section">
                    <h3><i class="fas fa-user-tie"></i> Senior Citizen Information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="lastName">Last Name</label>
                            <input type="text" id="lastName" name="lastName" class="form-control" placeholder="e.g. Dela Cruz" required>
                        </div>
                        <div class="form-group">
                            <label for="firstName">First Name</label>
                            <input type="text" id="firstName" name="firstName" class="form-control" placeholder="e.g. Juan" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="middleName">Middle Name</label>
                            <input type="text" id="middleName" name="middleName" class="form-control" placeholder="e.g. Santos">
                        </div>
                        <div class="form-group">
                            <label for="suffix">Suffix (Optional)</label>
                            <input type="text" id="suffix" name="suffix" class="form-control" placeholder="e.g. Jr., Sr., III">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="birthDate">Birth Date</label>
                            <input type="date" id="birthDate" name="birthDate" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="contactNumber">Contact Number</label>
                            <input type="text" id="contactNumber" name="contactNumber" class="form-control" placeholder="e.g. 09123456789" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="barangay">Barangay</label>
                            <select id="barangay" name="barangay" class="form-control" required>
                                <option value="">Select Barangay</option>
                                <?php foreach ($barangays_list as $b): ?>
                                    <option value="<?php echo htmlspecialchars($b); ?>"><?php echo htmlspecialchars($b); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="applicationType">Application Type</label>
                            <select id="applicationType" name="applicationType" class="form-control" onchange="toggleFormFields()" required>
                                <option value="senior">Senior Citizen ID Card</option>
                                <option value="pension">Local Social Pension</option>
                                <option value="burial">Burial Assistance</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="completeAddress">Complete Home Address</label>
                        <textarea id="completeAddress" name="completeAddress" class="form-control" rows="3" placeholder="Street, Block, Barangay, Pasig City" required></textarea>
                    </div>

                    <!-- Dynamic Pension Fields -->
                    <div id="pensionFields" style="display:none;">
                        <div class="form-group">
                            <label for="sssNumber">SSS Number</label>
                            <input type="text" id="sssNumber" name="sssNumber" class="form-control" placeholder="e.g. 33-1234567-8">
                        </div>
                    </div>

                    <!-- Dynamic Burial Fields -->
                    <div id="burialFields" style="display:none;">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="dateOfDeath">Date of Passing</label>
                                <input type="date" id="dateOfDeath" name="dateOfDeath" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="relationshipToDeceased">Relationship to Deceased</label>
                                <input type="text" id="relationshipToDeceased" name="relationshipToDeceased" class="form-control" placeholder="e.g. Spouse, Son, Daughter">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Proxy Details -->
                <div class="form-section">
                    <h3><i class="fas fa-users"></i> Authorized Proxy Information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="proxyName">Full Name of Proxy (Representative)</label>
                            <input type="text" id="proxyName" name="proxyName" class="form-control" placeholder="e.g. Maria Dela Cruz" required>
                        </div>
                        <div class="form-group">
                            <label for="proxyRelationship">Relationship to Senior Applicant</label>
                            <select id="proxyRelationship" name="proxyRelationship" class="form-control" required>
                                <option value="">Select Relationship</option>
                                <option value="Spouse">Spouse</option>
                                <option value="Son">Son</option>
                                <option value="Daughter">Daughter</option>
                                <option value="Sibling">Sibling</option>
                                <option value="Grandchild">Grandchild</option>
                                <option value="Legal Guardian">Legal Guardian</option>
                                <option value="Caregiver">Authorized Caregiver</option>
                            </select>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn"><i class="fas fa-qrcode"></i> Generate Proxy Priority Token</button>
            </form>
        <?php else: ?>
            <div class="success-screen">
                <i class="fas fa-check-circle" style="font-size: 4rem; color: #27ae60; margin-bottom: 20px;"></i>
                <h1 class="success-title">Pre-Registration Complete!</h1>
                <p class="success-desc">
                    A secure Proxy QR code has been generated. Please print this or save it on your mobile device.<br>
                    <strong>Show this QR code at the Barangay Counter to bypass normal lines and enter the High-Priority Queue.</strong>
                </p>

                <div class="qr-wrapper">
                    <img src="<?php echo $qrCodeUrl; ?>" alt="Proxy QR Code">
                    <div style="margin-top: 10px; font-weight: bold; font-size: 0.9rem; color: #2c3e50;">
                        Priority Token: <?php echo htmlspecialchars($transactionId); ?>
                    </div>
                </div>

                <div class="privacy-alert" style="margin: 20px auto; max-width: 600px; text-align: left;">
                    <i class="fas fa-shield-alt"></i>
                    <div>
                        <strong>Privacy Guard Guarded (RA 10173):</strong> The QR code contains an encrypted token of the applicant's private records. 
                        Scanning it with an unauthorized mobile app or camera will block access to protect your family's personal information.
                    </div>
                </div>

                <div style="max-width: 300px; margin: 0 auto;">
                    <button class="btn btn-print" onclick="window.print()"><i class="fas fa-print"></i> Print QR Code</button>
                    <a href="proxy_registration.php" class="btn btn-back"><i class="fas fa-redo"></i> Register Another</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function toggleFormFields() {
            const type = document.getElementById('applicationType').value;
            const pensionFields = document.getElementById('pensionFields');
            const burialFields = document.getElementById('burialFields');
            
            pensionFields.style.display = 'none';
            burialFields.style.display = 'none';

            document.getElementById('sssNumber').removeAttribute('required');
            document.getElementById('dateOfDeath').removeAttribute('required');
            document.getElementById('relationshipToDeceased').removeAttribute('required');

            if (type === 'pension') {
                pensionFields.style.display = 'block';
                document.getElementById('sssNumber').setAttribute('required', 'required');
            } else if (type === 'burial') {
                burialFields.style.display = 'block';
                document.getElementById('dateOfDeath').setAttribute('required', 'required');
                document.getElementById('relationshipToDeceased').setAttribute('required', 'required');
            }
        }
    </script>
</body>
</html>
