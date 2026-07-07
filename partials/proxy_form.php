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
    <form method="POST" action="<?php echo htmlspecialchars($formAction); ?>" class="proxy-form" id="proxyMainForm" novalidate>
        <input type="hidden" name="proxy_submit" value="1">

        <!-- Privacy Notice -->
        <div class="privacy-alert" role="note" style="margin-bottom: 24px;">
            <i class="fas fa-shield-alt" aria-hidden="true"></i>
            <div>
                <strong>RA 10173 Privacy Guard:</strong> This portal processes sensitive personal details under the Philippine Data Privacy Act of 2012.
                Submitting encrypts all details into a secure token. Only authenticated CARELINK terminals can decode and import this data.
            </div>
        </div>

        <!-- ── STEP 1: Application Type Card Selector ──────────────────── -->
        <div id="cardSelectorSection">
            <div class="step-heading">
                <div class="step-number">1</div>
                <div>
                    <h3>Choose Application Type</h3>
                    <p>Select the benefit or service you are registering a proxy for.</p>
                </div>
            </div>
            <?php
            $allowedProxyTypes = ['senior', 'pension', 'burial'];
            $typeIcons = [
                'senior'  => [
                    'icon'  => 'fas fa-id-card',
                    'color' => '#60a5fa',
                    'grad'  => 'linear-gradient(135deg,#1e3a8a,#1d4ed8)',
                    'desc'  => 'Senior Citizens ID card registration',
                    'code'  => 'Form 1',
                ],
                'pension' => [
                    'icon'  => 'fas fa-wallet',
                    'color' => '#fbbf24',
                    'grad'  => 'linear-gradient(135deg,#78350f,#d97706)',
                    'desc'  => 'Local senior social pension benefit',
                    'code'  => 'Local',
                ],
                'burial'  => [
                    'icon'  => 'fas fa-ribbon',
                    'color' => '#94a3b8',
                    'grad'  => 'linear-gradient(135deg,#1e293b,#334155)',
                    'desc'  => 'Burial financial assistance claim',
                    'code'  => 'Form 7',
                ],
            ];
            ?>
            <div class="app-type-grid" id="appTypeGrid">
                <?php foreach (getApplicationTypeOptions() as $val => $label):
                    if (!in_array($val, $allowedProxyTypes)) continue;
                    $meta = $typeIcons[$val] ?? ['icon' => 'fas fa-file', 'color' => '#94a3b8', 'grad' => 'linear-gradient(135deg,#1e293b,#334155)', 'desc' => '', 'code' => ''];
                ?>
                <div class="app-type-card"
                     data-value="<?php echo $val; ?>"
                     data-label="<?php echo htmlspecialchars($label); ?>"
                     data-icon="<?php echo $meta['icon']; ?>"
                     data-color="<?php echo $meta['color']; ?>"
                     data-bg="<?php echo $meta['grad']; ?>"
                     onclick="selectAppType('<?php echo $val; ?>')"
                     role="button"
                     tabindex="0"
                     aria-pressed="false"
                     onkeydown="if(event.key==='Enter'||event.key===' ')selectAppType('<?php echo $val; ?>')"
                     title="<?php echo htmlspecialchars($label); ?>">
                    <div class="card-check"><i class="fas fa-check"></i></div>
                    <div class="card-arrow"><i class="fas fa-arrow-right"></i></div>
                    <div class="card-icon" style="background:<?php echo $meta['grad']; ?>; color:<?php echo $meta['color']; ?>; box-shadow: 0 6px 20px rgba(0,0,0,0.18);">
                        <i class="<?php echo $meta['icon']; ?>"></i>
                    </div>
                    <div>
                        <div class="card-code"><?php echo htmlspecialchars($meta['code']); ?></div>
                        <div class="card-title"><?php echo htmlspecialchars($label); ?></div>
                    </div>
                    <div class="card-desc"><?php echo htmlspecialchars($meta['desc']); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div><!-- /#cardSelectorSection -->

        <!-- ── STEP 2: Full Form (revealed after card selection) ─────────── -->
        <div id="formBody">

            <!-- Selected type banner -->
            <div class="selected-type-banner" id="selectedTypeBanner" style="display:none;">
                <div class="banner-icon" id="bannerIcon"></div>
                <div class="banner-info">
                    <small><i class="fas fa-layer-group"></i> Selected Application Type</small>
                    <strong id="bannerLabel"></strong>
                </div>
                <button type="button" class="btn-change-type" onclick="resetAppType()">
                    <i class="fas fa-arrow-left"></i> Change
                </button>
            </div>

            <input type="hidden" id="applicationType" name="applicationType" value="" required>

            <!-- ── Step 2 heading ── -->
            <div class="step-heading" style="margin-top: 28px;">
                <div class="step-number">2</div>
                <div>
                    <h3>Senior Citizen Information</h3>
                    <p>Enter the personal details of the bedridden senior applicant.</p>
                </div>
            </div>

            <!-- Senior Citizen Info Section -->
            <div class="form-section">
                <!-- Name row -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="lastName">Last Name <span style="color:#b91c1c;">*</span></label>
                        <input type="text" id="lastName" name="lastName" class="form-control"
                               placeholder="e.g. Dela Cruz"
                               oninput="this.value = this.value.replace(/[^a-zA-Z\s\-']/g, '')"
                               required>
                    </div>
                    <div class="form-group">
                        <label for="firstName">First Name <span style="color:#b91c1c;">*</span></label>
                        <input type="text" id="firstName" name="firstName" class="form-control"
                               placeholder="e.g. Juan"
                               oninput="this.value = this.value.replace(/[^a-zA-Z\s\-']/g, '')"
                               required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="middleName">Middle Name</label>
                        <input type="text" id="middleName" name="middleName" class="form-control"
                               placeholder="e.g. Santos"
                               oninput="this.value = this.value.replace(/[^a-zA-Z\s\-']/g, '')">
                    </div>
                    <div class="form-group">
                        <label for="suffix">Suffix <small style="font-weight:400;color:#6b7f94;">(Optional)</small></label>
                        <input type="text" id="suffix" name="suffix" class="form-control"
                               placeholder="e.g. Jr., Sr., III"
                               oninput="this.value = this.value.replace(/[^a-zA-Z\s.]/g, '')">
                    </div>
                </div>

                <!-- Birth date + contact -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="birthDate">Birth Date <span style="color:#b91c1c;">*</span></label>
                        <input type="date" id="birthDate" name="birthDate" class="form-control"
                               required onchange="checkProxyAgeCompliance()">
                        <div id="ageComplianceResult" style="margin-top:6px; font-size:0.82rem;"></div>
                    </div>
                    <div class="form-group">
                        <label for="contactNumber">Contact Number <span style="color:#b91c1c;">*</span></label>
                        <input type="text" id="contactNumber" name="contactNumber" class="form-control"
                               placeholder="e.g. 09123456789"
                               maxlength="11"
                               oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                               required>
                    </div>
                </div>

                <!-- Barangay -->
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
                        <!-- spacer for grid alignment -->
                    </div>
                </div>

                <!-- Address -->
                <div class="form-group">
                    <label for="completeAddress">Complete Home Address <span style="color:#b91c1c;">*</span></label>
                    <textarea id="completeAddress" name="completeAddress" class="form-control"
                              rows="3" placeholder="House No., Street, Barangay, Pasig City" required></textarea>
                </div>

                <!-- Emergency contacts -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="emergencyContactName">Emergency Contact Name</label>
                        <input type="text" id="emergencyContactName" name="emergencyContactName" class="form-control"
                               placeholder="e.g. Maria Dela Cruz"
                               oninput="this.value = this.value.replace(/[^a-zA-Z\s\-']/g, '')">
                    </div>
                    <div class="form-group">
                        <label for="emergencyContact">Emergency Contact Number</label>
                        <input type="text" id="emergencyContact" name="emergencyContact" class="form-control"
                               placeholder="e.g. 09123456789"
                               maxlength="11"
                               oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                    </div>
                </div>

                <!-- ── Pension fields (shown only if pension selected) ── -->
                <div id="pensionFields" class="dynamic-fields" hidden>
                    <div style="height:1px; background:var(--border); margin:4px 0 20px;"></div>
                    <div class="step-heading" style="margin-bottom:16px;">
                        <div class="card-icon" style="width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,#78350f,#d97706);color:#fbbf24;display:flex;align-items:center;justify-content:center;font-size:0.95rem;">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <div>
                            <h3 style="margin:0;font-size:1rem;">Social Pension Details</h3>
                            <p style="margin:0;font-size:0.82rem;color:#6b7f94;">Required for Local Social Pension applications.</p>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="sssNumber">SSS Number <span style="color:#b91c1c;">*</span></label>
                            <input type="text" id="sssNumber" name="sssNumber" class="form-control"
                                   placeholder="e.g. 33-1234567-8">
                        </div>
                        <div class="form-group">
                            <label for="pensionAmount">Monthly SSS Pension (PHP)</label>
                            <input type="number" step="0.01" id="pensionAmount" name="pensionAmount" class="form-control"
                                   placeholder="e.g. 3500.00">
                            <div id="pensionComplianceResult" style="margin-top:6px; font-size:0.82rem;"></div>
                        </div>
                    </div>
                </div>

                <!-- ── Burial fields (shown only if burial selected) ── -->
                <div id="burialFields" class="dynamic-fields" hidden>
                    <div style="height:1px; background:var(--border); margin:4px 0 20px;"></div>
                    <div class="step-heading" style="margin-bottom:16px;">
                        <div class="card-icon" style="width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,#1e293b,#334155);color:#94a3b8;display:flex;align-items:center;justify-content:center;font-size:0.95rem;">
                            <i class="fas fa-ribbon"></i>
                        </div>
                        <div>
                            <h3 style="margin:0;font-size:1rem;">Burial Assistance Details</h3>
                            <p style="margin:0;font-size:0.82rem;color:#6b7f94;">Required for Burial Financial Assistance claims.</p>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="dateOfDeath">Date of Passing <span style="color:#b91c1c;">*</span></label>
                            <input type="date" id="dateOfDeath" name="dateOfDeath" class="form-control"
                                   onchange="checkBurialDeadline()">
                            <div id="burialComplianceResult" style="margin-top:6px; font-size:0.82rem;"></div>
                        </div>
                        <div class="form-group">
                            <label for="relationshipToDeceased">Relationship to Deceased <span style="color:#b91c1c;">*</span></label>
                            <input type="text" id="relationshipToDeceased" name="relationshipToDeceased" class="form-control"
                                   placeholder="e.g. Spouse, Son, Daughter">
                        </div>
                    </div>
                </div>
            </div><!-- /.form-section (Senior Info) -->

            <!-- ── Step 3: Proxy Representative ── -->
            <div class="step-heading" style="margin-top: 8px;">
                <div class="step-number">3</div>
                <div>
                    <h3>Authorized Proxy Representative</h3>
                    <p>Enter the details of the person acting on behalf of the senior.</p>
                </div>
            </div>

            <div class="form-section">
                <div class="form-row">
                    <div class="form-group">
                        <label for="proxyName">Full Name of Proxy <span style="color:#b91c1c;">*</span></label>
                        <input type="text" id="proxyName" name="proxyName" class="form-control"
                               placeholder="e.g. Maria Dela Cruz" required
                               oninput="this.value = this.value.replace(/[^a-zA-Z\s\-'.]/g, '')">
                    </div>
                    <div class="form-group">
                        <label for="proxyRelationship">Relationship to Senior Applicant <span style="color:#b91c1c;">*</span></label>
                        <select id="proxyRelationship" name="proxyRelationship" class="form-control" required>
                            <option value="">— Select Relationship —</option>
                            <option value="Spouse">Spouse</option>
                            <option value="Son">Son</option>
                            <option value="Daughter">Daughter</option>
                            <option value="Sibling">Sibling</option>
                            <option value="Grandchild">Grandchild</option>
                            <option value="Legal Guardian">Legal Guardian</option>
                            <option value="Caregiver">Authorized Caregiver</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>

                <!-- Bedridden confirmation -->
                <div class="checkbox-row" style="margin-top:4px;">
                    <input type="checkbox" id="confirmBedridden" name="confirmBedridden" value="1"
                           style="width:18px;height:18px;accent-color:var(--accent);" required>
                    <label for="confirmBedridden" style="font-size:0.875rem;font-weight:500;color:var(--text-body);margin:0;">
                        I confirm the senior applicant is bedridden and physically unable to appear at the barangay office.
                    </label>
                </div>
            </div><!-- /.form-section (Proxy) -->

            <!-- Submit -->
            <button type="submit" class="btn btn-accent btn-block" id="proxySubmitBtn" style="margin-top: 8px; padding: 15px 24px; font-size: 1rem;">
                <i class="fas fa-qrcode" aria-hidden="true"></i> Generate Proxy Priority Token
            </button>

        </div><!-- /#formBody -->
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
