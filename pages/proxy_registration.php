<?php
require_once '../includes/process_proxy_registration.php';
require_once '../includes/barangays_list.php';
require_once '../includes/application_types.php';

$proxyResult = processProxyRegistration();
$proxySuccess = $proxyResult['success'];
$proxyQrUrl = $proxyResult['qrCodeUrl'];
$proxyTransactionId = $proxyResult['transactionId'];
$proxyOption = $proxyResult['option'] ?? '';
$proxyMessage = $proxyResult['message'] ?? '';
$formAction = 'proxy_registration.php';
$resetUrl = 'proxy_registration.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SENIORLINK — Representative Pre-Registration</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/carelink-theme.css?v=5">
    <style>
        /* ── Card Grid ───────────────────────────────── */
        .app-type-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 16px;
            margin-top: 4px;
            margin-bottom: 24px;
        }

        /* ── Individual Card ─────────────────────────── */
        .app-type-card {
            position: relative;
            display: flex;
            flex-direction: column;
            gap: 14px;
            padding: 22px 20px;
            border-radius: 16px;
            border: 1.5px solid rgba(0,0,0,0.1);
            background: linear-gradient(145deg, #ffffff, #f8fafc);
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4,0,0.2,1);
            user-select: none;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            text-align: left;
        }
        .app-type-card:hover {
            transform: translateY(-5px) scale(1.02);
            border-color: rgba(96,165,250,0.5);
            box-shadow: 0 12px 40px rgba(59,130,246,0.15), 0 0 0 1px rgba(96,165,250,0.1);
        }
        .app-type-card.selected {
            border-color: rgba(59,130,246,0.8);
            background: linear-gradient(145deg, #eff6ff, #ffffff);
            box-shadow: 0 0 0 3px rgba(59,130,246,0.25), 0 16px 48px rgba(59,130,246,0.2);
        }
        
        /* Shimmer sweep on selected */
        .app-type-card.selected::after {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 60%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.6), transparent);
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
            color: #0f172a;
            margin-bottom: -8px;
        }
        .app-type-card .card-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.35;
        }
        .app-type-card .card-desc {
            font-size: 0.85rem;
            color: #475569;
            line-height: 1.5;
            margin-top: -4px;
        }
        .app-type-card.selected .card-desc { color: #1e40af; }

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
            color: #94a3b8;
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
            background: #eff6ff;
            margin-bottom: 28px;
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
            color: #0f172a;
        }
        .btn-change-type {
            background: #fff;
            border: 1px solid #cbd5e1;
            color: #475569;
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
            color: #2563eb;
            background: #f8fafc;
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
            color: #0f172a;
        }
        .step-heading p {
            margin: 0; font-size: 0.9rem; color: #475569;
        }
    </style>
    <link rel="stylesheet" href="../assets/css/seniorlink-ui.css?v=11">
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
                    <h2 id="proxy-heading">Representative Registration Portal</h2>
                    <p>Pre-register online for bedridden seniors to generate a priority queue token.</p>
                </div>
                <div class="proxy-panel-body">
                    <?php include '../partials/proxy_form.php'; ?>
                </div>
            </div>
        </section>
    </div>

    <script>
        /* ── Type meta (populated from card data-attributes) ──────────────── */
        const TYPE_META = {};
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('#appTypeGrid .app-type-card').forEach(card => {
                TYPE_META[card.dataset.value] = {
                    label: card.dataset.label,
                    icon:  card.dataset.icon,
                    color: card.dataset.color,
                    bg:    card.dataset.bg,
                };
            });
        });

        /* ── Toggle type-specific form sections ────────────────────────── */
        function toggleProxyFormFields() {
            const type = document.getElementById('applicationType')?.value;
            const pensionFields = document.getElementById('pensionFields');
            const burialFields  = document.getElementById('burialFields');
            if (!pensionFields || !burialFields) return;

            pensionFields.hidden = true;
            burialFields.hidden  = true;

            // Reset required flags
            document.getElementById('sssNumber')?.removeAttribute('required');
            document.getElementById('dateOfDeath')?.removeAttribute('required');
            document.getElementById('relationshipToDeceased')?.removeAttribute('required');

            if (type === 'pension') {
                pensionFields.hidden = false;
                document.getElementById('sssNumber')?.setAttribute('required', 'required');
            } else if (type === 'burial') {
                burialFields.hidden = false;
                document.getElementById('dateOfDeath')?.setAttribute('required', 'required');
                document.getElementById('relationshipToDeceased')?.setAttribute('required', 'required');
            }

            // Re-run compliance checks after toggling
            checkProxyAgeCompliance();
        }

        /* ── Select application type from card click ───────────────────── */
        function selectAppType(value) {
            const input = document.getElementById('applicationType');
            if (input) input.value = value;

            // Card highlight
            document.querySelectorAll('#appTypeGrid .app-type-card').forEach(card => {
                const isSelected = card.dataset.value === value;
                card.classList.toggle('selected', isSelected);
                card.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
            });

            // Banner
            const meta   = TYPE_META[value] || {};
            const banner = document.getElementById('selectedTypeBanner');
            if (banner) {
                document.getElementById('bannerLabel').textContent = meta.label || value;
                const bannerIcon = document.getElementById('bannerIcon');
                bannerIcon.innerHTML = `<i class="${meta.icon || 'fas fa-file'}" style="font-size:1.1rem;"></i>`;
                bannerIcon.style.background = meta.bg   || '';
                bannerIcon.style.color      = meta.color || '';
                banner.style.display = 'flex';
            }

            // Reveal form
            const formBody = document.getElementById('formBody');
            if (formBody) {
                formBody.classList.add('visible');
                setTimeout(() => formBody.scrollIntoView({ behavior: 'smooth', block: 'start' }), 80);
            }

            toggleProxyFormFields();
        }

        /* ── Reset card selection ──────────────────────────────────────── */
        function resetAppType() {
            const input = document.getElementById('applicationType');
            if (input) input.value = '';

            document.querySelectorAll('#appTypeGrid .app-type-card').forEach(c => {
                c.classList.remove('selected');
                c.setAttribute('aria-pressed', 'false');
            });

            const formBody = document.getElementById('formBody');
            if (formBody) formBody.classList.remove('visible');

            const banner = document.getElementById('selectedTypeBanner');
            if (banner) banner.style.display = 'none';

            document.getElementById('cardSelectorSection')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        /* ── Age Compliance Check (mirrors new_application.php) ────────── */
        function checkProxyAgeCompliance() {
            const birthDate = document.getElementById('birthDate')?.value;
            const appType   = document.getElementById('applicationType')?.value;
            const resultEl  = document.getElementById('ageComplianceResult');
            if (!resultEl) return;
            if (!birthDate || !appType) { resultEl.innerHTML = ''; return; }

            const today = new Date();
            const dob   = new Date(birthDate);
            let age = today.getFullYear() - dob.getFullYear();
            const m = today.getMonth() - dob.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;

            let html = '';
            if (appType === 'senior' || appType === 'burial') {
                if (age >= 60) {
                    html = `<span style="color:#1b8a4a;"><i class="fas fa-check-circle"></i> Age ${age} — Eligible (60+)</span>`;
                } else {
                    html = `<span style="color:#b91c1c;"><i class="fas fa-times-circle"></i> Age ${age} — Must be at least 60 years old</span>`;
                }
            } else if (appType === 'pension') {
                if (age >= 65) {
                    html = `<span style="color:#1b8a4a;"><i class="fas fa-check-circle"></i> Age ${age} — Eligible (65+)</span>`;
                } else {
                    html = `<span style="color:#b91c1c;"><i class="fas fa-times-circle"></i> Age ${age} — Must be at least 65 years old for Social Pension</span>`;
                }
            }
            resultEl.innerHTML = html;
        }

        /* ── Burial Deadline Check (30 working days) ───────────────────── */
        function checkBurialDeadline() {
            const dateOfDeathVal = document.getElementById('dateOfDeath')?.value;
            const resultEl       = document.getElementById('burialComplianceResult');
            if (!resultEl) return;
            if (!dateOfDeathVal) { resultEl.innerHTML = ''; return; }

            const dod   = new Date(dateOfDeathVal);
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            // Count working days between dod and today
            let workingDays = 0;
            const cursor = new Date(dod);
            cursor.setHours(0, 0, 0, 0);
            while (cursor < today) {
                cursor.setDate(cursor.getDate() + 1);
                const day = cursor.getDay();
                if (day !== 0 && day !== 6) workingDays++;
            }

            if (workingDays <= 30) {
                resultEl.innerHTML = `<span style="color:#1b8a4a;"><i class="fas fa-check-circle"></i> ${workingDays} working day(s) elapsed — Within deadline</span>`;
            } else {
                resultEl.innerHTML = `<span style="color:#b91c1c;"><i class="fas fa-times-circle"></i> ${workingDays} working day(s) — Exceeds 30-day filing window</span>`;
            }
        }

        <?php if ($proxySuccess): ?>
        window.scrollTo({ top: 0, behavior: 'smooth' });
        <?php endif; ?>
    </script>
</body>
</html>
