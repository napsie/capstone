<?php
require_once '../includes/process_proxy_registration.php';
require_once '../includes/barangays_list.php';
require_once '../includes/application_types.php';

$proxyResult = processProxyRegistration();
$proxySuccess = $proxyResult['success'];
$proxyQrUrl = $proxyResult['qrCodeUrl'];
$proxyTransactionId = $proxyResult['transactionId'];
$formAction = 'proxy_registration.php';
$resetUrl = 'proxy_registration.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CARELINK — Proxy Pre-Registration</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/carelink-theme.css?v=3">
</head>
<body>
    <a href="../index.php" class="back-btn">
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
        Back to Home
    </a>

    <div class="page-bg page-bg--pages" aria-hidden="true"></div>

    <div class="landing-wrapper" style="padding-top: 80px;">
        <section class="proxy-section" aria-labelledby="proxy-heading">
            <div class="proxy-panel">
                <div class="proxy-panel-header">
                    <div class="hero-badge">
                        <i class="fas fa-wheelchair" aria-hidden="true"></i>
                        Bedridden Senior Support
                    </div>
                    <h2 id="proxy-heading">Proxy Registration Portal</h2>
                    <p>Pre-register online for bedridden seniors to generate a priority queue token.</p>
                </div>
<<<<<<< Updated upstream

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
                                <?php foreach (getApplicationTypeOptions() as $val => $label): ?>
                                <option value="<?php echo $val; ?>"><?php echo htmlspecialchars($label); ?></option>
                                <?php endforeach; ?>
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
=======
                <div class="proxy-panel-body">
                    <?php include '../partials/proxy_form.php'; ?>
>>>>>>> Stashed changes
                </div>
            </div>
        </section>
    </div>

    <script>
        function toggleProxyFormFields() {
            const type = document.getElementById('applicationType')?.value;
            const pensionFields = document.getElementById('pensionFields');
            const burialFields = document.getElementById('burialFields');
            if (!pensionFields || !burialFields) return;

            pensionFields.hidden = true;
            burialFields.hidden = true;
            document.getElementById('sssNumber')?.removeAttribute('required');
            document.getElementById('dateOfDeath')?.removeAttribute('required');
            document.getElementById('relationshipToDeceased')?.removeAttribute('required');

<<<<<<< Updated upstream
            if (type === 'pension' || type === 'national_pension') {
                pensionFields.style.display = 'block';
                document.getElementById('sssNumber').setAttribute('required', 'required');
=======
            if (type === 'pension') {
                pensionFields.hidden = false;
                document.getElementById('sssNumber')?.setAttribute('required', 'required');
>>>>>>> Stashed changes
            } else if (type === 'burial') {
                burialFields.hidden = false;
                document.getElementById('dateOfDeath')?.setAttribute('required', 'required');
                document.getElementById('relationshipToDeceased')?.setAttribute('required', 'required');
            }
        }
        <?php if ($proxySuccess): ?>
        window.scrollTo({ top: 0, behavior: 'smooth' });
        <?php endif; ?>
    </script>
</body>
</html>
