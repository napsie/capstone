<?php
/**
 * Proxy pre-registration and benefit application form partial.
 * Expects: $barangays_list, $proxySuccess, $proxyQrUrl, $proxyTransactionId, $proxyOption, $proxyMessage, $formAction, $resetUrl
 */
$proxySuccess = $proxySuccess ?? false;
$proxyQrUrl = $proxyQrUrl ?? '';
$proxyTransactionId = $proxyTransactionId ?? '';
$proxyOption = $proxyOption ?? '';
$proxyMessage = $proxyMessage ?? '';
$formAction = $formAction ?? '';
$resetUrl = $resetUrl ?? $formAction;
$activePortalOption = $_POST['portal_option'] ?? 'new_senior';
$old = static function (string $key, string $default = ''): string {
    return htmlspecialchars((string) ($_POST[$key] ?? $default), ENT_QUOTES, 'UTF-8');
};
?>

<!-- Custom CSS for Premium Design & Animation -->
<style>
    /* Option selector cards */
    .portal-option-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .portal-option-card {
        background: linear-gradient(145deg, #ffffff, #f1f5f9);
        border: 2px solid rgba(0,0,0,0.06);
        border-radius: 16px;
        padding: 24px;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    
    .portal-option-card:hover {
        transform: translateY(-5px);
        border-color: #10b981;
        box-shadow: 0 15px 30px rgba(16, 185, 129, 0.15);
    }
    
    .portal-option-card.active {
        background: linear-gradient(145deg, #ecfdf5, #ffffff);
        border-color: #10b981;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2), 0 15px 35px rgba(16, 185, 129, 0.2);
    }
    
    .portal-option-card .option-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        background: #10b981;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        transition: transform 0.3s ease;
    }
    
    .portal-option-card:hover .option-icon {
        transform: scale(1.1) rotate(-3deg);
    }
    
    .portal-option-card h4 {
        margin: 0;
        font-size: 1.15rem;
        font-weight: 700;
        color: #0f172a;
    }
    
    .portal-option-card p {
        margin: 0;
        font-size: 0.88rem;
        color: #475569;
        line-height: 1.5;
    }

    .portal-option-card .check-indicator {
        position: absolute;
        top: 15px;
        right: 15px;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: #10b981;
        color: white;
        display: none;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
    }

    .portal-option-card.active .check-indicator {
        display: flex;
    }

    /* Section animations */
    .form-switch-section {
        display: none;
        animation: fadeInSlide 0.4s ease forwards;
    }

    @keyframes fadeInSlide {
        from { opacity: 0; transform: translateY(15px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Upload slots styling */
    .upload-slots-container {
        background: #f8fafc;
        border: 1.5px dashed #cbd5e1;
        border-radius: 12px;
        padding: 20px;
        margin-top: 15px;
        transition: all 0.3s ease;
    }

    .upload-slots-container.disabled {
        opacity: 0.5;
        pointer-events: none;
        background: #f1f5f9;
        border-color: #e2e8f0;
    }

    .upload-slot {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 16px;
        margin-bottom: 12px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.02);
        transition: border-color 0.2s;
    }

    .upload-slot:hover {
        border-color: #10b981;
    }

    .upload-slot label {
        font-size: 0.9rem;
        font-weight: 600;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .upload-slot label span.req {
        color: #ef4444;
    }

    .upload-slot p.slot-desc {
        margin: 0;
        font-size: 0.78rem;
        color: #64748b;
        line-height: 1.4;
    }

    .upload-slot input[type="file"] {
        font-size: 0.85rem;
        color: #475569;
        padding: 6px 0;
    }

    /* Success QR code layouts */
    .success-qr-card {
        background: white;
        border-radius: 20px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.06);
        padding: 35px;
        text-align: center;
        border: 1px solid #e2e8f0;
        max-width: 550px;
        margin: 30px auto;
        animation: popIn 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
    }

    @keyframes popIn {
        from { transform: scale(0.9); opacity: 0; }
        to { transform: scale(1); opacity: 1; }
    }

    .success-qr-icon {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        background: #ecfdf5;
        color: #10b981;
        font-size: 2.2rem;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
    }

    .qr-image-wrapper {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 20px;
        display: inline-block;
        margin: 20px 0;
    }

    .qr-image-wrapper img {
        display: block;
        max-width: 220px;
        height: auto;
    }

    .qr-token-label {
        font-family: monospace;
        font-size: 1.15rem;
        font-weight: 700;
        color: #0f172a;
        margin-top: 10px;
        background: #e2e8f0;
        padding: 6px 16px;
        border-radius: 8px;
        display: inline-block;
        letter-spacing: 1px;
    }

    .appointment-box {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 12px;
        padding: 16px;
        margin: 20px 0;
        text-align: left;
        display: flex;
        gap: 12px;
    }

    .appointment-box i {
        color: #2563eb;
        font-size: 1.25rem;
        margin-top: 2px;
    }

    .appointment-box div h5 {
        margin: 0 0 4px 0;
        font-size: 0.95rem;
        font-weight: 700;
        color: #1e3a8a;
    }

    .appointment-box div p {
        margin: 0;
        font-size: 0.85rem;
        color: #1e40af;
        line-height: 1.5;
    }

    /* Verification style on Phase 2 */
    .verification-status-panel {
        background: #f8fafc;
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 20px;
    }

    .verify-btn {
        background: #0f172a;
        color: white;
        border: none;
        padding: 10px 18px;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.9rem;
        transition: background 0.2s;
    }

    .verify-btn:hover {
        background: #1e293b;
    }

    .alert-banner {
        padding: 12px 16px;
        border-radius: 8px;
        font-size: 0.88rem;
        font-weight: 500;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .alert-banner-error {
        background: #fef2f2;
        border: 1px solid #fca5a5;
        color: #b91c1c;
    }

    .alert-banner-success {
        background: #ecfdf5;
        border: 1px solid #6ee7b7;
        color: #065f46;
    }

    .form-row-names {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr 0.5fr;
        gap: 16px;
    }

    .form-subheading {
        margin: 22px 0 14px;
        padding-bottom: 8px;
        border-bottom: 1px solid #e2e8f0;
        color: #334155;
        font-size: 0.88rem;
        font-weight: 800;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .field-help {
        display: block;
        margin-top: 6px;
        color: #64748b;
        font-size: 0.78rem;
        line-height: 1.4;
    }

    .benefit-specific-panel {
        margin-top: 18px;
        padding: 18px;
        border: 1px solid #bfdbfe;
        border-radius: 14px;
        background: #f8fbff;
    }

    .benefit-specific-panel[hidden] { display: none; }

    .benefit-panel-title {
        margin: 0 0 6px;
        color: #1e3a8a;
        font-size: 1rem;
        font-weight: 800;
    }

    .benefit-panel-copy {
        margin: 0 0 16px;
        color: #64748b;
        font-size: 0.84rem;
    }
    @media (max-width: 600px) {
        .form-row-names {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php if (!$proxySuccess): ?>
    
    <!-- Option Selection Header -->
    <div style="margin-bottom: 25px; text-align: center;">
        <h3 style="margin-bottom: 10px; color: #0f172a; font-weight: 700;">Select Transaction Portal</h3>
        <p style="color: #64748b; font-size: 0.95rem; margin: 0;">Choose the application service needed by the senior citizen.</p>
    </div>

    <!-- Portal Option Choice Cards -->
    <div class="portal-option-grid" id="portalOptionGrid">
        <div class="portal-option-card<?php echo $activePortalOption === 'new_senior' ? ' active' : ''; ?>" id="optionCardNew" onclick="selectPortalPath('new_senior')">
            <div class="check-indicator"><i class="fas fa-check"></i></div>
            <div class="option-icon" style="background: #10b981;">
                <i class="fas fa-user-plus"></i>
            </div>
            <h4>New Senior Pre-Registration</h4>
            <p>Start a public application for any eligible senior citizen. Assistance may be provided when needed.</p>
        </div>

        <div class="portal-option-card<?php echo $activePortalOption === 'existing_benefits' ? ' active' : ''; ?>" id="optionCardExisting" onclick="selectPortalPath('existing_benefits')">
            <div class="check-indicator"><i class="fas fa-check"></i></div>
            <div class="option-icon" style="background: #3b82f6;">
                <i class="fas fa-file-invoice-dollar"></i>
            </div>
            <h4>Apply for Existing Senior Benefits</h4>
            <p>For any approved senior citizen. Enter the Senior ID to apply for the Local Senior Pension benefit.</p>
        </div>
    </div>

    <!-- Display backend error message if any -->
    <?php if (!empty($proxyMessage)): ?>
        <div class="alert-banner alert-banner-error">
            <i class="fas fa-exclamation-circle"></i>
            <div><?php echo htmlspecialchars($proxyMessage); ?></div>
        </div>
    <?php endif; ?>

    <!-- ── FORM A: NEW SENIOR PRE-REGISTRATION ── -->
    <div id="newSeniorFormSection" class="form-switch-section" style="display: <?php echo $activePortalOption === 'new_senior' ? 'block' : 'none'; ?>;">
        <form method="POST" action="<?php echo htmlspecialchars($formAction); ?>" enctype="multipart/form-data" class="proxy-form" id="newSeniorForm">
            <input type="hidden" name="proxy_submit" value="1">
            <input type="hidden" name="portal_option" value="new_senior">

            <!-- Privacy Alert -->
            <div class="privacy-alert" role="note" style="margin-bottom: 24px;">
                <i class="fas fa-shield-alt" aria-hidden="true"></i>
                <div>
                    <strong>RA 10173 Compliance Guard:</strong> This portal processes sensitive personal details under the Philippine Data Privacy Act of 2012. Documents uploaded will be securely processed and mapped for counter verification.
                </div>
            </div>

            <!-- Step 1 Heading -->
            <div class="step-heading">
                <div class="step-number">1</div>
                <div>
                    <h3>Application and Senior Citizen Information</h3>
                    <p>Select the requested benefit, then enter the senior's details exactly as they appear in official records.</p>
                </div>
            </div>

            <div class="form-section">
                <div class="form-subheading" style="margin-top:0;">Benefit or service requested</div>
                <div class="form-group">
                    <label for="requestedBenefit">What is the senior applying for? <span style="color:#b91c1c;">*</span></label>
                    <select id="requestedBenefit" name="requestedBenefit" class="form-control" required>
                        <option value="">— Select Benefit or Service —</option>
                        <?php foreach ([
                            'Senior Citizen ID Registration',
                            'Local Social Pension Assessment',
                            'Land Bank Cash Card Enrollment',
                            'Milestone Cash Gift',
                            'Other OSCA Assistance',
                        ] as $value): ?>
                            <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $old('requestedBenefit') === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($value); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="field-help">This tells the reviewing office which service or benefit should be assessed.</small>
                </div>

                <!-- Name Row -->
                <div class="form-row-names">
                    <div class="form-group">
                        <label for="lastName">Last Name <span style="color:#b91c1c;">*</span></label>
                        <input type="text" id="lastName" name="lastName" class="form-control" placeholder="e.g. Dela Cruz" value="<?php echo $old('lastName'); ?>" autocomplete="family-name" required>
                    </div>
                    <div class="form-group">
                        <label for="firstName">First Name <span style="color:#b91c1c;">*</span></label>
                        <input type="text" id="firstName" name="firstName" class="form-control" placeholder="e.g. Tomas" value="<?php echo $old('firstName'); ?>" autocomplete="given-name" required>
                    </div>
                    <div class="form-group">
                        <label for="middleName">Middle Name</label>
                        <input type="text" id="middleName" name="middleName" class="form-control" placeholder="e.g. Santos" value="<?php echo $old('middleName'); ?>" autocomplete="additional-name">
                    </div>
                    <div class="form-group">
                        <label for="suffix">Suffix</label>
                        <input type="text" id="suffix" name="suffix" class="form-control" placeholder="e.g. Sr." value="<?php echo $old('suffix'); ?>">
                    </div>
                </div>

                <!-- Date of Birth & Contact -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="birthDate">Birth Date <span style="color:#b91c1c;">*</span></label>
                        <input type="date" id="birthDate" name="birthDate" class="form-control" value="<?php echo $old('birthDate'); ?>" autocomplete="bday" required onchange="calculateProxyAge2026()">
                        <div id="ageCheckStatus" style="margin-top: 8px;"></div>
                    </div>
                    <div class="form-group">
                        <label for="contactNumber">Contact Number <span style="color:#b91c1c;">*</span></label>
                        <input type="tel" id="contactNumber" name="contactNumber" class="form-control" maxlength="11" pattern="09[0-9]{9}" inputmode="numeric" placeholder="e.g. 09123456789" value="<?php echo $old('contactNumber'); ?>" autocomplete="tel" required>
                        <small class="field-help">Use an active 11-digit Philippine mobile number.</small>
                    </div>
                </div>

                <div class="form-subheading">Personal identity</div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="placeOfBirth">Place of Birth <span style="color:#b91c1c;">*</span></label>
                        <input type="text" id="placeOfBirth" name="placeOfBirth" class="form-control" value="<?php echo $old('placeOfBirth'); ?>" placeholder="City / Municipality, Province" required>
                    </div>
                    <div class="form-group">
                        <label for="mothersMaidenName">Mother's Maiden Name</label>
                        <input type="text" id="mothersMaidenName" name="mothersMaidenName" class="form-control" value="<?php echo $old('mothersMaidenName'); ?>" placeholder="Full maiden name">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="gender">Sex <span style="color:#b91c1c;">*</span></label>
                        <select id="gender" name="gender" class="form-control" required>
                            <option value="">— Select Sex —</option>
                            <?php foreach (['Male', 'Female'] as $value): ?>
                                <option value="<?php echo $value; ?>" <?php echo $old('gender') === $value ? 'selected' : ''; ?>><?php echo $value; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="civilStatus">Civil Status <span style="color:#b91c1c;">*</span></label>
                        <select id="civilStatus" name="civilStatus" class="form-control" required>
                            <option value="">— Select Civil Status —</option>
                            <?php foreach (['Single', 'Married', 'Widowed', 'Separated'] as $value): ?>
                                <option value="<?php echo $value; ?>" <?php echo $old('civilStatus') === $value ? 'selected' : ''; ?>><?php echo $value; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Address & Barangay -->
                <div class="form-subheading">Current home address</div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="houseNo">House / Unit No. <span style="color:#b91c1c;">*</span></label>
                        <input type="text" id="houseNo" name="houseNo" class="form-control" value="<?php echo $old('houseNo'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="street">Street / Subdivision <span style="color:#b91c1c;">*</span></label>
                        <input type="text" id="street" name="street" class="form-control" value="<?php echo $old('street'); ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="barangay">Barangay <span style="color:#b91c1c;">*</span></label>
                        <select id="barangay" name="barangay" class="form-control" required>
                            <option value="">— Select Barangay —</option>
                            <?php foreach ($barangays_list as $b): ?>
                                <option value="<?php echo htmlspecialchars($b); ?>" <?php echo $old('barangay') === $b ? 'selected' : ''; ?>><?php echo htmlspecialchars($b); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="zipCode">ZIP Code</label>
                        <input type="text" id="zipCode" name="zipCode" class="form-control" maxlength="4" pattern="[0-9]{4}" inputmode="numeric" value="<?php echo $old('zipCode', '1600'); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="landmark">Nearest Landmark</label>
                        <input type="text" id="landmark" name="landmark" class="form-control" value="<?php echo $old('landmark'); ?>" placeholder="Helps the home-visit team locate the senior">
                    </div>
                    <div class="form-group">
                        <label for="seniorEmail">Senior's Email Address</label>
                        <input type="email" id="seniorEmail" name="seniorEmail" class="form-control" value="<?php echo $old('seniorEmail'); ?>" autocomplete="email" placeholder="Optional">
                    </div>
                </div>
                <input type="hidden" id="completeAddress" name="completeAddress" value="<?php echo $old('completeAddress'); ?>">

                <div class="form-subheading">Senior support and mobility information</div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="mobilityStatus">Mobility Status <span style="color:#b91c1c;">*</span></label>
                        <select id="mobilityStatus" name="mobilityStatus" class="form-control" required>
                            <option value="">— Select Mobility Status —</option>
                            <?php foreach (['Physically Fit', 'Needs Mobility Assistance', 'Bedridden', 'Frail / Sickly', 'PWD'] as $value): ?>
                                <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $old('mobilityStatus') === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($value); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="livingArrangement">Living Arrangement <span style="color:#b91c1c;">*</span></label>
                        <select id="livingArrangement" name="livingArrangement" class="form-control" required>
                            <option value="">— Select Arrangement —</option>
                            <?php foreach (['Living alone', 'With spouse', 'With children or relatives', 'With caregiver', 'Care facility'] as $value): ?>
                                <option value="<?php echo $value; ?>" <?php echo $old('livingArrangement') === $value ? 'selected' : ''; ?>><?php echo $value; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="healthCondition">Medical or Support Notes</label>
                    <input type="text" id="healthCondition" name="healthCondition" class="form-control" value="<?php echo $old('healthCondition'); ?>" placeholder="Optional: mobility aid, medical condition, or assistance needed">
                </div>
                <div class="form-group">
                    <label for="visitPurpose">Requested Assistance <span style="color:#b91c1c;">*</span></label>
                    <textarea id="visitPurpose" name="visitPurpose" class="form-control" rows="2" required placeholder="Describe the registration, benefit, or assistance being requested."><?php echo $old('visitPurpose'); ?></textarea>
                </div>

                <div class="form-subheading">Benefit-specific information</div>
                <div id="benefitFieldsPrompt" class="privacy-alert" style="margin-bottom:0; background:#f8fafc; border-color:#cbd5e1; color:#475569;">
                    <i class="fas fa-hand-pointer" aria-hidden="true" style="color:#64748b;"></i>
                    <div>Select a benefit or service above to display its required questions.</div>
                </div>

                <div class="benefit-specific-panel" data-benefits="Senior Citizen ID Registration" hidden>
                    <h4 class="benefit-panel-title">Senior Citizen ID Registration</h4>
                    <p class="benefit-panel-copy">Complete the additional fields shown on the official Senior Citizens ID Application.</p>
                    <div class="form-group">
                        <label for="idPurpose">ID Application Purpose <span style="color:#b91c1c;">*</span></label>
                        <select id="idPurpose" name="idPurpose" class="form-control" data-benefit-required>
                            <option value="">— Select Purpose —</option>
                            <option value="new" <?php echo $old('idPurpose') === 'new' ? 'selected' : ''; ?>>New / First-time registration</option>
                            <option value="lost" <?php echo $old('idPurpose') === 'lost' ? 'selected' : ''; ?>>Lost ID replacement</option>
                            <option value="change" <?php echo $old('idPurpose') === 'change' ? 'selected' : ''; ?>>Information change</option>
                            <option value="transfer" <?php echo $old('idPurpose') === 'transfer' ? 'selected' : ''; ?>>Transfer</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="emergencyContactName">Emergency Contact Name <span style="color:#b91c1c;">*</span></label>
                            <input type="text" id="emergencyContactName" name="emergencyContactName" class="form-control" value="<?php echo $old('emergencyContactName'); ?>" data-benefit-required>
                        </div>
                        <div class="form-group">
                            <label for="emergencyContact">Emergency Contact Number <span style="color:#b91c1c;">*</span></label>
                            <input type="tel" id="emergencyContact" name="emergencyContact" class="form-control" maxlength="11" pattern="09[0-9]{9}" inputmode="numeric" value="<?php echo $old('emergencyContact'); ?>" data-benefit-required>
                        </div>
                    </div>
                </div>

                <div class="benefit-specific-panel" data-benefits="Local Social Pension Assessment" hidden>
                    <h4 class="benefit-panel-title">Social Pension Assessment</h4>
                    <p class="benefit-panel-copy">These details are used to assess pension and household-income eligibility.</p>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="isPensioner">Currently receiving any pension? <span style="color:#b91c1c;">*</span></label>
                            <select id="isPensioner" name="isPensioner" class="form-control" data-benefit-required>
                                <option value="">— Select —</option>
                                <option value="1" <?php echo $old('isPensioner') === '1' ? 'selected' : ''; ?>>Yes</option>
                                <option value="0" <?php echo $old('isPensioner') === '0' ? 'selected' : ''; ?>>No</option>
                            </select>
                        </div>
                        <div class="form-group" id="pensionSourceGroup">
                            <label for="pensionSource">Pension Source</label>
                            <input type="text" id="pensionSource" name="pensionSource" class="form-control" value="<?php echo $old('pensionSource'); ?>" placeholder="e.g. SSS, GSIS, private pension">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="sssNumber">SSS / GSIS Number</label>
                            <input type="text" id="sssNumber" name="sssNumber" class="form-control" value="<?php echo $old('sssNumber'); ?>" placeholder="Enter if available">
                        </div>
                        <div class="form-group">
                            <label for="pensionAmount">Current Monthly Pension Amount</label>
                            <input type="number" id="pensionAmount" name="pensionAmount" class="form-control" value="<?php echo $old('pensionAmount'); ?>" min="0" step="0.01" placeholder="0.00">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="familySupport">Receives regular family support? <span style="color:#b91c1c;">*</span></label>
                            <select id="familySupport" name="familySupport" class="form-control" data-benefit-required>
                                <option value="">— Select —</option>
                                <option value="1" <?php echo $old('familySupport') === '1' ? 'selected' : ''; ?>>Yes</option>
                                <option value="0" <?php echo $old('familySupport') === '0' ? 'selected' : ''; ?>>No</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="familySupportAmount">Monthly Family Support Amount</label>
                            <input type="number" id="familySupportAmount" name="familySupportAmount" class="form-control" value="<?php echo $old('familySupportAmount'); ?>" min="0" step="0.01" placeholder="0.00">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="personalIncome">Has personal income? <span style="color:#b91c1c;">*</span></label>
                            <select id="personalIncome" name="personalIncome" class="form-control" data-benefit-required>
                                <option value="">— Select —</option>
                                <option value="1" <?php echo $old('personalIncome') === '1' ? 'selected' : ''; ?>>Yes</option>
                                <option value="0" <?php echo $old('personalIncome') === '0' ? 'selected' : ''; ?>>No</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="personalIncomeAmount">Monthly Personal Income</label>
                            <input type="number" id="personalIncomeAmount" name="personalIncomeAmount" class="form-control" value="<?php echo $old('personalIncomeAmount'); ?>" min="0" step="0.01" placeholder="0.00">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="incomeSource">Permanent Income Source <span style="color:#b91c1c;">*</span></label>
                            <input type="text" id="incomeSource" name="incomeSource" class="form-control" value="<?php echo $old('incomeSource'); ?>" placeholder="Enter None if not applicable" data-benefit-required>
                        </div>
                        <div class="form-group">
                            <label for="ownsHouse">Owns House? <span style="color:#b91c1c;">*</span></label>
                            <select id="ownsHouse" name="ownsHouse" class="form-control" data-benefit-required>
                                <option value="">— Select —</option><option value="1" <?php echo $old('ownsHouse') === '1' ? 'selected' : ''; ?>>Yes</option><option value="0" <?php echo $old('ownsHouse') === '0' ? 'selected' : ''; ?>>No</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="isRenter">Renter? <span style="color:#b91c1c;">*</span></label>
                            <select id="isRenter" name="isRenter" class="form-control" data-benefit-required>
                                <option value="">— Select —</option><option value="1" <?php echo $old('isRenter') === '1' ? 'selected' : ''; ?>>Yes</option><option value="0" <?php echo $old('isRenter') === '0' ? 'selected' : ''; ?>>No</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="benefit-specific-panel" data-benefits="Land Bank Cash Card Enrollment" hidden>
                    <h4 class="benefit-panel-title">Land Bank Cash Card Enrollment</h4>
                    <p class="benefit-panel-copy">Enter the information required for bank enrollment and identity checks.</p>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nameOnCard">Name to Appear on Card <span style="color:#b91c1c;">*</span></label>
                            <input type="text" id="nameOnCard" name="nameOnCard" class="form-control" maxlength="23" value="<?php echo $old('nameOnCard'); ?>" data-benefit-required>
                        </div>
                        <div class="form-group">
                            <label for="tin">TIN <span style="color:#b91c1c;">*</span></label>
                            <input type="text" id="tin" name="tin" class="form-control" value="<?php echo $old('tin'); ?>" data-benefit-required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nationality">Nationality <span style="color:#b91c1c;">*</span></label>
                            <input type="text" id="nationality" name="nationality" class="form-control" value="<?php echo $old('nationality', 'Filipino'); ?>" data-benefit-required>
                        </div>
                        <div class="form-group">
                            <label for="seniorIdTypePresented">Senior's ID Presented <span style="color:#b91c1c;">*</span></label>
                            <input type="text" id="seniorIdTypePresented" name="seniorIdTypePresented" class="form-control" value="<?php echo $old('seniorIdTypePresented'); ?>" data-benefit-required placeholder="e.g. PhilSys ID, passport">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="sourceOfFunds">Source of Funds <span style="color:#b91c1c;">*</span></label>
                        <input type="text" id="sourceOfFunds" name="sourceOfFunds" class="form-control" value="<?php echo $old('sourceOfFunds'); ?>" data-benefit-required placeholder="e.g. Social pension, family support">
                    </div>
                </div>

                <div class="benefit-specific-panel" data-benefits="Milestone Cash Gift" hidden>
                    <h4 class="benefit-panel-title">Milestone Cash Gift</h4>
                    <p class="benefit-panel-copy">Complete the milestone and claimant fields shown on the official Octogenarian, Nonagenarian, and Centenarian form.</p>
                    <div class="form-group">
                        <label for="milestoneAge">Milestone Age <span style="color:#b91c1c;">*</span></label>
                        <select id="milestoneAge" name="milestoneAge" class="form-control" data-benefit-required>
                            <option value="">— Select Milestone —</option>
                            <?php foreach (['80', '85', '90', '95', '100'] as $value): ?>
                                <option value="<?php echo $value; ?>" <?php echo $old('milestoneAge') === $value ? 'selected' : ''; ?>><?php echo $value === '100' ? '100+ years old' : $value . ' years old'; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label for="claimantName">Claimant Name <span style="color:#b91c1c;">*</span></label><input type="text" id="claimantName" name="claimantName" class="form-control" value="<?php echo $old('claimantName'); ?>" data-benefit-required></div>
                        <div class="form-group"><label for="claimantRelationship">Relationship <span style="color:#b91c1c;">*</span></label><input type="text" id="claimantRelationship" name="claimantRelationship" class="form-control" value="<?php echo $old('claimantRelationship'); ?>" data-benefit-required placeholder="Self, child, spouse, etc."></div>
                        <div class="form-group"><label for="claimantContact">Claimant Contact <span style="color:#b91c1c;">*</span></label><input type="tel" id="claimantContact" name="claimantContact" class="form-control" maxlength="11" pattern="09[0-9]{9}" inputmode="numeric" value="<?php echo $old('claimantContact'); ?>" data-benefit-required></div>
                    </div>
                </div>

                <div class="benefit-specific-panel" data-benefits="Other OSCA Assistance" hidden>
                    <h4 class="benefit-panel-title">Other OSCA Assistance</h4>
                    <p class="benefit-panel-copy">Specify the service or assistance not listed above.</p>
                    <div class="form-group">
                        <label for="otherAssistanceDetails">Assistance Details <span style="color:#b91c1c;">*</span></label>
                        <textarea id="otherAssistanceDetails" name="otherAssistanceDetails" class="form-control" rows="3" data-benefit-required><?php echo $old('otherAssistanceDetails'); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Step 2 Heading (Dynamic Uploads) -->
            <div class="step-heading" style="margin-top: 25px;">
                <div class="step-number">2</div>
                <div>
                    <h3>Required Target Documents</h3>
                    <p>Upload digital copies (PDF or JPEG) below. Enabled upon senior age eligibility verification.</p>
                </div>
            </div>

            <!-- Dynamic Upload Slots Container -->
            <div class="upload-slots-container disabled" id="uploadSlotsContainer">
                
                <h5 style="margin-top:0; color:#475569; font-size:0.88rem; text-transform:uppercase; letter-spacing:0.5px;">Senior's Credentials</h5>
                
                <div class="upload-slot">
                    <label for="psa_birth_cert_file"><span data-document-label="primary">PSA Birth Certificate</span> <span class="req">*</span></label>
                    <p class="slot-desc" data-document-description="primary">Upload a clear scanned copy or photo of the senior citizen's PSA birth certificate.</p>
                    <input type="file" id="psa_birth_cert_file" name="psa_birth_cert_file" accept="image/jpeg,application/pdf" required>
                </div>
                
                <div class="upload-slot">
                    <label for="barangay_residency_file"><span data-document-label="secondary">Barangay Residency Certificate</span> <span class="req">*</span></label>
                    <p class="slot-desc" data-document-description="secondary">Issued by the barangay within the last 6 months confirming Pasig residency.</p>
                    <input type="file" id="barangay_residency_file" name="barangay_residency_file" accept="image/jpeg,application/pdf" required>
                </div>

                <div class="upload-slot">
                    <label for="comelec_cert_file"><span data-document-label="supporting">2-Year COMELEC Certification</span> <span class="req">*</span></label>
                    <p class="slot-desc" data-document-description="supporting">Official voter residency certification of the senior citizen applicant.</p>
                    <input type="file" id="comelec_cert_file" name="comelec_cert_file" accept="image/jpeg,application/pdf" required>
                </div>

                <h5 style="margin-top:20px; color:#475569; font-size:0.88rem; text-transform:uppercase; letter-spacing:0.5px;">Senior Identity Confirmation</h5>
                
                <div class="upload-slot">
                    <label for="proof_of_life_file">Current Senior Photo (Proof of Life) <span class="req">*</span></label>
                    <p class="slot-desc"><strong>Important:</strong> Upload a clear and current photo of the senior with the date visible on a phone screen or written card. A bedside photo is needed only when the senior is homebound.</p>
                    <input type="file" id="proof_of_life_file" name="proof_of_life_file" accept="image/jpeg" required>
                </div>

                <h5 style="margin-top:20px; color:#475569; font-size:0.88rem; text-transform:uppercase; letter-spacing:0.5px;">Representative Verification Documents</h5>
                
                <div class="upload-slot">
                    <label for="auth_letter_file">Authorization Letter <span class="field-help">(Optional)</span></label>
                    <p class="slot-desc">Authorization letter signed or marked with the senior's thumbmark authorizing the representative.</p>
                    <input type="file" id="auth_letter_file" name="auth_letter_file" accept="image/jpeg,application/pdf">
                </div>

                <div class="upload-slot">
                    <label for="proxy_id_file">Representative's Government ID <span class="field-help">(Optional)</span></label>
                    <p class="slot-desc">Valid government ID of the authorized representative.</p>
                    <input type="file" id="proxy_id_file" name="proxy_id_file" accept="image/jpeg,application/pdf">
                </div>

                <div class="upload-slot">
                    <label for="proxy_birth_cert_file">Representative's Birth Certificate <span class="field-help">(Optional)</span></label>
                    <p class="slot-desc">Representative's birth certificate or equivalent document proving the relationship to the senior.</p>
                    <input type="file" id="proxy_birth_cert_file" name="proxy_birth_cert_file" accept="image/jpeg,application/pdf">
                </div>
            </div>

            <!-- Step 3 Heading -->
            <div class="step-heading" style="margin-top: 25px;">
                <div class="step-number">3</div>
                <div>
                    <h3>Representative Details <small style="font-size:.72em;font-weight:500;color:#64748b;">(Optional)</small></h3>
                    <p>Complete this section only when another person is submitting on behalf of the senior citizen.</p>
                </div>
            </div>

            <div class="form-section">
                <div class="form-row">
                    <div class="form-group">
                        <label for="proxyName">Representative Name</label>
                        <input type="text" id="proxyName" name="proxyName" class="form-control" placeholder="Optional" value="<?php echo $old('proxyName'); ?>" autocomplete="name">
                    </div>
                    <div class="form-group">
                        <label for="proxyRelationship">Relationship to Senior</label>
                        <select id="proxyRelationship" name="proxyRelationship" class="form-control">
                            <option value="">— Select Relationship —</option>
                            <?php foreach (['Grandchild', 'Daughter', 'Son', 'Spouse', 'Sibling', 'Caregiver', 'Other'] as $value): ?>
                                <option value="<?php echo $value; ?>" <?php echo $old('proxyRelationship') === $value ? 'selected' : ''; ?>><?php echo $value; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="proxyContactNumber">Representative Contact Number</label>
                        <input type="tel" id="proxyContactNumber" name="proxyContactNumber" class="form-control" placeholder="Optional" maxlength="11" pattern="09[0-9]{9}" inputmode="numeric" value="<?php echo $old('proxyContactNumber'); ?>" autocomplete="tel">
                    </div>
                    <div class="form-group">
                        <label for="proxyEmail">Representative Email Address</label>
                        <input type="email" id="proxyEmail" name="proxyEmail" class="form-control" value="<?php echo $old('proxyEmail'); ?>" autocomplete="email" placeholder="Optional">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="proxyBirthDate">Representative Birth Date</label>
                        <input type="date" id="proxyBirthDate" name="proxyBirthDate" class="form-control" value="<?php echo $old('proxyBirthDate'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="proxyAddress">Representative Complete Address</label>
                        <input type="text" id="proxyAddress" name="proxyAddress" class="form-control" value="<?php echo $old('proxyAddress'); ?>" autocomplete="street-address" placeholder="Optional">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="proxyIdType">Government ID Type</label>
                        <select id="proxyIdType" name="proxyIdType" class="form-control">
                            <option value="">— Select ID Type —</option>
                            <?php foreach (['PhilSys ID', 'Driver\'s License', 'Passport', 'UMID', 'Voter\'s ID', 'Postal ID', 'PRC ID', 'Other government ID'] as $value): ?>
                                <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $old('proxyIdType') === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($value); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="proxyIdNumber">Government ID Number</label>
                        <input type="text" id="proxyIdNumber" name="proxyIdNumber" class="form-control" value="<?php echo $old('proxyIdNumber'); ?>" placeholder="Optional">
                    </div>
                </div>

                <div class="checkbox-row" style="margin-top: 15px;">
                    <input type="checkbox" id="confirmRepresentative" name="confirmRepresentative" required style="width: 18px; height: 18px; accent-color: #10b981;">
                    <label for="confirmRepresentative" style="font-size: 0.85rem; font-weight: 500; color: #475569; margin: 0;">
                        I certify that the information is accurate and that I am the senior applicant or am authorized to assist the senior citizen.
                    </label>
                </div>
                <div class="checkbox-row" style="margin-top: 12px;">
                    <input type="checkbox" id="confirmPrivacy" name="confirmPrivacy" required style="width: 18px; height: 18px; accent-color: #10b981;">
                    <label for="confirmPrivacy" style="font-size: 0.85rem; font-weight: 500; color: #475569; margin: 0;">
                        I confirm that the senior authorized this submission and consented to the collection and verification of the information and documents provided.
                    </label>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn btn-accent btn-block" id="btnSubmitNew" style="margin-top: 25px; padding: 14px; font-size: 1rem; background: #10b981; border-color: #10b981;" disabled>
                <i class="fas fa-qrcode"></i> Submit Pre-Registration
            </button>
        </form>
    </div>

    <!-- ── FORM B: APPLY FOR EXISTING SENIOR BENEFITS (LOCAL SENIOR PENSION) ── -->
    <div id="existingBenefitsSection" class="form-switch-section" style="display: <?php echo $activePortalOption === 'existing_benefits' ? 'block' : 'none'; ?>;">
        <form method="POST" action="<?php echo htmlspecialchars($formAction); ?>" enctype="multipart/form-data" class="proxy-form" id="existingPensionForm">
            <input type="hidden" name="proxy_submit" value="1">
            <input type="hidden" name="portal_option" value="existing_benefits">
            <input type="hidden" name="requestedBenefit" value="Local Senior Pension Benefit">

            <div class="privacy-alert" role="note" style="margin-bottom: 24px; background: #eff6ff; border-color: #bfdbfe; color: #1e40af;">
                <i class="fas fa-info-circle" aria-hidden="true" style="color: #2563eb;"></i>
                <div>
                    <strong>Secondary Benefit Enrollment:</strong> Any verified senior citizen may apply for an eligible municipal pension benefit through an authorized representative.
                    <div style="margin-top:8px;"><strong>Benefit being applied for:</strong> Local Senior Pension Benefit</div>
                </div>
            </div>

            <div class="step-heading">
                <div class="step-number">1</div>
                <div>
                    <h3>Senior Citizen ID Lookup</h3>
                    <p>Enter the senior citizen's ID number to run the profile integrity verification.</p>
                </div>
            </div>

            <div class="form-section">
                <div class="form-row" style="align-items: flex-end;">
                    <div class="form-group" style="flex: 2;">
                        <label for="seniorCitizenId">Senior Citizen ID Number <span style="color:#b91c1c;">*</span></label>
                        <input type="text" id="seniorCitizenId" name="seniorCitizenId" class="form-control" placeholder="e.g. PRX-XXXXXX or Senior ID" value="<?php echo $old('seniorCitizenId'); ?>" required>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <button type="button" class="verify-btn" onclick="verifySeniorID()">Verify Profile</button>
                    </div>
                </div>
                
                <!-- Dynamic lookup result panel -->
                <div id="verificationResultPanel" style="display: none; margin-top: 15px;"></div>
            </div>

            <!-- Dynamic Pension upload form (revealed only upon verification success) -->
            <div id="pensionUploadsGroup" style="display: none;">
                <div class="step-heading" style="margin-top: 25px;">
                    <div class="step-number">2</div>
                    <div>
                        <h3>Local Senior Pension Requirements</h3>
                        <p>Upload the required documentation below to complete the pension benefit claim application.</p>
                    </div>
                </div>

                <div class="upload-slots-container">
                    <div class="upload-slot">
                        <label for="home_visitation_form_file">Social Worker Confirmation Form <span class="req">*</span></label>
                        <p class="slot-desc">A scanned copy or clear image of the Home Visitation/Confirmation Form signed by the local social worker.</p>
                        <input type="file" id="home_visitation_form_file" name="home_visitation_form_file" accept="image/jpeg,application/pdf" required>
                    </div>

                    <div class="upload-slot">
                        <label for="landbank_enrollment_form_file">Land Bank Cash Card Enrollment Form <span class="req">*</span></label>
                        <p class="slot-desc">Fully filled-out Land Bank Cash Card Enrollment Form for pension disbursement setup.</p>
                        <input type="file" id="landbank_enrollment_form_file" name="landbank_enrollment_form_file" accept="image/jpeg,application/pdf" required>
                    </div>
                </div>

                <!-- Submit Pension Button -->
                <button type="submit" class="btn btn-accent btn-block" style="margin-top: 25px; padding: 14px; font-size: 1rem; background: #3b82f6; border-color: #3b82f6;">
                    <i class="fas fa-file-invoice-dollar"></i> Submit Pension Benefit Claim
                </button>
            </div>
        </form>
    </div>

    <!-- Client-Side Form Scripts -->
    <script>
        // Switch between Option A (New Pre-Reg) and Option B (Existing Pension)
        function selectPortalPath(path) {
            document.querySelectorAll('.portal-option-card').forEach(card => card.classList.remove('active'));
            
            if (path === 'new_senior') {
                document.getElementById('optionCardNew').classList.add('active');
                document.getElementById('newSeniorFormSection').style.display = 'block';
                document.getElementById('existingBenefitsSection').style.display = 'none';
            } else {
                document.getElementById('optionCardExisting').classList.add('active');
                document.getElementById('newSeniorFormSection').style.display = 'none';
                document.getElementById('existingBenefitsSection').style.display = 'block';
            }
        }

        // Rule-Based Age check: If Age in 2026 >= 60, enable upload slots
        function calculateProxyAge2026() {
            const birthDateInput = document.getElementById('birthDate');
            const ageCheckStatus = document.getElementById('ageCheckStatus');
            const uploadSlotsContainer = document.getElementById('uploadSlotsContainer');
            const submitBtn = document.getElementById('btnSubmitNew');
            
            if (!birthDateInput.value) {
                ageCheckStatus.innerHTML = '';
                uploadSlotsContainer.classList.add('disabled');
                submitBtn.disabled = true;
                return;
            }

            const dob = new Date(birthDateInput.value);
            const targetDate = new Date();
            let age = targetDate.getFullYear() - dob.getFullYear();
            const monthDiff = targetDate.getMonth() - dob.getMonth();
            if (monthDiff < 0 || (monthDiff === 0 && targetDate.getDate() < dob.getDate())) {
                age--;
            }

            if (age >= 60) {
                // Pass Rule
                ageCheckStatus.innerHTML = `<span style="color: #059669; font-weight: 600;"><i class="fas fa-check-circle"></i> Eligible Senior Citizen (Current age: ${age} years old)</span>`;
                uploadSlotsContainer.classList.remove('disabled');
                
                // Remove disabled state from file inputs
                uploadSlotsContainer.querySelectorAll('input[type="file"]').forEach(input => {
                    input.removeAttribute('disabled');
                });
                submitBtn.disabled = false;
            } else {
                // Fail Rule
                ageCheckStatus.innerHTML = `<span style="color: #dc2626; font-weight: 600;"><i class="fas fa-times-circle"></i> Ineligible (Age in 2026: ${age} years old. Must be 60 years or older).</span>`;
                uploadSlotsContainer.classList.add('disabled');
                
                // Disable file inputs
                uploadSlotsContainer.querySelectorAll('input[type="file"]').forEach(input => {
                    input.setAttribute('disabled', 'true');
                });
                submitBtn.disabled = true;
            }
        }

        function syncCompleteAddress() {
            const barangay = document.getElementById('barangay')?.value;
            const parts = [
                document.getElementById('houseNo')?.value,
                document.getElementById('street')?.value,
                barangay ? `Barangay ${barangay}` : '',
                'Pasig City',
                document.getElementById('zipCode')?.value
            ].filter(Boolean);
            const target = document.getElementById('completeAddress');
            if (target) target.value = parts.join(', ');
        }

        function updateFinancialRequirements() {
            const rules = [
                ['isPensioner', 'pensionSource'],
                ['familySupport', 'familySupportAmount'],
                ['personalIncome', 'personalIncomeAmount']
            ];
            rules.forEach(([choiceId, detailId]) => {
                const choice = document.getElementById(choiceId);
                const detail = document.getElementById(detailId);
                if (!choice || !detail || choice.disabled) return;
                const isApplicable = choice.value === '1';
                detail.disabled = !isApplicable;
                detail.required = isApplicable;
            });
        }

        function updateBenefitSpecificFields() {
            const selectedBenefit = document.getElementById('requestedBenefit')?.value || '';
            let visiblePanel = false;

            document.querySelectorAll('.benefit-specific-panel').forEach(panel => {
                const benefits = (panel.dataset.benefits || '').split('|');
                const shouldShow = benefits.includes(selectedBenefit);
                panel.hidden = !shouldShow;
                visiblePanel = visiblePanel || shouldShow;

                panel.querySelectorAll('input, select, textarea').forEach(control => {
                    control.disabled = !shouldShow;
                    control.required = shouldShow && control.hasAttribute('data-benefit-required');
                });
            });

            const prompt = document.getElementById('benefitFieldsPrompt');
            if (prompt) prompt.hidden = visiblePanel;
            updateRequiredDocuments(selectedBenefit);
            updateFinancialRequirements();
        }

        function updateRequiredDocuments(selectedBenefit) {
            const documentRequirements = {
                'Senior Citizen ID Registration': [
                    ['PSA Birth Certificate', "Clear copy of the senior citizen's PSA birth certificate or accepted proof of age."],
                    ['Barangay Residency Certificate', 'Current barangay certificate confirming Pasig residency.'],
                    ['2-Year COMELEC Certification', 'Official voter residency certification of the senior citizen applicant.']
                ],
                'Local Social Pension Assessment': [
                    ['Senior Citizen ID or Valid Government ID', 'Identification document of the senior citizen.'],
                    ['Barangay Certificate of Indigency', 'Current indigency and residency certification issued by the barangay.'],
                    ['SSS / GSIS Pension Record or Certification', 'Document showing the pension source and monthly amount, or proof that no pension is received.']
                ],
                'Land Bank Cash Card Enrollment': [
                    ['Senior Citizen ID or Proof of Registration', 'Senior Citizen ID or proof of an active senior registration.'],
                    ['Valid Government-Issued ID', 'Government ID presented for Land Bank identity verification.'],
                    ['Proof of Address', 'Current barangay certificate, utility bill, or equivalent address document.']
                ],
                'Milestone Cash Gift': [
                    ['Senior Citizen ID', 'Clear copy of the senior citizen ID.'],
                    ['Certified PSA Birth Certificate', "Certified proof of the senior citizen's date of birth."],
                    ['Latest Whole-Body Photo', 'Recent whole-body photo shown clearly against a plain background.']
                ],
                'Other OSCA Assistance': [
                    ['Senior Citizen ID or Valid Government ID', 'Identification document of the senior citizen.'],
                    ['Proof of Address', "Current document confirming the senior citizen's Pasig address."],
                    ['Supporting Document for the Request', 'Document that supports the assistance described above.']
                ]
            };
            const fallback = [
                ['Required identity document', 'Select a benefit or service to see the exact identity document required.'],
                ['Required residency document', 'Select a benefit or service to see the exact residency document required.'],
                ['Required supporting document', 'Select a benefit or service to see the exact supporting document required.']
            ];
            const requirements = documentRequirements[selectedBenefit] || fallback;
            ['primary', 'secondary', 'supporting'].forEach((role, index) => {
                const label = document.querySelector(`[data-document-label="${role}"]`);
                const description = document.querySelector(`[data-document-description="${role}"]`);
                if (label) label.textContent = requirements[index][0];
                if (description) description.textContent = requirements[index][1];
            });
        }

        document.getElementById('newSeniorForm')?.addEventListener('submit', event => {
            syncCompleteAddress();
            if (!event.currentTarget.checkValidity()) {
                event.preventDefault();
                event.currentTarget.reportValidity();
            }
        });

        ['houseNo', 'street', 'barangay', 'zipCode'].forEach(id => {
            document.getElementById(id)?.addEventListener('input', syncCompleteAddress);
            document.getElementById(id)?.addEventListener('change', syncCompleteAddress);
        });

        document.getElementById('requestedBenefit')?.addEventListener('change', updateBenefitSpecificFields);
        ['isPensioner', 'familySupport', 'personalIncome'].forEach(id => {
            document.getElementById(id)?.addEventListener('change', updateFinancialRequirements);
        });
        updateBenefitSpecificFields();

        if (document.getElementById('birthDate')?.value) calculateProxyAge2026();

        // Option B: Verify Existing Senior ID
        async function verifySeniorID() {
            const seniorIdInput = document.getElementById('seniorCitizenId');
            const resultPanel = document.getElementById('verificationResultPanel');
            const pensionUploadsGroup = document.getElementById('pensionUploadsGroup');
            
            const seniorId = seniorIdInput.value.trim();
            if (!seniorId) {
                window.showCarelinkResult('Please enter a Senior Citizen ID.', false);
                return;
            }

            resultPanel.style.display = 'block';
            resultPanel.innerHTML = '<div style="color:#2563eb; font-weight:600;"><i class="fas fa-spinner fa-spin"></i> Querying databases & verifying integrity...</div>';
            pensionUploadsGroup.style.display = 'none';

            try {
                const response = await fetch(`../api/verify_senior_integrity.php?id=${encodeURIComponent(seniorId)}`);
                const data = await response.json();
                
                if (data.success) {
                    // Valid senior citizen found.
                    resultPanel.innerHTML = `
                        <div class="alert-banner alert-banner-success" style="margin-bottom:0;">
                            <i class="fas fa-check-circle" style="font-size:1.2rem;"></i>
                            <div>
                                <h5 style="margin:0 0 4px 0; font-weight:700;">Verified Senior Profile Found</h5>
                                <p style="margin:0; font-size:0.85rem; color:#064e3b;">
                                    <strong>Name:</strong> ${data.senior.full_name}<br>
                                    <strong>Barangay:</strong> ${data.senior.barangay}<br>
                                    <strong>Address:</strong> ${data.senior.complete_address}<br>
                                    <strong>Status:</strong> ${data.senior.workflow_state}
                                </p>
                                <div style="margin-top:10px; font-weight:700; color:#0f766e;">
                                    <i class="fas fa-clipboard-check"></i> Eligible Benefit: Local Senior Pension Form
                                </div>
                            </div>
                        </div>
                    `;
                    pensionUploadsGroup.style.display = 'block';
                } else {
                    // Invalid/Not found
                    resultPanel.innerHTML = `
                        <div class="alert-banner alert-banner-error" style="margin-bottom:0;">
                            <i class="fas fa-exclamation-triangle" style="font-size:1.2rem;"></i>
                            <div>
                                <h5 style="margin:0 0 4px 0; font-weight:700;">Profile Integrity Error</h5>
                                <p style="margin:0; font-size:0.85rem;">${data.message}</p>
                            </div>
                        </div>
                    `;
                    pensionUploadsGroup.style.display = 'none';
                }
            } catch (err) {
                resultPanel.innerHTML = `
                    <div class="alert-banner alert-banner-error" style="margin-bottom:0;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>Could not connect to authentication services. Please check connection.</div>
                    </div>
                `;
                pensionUploadsGroup.style.display = 'none';
            }
        }
    </script>

<?php else: ?>

    <!-- Rule-based workflow routing success scenarios -->
    <?php if ($proxyOption === 'new_senior'): ?>
        
        <!-- OPTION A SUCCESS: REPRESENTATIVE QUEUE TOKEN -->
        <div class="success-qr-card">
            <div class="success-qr-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h3 style="color:#0f172a; font-weight:800; font-size:1.45rem; margin-bottom:10px;">Pre-registration successful.</h3>
            <p style="color:#64748b; font-size:0.95rem; line-height:1.5; margin:0 0 15px 0;">
                The senior citizen's digital application has shifted from <strong>[Draft] &rarr; [Submitted]</strong>.
                Download or print the queue token and check the appointment details.
            </p>
            
            <div class="qr-image-wrapper">
                <img src="<?php echo htmlspecialchars($proxyQrUrl); ?>" alt="Representative QR Token">
                <div>
                    <span class="qr-token-label">TOKEN: <?php echo htmlspecialchars($proxyTransactionId); ?></span>
                </div>
            </div>

            <!-- Appointment Notice -->
            <div class="appointment-box">
                <i class="fas fa-calendar-alt"></i>
                <div>
                    <h5>Automated Appointment Notice</h5>
                    <p>Please print this queue token page and bring the <strong>physical original documents</strong> to the selected barangay office for counter verification on the scheduled date.</p>
                </div>
            </div>

            <div style="display:flex; flex-direction:column; gap:10px; margin-top:25px;">
                <button type="button" class="btn btn-primary" onclick="window.print()" style="padding:12px; font-weight:600;">
                    <i class="fas fa-download"></i> Download Queue Token
                </button>
                <a href="<?php echo htmlspecialchars($resetUrl); ?>" class="btn btn-muted" style="padding:10px;">
                    <i class="fas fa-redo"></i> Done / Register Another
                </a>
            </div>
        </div>

    <?php else: ?>

        <!-- OPTION B SUCCESS: BENEFIT CLAIM RECEIPT -->
        <div class="success-qr-card">
            <div class="success-qr-icon" style="background:#eff6ff; color:#3b82f6;">
                <i class="fas fa-receipt"></i>
            </div>
            <h3 style="color:#0f172a; font-weight:800; font-size:1.45rem; margin-bottom:10px;">Pension Claim Submitted</h3>
            <p style="color:#64748b; font-size:0.95rem; line-height:1.5; margin:0 0 15px 0;">
                The pension claim has been received and added to the standard review queue.
                Scan or download the Benefit Tracking QR receipt below.
            </p>
            
            <div class="qr-image-wrapper" style="border-color:#bfdbfe; background:#f0f7ff;">
                <img src="<?php echo htmlspecialchars($proxyQrUrl); ?>" alt="Benefit Tracking Receipt">
                <div>
                    <span class="qr-token-label" style="background:#dbeafe; color:#1e40af;">CLAIM ID: <?php echo htmlspecialchars($proxyTransactionId); ?></span>
                </div>
            </div>

            <div class="appointment-box" style="background:#f0fdf4; border-color:#bbf7d0; color:#166534;">
                <i class="fas fa-info-circle" style="color:#16a34a;"></i>
                <div>
                    <h5 style="color:#14532d;">Tracking Access Activated</h5>
                    <p style="color:#15803d;">You can now track this claim's review and disbursement status at any time by entering this Claim ID on the public tracking portal.</p>
                </div>
            </div>

            <div style="display:flex; flex-direction:column; gap:10px; margin-top:25px;">
                <a href="benefit_tracker.php?token=<?php echo urlencode($proxyTransactionId); ?>" class="btn btn-primary" style="padding:12px; font-weight:600; background:#3b82f6; border-color:#3b82f6;">
                    <i class="fas fa-search"></i> Track Status Now
                </a>
                <a href="<?php echo htmlspecialchars($resetUrl); ?>" class="btn btn-muted" style="padding:10px;">
                    <i class="fas fa-redo"></i> Done
                </a>
            </div>
        </div>

    <?php endif; ?>

<?php endif; ?>
