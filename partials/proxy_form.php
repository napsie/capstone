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
        <p style="color: #64748b; font-size: 0.95rem; margin: 0;">Choose the action that corresponds to your senior representative status.</p>
    </div>

    <!-- Portal Option Choice Cards -->
    <div class="portal-option-grid" id="portalOptionGrid">
        <div class="portal-option-card active" id="optionCardNew" onclick="selectPortalPath('new_senior')">
            <div class="check-indicator"><i class="fas fa-check"></i></div>
            <div class="option-icon" style="background: #10b981;">
                <i class="fas fa-user-plus"></i>
            </div>
            <h4>New Bedridden Pre-Registration</h4>
            <p>For seniors without an ID card yet. Pre-register to place them in the Counter Priority Queue.</p>
        </div>

        <div class="portal-option-card" id="optionCardExisting" onclick="selectPortalPath('existing_benefits')">
            <div class="check-indicator"><i class="fas fa-check"></i></div>
            <div class="option-icon" style="background: #3b82f6;">
                <i class="fas fa-file-invoice-dollar"></i>
            </div>
            <h4>Apply for Existing Senior Benefits</h4>
            <p>For already approved Bedridden seniors. Input Senior ID to apply for the Local Senior Pension benefit.</p>
        </div>
    </div>

    <!-- Display backend error message if any -->
    <?php if (!empty($proxyMessage)): ?>
        <div class="alert-banner alert-banner-error">
            <i class="fas fa-exclamation-circle"></i>
            <div><?php echo htmlspecialchars($proxyMessage); ?></div>
        </div>
    <?php endif; ?>

    <!-- ── FORM A: NEW BEDRIDDEN SENIOR PRE-REGISTRATION ── -->
    <div id="newSeniorFormSection" class="form-switch-section" style="display: block;">
        <form method="POST" action="<?php echo htmlspecialchars($formAction); ?>" enctype="multipart/form-data" class="proxy-form" id="newSeniorForm" novalidate>
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
                    <h3>Senior Citizen Information</h3>
                    <p>Enter Lolo Tomas's details exactly as they appear in official records.</p>
                </div>
            </div>

            <div class="form-section">
                <!-- Name Row -->
                <div class="form-row-names">
                    <div class="form-group">
                        <label for="lastName">Last Name <span style="color:#b91c1c;">*</span></label>
                        <input type="text" id="lastName" name="lastName" class="form-control" placeholder="e.g. Dela Cruz" required>
                    </div>
                    <div class="form-group">
                        <label for="firstName">First Name <span style="color:#b91c1c;">*</span></label>
                        <input type="text" id="firstName" name="firstName" class="form-control" placeholder="e.g. Tomas" required>
                    </div>
                    <div class="form-group">
                        <label for="middleName">Middle Name</label>
                        <input type="text" id="middleName" name="middleName" class="form-control" placeholder="e.g. Santos">
                    </div>
                    <div class="form-group">
                        <label for="suffix">Suffix</label>
                        <input type="text" id="suffix" name="suffix" class="form-control" placeholder="e.g. Sr.">
                    </div>
                </div>

                <!-- Date of Birth & Contact -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="birthDate">Birth Date <span style="color:#b91c1c;">*</span></label>
                        <input type="date" id="birthDate" name="birthDate" class="form-control" required onchange="calculateProxyAge2026()">
                        <div id="ageCheckStatus" style="margin-top: 8px;"></div>
                    </div>
                    <div class="form-group">
                        <label for="contactNumber">Contact Number <span style="color:#b91c1c;">*</span></label>
                        <input type="text" id="contactNumber" name="contactNumber" class="form-control" maxlength="11" placeholder="e.g. 09123456789" required>
                    </div>
                </div>

                <!-- Address & Barangay -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="barangay">Barangay <span style="color:#b91c1c;">*</span></label>
                        <select id="barangay" name="barangay" class="form-control" required>
                            <option value="">— Select Barangay —</option>
                            <?php foreach ($barangays_list as $b): ?>
                                <option value="<?php echo htmlspecialchars($b); ?>"><?php echo htmlspecialchars($b); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <!-- Spacer -->
                    </div>
                </div>
                <div class="form-group">
                    <label for="completeAddress">Complete Address (in Pasig) <span style="color:#b91c1c;">*</span></label>
                    <textarea id="completeAddress" name="completeAddress" class="form-control" rows="2" placeholder="House No., Street, Barangay Rosario, Pasig City" required></textarea>
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
                    <label for="psa_birth_cert_file">PSA Birth Certificate <span class="req">*</span></label>
                    <p class="slot-desc">Upload a clear scanned copy or photo of the Senior Citizen's PSA birth certificate.</p>
                    <input type="file" id="psa_birth_cert_file" name="psa_birth_cert_file" accept="image/jpeg,application/pdf" required>
                </div>
                
                <div class="upload-slot">
                    <label for="barangay_residency_file">Barangay Residency Certificate <span class="req">*</span></label>
                    <p class="slot-desc">Issued by the barangay within the last 6 months confirming Pasig residency.</p>
                    <input type="file" id="barangay_residency_file" name="barangay_residency_file" accept="image/jpeg,application/pdf" required>
                </div>

                <div class="upload-slot">
                    <label for="comelec_cert_file">2-Year COMELEC Certification <span class="req">*</span></label>
                    <p class="slot-desc">Official voter residency certification of the senior citizen applicant.</p>
                    <input type="file" id="comelec_cert_file" name="comelec_cert_file" accept="image/jpeg,application/pdf" required>
                </div>

                <h5 style="margin-top:20px; color:#475569; font-size:0.88rem; text-transform:uppercase; letter-spacing:0.5px;">Proof of Bedridden Condition</h5>
                
                <div class="upload-slot">
                    <label for="proof_of_life_file">Bedridden Photo (Proof of Life) <span class="req">*</span></label>
                    <p class="slot-desc"><strong>CRITICAL:</strong> Upload a digital photo of Lolo Tomas in bed holding a smartphone with the current date clearly visible on the screen.</p>
                    <input type="file" id="proof_of_life_file" name="proof_of_life_file" accept="image/jpeg" required>
                </div>

                <h5 style="margin-top:20px; color:#475569; font-size:0.88rem; text-transform:uppercase; letter-spacing:0.5px;">Representative Verification Documents</h5>
                
                <div class="upload-slot">
                    <label for="auth_letter_file">Authorization Letter <span class="req">*</span></label>
                    <p class="slot-desc">Authorization letter signed or marked with Lolo Tomas's thumbmark authorizing the representative.</p>
                    <input type="file" id="auth_letter_file" name="auth_letter_file" accept="image/jpeg,application/pdf" required>
                </div>

                <div class="upload-slot">
                    <label for="proxy_id_file">Representative's Government ID <span class="req">*</span></label>
                    <p class="slot-desc">Valid government ID of the authorized representative (Maria).</p>
                    <input type="file" id="proxy_id_file" name="proxy_id_file" accept="image/jpeg,application/pdf" required>
                </div>

                <div class="upload-slot">
                    <label for="proxy_birth_cert_file">Representative's Birth Certificate <span class="req">*</span></label>
                    <p class="slot-desc">Representative's birth certificate proving relation to Lolo Tomas.</p>
                    <input type="file" id="proxy_birth_cert_file" name="proxy_birth_cert_file" accept="image/jpeg,application/pdf" required>
                </div>
            </div>

            <!-- Step 3 Heading -->
            <div class="step-heading" style="margin-top: 25px;">
                <div class="step-number">3</div>
                <div>
                    <h3>Representative Details</h3>
                    <p>Details of the representative submitting on behalf of the senior citizen.</p>
                </div>
            </div>

            <div class="form-section">
                <div class="form-row">
                    <div class="form-group">
                        <label for="proxyName">Representative Name <span style="color:#b91c1c;">*</span></label>
                        <input type="text" id="proxyName" name="proxyName" class="form-control" placeholder="e.g. Maria Dela Cruz" required>
                    </div>
                    <div class="form-group">
                        <label for="proxyRelationship">Relationship to Senior <span style="color:#b91c1c;">*</span></label>
                        <select id="proxyRelationship" name="proxyRelationship" class="form-control" required>
                            <option value="">— Select Relationship —</option>
                            <option value="Grandchild" selected>Grandchild (e.g. Maria)</option>
                            <option value="Daughter">Daughter</option>
                            <option value="Son">Son</option>
                            <option value="Spouse">Spouse</option>
                            <option value="Sibling">Sibling</option>
                            <option value="Caregiver">Caregiver</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="proxyContactNumber">Representative Contact Number <span style="color:#b91c1c;">*</span></label>
                        <input type="text" id="proxyContactNumber" name="proxyContactNumber" class="form-control" placeholder="e.g. 09123456789" maxlength="11" required>
                    </div>
                    <div class="form-group">
                        <!-- Spacer -->
                    </div>
                </div>

                <div class="checkbox-row" style="margin-top: 15px;">
                    <input type="checkbox" id="confirmBedridden" name="confirmBedridden" required style="width: 18px; height: 18px; accent-color: #10b981;">
                    <label for="confirmBedridden" style="font-size: 0.85rem; font-weight: 500; color: #475569; margin: 0;">
                        I certify that the applicant senior citizen is verified bedridden/low-mobility and physically unable to travel.
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
    <div id="existingBenefitsSection" class="form-switch-section" style="display: none;">
        <form method="POST" action="<?php echo htmlspecialchars($formAction); ?>" enctype="multipart/form-data" class="proxy-form" id="existingPensionForm" novalidate>
            <input type="hidden" name="proxy_submit" value="1">
            <input type="hidden" name="portal_option" value="existing_benefits">

            <div class="privacy-alert" role="note" style="margin-bottom: 24px; background: #eff6ff; border-color: #bfdbfe; color: #1e40af;">
                <i class="fas fa-info-circle" aria-hidden="true" style="color: #2563eb;"></i>
                <div>
                    <strong>Secondary Benefit Enrollment:</strong> Verified bedridden senior citizens can apply for additional municipal pension payouts through this representative portal.
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
                        <input type="text" id="seniorCitizenId" name="seniorCitizenId" class="form-control" placeholder="e.g. PRX-XXXXXX or Senior ID">
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
            // Calculate age relative to target date: 2026-07-08
            const targetDate = new Date('2026-07-08');
            let age = targetDate.getFullYear() - dob.getFullYear();
            const monthDiff = targetDate.getMonth() - dob.getMonth();
            if (monthDiff < 0 || (monthDiff === 0 && targetDate.getDate() < dob.getDate())) {
                age--;
            }

            if (age >= 60) {
                // Pass Rule
                ageCheckStatus.innerHTML = `<span style="color: #059669; font-weight: 600;"><i class="fas fa-check-circle"></i> Eligible Bedridden Senior (Age in 2026: ${age} years old)</span>`;
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

        // Option B: Verify Existing Senior ID
        async function verifySeniorID() {
            const seniorIdInput = document.getElementById('seniorCitizenId');
            const resultPanel = document.getElementById('verificationResultPanel');
            const pensionUploadsGroup = document.getElementById('pensionUploadsGroup');
            
            const seniorId = seniorIdInput.value.trim();
            if (!seniorId) {
                alert('Please enter a Senior Citizen ID.');
                return;
            }

            resultPanel.style.display = 'block';
            resultPanel.innerHTML = '<div style="color:#2563eb; font-weight:600;"><i class="fas fa-spinner fa-spin"></i> Querying databases & verifying integrity...</div>';
            pensionUploadsGroup.style.display = 'none';

            try {
                const response = await fetch(`../api/verify_senior_integrity.php?id=${encodeURIComponent(seniorId)}`);
                const data = await response.json();
                
                if (data.success) {
                    // Valid Bedridden senior citizen found!
                    resultPanel.innerHTML = `
                        <div class="alert-banner alert-banner-success" style="margin-bottom:0;">
                            <i class="fas fa-check-circle" style="font-size:1.2rem;"></i>
                            <div>
                                <h5 style="margin:0 0 4px 0; font-weight:700;">Verified Bedridden Profile Found</h5>
                                <p style="margin:0; font-size:0.85rem; color:#064e3b;">
                                    <strong>Name:</strong> ${data.senior.full_name}<br>
                                    <strong>Barangay:</strong> ${data.senior.barangay}<br>
                                    <strong>Address:</strong> ${data.senior.complete_address}<br>
                                    <strong>Status:</strong> ${data.senior.workflow_state} (Low Mobility verified)
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

    <!-- ── FSM TRANSITION SUCCESS SCENARIOS ── -->
    <?php if ($proxyOption === 'new_senior'): ?>
        
        <!-- OPTION A SUCCESS: PRIORITY QUEUE TOKEN -->
        <div class="success-qr-card">
            <div class="success-qr-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h3 style="color:#0f172a; font-weight:800; font-size:1.45rem; margin-bottom:10px;">Pre-registration successful.</h3>
            <p style="color:#64748b; font-size:0.95rem; line-height:1.5; margin:0 0 15px 0;">
                Lolo Tomas's digital application has shifted from <strong>[Draft] &rarr; [Submitted]</strong>. 
                Download/print your priority queue token and check the appointment details.
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
                    <p>Please print this priority token page and bring the <strong>physical original documents</strong> to the <strong>Barangay Rosario Hall</strong> for counter verification on your scheduled appointment date.</p>
                </div>
            </div>

            <div style="display:flex; flex-direction:column; gap:10px; margin-top:25px;">
                <button type="button" class="btn btn-primary" onclick="window.print()" style="padding:12px; font-weight:600;">
                    <i class="fas fa-download"></i> Download Priority Token
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
                The pension claim has been isolated as a sub-process status labeled <strong>[Pension Benefit - Submitted]</strong>. 
                Scan/download your distinct Benefit Tracking QR Receipt below.
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
