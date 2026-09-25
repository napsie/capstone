<?php
?><script src="../assets/js/form-language.js?v=2" defer></script><?php
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
$benefitPortalMode = $benefitPortalMode ?? false;
$benefitPrefill = $benefitPrefill ?? [];
$unavailableBenefitRequests = $unavailableBenefitRequests ?? [];
$verifiedBenefitAge = null;
$verifiedMilestoneAge = null;
if ($benefitPortalMode && !empty($benefitPrefill['birthDate'])) {
    try {
        $verifiedBirthDate = new DateTimeImmutable((string)$benefitPrefill['birthDate']);
        $verifiedToday = new DateTimeImmutable('today');
        if ($verifiedBirthDate <= $verifiedToday) {
            $verifiedBenefitAge = $verifiedToday->diff($verifiedBirthDate)->y;
            $verifiedMilestoneAge = milestoneAgeForCurrentAge($verifiedBenefitAge);
        }
    } catch (Throwable $e) {
        $verifiedBenefitAge = null;
    }
}
$activePortalOption = $benefitPortalMode ? 'verified_benefits' : 'new_senior';
$publicBenefitDefinitions = getPublicBenefitDefinitions();
if ($benefitPortalMode) {
    if (isset($publicBenefitDefinitions['Senior Citizen ID Registration'])) {
        $publicBenefitDefinitions['Senior Citizen ID Registration']['label'] = 'Update or Replace Senior ID';
        $publicBenefitDefinitions['Senior Citizen ID Registration']['summary'] = 'Request an information change or replacement for a lost approved Senior Citizen ID.';
        $publicBenefitDefinitions['Senior Citizen ID Registration']['benefits'] = [
            'Update incorrect or outdated information on an approved Senior Citizen ID',
            'Request a replacement for a lost Senior Citizen ID',
            'Keep the request connected to the senior citizen’s verified OSCA record',
        ];
        $publicBenefitDefinitions['Senior Citizen ID Registration']['requirements'] = [
            'An approved Senior Citizen ID and permanent token are required',
            'Select Information Change or Lost ID Replacement',
            'Submit the purpose-specific supporting documents for verification',
        ];
    }
} else {
    $publicBenefitDefinitions = array_intersect_key($publicBenefitDefinitions, ['Senior Citizen ID Registration' => true]);
}
$old = static function (string $key, string $default = '') use ($benefitPrefill): string {
    return htmlspecialchars((string) ($_POST[$key] ?? $benefitPrefill[$key] ?? $default), ENT_QUOTES, 'UTF-8');
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
        text-decoration: none;
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
    #optionCardBenefits.active {
        background: linear-gradient(145deg, #eff6ff, #ffffff);
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, .18), 0 15px 35px rgba(59, 130, 246, .18);
    }
    #optionCardBenefits.active .check-indicator { background: #3b82f6; }
    
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

    .benefit-access-section {
        padding: 4px 0 10px;
    }
    .benefit-access-heading {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        margin-bottom: 22px;
    }
    .benefit-access-heading .option-icon {
        width: 46px;
        height: 46px;
        border-radius: 12px;
        background: #3b82f6;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        font-size: 1.2rem;
    }
    .benefit-access-heading h3 { margin: 0 0 5px; color: #0f172a; font-size: 1.2rem; }
    .benefit-access-heading p { margin: 0; color: #64748b; line-height: 1.5; }
    .benefit-access-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .benefit-access-grid label { display: block; margin-bottom: 7px; color: #475569; font-size: .78rem; font-weight: 800; text-transform: uppercase; }
    .benefit-access-grid input { width: 100%; min-height: 48px; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 10px; font: inherit; box-sizing: border-box; }
    .benefit-access-submit { width: 100%; min-height: 48px; margin-top: 18px; border: 0; border-radius: 10px; background: #2563eb; color: #fff; font-weight: 800; cursor: pointer; }
    .benefit-access-submit:hover { background: #1d4ed8; }

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

    .selected-file-view {
        display: none;
        align-items: center;
        gap: 8px;
        width: fit-content;
        margin-top: 6px;
        padding: 8px 12px;
        border: 1px solid #bfdbfe;
        border-radius: 8px;
        background: #eff6ff;
        color: #1d4ed8;
        font-size: .78rem;
        font-weight: 700;
        text-decoration: none;
    }
    .selected-file-view.is-visible { display: inline-flex; }
    .selected-file-view:hover { border-color:#60a5fa; background:#dbeafe; }
    .id-pair-preview[hidden], .id-pair-error[hidden] { display:none; }
    .id-pair-preview { display:grid; gap:10px; margin-top:8px; }
    .id-pair-item { display:flex; align-items:center; gap:12px; padding:10px; border:1px solid #cbd5e1; border-radius:9px; background:#f8fafc; }
    .id-pair-item img { width:64px; height:48px; object-fit:cover; border-radius:5px; }
    .id-pair-item strong { min-width:0; flex:1; overflow-wrap:anywhere; font-size:.8rem; }
    .id-pair-actions { display:flex; gap:7px; flex-wrap:wrap; }
    .id-pair-actions a, .id-pair-actions button { padding:6px 8px; border:1px solid #93c5fd; border-radius:6px; background:#fff; color:#1d4ed8; font:inherit; font-size:.76rem; font-weight:700; text-decoration:none; cursor:pointer; }
    .id-pair-error { margin:0; color:#b91c1c; font-size:.8rem; font-weight:700; }
    @media(max-width:600px){.id-pair-item{flex-wrap:wrap}.id-pair-actions{width:100%}}

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
        display: flex;
        width: fit-content;
        max-width: 100%;
        margin: 20px auto;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        box-sizing: border-box;
    }

    .qr-image-wrapper img {
        display: block;
        width: 220px;
        max-width: 220px;
        height: auto;
        margin: 0 auto;
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
        display: block;
        max-width: 100%;
        margin-left: auto;
        margin-right: auto;
        letter-spacing: 1px;
        text-align: center;
        overflow-wrap: anywhere;
    }

    @media (max-width: 480px) {
        .qr-image-wrapper { width: 100%; padding: 16px; }
        .qr-image-wrapper img { width: min(220px, 100%); }
        .qr-token-label { width: 100%; font-size: 1rem; }
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

    .benefit-choice-hint {
        margin: 0 0 16px;
        color: #52657d;
        font-size: 0.9rem;
    }

    .benefit-choice-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
        gap: 16px;
        margin-bottom: 20px;
    }

    .benefit-choice-card {
        position: relative;
        min-height: 230px;
        padding: 24px 22px;
        overflow: hidden;
        border: 1.5px solid #d8e1ec;
        border-radius: 16px;
        background: linear-gradient(145deg, #fff, #f8fafc);
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.08);
        color: #0f172a;
        cursor: pointer;
        text-align: left;
        transition: transform .22s ease, border-color .22s ease, box-shadow .22s ease, background .22s ease;
    }

    .benefit-choice-card:hover,
    .benefit-choice-card:focus-visible {
        transform: translateY(-4px);
        border-color: var(--benefit-color);
        box-shadow: 0 14px 34px rgba(15, 23, 42, 0.14);
        outline: none;
    }

    .benefit-choice-card.selected {
        border-color: var(--benefit-color);
        background: linear-gradient(145deg, color-mix(in srgb, var(--benefit-color) 10%, white), #fff);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--benefit-color) 22%, transparent), 0 14px 34px rgba(15, 23, 42, 0.12);
    }

    .benefit-choice-icon {
        display: grid;
        place-items: center;
        width: 58px;
        height: 58px;
        margin-bottom: 22px;
        border-radius: 50%;
        background: var(--benefit-color);
        color: #fff;
        font-size: 1.35rem;
        box-shadow: 0 10px 24px color-mix(in srgb, var(--benefit-color) 28%, transparent);
    }

    .benefit-choice-title { display: block; max-width: calc(100% - 22px); font-size: 1.02rem; font-weight: 800; line-height: 1.35; }
    .benefit-choice-desc { display: block; margin-top: 14px; color: #526178; font-size: 0.82rem; font-weight: 600; line-height: 1.5; }
    .benefit-choice-arrow { position: absolute; top: 50%; right: 18px; color: #94a3b8; transform: translateY(-50%); }
    .benefit-choice-check { display: none; position: absolute; top: 14px; right: 14px; width: 24px; height: 24px; place-items: center; border-radius: 50%; background: var(--benefit-color); color: #fff; font-size: .7rem; }
    .benefit-choice-card.selected .benefit-choice-check { display: grid; }
    .benefit-choice-card.selected .benefit-choice-arrow { display: none; }
    .benefit-choice-card:disabled {
        cursor: not-allowed;
        opacity: .58;
        filter: grayscale(.35);
        transform: none;
        box-shadow: none;
    }
    .benefit-choice-card:disabled:hover { transform: none; border-color: #d8e1ec; box-shadow: none; }
    .benefit-choice-ineligible { display:block; margin-top:10px; color:#b91c1c; font-size:.76rem; font-weight:800; line-height:1.4; }

    .public-benefit-modal { display:none; position:fixed; inset:0; z-index:3000; align-items:center; justify-content:center; padding:20px; background:rgba(15,23,42,.72); backdrop-filter:blur(4px); }
    .public-benefit-modal.open { display:flex; }
    .public-benefit-dialog { width:min(680px,100%); height:min(760px,90vh); overflow:hidden; border:1px solid #d0dae8; border-radius:18px; background:#fff; box-shadow:0 28px 80px rgba(15,23,42,.35); display:flex; flex-direction:column; }
    .public-benefit-head { position:relative; flex:0 0 auto; padding:24px 56px 22px 28px; border-bottom:1px solid #e2e8f0; background:linear-gradient(135deg,#f0f7ff,#f8fafc 55%,#fff); }
    .public-benefit-head::before { content:''; position:absolute; inset:0 auto 0 0; width:5px; border-radius:18px 0 0; background:var(--modal-accent,#3b82f6); }
    .public-benefit-head { display:flex; align-items:flex-start; gap:16px; }
    .public-benefit-icon { flex:0 0 48px; width:48px; height:48px; display:grid; place-items:center; border-radius:12px; color:#fff; background:var(--modal-accent,#3b82f6); box-shadow:0 8px 18px color-mix(in srgb,var(--modal-accent,#3b82f6) 30%,transparent); }
    .public-benefit-copy { min-width:0; }
    .public-benefit-head h3 { margin:0 44px 6px 0; color:#0f172a; font-size:1.35rem; }
    .public-benefit-head p { margin:0; color:#64748b; line-height:1.55; }
    .public-benefit-close { position:absolute; top:16px; right:18px; width:36px; height:36px; border:0; border-radius:9px; background:#e2e8f0; color:#475569; cursor:pointer; font-size:1.2rem; }
    .public-benefit-body { flex:1 1 auto; min-height:0; overflow-y:auto; padding:20px 24px 24px; }
    .public-benefit-block { margin-bottom:14px; padding:16px 18px; border:1px solid #d8e0ea; border-radius:12px; background:#f8fafc; box-shadow:0 5px 14px rgba(15,23,42,.05); }
    .public-benefit-block h4 { margin:0 0 12px; color:#475569; font-size:.78rem; text-transform:uppercase; letter-spacing:.06em; }
    .public-benefit-block ul { margin:0; padding-left:20px; color:#334155; line-height:1.55; }
    .public-benefit-block li { padding:7px 0; border-bottom:1px solid #e8eef4; }
    .public-benefit-block li:last-child { border-bottom:0; }
    .public-benefit-ack { display:flex; gap:10px; align-items:flex-start; padding:14px; border:1px solid #dbe4ef; border-radius:10px; background:#f8fafc; color:#334155; font-size:.86rem; line-height:1.45; }
    .public-benefit-ack input { margin-top:3px; accent-color:var(--modal-accent,#3b82f6); }
    .public-benefit-actions { flex:0 0 auto; display:flex; justify-content:flex-end; gap:10px; padding:16px 24px 20px; border-top:1px solid #e2e8f0; background:#f8fafc; }
    .public-benefit-actions button { padding:10px 18px; border-radius:9px; font:700 .88rem Inter,sans-serif; cursor:pointer; }
    .public-benefit-cancel { border:1px solid #cbd5e1; background:#fff; color:#475569; }
    .public-benefit-proceed { border:0; background:var(--modal-accent,#3b82f6); color:#fff; }
    .public-benefit-proceed:disabled { opacity:.45; cursor:not-allowed; }
    .public-benefit-selector.hidden { display:none; }
    .public-application-body { display:none; }
    .public-application-body.visible { display:block; animation:fadeInSlide .35s ease; }
    .public-selected-benefit { display:flex; align-items:center; gap:14px; margin-bottom:22px; padding:15px 18px; border:1px solid #bfdbfe; border-radius:13px; background:#eff6ff; }
    .public-selected-benefit i { color:#2563eb; font-size:1.25rem; }
    .public-selected-benefit strong { display:block; color:#172033; }
    .public-selected-benefit span { color:#64748b; font-size:.8rem; }
    .public-selected-benefit button { margin-left:auto; padding:8px 11px; border:1px solid #93c5fd; border-radius:8px; background:#fff; color:#1d4ed8; font-weight:700; cursor:pointer; }
    @media (max-width: 600px) {
        .public-benefit-modal { align-items:flex-end; padding:0; }
        .public-benefit-dialog { width:100%; height:min(88dvh,820px); border-width:1px 0 0; border-radius:18px 18px 0 0; padding-bottom:env(safe-area-inset-bottom); }
        .public-benefit-head { padding:18px 50px 16px 18px; }
        .public-benefit-body { padding:14px; }
        .public-benefit-actions { padding:12px 14px; }
        .public-benefit-actions button { flex:1 1 0; }
        .benefit-choice-grid { grid-template-columns:1fr; gap:10px; }
        .benefit-choice-card { display:grid; grid-template-columns:48px minmax(0,1fr) 22px; grid-template-rows:auto auto auto; column-gap:13px; min-height:0; padding:16px; }
        .benefit-choice-icon { grid-column:1; grid-row:1 / span 3; width:46px; height:46px; margin:0; align-self:start; font-size:1.05rem; }
        .benefit-choice-title { grid-column:2; grid-row:1; max-width:none; padding-right:0; font-size:.98rem; }
        .benefit-choice-desc { grid-column:2; grid-row:2; margin-top:5px; font-size:.79rem; line-height:1.4; }
        .benefit-choice-ineligible { grid-column:2 / span 2; grid-row:3; margin-top:7px; }
        .benefit-choice-arrow { grid-column:3; grid-row:1 / span 3; position:static; align-self:center; transform:none; }
        .benefit-choice-check { top:10px; right:10px; }
        .benefit-access-grid { grid-template-columns: 1fr; }
        .form-row-names {
            grid-template-columns: 1fr;
        }
        .proxy-form :is(input, select, textarea, button, .btn) { min-height: 48px; font-size: 16px; }
        .proxy-form input[type="checkbox"], .proxy-form input[type="radio"] { min-height: 22px; width: 22px; height: 22px; }
        .proxy-form .upload-slot { padding: 14px; }
        .proxy-form .upload-slot input[type="file"] { width: 100%; min-height: 52px; padding: 9px; }
        .mobile-form-progress { position: sticky; top: 8px; z-index: 20; }
        #btnSubmitNew { position:static; width:100%; touch-action:manipulation; box-shadow:0 10px 28px rgba(15,23,42,.25); }
        .proxy-form { padding-bottom: max(28px, env(safe-area-inset-bottom)); }
        .proxy-form .form-group { scroll-margin-top: 92px; scroll-margin-bottom: 28vh; }
        .proxy-form :is(input, select, textarea) { scroll-margin-top: 92px; scroll-margin-bottom: 32vh; }
        body.mobile-keyboard-open .mobile-form-progress { position: static; }
        body.mobile-keyboard-open .site-header { position: static; }
        body.mobile-keyboard-open .proxy-panel-body { padding-bottom: 34vh; }
    }

    @media (max-width: 380px) {
        .benefit-choice-card { grid-template-columns:42px minmax(0,1fr) 18px; gap:10px; padding:14px 12px; }
        .benefit-choice-icon { width:40px; height:40px; }
        .public-benefit-actions { flex-direction:column-reverse; }
        .public-benefit-actions button { width:100%; flex-basis:auto; min-height:48px; }
        .mobile-form-progress { align-items:flex-start; flex-direction:column; }
        .mobile-form-progress__track { width:100%; }
    }

    .mobile-form-progress { display:flex; align-items:center; gap:8px; margin:0 0 18px; padding:10px 12px; border:1px solid #bfdbfe; border-radius:10px; background:rgba(239,246,255,.96); color:#475569; font-size:.76rem; font-weight:700; backdrop-filter:blur(8px); }
    .mobile-form-progress__label { white-space:nowrap; }
    .mobile-form-progress__track { display:grid; grid-template-columns:repeat(3,1fr); gap:5px; flex:1; }
    .mobile-form-progress__track span { height:6px; border-radius:999px; background:#cbd5e1; transition:background .2s ease; }
    .mobile-form-progress[data-step="1"] .mobile-form-progress__track span:nth-child(1),
    .mobile-form-progress[data-step="2"] .mobile-form-progress__track span:nth-child(-n+2),
    .mobile-form-progress[data-step="3"] .mobile-form-progress__track span { background:#2563eb; }
</style>

<?php if (!$proxySuccess): ?>
    
    <!-- Option Selection Header -->
    <div style="margin-bottom: 25px; text-align: center;">
        <h3 style="margin-bottom: 10px; color: #0f172a; font-weight: 700;"><?php echo $benefitPortalMode ? 'Choose a Senior Benefit' : 'Select Transaction Portal'; ?></h3>
        <p style="color: #64748b; font-size: 0.95rem; margin: 0;"><?php echo $benefitPortalMode ? 'Identity verified. Choose the benefit the senior citizen needs.' : 'Choose the service that matches the senior citizen’s current status.'; ?></p>
    </div>

    <!-- Portal Option Choice Cards -->
    <?php if (!$benefitPortalMode): ?>
    <div class="portal-option-grid" id="portalOptionGrid">
        <div class="portal-option-card<?php echo $activePortalOption === 'new_senior' ? ' active' : ''; ?>" id="optionCardNew" onclick="selectPortalPath('new_senior')">
            <div class="check-indicator"><i class="fas fa-check"></i></div>
            <div class="option-icon" style="background: #10b981;">
                <i class="fas fa-user-plus"></i>
            </div>
            <h4>Apply for Senior ID</h4>
            <p>For seniors without an approved ID. Complete the required Senior Citizen ID application first.</p>
        </div>

        <div class="portal-option-card" id="optionCardBenefits" role="button" tabindex="0" aria-pressed="false" onclick="selectPortalPath('existing_benefits')" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();selectPortalPath('existing_benefits');}">
            <div class="check-indicator"><i class="fas fa-check"></i></div>
            <div class="option-icon" style="background:#3b82f6;">
                <i class="fas fa-hand-holding-heart"></i>
            </div>
            <h4>Apply for Senior Benefits</h4>
            <p>For approved senior citizens. Verify the official Senior ID number and permanent token to continue.</p>
        </div>

    </div>
    <?php endif; ?>

    <?php if (!$benefitPortalMode): ?>
    <div id="benefitAccessSection" class="form-switch-section benefit-access-section">
        <div class="benefit-access-heading">
            <div class="option-icon"><i class="fas fa-shield-halved" aria-hidden="true"></i></div>
            <div>
                <h3>Verify Senior Identity</h3>
                <p>Enter the approved Senior Citizen ID number and permanent token to open the benefits application.</p>
            </div>
        </div>
        <form method="post" action="senior_benefits.php">
            <input type="hidden" name="verify_benefit_access" value="1">
            <div class="benefit-access-grid">
                <div><label for="benefitSeniorCitizenId">Senior Citizen ID Number</label><input id="benefitSeniorCitizenId" name="seniorCitizenId" autocomplete="off" required></div>
                <div><label for="benefitPermanentToken">Permanent ID Token</label><input id="benefitPermanentToken" name="permanentToken" value="PRX-" placeholder="PRX-XXXX" maxlength="16" autocomplete="off" autocapitalize="characters" spellcheck="false" aria-describedby="benefitPermanentTokenHint" required><small id="benefitPermanentTokenHint">PRX- is added for you. Enter the remaining letters or numbers.</small></div>
            </div>
            <button class="benefit-access-submit" type="submit"><i class="fas fa-user-check" aria-hidden="true"></i> Verify Identity</button>
        </form>
    </div>
    <?php endif; ?>

    <!-- Display backend error message if any -->
    <?php if (!empty($proxyMessage)): ?>
        <div class="alert-banner alert-banner-error">
            <i class="fas fa-exclamation-circle"></i>
            <div><?php echo htmlspecialchars($proxyMessage); ?></div>
        </div>
    <?php endif; ?>

    <!-- ── FORM A: NEW SENIOR PRE-REGISTRATION ── -->
    <div id="newSeniorFormSection" class="form-switch-section" style="display:block;">
        <form method="POST" action="<?php echo htmlspecialchars($formAction); ?>" enctype="multipart/form-data" class="proxy-form" id="newSeniorForm" novalidate>
            <input type="hidden" name="proxy_submit" value="1">
            <input type="hidden" name="portal_option" value="<?php echo $benefitPortalMode ? 'verified_benefits' : 'new_senior'; ?>">
            <?php if ($benefitPortalMode): ?>
                <input type="hidden" name="seniorCitizenId" value="<?php echo htmlspecialchars((string)($benefitPrefill['seniorCitizenId'] ?? '')); ?>">
                <input type="hidden" name="permanentToken" value="<?php echo htmlspecialchars((string)($benefitPrefill['permanentToken'] ?? '')); ?>">
            <?php endif; ?>
            <div class="mobile-form-progress" id="newSeniorProgress" data-step="1" role="status" aria-live="polite">
                <span class="mobile-form-progress__label">Step 1 of 3 · Details</span>
                <span class="mobile-form-progress__track" aria-hidden="true"><span></span><span></span><span></span></span>
            </div>
            <?php $defaultPublicBenefit = $benefitPortalMode ? '' : 'Senior Citizen ID Registration'; ?>
            <select id="requestedBenefit" name="requestedBenefit" hidden aria-hidden="true">
                <option value="">— Select Benefit or Service —</option>
                <?php foreach (array_keys($publicBenefitDefinitions) as $value): ?>
                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($old('requestedBenefit', $defaultPublicBenefit) === $value && (empty($transferBenefitsLocked) || $value === 'Senior Citizen ID Registration')) ? 'selected' : ''; ?>><?php echo htmlspecialchars($value); ?></option>
                <?php endforeach; ?>
            </select>

            <?php
            $selectedPublicBenefit = $old('requestedBenefit', $defaultPublicBenefit);
            if (!empty($transferBenefitsLocked) && $selectedPublicBenefit !== 'Senior Citizen ID Registration') {
                $selectedPublicBenefit = '';
            }
            $hasSelectedPublicBenefit = $selectedPublicBenefit !== '';
            ?>
            <div class="public-benefit-selector<?php echo $hasSelectedPublicBenefit ? ' hidden' : ''; ?>" id="publicBenefitSelector">
                <p class="benefit-choice-hint"><i class="fas fa-hand-pointer" aria-hidden="true"></i> Click a benefit below to view details and requirements before applying.</p>
                <div class="benefit-choice-grid" id="benefitChoiceGrid" role="group" aria-label="Choose a benefit or service" tabindex="-1">
                    <?php foreach ($publicBenefitDefinitions as $value => $definition):
                        $icon = $definition['icon']; $color = $definition['accent'];
                        $title = $definition['label']; $description = $definition['summary'];
                        $minimumBenefitAge = (int)($definition['minimum_age'] ?? 60);
                        $ageBlocked = $benefitPortalMode && ($verifiedBenefitAge === null || $verifiedBenefitAge < $minimumBenefitAge);
                        $milestoneBlocked = $benefitPortalMode && $value === 'Milestone Cash Gift' && $verifiedMilestoneAge === null;
                        $alreadyApplied = $benefitPortalMode && array_key_exists($value, $unavailableBenefitRequests);
                        $residencyBlocked = $benefitPortalMode && !empty($transferBenefitsLocked) && $value !== 'Senior Citizen ID Registration';
                        $benefitBlocked = $ageBlocked || $milestoneBlocked || $alreadyApplied || $residencyBlocked;
                        ?>
                        <button type="button" class="benefit-choice-card<?php echo $residencyBlocked ? ' residency-locked' : ''; ?>" style="--benefit-color:<?php echo $color; ?>" data-benefit-value="<?php echo htmlspecialchars($value); ?>" data-residency-locked="<?php echo $residencyBlocked ? 'true' : 'false'; ?>" aria-pressed="false" <?php echo $benefitBlocked ? 'disabled aria-disabled="true"' : ''; ?> onclick="openPublicBenefitModal(<?php echo htmlspecialchars(json_encode($value), ENT_QUOTES, 'UTF-8'); ?>, this)">
                            <span class="benefit-choice-check"><i class="fas fa-check" aria-hidden="true"></i></span>
                            <span class="benefit-choice-icon"><i class="<?php echo $icon; ?>" aria-hidden="true"></i></span>
                            <span class="benefit-choice-title"><?php echo htmlspecialchars($title); ?></span>
                            <span class="benefit-choice-desc"><?php echo htmlspecialchars($description); ?></span>
                            <?php if ($residencyBlocked): ?><span class="benefit-choice-ineligible"><i class="fas fa-lock" aria-hidden="true"></i> Available <?php echo htmlspecialchars($transferBenefitEligibleAt->format('F j, Y')); ?> after the 2-year residency period.</span>
                            <?php elseif ($alreadyApplied): ?><span class="benefit-choice-ineligible">Already applied — <?php echo htmlspecialchars($unavailableBenefitRequests[$value]); ?>.</span>
                            <?php elseif ($ageBlocked): ?><span class="benefit-choice-ineligible">Not eligible at current age<?php echo $verifiedBenefitAge !== null ? ' (' . (int)$verifiedBenefitAge . ')' : ''; ?>. Required: <?php echo $minimumBenefitAge; ?> or older.</span>
                            <?php elseif ($milestoneBlocked): ?><span class="benefit-choice-ineligible">Not eligible at current age<?php echo $verifiedBenefitAge !== null ? ' (' . (int)$verifiedBenefitAge . ')' : ''; ?>. Required: 80, 85, 90, 95, or 100+.</span><?php endif; ?>
                            <span class="benefit-choice-arrow"><i class="fas fa-chevron-right" aria-hidden="true"></i></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="public-application-body<?php echo $hasSelectedPublicBenefit ? ' visible' : ''; ?>" id="publicApplicationBody">
            <div class="public-selected-benefit">
                <i class="fas fa-circle-check" aria-hidden="true"></i>
                <div><span>Selected Application Form</span><strong id="publicSelectedBenefitLabel"><?php echo htmlspecialchars($selectedPublicBenefit); ?></strong></div>
                <?php if ($benefitPortalMode): ?><button type="button" onclick="changePublicBenefit()">Change Benefit</button><?php endif; ?>
            </div>

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
                    <div class="form-group" data-omit-benefits="Local Social Pension Assessment">
                        <label for="placeOfBirth">Place of Birth <span style="color:#b91c1c;">*</span></label>
                        <input type="text" id="placeOfBirth" name="placeOfBirth" class="form-control" value="<?php echo $old('placeOfBirth'); ?>" placeholder="City / Municipality, Province" required>
                    </div>
                    <div class="form-group">
                        <label for="mothersMaidenName">Mother's Maiden Name <span id="mothersMaidenRequired" class="required-marker" hidden>*</span></label>
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
                    <div class="form-group" data-omit-benefits="Local Social Pension Assessment">
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
                    <div class="form-group" data-omit-benefits="Local Social Pension Assessment">
                        <label for="zipCode">ZIP Code</label>
                        <input type="text" id="zipCode" name="zipCode" class="form-control" maxlength="4" pattern="[0-9]{4}" inputmode="numeric" value="<?php echo $old('zipCode'); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group" data-omit-benefits="Local Social Pension Assessment">
                        <label for="landmark">Nearest Landmark</label>
                        <input type="text" id="landmark" name="landmark" class="form-control" value="<?php echo $old('landmark'); ?>" placeholder="Helps the home-visit team locate the senior">
                    </div>
                    <div class="form-group" data-omit-benefits="Local Social Pension Assessment">
                        <label for="seniorEmail">Senior's Email Address</label>
                        <input type="email" id="seniorEmail" name="seniorEmail" class="form-control" value="<?php echo $old('seniorEmail'); ?>" autocomplete="email" placeholder="Optional">
                    </div>
                </div>
                <input type="hidden" id="completeAddress" name="completeAddress" value="<?php echo $old('completeAddress'); ?>">

                <div id="seniorSupportSection" hidden>
                <div class="form-subheading">Senior support and mobility information</div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="mobilityStatus">Mobility Status <span style="color:#b91c1c;">*</span></label>
                        <select id="mobilityStatus" name="mobilityStatus" class="form-control" disabled>
                            <option value="">— Select Mobility Status —</option>
                            <?php foreach (['Physically Fit', 'Needs Mobility Assistance', 'Bedridden', 'Frail / Sickly', 'PWD'] as $value): ?>
                                <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $old('mobilityStatus') === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($value); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="livingArrangement">Living Arrangement <span style="color:#b91c1c;">*</span></label>
                        <select id="livingArrangement" name="livingArrangement" class="form-control" disabled>
                            <option value="">— Select Arrangement —</option>
                            <?php foreach (['Living alone', 'With spouse', 'With children or relatives', 'With caregiver', 'Care facility'] as $value): ?>
                                <option value="<?php echo $value; ?>" <?php echo $old('livingArrangement') === $value ? 'selected' : ''; ?>><?php echo $value; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                </div>
                <div class="form-subheading">Benefit-specific information</div>
                <div id="benefitFieldsPrompt" class="privacy-alert" style="margin-bottom:0; background:#f8fafc; border-color:#cbd5e1; color:#475569;">
                    <i class="fas fa-hand-pointer" aria-hidden="true" style="color:#64748b;"></i>
                    <div>Select a benefit or service above to display its required questions.</div>
                </div>

                <div class="benefit-specific-panel" data-benefits="Senior Citizen ID Registration" hidden>
                    <h4 class="benefit-panel-title"><?php echo $benefitPortalMode ? 'Update or Replace Senior ID' : 'Senior Citizen ID Registration'; ?></h4>
                    <p class="benefit-panel-copy"><?php echo $benefitPortalMode ? 'Choose whether to change the approved ID information or replace a lost ID.' : 'Complete the additional fields shown on the official Senior Citizens ID Application.'; ?></p>
                    <div class="form-group">
                        <label for="idPurpose">ID Application Purpose <span style="color:#b91c1c;">*</span></label>
                        <select id="idPurpose" name="idPurpose" class="form-control" data-benefit-required onchange="updateSeniorIdDocuments(); updateNewSeniorProgress();">
                            <option value="">— Select Purpose —</option>
                            <?php if ($benefitPortalMode): ?>
                                <option value="change" <?php echo $old('idPurpose') === 'change' ? 'selected' : ''; ?>>Information change</option>
                                <option value="lost" <?php echo $old('idPurpose') === 'lost' ? 'selected' : ''; ?>>Lost ID replacement</option>
                            <?php else: ?>
                                <option value="new" <?php echo $old('idPurpose') === 'new' ? 'selected' : ''; ?>>New / First-time registration</option>
                                <option value="transfer" <?php echo $old('idPurpose') === 'transfer' ? 'selected' : ''; ?>>Transfer</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <?php if ($benefitPortalMode): ?>
                    <div id="informationChangeNotice" class="information-change-guide" role="status" aria-live="polite" hidden>
                        <span class="information-change-guide__icon" aria-hidden="true"><i class="fas fa-pen-to-square"></i></span>
                        <div>
                            <strong>How to change your information</strong>
                            <ol>
                                <li>Select and edit the blue-highlighted personal information fields above.</li>
                                <li>Change only the details that need correction.</li>
                                <li>Upload a clear copy of the current Senior Citizen ID as supporting proof.</li>
                                <li>Review and submit the request. Your approved profile will be updated only after OSCA verifies the change.</li>
                            </ol>
                            <button type="button" class="information-change-guide__button" onclick="focusInformationChangeFields()"><i class="fas fa-arrow-up" aria-hidden="true"></i> Go to editable information</button>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="healthStatus">Health Status <span style="color:#b91c1c;">*</span></label>
                            <select id="healthStatus" name="healthStatus" class="form-control" data-benefit-required>
                                <option value="">— Select Health Status —</option>
                                <?php foreach (['Physically Fit', 'Bedridden', 'Frail/Sickly', 'PWD'] as $value): ?>
                                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $old('healthStatus') === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($value); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="healthCondition">Frail/Sickly or PWD Details</label>
                            <input type="text" id="healthCondition" name="healthCondition" class="form-control" value="<?php echo $old('healthCondition'); ?>" placeholder="Specify condition, if applicable">
                        </div>
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
                        <div class="form-group">
                            <label for="emergencyContactRelationship">Relationship <span style="color:#b91c1c;">*</span></label>
                            <select id="emergencyContactRelationship" name="emergencyContactRelationship" class="form-control" data-benefit-required>
                                <option value="">Select relationship</option>
                                <?php foreach (getEmergencyContactRelationshipOptions() as $relationship): ?>
                                    <option value="<?= htmlspecialchars($relationship) ?>" <?= $old('emergencyContactRelationship') === $relationship ? 'selected' : '' ?>><?= htmlspecialchars($relationship) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="benefit-specific-panel" data-benefits="Local Social Pension Assessment" hidden>
                    <h4 class="benefit-panel-title">Local Senior Pension Form</h4>
                    <p class="benefit-panel-copy">Complete the personal and economic-status fields shown on the official Local Senior Pension Form.</p>
                    <div class="form-row">
                        <div class="form-group"><label for="pensionControlNo">Control Number</label><input type="text" id="pensionControlNo" name="controlNo" class="form-control" value="<?php echo $old('controlNo'); ?>"></div>
                        <div class="form-group"><label for="atmCardNo">ATM Number or Temporary Cash Card Stub Number <span style="color:#b91c1c;">*</span></label><input type="text" id="atmCardNo" name="atmCardNo" class="form-control" value="<?php echo $old('atmCardNo'); ?>" data-benefit-required></div>
                    </div>
                    <div class="form-group">
                        <label for="pensionSeniorIdNo">Senior Citizen ID Number</label>
                        <input type="text" id="pensionSeniorIdNo" class="form-control" value="<?php echo htmlspecialchars((string)($verifiedSenior['senior_id_no'] ?? '')); ?>" readonly>
                    </div>
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
                            <label for="familySupportAmount">Family Support — Cash Amount</label>
                            <input type="number" id="familySupportAmount" name="familySupportAmount" class="form-control" value="<?php echo $old('familySupportAmount'); ?>" min="0" step="0.01" placeholder="0.00">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="familySupportType">Type of Family Support</label>
                            <input type="text" id="familySupportType" name="familySupportType" class="form-control" value="<?php echo $old('familySupportType'); ?>" placeholder="e.g. Cash, food, medicine">
                        </div>
                        <div class="form-group">
                            <label for="isPermanentIncome">Permanent source of income? <span style="color:#b91c1c;">*</span></label>
                            <select id="isPermanentIncome" name="isPermanentIncome" class="form-control" data-benefit-required>
                                <option value="">— Select —</option>
                                <option value="1" <?php echo $old('isPermanentIncome') === '1' ? 'selected' : ''; ?>>Yes</option>
                                <option value="0" <?php echo $old('isPermanentIncome') === '0' ? 'selected' : ''; ?>>No</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="incomeSource">Permanent Income Source (if yes)</label>
                            <input type="text" id="incomeSource" name="incomeSource" class="form-control" value="<?php echo $old('incomeSource'); ?>" placeholder="Specify the source of income">
                        </div>
                        <div class="form-group">
                            <label for="pensionHealthCondition">Condition / Illness <span style="color:#b91c1c;">*</span></label>
                            <select id="pensionHealthCondition" name="healthCondition" class="form-control" data-benefit-required>
                                <option value="">— Select condition —</option>
                                <?php foreach (getHealthConditionOptions() as $condition): ?>
                                    <option value="<?php echo htmlspecialchars($condition); ?>" <?php echo $old('healthCondition') === $condition ? 'selected' : ''; ?>><?php echo htmlspecialchars($condition); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
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
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="seniorIdTypePresented">ID Presented <span style="color:#b91c1c;">*</span></label>
                            <select id="seniorIdTypePresented" name="seniorIdTypePresented" class="form-control" data-benefit-required>
                                <option value="">Select ID</option>
                                <?php foreach (['OSCA / Senior Citizen ID', 'PhilSys ID', 'Passport', 'Driver’s License', 'Other Government ID'] as $value): ?>
                                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $old('seniorIdTypePresented') === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($value); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="nationality">Nationality <span style="color:#b91c1c;">*</span></label>
                            <select id="nationality" name="nationality" class="form-control" data-benefit-required>
                                <option value="">Select nationality</option>
                                <?php foreach (['Filipino', 'Dual Citizen', 'Foreign National'] as $value): ?>
                                    <option value="<?php echo $value; ?>" <?php echo $old('nationality') === $value ? 'selected' : ''; ?>><?php echo $value; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="sourceOfFunds">Source of Funds <span style="color:#b91c1c;">*</span></label>
                        <select id="sourceOfFunds" name="sourceOfFunds" class="form-control" data-benefit-required>
                            <option value="">Select source of funds</option>
                            <?php foreach (['Senior Pension', 'Government Assistance', 'Family Support', 'Employment / Business Income', 'Savings', 'Other'] as $value): ?>
                                <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $old('sourceOfFunds') === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($value); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="benefit-specific-panel" data-benefits="Milestone Cash Gift" hidden>
                    <h4 class="benefit-panel-title">Milestone Cash Gift</h4>
                    <p class="benefit-panel-copy">Complete the milestone and claimant fields shown on the official Octogenarian, Nonagenarian, and Centenarian form.</p>
                    <div class="form-group">
                        <label for="milestoneAge">Milestone Age <span style="color:#b91c1c;">*</span></label>
                        <select id="milestoneAge" name="milestoneAge" class="form-control" data-benefit-required>
                            <option value="">— Select Milestone —</option>
                            <?php foreach ((getPublicBenefitDefinitions()['Milestone Cash Gift']['milestone_ages'] ?? []) as $milestone): $value = (string)$milestone; ?>
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

                <div class="benefit-specific-panel" data-benefits="Burial Assistance" hidden>
                    <h4 class="benefit-panel-title">Burial Assistance</h4>
                    <p class="benefit-panel-copy">Provide the passing and claimant relationship details required for the burial assistance claim.</p>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="dateOfDeath">Date of Passing <span style="color:#b91c1c;">*</span></label>
                            <input type="date" id="dateOfDeath" name="dateOfDeath" class="form-control" value="<?php echo $old('dateOfDeath'); ?>" max="<?php echo date('Y-m-d'); ?>" data-benefit-required>
                        </div>
                        <div class="form-group">
                            <label for="relationshipToDeceased">Relationship to Deceased <span style="color:#b91c1c;">*</span></label>
                            <select id="relationshipToDeceased" name="relationshipToDeceased" class="form-control" data-benefit-required>
                                <option value="">Select relationship</option>
                                <?php foreach (getDeceasedRelationshipOptions() as $relationship): ?>
                                    <option value="<?= htmlspecialchars($relationship) ?>" <?= $old('relationshipToDeceased') === $relationship ? 'selected' : '' ?>><?= htmlspecialchars($relationship) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label for="seniorIdNo">Deceased Senior ID Number <span style="color:#b91c1c;">*</span></label><input type="text" id="seniorIdNo" name="seniorIdNo" class="form-control" value="<?php echo $old('seniorIdNo'); ?>" data-benefit-required></div>
                        <div class="form-group"><label for="landbankCardNo">Landbank Cash Card Number <span style="color:#b91c1c;">*</span></label><input type="text" id="landbankCardNo" name="landbankCardNo" class="form-control" value="<?php echo $old('landbankCardNo'); ?>" data-benefit-required></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label for="burialClaimantName">Name of Applicant / Claimant <span style="color:#b91c1c;">*</span></label><input type="text" id="burialClaimantName" name="claimantName" class="form-control" value="<?php echo $old('claimantName'); ?>" data-benefit-required></div>
                        <div class="form-group"><label for="burialClaimantContact">Claimant Contact Number <span style="color:#b91c1c;">*</span></label><input type="tel" id="burialClaimantContact" name="claimantContact" class="form-control" maxlength="11" pattern="09[0-9]{9}" inputmode="numeric" value="<?php echo $old('claimantContact'); ?>" data-benefit-required></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="idTypePresented">Proof of Relationship <span style="color:#b91c1c;">*</span></label>
                            <select id="idTypePresented" name="idTypePresented" class="form-control" data-benefit-required>
                                <option value="">— Select Proof —</option>
                                <?php foreach (['Marriage Contract', 'Birth Certificate', 'Other'] as $value): ?><option value="<?php echo $value; ?>" <?php echo $old('idTypePresented') === $value ? 'selected' : ''; ?>><?php echo $value; ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="controlNo">Affidavit Type (if applicable)</label>
                            <select id="controlNo" name="controlNo" class="form-control">
                                <option value="">Not applicable</option>
                                <?php foreach (['Kinship', 'Discrepancy', 'Died single without a child', 'Cohabitation', 'Other'] as $value): ?><option value="<?php echo $value; ?>" <?php echo $old('controlNo') === $value ? 'selected' : ''; ?>><?php echo $value; ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group"><label for="visitSummary">Remarks / Notes</label><textarea id="visitSummary" name="visitSummary" class="form-control" rows="3"><?php echo $old('visitSummary'); ?></textarea></div>
                </div>

            </div>

            <!-- Step 2 Heading (Dynamic Uploads) -->
            <div class="step-heading" style="margin-top: 25px;">
                <div class="step-number">2</div>
                <div>
                    <h3>Required Documents</h3>
                    <p>Upload the file types listed for each requirement below. Enabled upon senior age eligibility verification.</p>
                </div>
            </div>

            <!-- Dynamic Upload Slots Container -->
            <div class="upload-slots-container disabled" id="uploadSlotsContainer">
                
                <h5 style="margin-top:0; color:#475569; font-size:0.88rem; text-transform:uppercase; letter-spacing:0.5px;">Senior's Credentials</h5>
                
                <div class="upload-slot" data-document-slot="primary">
                    <label for="psa_birth_cert_file"><span data-document-label="primary">Birth Cert / Negative of Birth</span> <span class="req">*</span></label>
                    <p class="slot-desc" data-document-description="primary">Upload a birth certificate or, if unavailable, a Negative of Birth certification.</p>
                    <input type="file" id="psa_birth_cert_file" name="psa_birth_cert_file" accept="image/jpeg,application/pdf" capture="environment" required>
                    <div class="id-pair-preview" data-id-pair-preview hidden></div><p class="id-pair-error" data-id-pair-error role="alert" hidden></p>
                </div>
                
                <div class="upload-slot" data-document-slot="secondary">
                    <label for="barangay_residency_file"><span data-document-label="secondary">Barangay Residency Certificate</span> <span class="req">*</span></label>
                    <p class="slot-desc" data-document-description="secondary">Issued by the barangay within the last 6 months confirming Pasig residency.</p>
                    <input type="file" id="barangay_residency_file" name="barangay_residency_file" accept="image/jpeg,application/pdf" capture="environment" required>
                </div>

                <div class="upload-slot" data-document-slot="supporting">
                    <label for="comelec_cert_file"><span data-document-label="supporting">2-Year COMELEC Certification</span> <span class="req">*</span></label>
                    <p class="slot-desc" data-document-description="supporting">Official voter residency certification of the senior citizen applicant.</p>
                    <input type="file" id="comelec_cert_file" name="comelec_cert_file" accept="image/jpeg,application/pdf" capture="environment" required>
                </div>

                <h5 id="identityDocumentsHeading" style="margin-top:20px; color:#475569; font-size:0.88rem; text-transform:uppercase; letter-spacing:0.5px;">Additional Required Documents</h5>

                <div class="upload-slot" id="idPhotoUploadSlot" data-extra-document-benefits="<?php echo htmlspecialchars(getApplicationBenefitDetails()['senior']['public_request']); ?>|Local Social Pension Assessment">
                    <label for="id_photo_file">Recent 1×1 ID Photo <span class="req">*</span></label>
                    <p class="slot-desc">Upload a clear, recent 1×1 portrait with a white background. Bring two printed 1×1 copies during counter verification. JPEG or PNG only.</p>
                    <input type="file" id="id_photo_file" name="id_photo_file" accept="image/jpeg,image/png" capture="user">
                </div>
                <div class="upload-slot" id="validIdUploadSlot" data-extra-document-benefits="<?php echo htmlspecialchars(getApplicationBenefitDetails()['senior']['public_request']); ?>">
                    <label for="valid_id_file">Valid Government ID <span class="req">*</span></label>
                    <p class="slot-desc">Upload clear images of the front and back of one valid government-issued ID. PNG, JPG, or JPEG only. Maximum of 2 images.</p>
                    <input type="file" id="valid_id_file" name="valid_id_file[]" accept=".png,.jpg,.jpeg,image/png,image/jpeg" multiple>
                    <div class="id-pair-preview" data-id-pair-preview hidden></div><p class="id-pair-error" data-id-pair-error role="alert" hidden></p>
                </div>

                <div class="upload-slot" data-extra-document-benefits="Burial Assistance">
                    <label for="deceased_landbank_card_file">Deceased Landbank Cash Card <span class="req">*</span></label>
                    <p class="slot-desc">Upload the front and back of the deceased senior citizen's Landbank cash card.</p>
                    <input type="file" id="deceased_landbank_card_file" name="deceased_landbank_card_file" accept="image/jpeg,image/png,application/pdf">
                </div>

                <div class="upload-slot" data-extra-document-benefits="Burial Assistance">
                    <label for="proof_of_life_file">Proof of Relationship <span class="req">*</span></label>
                    <p class="slot-desc">Marriage contract, claimant birth certificate, or other accepted proof connecting the claimant and deceased.</p>
                    <input type="file" id="proof_of_life_file" name="proof_of_life_file" accept="image/jpeg,image/png,application/pdf">
                </div>
                <div class="upload-slot" data-extra-document-benefits="Burial Assistance" id="burialAffidavitSlot" hidden>
                    <label for="auth_letter_file">Original Copy of Affidavit (if applicable) <span class="req">*</span></label>
                    <p class="slot-desc">Upload the selected affidavit and bring the original plus one photocopy for verification.</p>
                    <input type="file" id="auth_letter_file" name="auth_letter_file" accept="image/jpeg,image/png,application/pdf">
                </div>
                
            </div>

            <!-- Step 3 Heading -->
            <div class="step-heading" style="margin-top: 25px;">
                <div class="step-number">3</div>
                <div>
                    <h3>Review and Consent</h3>
                    <p>Confirm that the senior applicant's information is complete and accurate before submission.</p>
                </div>
            </div>

            <div class="form-section">
                <div class="checkbox-row" style="margin-top: 15px;">
                    <input type="checkbox" id="confirmPrivacy" name="confirmPrivacy" required style="width: 18px; height: 18px; accent-color: #10b981;">
                    <label for="confirmPrivacy" style="font-size: 0.85rem; font-weight: 500; color: #475569; margin: 0;">
                        I confirm that the information is accurate and consent to the collection and verification of the senior applicant's information and documents.
                    </label>
                </div>
            </div>

            <!-- Submit Button -->
            <div id="newSeniorValidationSummary" class="alert-banner alert-banner-error" role="alert" aria-live="assertive" hidden style="margin-top:25px;"></div>
            <button type="submit" class="btn btn-accent btn-block" id="btnSubmitNew" style="margin-top: 25px; padding: 14px; font-size: 1rem; background: #10b981; border-color: #10b981;">
                <i class="fas fa-qrcode"></i> <?php echo $benefitPortalMode ? 'Submit Application' : 'Submit Pre-Registration'; ?>
            </button>
            </div><!-- /#publicApplicationBody -->
        </form>
    </div>

    <div class="public-benefit-modal" id="publicBenefitModal" role="dialog" aria-modal="true" aria-labelledby="publicBenefitModalTitle" aria-hidden="true">
        <div class="public-benefit-dialog">
            <div class="public-benefit-head">
                <button type="button" class="public-benefit-close" onclick="closePublicBenefitModal()" aria-label="Close benefit details">&times;</button>
                <div class="public-benefit-icon" id="publicBenefitModalIcon"><i class="fas fa-file-alt"></i></div>
                <div class="public-benefit-copy"><h3 id="publicBenefitModalTitle"></h3><p id="publicBenefitModalSummary"></p></div>
            </div>
            <div class="public-benefit-body">
                <div class="public-benefit-block public-benefit-block--benefits"><h4><i class="fas fa-star" aria-hidden="true"></i> What You Get</h4><ul id="publicBenefitBenefits"></ul></div>
                <div class="public-benefit-block public-benefit-block--requirements"><h4><i class="fas fa-clipboard-check" aria-hidden="true"></i> Requirements to Apply</h4><ul id="publicBenefitRequirements"></ul></div>
                <div class="public-benefit-block public-benefit-block--documents"><h4><i class="fas fa-folder-open" aria-hidden="true"></i> Documents Needed</h4><ul id="publicBenefitDocuments"></ul></div>
                <label class="public-benefit-ack">
                    <input type="checkbox" id="publicBenefitAck" onchange="document.getElementById('publicBenefitProceed').disabled = !this.checked">
                    <span>I have read and understand the benefit details and have the required documents ready.</span>
                </label>
            </div>
            <div class="public-benefit-actions">
                <button type="button" class="public-benefit-cancel" onclick="closePublicBenefitModal()">Cancel</button>
                <button type="button" class="public-benefit-proceed" id="publicBenefitProceed" onclick="proceedPublicBenefit()" disabled>Proceed with Application</button>
            </div>
        </div>
    </div>

    <!-- Client-Side Form Scripts -->
    <script>
        const PUBLIC_BENEFIT_CONFIG = <?php echo json_encode($publicBenefitDefinitions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        let pendingPublicBenefit = '';
        let pendingPublicBenefitCard = null;

        function fillPublicBenefitList(id, items) {
            const list = document.getElementById(id);
            list.replaceChildren(...(items || []).map(item => {
                const li = document.createElement('li');
                li.textContent = item;
                return li;
            }));
        }

        function openPublicBenefitModal(value, card) {
            const config = PUBLIC_BENEFIT_CONFIG[value];
            const details = config;
            if (!config || !details) return;
            pendingPublicBenefit = value;
            pendingPublicBenefitCard = card;
            document.getElementById('publicBenefitModalTitle').textContent = config.label;
            document.getElementById('publicBenefitModalSummary').textContent = details.summary || '';
            document.getElementById('publicBenefitModalIcon').innerHTML = `<i class="${config.icon || 'fas fa-file-alt'}"></i>`;
            fillPublicBenefitList('publicBenefitBenefits', details.benefits);
            fillPublicBenefitList('publicBenefitRequirements', details.requirements);
            fillPublicBenefitList('publicBenefitDocuments', details.documents);
            const modal = document.getElementById('publicBenefitModal');
            modal.style.setProperty('--modal-accent', config.accent);
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
            document.getElementById('publicBenefitAck').checked = false;
            document.getElementById('publicBenefitProceed').disabled = true;
            document.body.style.overflow = 'hidden';
        }

        function closePublicBenefitModal() {
            const modal = document.getElementById('publicBenefitModal');
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }

        function proceedPublicBenefit() {
            if (!pendingPublicBenefit || !document.getElementById('publicBenefitAck').checked) return;
            const value = pendingPublicBenefit;
            const card = pendingPublicBenefitCard;
            closePublicBenefitModal();
            selectRequestedBenefit(value, card);
            document.getElementById('publicApplicationBody')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function selectPortalPath(path = 'new_senior') {
            document.querySelectorAll('.portal-option-card').forEach(card => card.classList.remove('active'));
            const showBenefits = path === 'existing_benefits';
            document.getElementById(showBenefits ? 'optionCardBenefits' : 'optionCardNew')?.classList.add('active');
            document.querySelectorAll('.portal-option-card').forEach(card => card.setAttribute('aria-pressed', card.classList.contains('active') ? 'true' : 'false'));
            const newSeniorSection = document.getElementById('newSeniorFormSection');
            const benefitAccessSection = document.getElementById('benefitAccessSection');
            if (newSeniorSection) newSeniorSection.style.display = showBenefits ? 'none' : 'block';
            if (benefitAccessSection) benefitAccessSection.style.display = showBenefits ? 'block' : 'none';
            (showBenefits ? benefitAccessSection : newSeniorSection)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function getCurrentAge(birthDateValue) {
            if (!/^\d{4}-\d{2}-\d{2}$/.test(birthDateValue || '')) return null;
            const [year, month, day] = birthDateValue.split('-').map(Number);
            const dob = new Date(year, month - 1, day);
            if (dob.getFullYear() !== year || dob.getMonth() !== month - 1 || dob.getDate() !== day) return null;
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            if (dob > today) return null;
            let age = today.getFullYear() - year;
            if (today.getMonth() < month - 1 || (today.getMonth() === month - 1 && today.getDate() < day)) age--;
            return age;
        }

        function syncMilestoneEligibility(age) {
            const milestone = document.getElementById('milestoneAge');
            if (!milestone) return true;
            const milestoneAges = PUBLIC_BENEFIT_CONFIG['Milestone Cash Gift']?.milestone_ages || [];
            let eligibleValue = '';
            const highestMilestone = Math.max(...milestoneAges);
            if (age >= highestMilestone) eligibleValue = String(highestMilestone);
            else if (milestoneAges.includes(age)) eligibleValue = String(age);

            milestone.querySelectorAll('option[value]').forEach(option => {
                if (!option.value) return;
                option.disabled = option.value !== eligibleValue;
            });
            milestone.value = eligibleValue;
            milestone.setCustomValidity(
                document.getElementById('requestedBenefit')?.value === 'Milestone Cash Gift' && !eligibleValue
                    ? 'Milestone cash gifts may only be claimed at age 80, 85, 90, 95, or 100 and above.'
                    : ''
            );
            return eligibleValue !== '';
        }

        // Rule-based age check: eligible seniors can proceed to document uploads.
        function calculateProxyAge2026() {
            const birthDateInput = document.getElementById('birthDate');
            const ageCheckStatus = document.getElementById('ageCheckStatus');
            const uploadSlotsContainer = document.getElementById('uploadSlotsContainer');
            
            if (!birthDateInput.value) {
                ageCheckStatus.innerHTML = '';
                uploadSlotsContainer.classList.add('disabled');
                birthDateInput.setCustomValidity('');
                syncMilestoneEligibility(null);
                return;
            }

            const age = getCurrentAge(birthDateInput.value);
            const selectedBenefit = document.getElementById('requestedBenefit')?.value || '';
            const milestoneEligible = syncMilestoneEligibility(age);
            const minimumAge = Number(PUBLIC_BENEFIT_CONFIG[selectedBenefit]?.minimum_age || 60);
            const benefitEligible = age !== null && age >= minimumAge &&
                (selectedBenefit !== 'Milestone Cash Gift' || milestoneEligible);
            birthDateInput.setCustomValidity(benefitEligible
                ? ''
                : (age === null
                    ? 'Enter a valid birth date that is not in the future.'
                    : `The applicant must be at least ${minimumAge} years old for this service.`));

            if (benefitEligible) {
                // Pass Rule
                ageCheckStatus.innerHTML = `<span style="color: #059669; font-weight: 600;"><i class="fas fa-check-circle"></i> Eligible for the selected service (Current age: ${age} years old)</span>`;
                uploadSlotsContainer.classList.remove('disabled');
                
                // Enable only visible document inputs. Hidden purpose-specific
                // inputs must remain disabled or browser validation can block
                // submission without showing the user which field is missing.
                uploadSlotsContainer.querySelectorAll('input[type="file"]').forEach(input => {
                    const slot = input.closest('.upload-slot');
                    input.disabled = Boolean(slot?.hidden);
                });
            } else {
                // Fail Rule
                let reason = 'Enter a valid birth date that is not in the future.';
                if (age !== null && selectedBenefit === 'Milestone Cash Gift') reason = `Age ${age} is not an eligible milestone. The senior must currently be 80, 85, 90, 95, or 100+.`;
                else if (age !== null) reason = `Current age is ${age}; this service requires age ${minimumAge} or older.`;
                ageCheckStatus.innerHTML = `<span style="color: #dc2626; font-weight: 600;"><i class="fas fa-times-circle"></i> Ineligible: ${reason}</span>`;
                uploadSlotsContainer.classList.add('disabled');
                
                // Disable file inputs
                uploadSlotsContainer.querySelectorAll('input[type="file"]').forEach(input => {
                    input.setAttribute('disabled', 'true');
                });
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
                ['isPensioner', ['pensionSource', 'pensionAmount']],
                ['isPermanentIncome', ['incomeSource']],
                ['familySupport', ['familySupportType', 'familySupportAmount']]
            ];
            rules.forEach(([choiceId, detailIds]) => {
                const choice = document.getElementById(choiceId);
                if (!choice || choice.disabled) return;
                const isApplicable = choice.value === '1';
                detailIds.forEach(detailId => {
                    const detail = document.getElementById(detailId);
                    if (!detail) return;
                    detail.disabled = !isApplicable;
                    detail.required = isApplicable;
                });
            });
        }

        function updateBenefitSpecificFields() {
            const selectedBenefit = document.getElementById('requestedBenefit')?.value || '';
            let visiblePanel = false;

            const supportSection = document.getElementById('seniorSupportSection');
            const benefitDefinition = PUBLIC_BENEFIT_CONFIG[selectedBenefit] || {};
            const requiredFieldNames = benefitDefinition.required_fields || [];
            const needsSupportAssessment = benefitDefinition.support_assessment === true;

            document.querySelectorAll('[data-omit-benefits]').forEach(group => {
                const omittedBenefits = (group.dataset.omitBenefits || '').split('|');
                const shouldOmit = omittedBenefits.includes(selectedBenefit);
                group.hidden = shouldOmit;
                group.querySelectorAll('input, select, textarea').forEach(control => {
                    if (!control.dataset.baseRequired) {
                        control.dataset.baseRequired = control.required ? '1' : '0';
                    }
                    control.disabled = shouldOmit;
                    control.required = !shouldOmit && control.dataset.baseRequired === '1';
                });
            });

            const mothersMaidenName = document.getElementById('mothersMaidenName');
            if (mothersMaidenName) mothersMaidenName.required = requiredFieldNames.includes('mothersMaidenName');
            const mothersMaidenRequired = document.getElementById('mothersMaidenRequired');
            if (mothersMaidenRequired) mothersMaidenRequired.hidden = !mothersMaidenName?.required;
            if (supportSection) supportSection.hidden = !needsSupportAssessment;
            ['mobilityStatus', 'livingArrangement'].forEach(id => {
                const control = document.getElementById(id);
                if (!control) return;
                control.disabled = !needsSupportAssessment;
                control.required = needsSupportAssessment;
            });

            document.querySelectorAll('.benefit-specific-panel').forEach(panel => {
                const benefits = (panel.dataset.benefits || '').split('|');
                const shouldShow = benefits.includes(selectedBenefit);
                panel.hidden = !shouldShow;
                visiblePanel = visiblePanel || shouldShow;

                panel.querySelectorAll('input, select, textarea').forEach(control => {
                    control.disabled = !shouldShow;
                    control.required = shouldShow && requiredFieldNames.includes(control.name);
                });
            });

            const prompt = document.getElementById('benefitFieldsPrompt');
            if (prompt) prompt.hidden = visiblePanel;
            updateRequiredDocuments(selectedBenefit);
            updateFinancialRequirements();
            calculateProxyAge2026();
            updateNewSeniorProgress();
        }

        function updateNewSeniorProgress() {
            const form = document.getElementById('newSeniorForm');
            const progress = document.getElementById('newSeniorProgress');
            if (!form || !progress) return;

            const requiredDetails = Array.from(form.querySelectorAll(':required:not([type="file"])'))
                .filter(control => !control.disabled && control.id !== 'confirmPrivacy');
            const detailsComplete = Boolean(document.getElementById('requestedBenefit')?.value)
                && requiredDetails.every(control => control.checkValidity());
            const requiredFiles = Array.from(form.querySelectorAll('input[type="file"]:required'))
                .filter(control => !control.disabled);
            const documentsComplete = requiredFiles.every(control => control.files?.length > 0);

            const step = !detailsComplete ? 1 : (documentsComplete ? 3 : 2);
            const labels = {
                1: 'Step 1 of 3 · Details',
                2: 'Step 2 of 3 · Documents',
                3: 'Step 3 of 3 · Review and submit'
            };
            progress.dataset.step = String(step);
            const label = progress.querySelector('.mobile-form-progress__label');
            if (label) label.textContent = labels[step];
        }

        function selectRequestedBenefit(value, selectedCard) {
            const select = document.getElementById('requestedBenefit');
            if (!select || selectedCard?.disabled || selectedCard?.dataset.residencyLocked === 'true') return;
            select.value = value;
            const config = PUBLIC_BENEFIT_CONFIG[value];
            document.querySelectorAll('.benefit-choice-card').forEach(card => {
                const selected = card === selectedCard;
                card.classList.toggle('selected', selected);
                card.setAttribute('aria-pressed', selected ? 'true' : 'false');
            });
            select.dispatchEvent(new Event('change', { bubbles: true }));
            document.getElementById('publicBenefitSelector')?.classList.add('hidden');
            document.getElementById('publicApplicationBody')?.classList.add('visible');
            const label = document.getElementById('publicSelectedBenefitLabel');
            if (label) label.textContent = config?.label || value;
        }

        function changePublicBenefit() {
            const select = document.getElementById('requestedBenefit');
            if (select) select.value = '';
            document.querySelectorAll('.benefit-choice-card').forEach(card => {
                card.classList.remove('selected');
                card.setAttribute('aria-pressed', 'false');
            });
            updateBenefitSpecificFields();
            document.getElementById('publicApplicationBody')?.classList.remove('visible');
            const selector = document.getElementById('publicBenefitSelector');
            selector?.classList.remove('hidden');
            selector?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        const governmentIdUploads = new WeakMap();
        const idImageMessage = 'Please upload both the front and back of your valid government ID.';
        const idImageLimitMessage = 'You can only upload 2 images: front and back of the ID.';
        const idImageTypeMessage = 'Upload PNG, JPG, or JPEG images only for both sides of the valid government ID.';
        const isIdImage = file => (/\.png$/i.test(file.name) && ['image/png', ''].includes(file.type))
            || (/\.jpe?g$/i.test(file.name) && ['image/jpeg', ''].includes(file.type));

        function showGovernmentIdError(input, message) {
            const error = input.closest('.upload-slot')?.querySelector('[data-id-pair-error]');
            if (error) { error.textContent = message; error.hidden = !message; }
            input.setAttribute('aria-invalid', message ? 'true' : 'false');
        }

        function renderGovernmentIdFiles(input) {
            const state = governmentIdUploads.get(input);
            if (!state) return;
            state.urls.forEach(url => URL.revokeObjectURL(url));
            state.urls = [];
            const transfer = new DataTransfer();
            state.files.forEach(file => { if (file) transfer.items.add(file); });
            input.files = transfer.files;
            const preview = input.closest('.upload-slot')?.querySelector('[data-id-pair-preview]');
            if (!preview) return;
            preview.replaceChildren();
            preview.hidden = !state.files.some(Boolean);
            ['Front of ID', 'Back of ID'].forEach((side, index) => {
                const file = state.files[index];
                if (!file) return;
                const url = URL.createObjectURL(file);
                state.urls.push(url);
                const row = document.createElement('div'); row.className = 'id-pair-item';
                const image = document.createElement('img'); image.src = url; image.alt = `${side} preview`;
                const title = document.createElement('strong'); title.textContent = `${side} — ${file.name}`;
                const actions = document.createElement('div'); actions.className = 'id-pair-actions';
                const view = document.createElement('a'); view.href = url; view.target = '_blank'; view.rel = 'noopener'; view.textContent = 'View';
                const replace = document.createElement('button'); replace.type = 'button'; replace.textContent = 'Replace';
                replace.addEventListener('click', () => { state.replaceIndex = index; state.replacePicker.click(); });
                const remove = document.createElement('button'); remove.type = 'button'; remove.textContent = 'Remove';
                remove.addEventListener('click', () => { state.files[index] = null; renderGovernmentIdFiles(input); showGovernmentIdError(input, idImageMessage); });
                actions.append(view, replace, remove); row.append(image, title, actions); preview.append(row);
            });
        }

        function setGovernmentIdPairMode(input, enabled) {
            if (!input) return;
            const active = input.dataset.idPairEnabled === 'true';
            if (active === enabled) return;
            input.dataset.idPairEnabled = enabled ? 'true' : 'false';
            input.value = '';
            if (input.dataset.previewUrl) { URL.revokeObjectURL(input.dataset.previewUrl); delete input.dataset.previewUrl; }
            const oldLink = input.closest('.upload-slot')?.querySelector('.selected-file-view');
            if (oldLink) { oldLink.classList.remove('is-visible'); oldLink.removeAttribute('href'); oldLink.replaceChildren(); }
            input.multiple = enabled;
            input.name = enabled ? `${input.id}[]` : input.id;
            input.accept = enabled ? '.png,.jpg,.jpeg,image/png,image/jpeg' : 'image/jpeg,application/pdf';
            if (enabled) input.removeAttribute('capture');
            else if (input.id === 'psa_birth_cert_file') input.setAttribute('capture', 'environment');
            const previous = governmentIdUploads.get(input);
            previous?.urls.forEach(url => URL.revokeObjectURL(url));
            if (enabled) {
                const replacePicker = document.createElement('input');
                replacePicker.type = 'file'; replacePicker.accept = '.png,.jpg,.jpeg,image/png,image/jpeg';
                replacePicker.hidden = true; document.body.append(replacePicker);
                const state = { files: [null, null], urls: [], replacePicker, replaceIndex: null };
                replacePicker.addEventListener('change', () => {
                    const file = replacePicker.files?.[0];
                    if (file && isIdImage(file) && state.replaceIndex !== null) {
                        state.files[state.replaceIndex] = file;
                        renderGovernmentIdFiles(input);
                        showGovernmentIdError(input, state.files.every(Boolean) ? '' : idImageMessage);
                    } else if (file) showGovernmentIdError(input, idImageTypeMessage);
                    state.replaceIndex = null; replacePicker.value = '';
                });
                governmentIdUploads.set(input, state);
            } else {
                previous?.replacePicker.remove();
                governmentIdUploads.delete(input);
            }
            const preview = input.closest('.upload-slot')?.querySelector('[data-id-pair-preview]');
            if (preview) { preview.replaceChildren(); preview.hidden = true; }
            showGovernmentIdError(input, '');
        }

        function updateRequiredDocuments(selectedBenefit) {
            const documentRequirements = Object.fromEntries(Object.entries(PUBLIC_BENEFIT_CONFIG).map(([request, definition]) => [
                request,
                (definition.form_documents || []).filter(document => !document.extra).map(document => [document.label, document.description, Boolean(document.optional)])
            ]));
            const fallback = [
                ['Required identity document', 'Select a benefit or service to see the exact identity document required.'],
                ['Required residency document', 'Select a benefit or service to see the exact residency document required.'],
                ['Required supporting document', 'Select a benefit or service to see the exact supporting document required.']
            ];
            const requirements = documentRequirements[selectedBenefit] || fallback;
            ['primary', 'secondary', 'supporting'].forEach((role, index) => {
                const label = document.querySelector(`[data-document-label="${role}"]`);
                const description = document.querySelector(`[data-document-description="${role}"]`);
                const slot = document.querySelector(`[data-document-slot="${role}"]`);
                const requiredMark = slot?.querySelector('.req');
                const input = slot?.querySelector('input[type="file"]');
                const requirement = requirements[index];
                if (slot) slot.hidden = !requirement;
                if (!requirement) {
                    if (input) { input.required = false; input.disabled = true; }
                    return;
                }
                if (label) label.textContent = requirement[0];
                if (description) description.textContent = requirement[1];
                const isOptional = Boolean(requirement[2]);
                if (requiredMark) requiredMark.hidden = isOptional;
                if (input) input.required = !isOptional;
            });
            const supportingInput = document.getElementById('comelec_cert_file');
            if (supportingInput) {
                supportingInput.accept = selectedBenefit === 'Milestone Cash Gift'
                    ? 'image/jpeg,image/png,image/gif'
                    : 'image/jpeg,application/pdf';
            }
            setGovernmentIdPairMode(document.getElementById('psa_birth_cert_file'),
                ['Land Bank Cash Card Enrollment', 'Local Social Pension Assessment'].includes(selectedBenefit));

            let hasExtraDocuments = false;
            document.querySelectorAll('[data-extra-document-benefits]').forEach(slot => {
                const benefits = (slot.dataset.extraDocumentBenefits || '').split('|');
                const shouldShow = benefits.includes(selectedBenefit);
                slot.hidden = !shouldShow;
                hasExtraDocuments = hasExtraDocuments || shouldShow;
                slot.querySelectorAll('input[type="file"]').forEach(input => {
                    input.disabled = !shouldShow;
                    input.required = shouldShow;
                });
            });
            const identityHeading = document.getElementById('identityDocumentsHeading');
            if (identityHeading) identityHeading.hidden = !hasExtraDocuments;
            if (selectedBenefit === 'Senior Citizen ID Registration') updateSeniorIdDocuments();
            else setGovernmentIdPairMode(document.getElementById('valid_id_file'), false);
            updateBurialAffidavitRequirement();
        }

        function updateBurialAffidavitRequirement() {
            const isBurial = document.getElementById('requestedBenefit')?.value === 'Burial Assistance';
            const hasAffidavit = isBurial && Boolean(document.getElementById('controlNo')?.value);
            const slot = document.getElementById('burialAffidavitSlot');
            const input = document.getElementById('auth_letter_file');
            if (slot) slot.hidden = !hasAffidavit;
            if (input) { input.disabled = !hasAffidavit; input.required = hasAffidavit; }
        }

        function updateSeniorIdDocuments() {
            const purpose = document.getElementById('idPurpose')?.value || 'new';
            const configurations = {
                new: [
                    ['Birth Cert / Negative of Birth', 'Upload a birth certificate or, if unavailable, a Negative of Birth certification.'],
                    ['Original Barangay Residency Certificate', 'Upload the current original barangay residency certificate.']
                ],
                change: [
                    ['Original Senior Citizen ID', 'Upload the current Senior Citizen ID for replacement or information change.']
                ],
                lost: [
                    ['Original Affidavit of Loss', 'Upload the notarized original affidavit of loss.'],
                    ['Copy of Senior ID / Landbank Card / Temporary Stub', 'Upload the available front-and-back copy.']
                ],
                transfer: [
                    ['Certificate of Cancellation from Previous OSCA', 'Required when transferring from another city or municipality.'],
                    ['Birth Cert / Negative of Birth', 'Upload a birth certificate or, if unavailable, a Negative of Birth certification.'],
                    ['Original Barangay Residency Certificate', 'Upload the current Pasig barangay residency certificate.']
                ]
            };
            const roles = ['primary', 'secondary', 'supporting'];
            (configurations[purpose] || configurations.new).forEach((requirement, index) => {
                const role = roles[index];
                const label = document.querySelector(`[data-document-label="${role}"]`);
                const description = document.querySelector(`[data-document-description="${role}"]`);
                if (label) label.textContent = requirement[0];
                if (description) description.textContent = requirement[1];
            });
            roles.forEach((role, index) => {
                const slot = document.querySelector(`[data-document-slot="${role}"]`);
                const input = slot?.querySelector('input[type="file"]');
                const enabled = index < (configurations[purpose] || configurations.new).length;
                if (slot) slot.hidden = !enabled;
                if (input) { input.disabled = !enabled; input.required = enabled; }
            });

            // An ID photo is a server-side requirement for every Senior Citizen
            // ID application, so keep it visible and required regardless of the
            // selected application purpose.
            const idPhotoSlot = document.getElementById('idPhotoUploadSlot');
            const idPhotoInput = document.getElementById('id_photo_file');
            if (idPhotoSlot) idPhotoSlot.hidden = false;
            if (idPhotoInput) {
                idPhotoInput.disabled = false;
                idPhotoInput.required = true;
            }
            const identityHeading = document.getElementById('identityDocumentsHeading');
            if (identityHeading) identityHeading.hidden = false;
            const validIdSlot = document.getElementById('validIdUploadSlot');
            const validIdInput = document.getElementById('valid_id_file');
            if (validIdSlot) validIdSlot.hidden = false;
            if (validIdInput) { validIdInput.disabled = false; validIdInput.required = true; setGovernmentIdPairMode(validIdInput, true); }
        }

        document.getElementById('newSeniorForm')?.addEventListener('submit', event => {
            syncCompleteAddress();
            const validationSummary = document.getElementById('newSeniorValidationSummary');
            if (!document.getElementById('requestedBenefit')?.value) {
                event.preventDefault();
                const grid = document.getElementById('benefitChoiceGrid');
                grid?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                grid?.focus({ preventScroll: true });
                window.showCarelinkResult?.('Please select a benefit or service card before submitting.', false);
                return;
            }
            const pairInput = document.querySelector('#newSeniorForm input[data-id-pair-enabled="true"]:not(:disabled)');
            if (pairInput) {
                const pair = governmentIdUploads.get(pairInput);
                const pairError = pairInput.closest('.upload-slot')?.querySelector('[data-id-pair-error]');
                if (!pair?.files.every(Boolean) || (pairError && !pairError.hidden)) {
                    event.preventDefault();
                    if (!pair?.files.every(Boolean)) showGovernmentIdError(pairInput, idImageMessage);
                    pairInput.closest('.upload-slot')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    pairInput.focus({ preventScroll: true });
                    return;
                }
            }
            if (!event.currentTarget.checkValidity()) {
                event.preventDefault();
                const visibleInvalidControls = Array.from(event.currentTarget.querySelectorAll(':invalid'))
                    .filter(control => !control.disabled && !control.closest('[hidden]') && control.offsetParent !== null);
                const firstInvalid = visibleInvalidControls[0];
                const invalidFields = visibleInvalidControls
                    .map(control => {
                        const label = event.currentTarget.querySelector(`label[for="${CSS.escape(control.id)}"]`);
                        return (label?.textContent || control.name || 'Required field').replace('*', '').trim();
                    });
                if (validationSummary) {
                    validationSummary.hidden = false;
                    validationSummary.innerHTML = `<i class="fas fa-exclamation-circle" aria-hidden="true"></i><div><strong>Please complete the following:</strong> ${invalidFields.join(', ')}</div>`;
                }
                firstInvalid?.closest('.form-group, .upload-slot, .checkbox-row')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstInvalid?.focus({ preventScroll: true });
                window.showCarelinkResult?.('Please complete the highlighted required field before submitting.', false);
                return;
            }
            if (validationSummary) validationSummary.hidden = true;
        });

        function updateSelectedFileLink(input) {
            let viewLink = input.parentElement.querySelector('.selected-file-view');
            if (!viewLink) {
                viewLink = document.createElement('a');
                viewLink.className = 'selected-file-view';
                viewLink.target = '_blank';
                viewLink.rel = 'noopener';
                input.insertAdjacentElement('afterend', viewLink);
            }

            if (input.dataset.previewUrl) {
                URL.revokeObjectURL(input.dataset.previewUrl);
                delete input.dataset.previewUrl;
            }

            const file = input.files && input.files[0];
            if (!file) {
                viewLink.classList.remove('is-visible');
                viewLink.removeAttribute('href');
                viewLink.replaceChildren();
                return;
            }

            const objectUrl = URL.createObjectURL(file);
            input.dataset.previewUrl = objectUrl;
            viewLink.href = objectUrl;
            viewLink.replaceChildren();
            const icon = document.createElement('i');
            icon.className = file.type === 'application/pdf' ? 'fas fa-file-pdf' : 'fas fa-image';
            icon.setAttribute('aria-hidden', 'true');
            viewLink.append(icon, document.createTextNode(` View selected file: ${file.name}`));
            viewLink.classList.add('is-visible');
        }

        document.querySelectorAll('.proxy-form input[type="file"]').forEach(input => {
            input.addEventListener('change', () => {
                if (input.dataset.idPairEnabled !== 'true') { updateSelectedFileLink(input); return; }
                const state = governmentIdUploads.get(input);
                if (!state) return;
                const incoming = Array.from(input.files || []);
                if (incoming.length > 2) { renderGovernmentIdFiles(input); showGovernmentIdError(input, idImageLimitMessage); return; }
                if (incoming.some(file => !isIdImage(file))) { renderGovernmentIdFiles(input); showGovernmentIdError(input, idImageTypeMessage); return; }
                if (incoming.length === 2) state.files = incoming;
                else if (incoming.length === 1) {
                    const empty = state.files.findIndex(file => !file);
                    if (empty === -1) { renderGovernmentIdFiles(input); showGovernmentIdError(input, 'Remove an image first, or use Replace for the side you want to change.'); return; }
                    state.files[empty] = incoming[0];
                }
                renderGovernmentIdFiles(input);
                showGovernmentIdError(input, state.files.every(Boolean) ? '' : idImageMessage);
            });
        });

        ['houseNo', 'street', 'barangay', 'zipCode'].forEach(id => {
            document.getElementById(id)?.addEventListener('input', syncCompleteAddress);
            document.getElementById(id)?.addEventListener('change', syncCompleteAddress);
        });

        document.getElementById('requestedBenefit')?.addEventListener('change', updateBenefitSpecificFields);
        document.getElementById('idPurpose')?.addEventListener('change', updateSeniorIdDocuments);
        document.getElementById('controlNo')?.addEventListener('change', updateBurialAffidavitRequirement);
        document.getElementById('newSeniorForm')?.addEventListener('input', updateNewSeniorProgress);
        document.getElementById('newSeniorForm')?.addEventListener('change', updateNewSeniorProgress);
        ['isPensioner', 'isPermanentIncome', 'familySupport'].forEach(id => {
            document.getElementById(id)?.addEventListener('change', updateFinancialRequirements);
        });
        updateBenefitSpecificFields();
        updateNewSeniorProgress();

        if (document.getElementById('birthDate')?.value) calculateProxyAge2026();

        // Mobile keyboards resize the visual viewport without always updating
        // the layout viewport. Keep the active field visible and remove sticky
        // elements while the keyboard occupies the lower part of the screen.
        const mobileViewport = window.visualViewport;
        const updateKeyboardState = () => {
            if (!mobileViewport) return;
            const keyboardOpen = window.innerHeight - mobileViewport.height > 140;
            document.body.classList.toggle('mobile-keyboard-open', keyboardOpen);
        };
        mobileViewport?.addEventListener('resize', updateKeyboardState);
        mobileViewport?.addEventListener('scroll', updateKeyboardState);
        document.getElementById('newSeniorForm')?.querySelectorAll('input, select, textarea').forEach(control => {
            if (control instanceof HTMLInputElement && !control.enterKeyHint) {
                control.enterKeyHint = control.type === 'email' ? 'next' : 'next';
            }
            control.addEventListener('focus', () => window.setTimeout(() => {
                if (window.matchMedia('(max-width: 600px)').matches) {
                    control.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }, 280));
        });

    </script>

<?php else: ?>

    <!-- Rule-based workflow routing success scenarios -->
    <?php if ($proxyOption === 'new_senior'): ?>
        
        <!-- OPTION A SUCCESS: PUBLIC APPLICATION QUEUE TOKEN -->
        <div class="success-qr-card">
            <div class="success-qr-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h3 style="color:#0f172a; font-weight:800; font-size:1.45rem; margin-bottom:10px;">Pre-registration successful.</h3>
            <p style="color:#64748b; font-size:0.95rem; line-height:1.5; margin:0 0 15px 0;">
                The senior citizen's application was sent directly to the <strong>Department Admin verification queue</strong>.
                Save this permanent token. After the ID is approved, the senior must use this token together with the issued Senior Citizen ID number to apply for benefits.
            </p>
            
            <div class="qr-image-wrapper">
                <img id="queueTokenQr" src="<?php echo htmlspecialchars($proxyQrUrl); ?>" crossorigin="anonymous" alt="Application tracking QR code">
                <div>
                    <span class="qr-token-label">PERMANENT ID TOKEN: <?php echo htmlspecialchars($proxyTransactionId); ?></span>
                </div>
            </div>

            <!-- Appointment Notice -->
            <div class="appointment-box">
                <i class="fas fa-calendar-alt"></i>
                <div>
                    <h5>Automated Appointment Notice</h5>
                    <p>Please download and save this queue token, then bring the <strong>physical original documents</strong> to the selected barangay office for counter verification on the scheduled date.</p>
                </div>
            </div>

            <div style="display:flex; flex-direction:column; gap:10px; margin-top:25px;">
                <button type="button" class="btn btn-primary" id="downloadQueueToken" style="padding:12px; font-weight:600;">
                    <i class="fas fa-download"></i> Download Queue Token
                </button>
                <a href="<?php echo htmlspecialchars($resetUrl); ?>" class="btn btn-muted" style="padding:10px;">
                    <i class="fas fa-redo"></i> Done / Register Another
                </a>
                <a href="senior_benefits.php" class="btn btn-muted" style="padding:10px;">
                    <i class="fas fa-hand-holding-heart"></i> Apply for Benefits After ID Approval
                </a>
            </div>
        </div>

        <script>
            document.getElementById('downloadQueueToken')?.addEventListener('click', async function () {
                const button = this;
                const qrImage = document.getElementById('queueTokenQr');
                const token = <?php echo json_encode($proxyTransactionId); ?>;
                const originalHtml = button.innerHTML;

                try {
                    button.disabled = true;
                    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Preparing Download...';

                    const response = await fetch(qrImage.src, { mode: 'cors' });
                    if (!response.ok) throw new Error('Unable to retrieve the QR code.');

                    const qrBitmap = await createImageBitmap(await response.blob());
                    const canvas = document.createElement('canvas');
                    canvas.width = 900;
                    canvas.height = 1120;
                    const ctx = canvas.getContext('2d');

                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(0, 0, canvas.width, canvas.height);
                    ctx.textAlign = 'center';
                    ctx.fillStyle = '#9a6b16';
                    ctx.font = '700 24px Arial, sans-serif';
                    ctx.fillText('SENIORLINK — PUBLIC SENIOR SERVICE', 450, 90);

                    ctx.fillStyle = '#0f172a';
                    ctx.font = '700 42px Arial, sans-serif';
                    ctx.fillText('Application Queue Token', 450, 155);

                    ctx.fillStyle = '#475569';
                    ctx.font = '24px Arial, sans-serif';
                    ctx.fillText('Keep this token for ID verification and benefit access.', 450, 205);

                    ctx.fillStyle = '#f8fafc';
                    ctx.strokeStyle = '#e2e8f0';
                    ctx.lineWidth = 3;
                    ctx.beginPath();
                    ctx.roundRect(130, 255, 640, 650, 28);
                    ctx.fill();
                    ctx.stroke();
                    ctx.drawImage(qrBitmap, 250, 315, 400, 400);

                    ctx.fillStyle = '#64748b';
                    ctx.font = '700 20px Arial, sans-serif';
                    ctx.fillText('PERMANENT ID TOKEN', 450, 780);
                    ctx.fillStyle = '#0f172a';
                    ctx.font = '700 30px monospace';
                    ctx.fillText(token, 450, 830);

                    ctx.fillStyle = '#475569';
                    ctx.font = '22px Arial, sans-serif';
                    ctx.fillText('Bring your physical original documents to the selected', 450, 970);
                    ctx.fillText('barangay office on your scheduled verification date.', 450, 1005);
                    ctx.font = '18px Arial, sans-serif';
                    ctx.fillStyle = '#94a3b8';
                    ctx.fillText('Downloaded ' + new Date().toLocaleString(), 450, 1070);

                    const blob = await new Promise((resolve, reject) =>
                        canvas.toBlob(value => value ? resolve(value) : reject(new Error('Unable to create the download.')), 'image/png')
                    );
                    const link = document.createElement('a');
                    const objectUrl = URL.createObjectURL(blob);
                    link.href = objectUrl;
                    link.download = 'SENIORLINK-Queue-Token-' + token.replace(/[^a-z0-9_-]/gi, '-') + '.png';
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                    setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
                } catch (error) {
                    window.showCarelinkResult?.('The queue token could not be downloaded. Please check your connection and try again.', false);
                } finally {
                    button.disabled = false;
                    button.innerHTML = originalHtml;
                }
            });
        </script>

    <?php else: ?>

        <!-- OPTION B SUCCESS: BENEFIT CLAIM RECEIPT -->
        <div class="success-qr-card">
            <div class="success-qr-icon" style="background:#eff6ff; color:#3b82f6;">
                <i class="fas fa-receipt"></i>
            </div>
            <h3 style="color:#0f172a; font-weight:800; font-size:1.45rem; margin-bottom:10px;">Benefit Application Submitted</h3>
            <p style="color:#64748b; font-size:0.95rem; line-height:1.5; margin:0 0 15px 0;">
                The benefit application has been received and added to the standard review queue.
                Scan or download the Benefit Tracking QR receipt below.
            </p>
            
            <div class="qr-image-wrapper" style="border-color:#bfdbfe; background:#f0f7ff;">
                <img src="<?php echo htmlspecialchars($proxyQrUrl); ?>" alt="Benefit Tracking Receipt">
                <div>
                    <span class="qr-token-label" style="background:#dbeafe; color:#1e40af;">PERMANENT TOKEN ID: <?php echo htmlspecialchars($proxyTransactionId); ?></span>
                </div>
            </div>

            <div class="appointment-box" style="background:#f0fdf4; border-color:#bbf7d0; color:#166534;">
                <i class="fas fa-info-circle" style="color:#16a34a;"></i>
                <div>
                    <h5 style="color:#14532d;">Tracking Access Activated</h5>
                    <p style="color:#15803d;">Use this senior citizen's permanent Token ID to track this service and every future application from the service selector.</p>
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
