<?php
$quickAccessRole = $_SESSION['role'] ?? '';
$notificationEndpoint = $quickAccessRole === 'barangay_staff'
    ? '../api/barangay_dashboard_data.php'
    : '../api/get_realtime_data.php';
$notificationQueueUrl = $quickAccessRole === 'barangay_staff'
    ? 'submit_application.php'
    : 'verify_document.php';
?>
<link rel="stylesheet" href="../assets/css/legal-quick-access.css?v=6">

<div class="legal-quick-actions" id="quickActionsMenu" aria-label="Quick actions">
    <button class="legal-quick-button quick-action-choice legal-quick-notification-button" id="notificationQuickButton" type="button"
            data-quick-action-choice data-label="Notifications" tabindex="-1"
            aria-label="Open recent notifications" aria-controls="notificationQuickPanel" aria-expanded="false"
            title="Recent notifications">
        <i class="fas fa-bell" aria-hidden="true"></i>
    </button>
    <button class="legal-quick-button quick-action-choice" id="legalQuickButton" type="button"
            data-quick-action-choice data-label="Legal Guide" tabindex="-1"
            aria-label="Open Legal Guide" aria-controls="legalQuickDrawer" aria-expanded="false"
            title="Open Legal Guide">
        <i class="fas fa-scale-balanced" aria-hidden="true"></i>
    </button>
    <?php if ($quickAccessRole === 'barangay_staff'): ?>
        <a class="legal-quick-button quick-action-choice legal-quick-scan-button" href="submit_application.php?openScanner=1"
           data-quick-action-choice data-label="Scan QR" tabindex="-1" aria-label="Open QR scanner" title="Scan representative QR code">
            <i class="fas fa-qrcode" aria-hidden="true"></i>
        </a>
    <?php endif; ?>
    <button class="legal-quick-button quick-action-toggle" id="quickActionsToggle" type="button"
            data-label="Quick actions" aria-label="Open quick actions" aria-controls="quickActionsMenu"
            aria-expanded="false" title="Quick actions">
        <i class="fas fa-ellipsis-vertical" aria-hidden="true"></i>
        <b class="legal-quick-badge" id="notificationQuickBadge" hidden aria-label="0 recent updates">0</b>
    </button>
</div>

<section class="notification-quick-panel" id="notificationQuickPanel" role="dialog"
         aria-labelledby="notificationQuickTitle" aria-hidden="true"
         data-role="<?php echo htmlspecialchars($quickAccessRole, ENT_QUOTES, 'UTF-8'); ?>"
         data-endpoint="<?php echo htmlspecialchars($notificationEndpoint, ENT_QUOTES, 'UTF-8'); ?>">
    <header class="notification-quick-header">
        <div><p>Quick updates</p><h2 id="notificationQuickTitle"><i class="fas fa-bell" aria-hidden="true"></i> Recent notifications</h2></div>
        <button type="button" id="notificationQuickClose" aria-label="Close notifications" title="Close"><i class="fas fa-xmark" aria-hidden="true"></i></button>
    </header>
    <div class="notification-quick-list" id="notificationQuickList" aria-live="polite">
        <div class="notification-quick-state"><i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Loading recent updates…</div>
    </div>
    <footer class="notification-quick-footer">
        <a href="<?php echo htmlspecialchars($notificationQueueUrl, ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-list-check" aria-hidden="true"></i> Open application queue</a>
    </footer>
</section>

