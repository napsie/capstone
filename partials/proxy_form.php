<?php
/**
 * Proxy pre-registration form partial.
 * Expects: $barangays_list, $proxySuccess, $proxyQrUrl, $proxyTransactionId, $formAction, $resetUrl
 */
$proxySuccess = $proxySuccess ?? false;
$proxyQrUrl = $proxyQrUrl ?? '';
$proxyTransactionId = $proxyTransactionId ?? '';
$formAction = $formAction ?? '';
$resetUrl = $resetUrl ?? $formAction;
?>
<?php if (!$proxySuccess): ?>
    <form method="POST" action="<?php echo htmlspecialchars($formAction); ?>" class="proxy-form">
        <input type="hidden" name="proxy_submit" value="1">

        <div class="privacy-alert" role="note">
            <i class="fas fa-shield-alt" aria-hidden="true"></i>
            <div>
                <strong>RA 10173 Privacy Guard:</strong> This portal processes sensitive personal details under the Philippine Data Privacy Act of 2012.
                Submitting encrypts all details into a secure token. Only authenticated CARELINK terminals can decode and import this data.
            </div>
        </div>

        <div class="form-section">
            <h3><i class="fas fa-user-tie" aria-hidden="true"></i> Senior Citizen Information</h3>
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
                    <select id="applicationType" name="applicationType" class="form-control" onchange="toggleProxyFormFields()" required>
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
            <div id="pensionFields" class="dynamic-fields" hidden>
                <div class="form-group">
                    <label for="sssNumber">SSS Number</label>
                    <input type="text" id="sssNumber" name="sssNumber" class="form-control" placeholder="e.g. 33-1234567-8">
                </div>
            </div>
            <div id="burialFields" class="dynamic-fields" hidden>
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

        <div class="form-section">
            <h3><i class="fas fa-users" aria-hidden="true"></i> Authorized Proxy Information</h3>
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

        <button type="submit" class="btn btn-accent btn-block">
            <i class="fas fa-qrcode" aria-hidden="true"></i> Generate Proxy Priority Token
        </button>
    </form>
<?php else: ?>
    <div class="proxy-success">
        <div class="proxy-success-icon" aria-hidden="true">
            <i class="fas fa-check-circle"></i>
        </div>
        <h3>Pre-Registration Complete!</h3>
        <p class="proxy-success-desc">
            A secure Proxy QR code has been generated. Print this or save it on your mobile device.
            <strong>Show this QR code at the Barangay Counter to enter the High-Priority Queue.</strong>
        </p>
        <div class="qr-wrapper">
            <img src="<?php echo htmlspecialchars($proxyQrUrl); ?>" alt="Proxy QR Code for <?php echo htmlspecialchars($proxyTransactionId); ?>">
            <p class="qr-token">Priority Token: <?php echo htmlspecialchars($proxyTransactionId); ?></p>
        </div>
        <div class="privacy-alert" role="note">
            <i class="fas fa-shield-alt" aria-hidden="true"></i>
            <div>
                <strong>Privacy Protected (RA 10173):</strong> The QR code contains an encrypted token.
                Unauthorized devices scanning it will be blocked from viewing the contents.
            </div>
        </div>
        <div class="proxy-success-actions">
            <button type="button" class="btn btn-primary btn-block" onclick="window.print()">
                <i class="fas fa-print" aria-hidden="true"></i> Print QR Code
            </button>
            <a href="<?php echo htmlspecialchars($resetUrl); ?>" class="btn btn-muted btn-block">
                <i class="fas fa-redo" aria-hidden="true"></i> Register Another
            </a>
        </div>
    </div>
<?php endif; ?>