<div class="legal-quick-backdrop" id="legalQuickBackdrop" hidden></div>
<aside class="legal-quick-drawer" id="legalQuickDrawer" role="dialog" aria-modal="true"
       aria-labelledby="legalQuickTitle" aria-hidden="true">
    <header class="legal-quick-header">
        <div class="legal-quick-heading">
            <span class="legal-quick-heading-icon" aria-hidden="true"><i class="fas fa-scale-balanced"></i></span>
            <div>
                <p>Quick reference</p>
                <h2 id="legalQuickTitle">Legal Guide</h2>
            </div>
        </div>
        <button class="legal-quick-close" id="legalQuickClose" type="button" aria-label="Close Legal Guide" title="Close">
            <i class="fas fa-xmark" aria-hidden="true"></i>
        </button>
    </header>

    <div class="legal-quick-body">
        <label class="legal-quick-search" for="legalQuickSearch">
            <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
            <span class="legal-quick-sr-only">Search legal topics</span>
            <input id="legalQuickSearch" type="search" placeholder="Search laws, benefits, or privacy…" autocomplete="off">
        </label>

        <p class="legal-quick-note"><i class="fas fa-circle-info" aria-hidden="true"></i> Use this guide while working without leaving the current page.</p>

        <div class="legal-quick-topics" id="legalQuickTopics">
            <details class="legal-quick-topic" data-search="ra 9994 expanded senior citizens act discounts privileges medicine transport">
                <summary><span><i class="fas fa-id-card" aria-hidden="true"></i> RA 9994 — Senior Privileges</span><i class="fas fa-chevron-down" aria-hidden="true"></i></summary>
                <div class="legal-quick-topic-body">
                    <p><strong>Expanded Senior Citizens Act of 2010.</strong> A senior citizen is a Filipino citizen who is at least 60 years old.</p>
                    <h3>Discount and tax privileges</h3>
                    <ul>
                        <li>20% discount on covered medicines, food, accommodation, transportation, entertainment, medical care, and funeral services.</li>
                        <li>VAT exemption applies to qualified purchases covered by the law.</li>
                        <li>A valid Senior Citizen ID or another legally accepted proof of age and citizenship must be presented.</li>
                    </ul>
                    <h3>Health and public services</h3>
                    <ul>
                        <li>Medical, dental, diagnostic, and laboratory services in government facilities, subject to applicable rules.</li>
                        <li>Priority lanes and priority service in government offices and commercial establishments.</li>
                        <li>Access to government assistance and PhilHealth coverage under implementing programs.</li>
                    </ul>
                    <h3>ID verification checklist</h3>
                    <ul class="legal-quick-checklist">
                        <li>Filipino citizen and 60 years old or older</li>
                        <li>Proof of age or PSA birth certificate</li>
                        <li>Proof of identity and barangay residency</li>
                        <li>Representative authorization when filed on the senior's behalf</li>
                    </ul>
                    <p class="legal-quick-warning"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> Do not approve eligibility using age alone; identity, citizenship, and residency must also be verified.</p>
                </div>
            </details>
            <details class="legal-quick-topic" data-search="ra 11916 social pension indigent senior monthly pension dswd">
                <summary><span><i class="fas fa-hand-holding-heart" aria-hidden="true"></i> RA 11916 — Social Pension</span><i class="fas fa-chevron-down" aria-hidden="true"></i></summary>
                <div class="legal-quick-topic-body">
                    <p><strong>Social Pension for Indigent Senior Citizens Act.</strong> RA 11916 increased the statutory monthly social pension from ₱500 to ₱1,000, administered through DSWD and its implementing partners.</p>
                    <h3>Eligibility screening</h3>
                    <ul>
                        <li>Senior citizen who meets the program's indigency criteria.</li>
                        <li>Frail, sick, disabled, without regular income, or without sufficient family support, subject to social-worker assessment.</li>
                        <li>No disqualifying government or private pension under the current program rules.</li>
                        <li>Must pass validation, deduplication, residency, and means-test checks.</li>
                    </ul>
                    <h3>Required documents</h3>
                    <ul class="legal-quick-checklist">
                        <li>PSA birth certificate and Senior Citizen ID or valid government ID</li>
                        <li>Barangay or CSWD certificate of indigency</li>
                        <li>Pension-status record or declaration</li>
                        <li>Home Visitation Form signed by the assigned social worker</li>
                        <li>Land Bank Enrollment Form for cash-card processing</li>
                        <li>Required applicant photograph</li>
                    </ul>
                    <h3>Processing sequence</h3>
                    <ol>
                        <li>Receive and validate the application.</li>
                        <li>Conduct the social-worker assessment or home visit.</li>
                        <li>Review documents and perform deduplication.</li>
                        <li>Endorse qualified records for enrollment and disbursement.</li>
                        <li>Release the approved cash card or benefit through the designated channel.</li>
                    </ol>
                    <p class="legal-quick-warning"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> Never promise approval or immediate payment. Final eligibility remains subject to validation and available program implementation.</p>
                </div>
            </details>
            <details class="legal-quick-topic" data-search="ra 11982 expanded centenarians act 80 85 90 95 100 milestone cash gift octogenarian nonagenarian">
                <summary><span><i class="fas fa-cake-candles" aria-hidden="true"></i> RA 11982 — Milestone Benefits</span><i class="fas fa-chevron-down" aria-hidden="true"></i></summary>
                <div class="legal-quick-topic-body">
                    <p><strong>Expanded Centenarians Act.</strong> Qualified Filipino senior citizens receive recognition and cash gifts at the prescribed milestone ages, following OSCA validation and endorsement.</p>
                    <h3>Cash-gift schedule</h3>
                    <div class="legal-quick-table-wrap">
                        <table><thead><tr><th>Age</th><th>Benefit</th></tr></thead><tbody>
                            <tr><td>80</td><td>₱10,000</td></tr><tr><td>85</td><td>₱10,000</td></tr>
                            <tr><td>90</td><td>₱10,000</td></tr><tr><td>95</td><td>₱10,000</td></tr>
                            <tr><td>100+</td><td>₱100,000 centenarian gift</td></tr>
                        </tbody></table>
                    </div>
                    <h3>Document checklist</h3>
                    <ul class="legal-quick-checklist">
                        <li>Authenticated or certified PSA birth certificate</li>
                        <li>Senior Citizen ID or valid government-issued ID</li>
                        <li>Barangay certification of residence</li>
                        <li>Recent required photograph</li>
                        <li>OSCA endorsement and representative authorization, when applicable</li>
                    </ul>
                    <h3>Validation reminders</h3>
                    <ul>
                        <li>The selected milestone must match the verified birth date.</li>
                        <li>Check for prior release at the same milestone before endorsement.</li>
                        <li>Confirm claimant identity and authority for representative-filed claims.</li>
                    </ul>
                    <p class="legal-quick-warning"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> Each milestone cash gift is released only once after age validation and official endorsement.</p>
                </div>
            </details>
            <details class="legal-quick-topic" data-search="ra 10173 data privacy act personal sensitive information consent security">
                <summary><span><i class="fas fa-shield-halved" aria-hidden="true"></i> RA 10173 — Data Privacy</span><i class="fas fa-chevron-down" aria-hidden="true"></i></summary>
                <div class="legal-quick-topic-body">
                    <p><strong>Data Privacy Act of 2012.</strong> Applicant records contain personal and sensitive information and must be handled only for authorized SENIORLINK functions.</p>
                    <h3>Core handling rules</h3>
                    <ul>
                        <li>Collect only information necessary for a declared, lawful purpose.</li>
                        <li>Explain the purpose and obtain the required consent or other lawful basis.</li>
                        <li>Keep records accurate, protected, and accessible only to authorized personnel.</li>
                        <li>Do not share applicant data through personal messaging, unauthorized devices, or public files.</li>
                        <li>Retain and dispose of records according to approved government schedules and procedures.</li>
                    </ul>
                    <h3>Staff checklist</h3>
                    <ul class="legal-quick-checklist">
                        <li>Verify the user's role before opening or changing a record</li>
                        <li>Use the system audit trail and official workflow</li>
                        <li>Lock the workstation when unattended</li>
                        <li>Report suspected loss, disclosure, or unauthorized access immediately</li>
                    </ul>
                    <p class="legal-quick-warning"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> Never download, photograph, print, or disclose applicant information unless it is necessary and authorized for official processing.</p>
                </div>
            </details>
            <details class="legal-quick-topic" data-search="documents requirements senior id pension landbank home visit burial forms">
                <summary><span><i class="fas fa-file-circle-check" aria-hidden="true"></i> Document Requirements</span><i class="fas fa-chevron-down" aria-hidden="true"></i></summary>
                <div class="legal-quick-topic-body">
                    <p>Use the selected application type—not a universal document list—to determine what must be submitted.</p>
                    <h3>Senior Citizen ID</h3>
                    <ul><li>Birth certificate or valid proof of age</li><li>Proof of address or barangay residency</li><li>Required ID photograph</li></ul>
                    <h3>Local or national pension</h3>
                    <ul><li>Senior ID or valid government ID</li><li>Indigency and residency certification</li><li>Pension-status and household-income declarations</li><li>Home Visitation and Land Bank forms when required</li></ul>
                    <h3>Land Bank cash card</h3>
                    <ul><li>Senior ID or proof of registration</li><li>Valid government ID and proof of address</li><li>Completed enrollment details, including KYC information</li></ul>
                    <h3>Milestone cash gift</h3>
                    <ul><li>Certified birth certificate</li><li>Senior ID, residency certification, and current photograph</li><li>Claimant authorization for representative submissions</li></ul>
                    <h3>Home visitation</h3>
                    <ul><li>Senior ID or valid ID</li><li>Proof of the visit address</li><li>Medical certificate or recommendation when available</li><li>Caregiver or family contact and representative authorization</li></ul>
                    <h3>Representative applications</h3>
                    <ul class="legal-quick-checklist"><li>Senior's signed authorization or thumbmark</li><li>Representative's government ID</li><li>Proof of relationship or authority</li><li>Current proof of life for bedridden applicants</li></ul>
                    <p class="legal-quick-warning"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> Confirm the current form and program checklist before rejecting an application because implementing requirements may be updated.</p>
                </div>
            </details>
        </div>
        <div class="legal-quick-empty" id="legalQuickEmpty" hidden>
            <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
            <p>No matching legal topic found.</p>
        </div>
    </div>

    <footer class="legal-quick-footer">
        <p>This quick guide is a summary. Consult the complete reference for detailed provisions.</p>
    </footer>
</aside>
<script src="../assets/js/legal-quick-access.js?v=6" defer></script>
