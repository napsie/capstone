<?php
session_start();
require_once '../includes/db_connect.php';

// Allow both barangay staff and department admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['barangay_staff', 'department_admin', 'super_admin'])) {
    header('Location: ../index.php');
    exit;
}

$role = $_SESSION['role'];
$sidebarPartial = ($role === 'barangay_staff') ? '../partials/barangay_sidebar.php' : '../partials/department_sidebar.php';
$sidebarCss = ($role === 'barangay_staff') ? '../assets/css/barangay-sidebar.css' : '../assets/css/department-sidebar.css';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SENIORLINK — Legal Reference & Compliance</title>
    <meta name="description" content="Key Philippine Senior Citizen Laws: RA 9994, RA 11916, RA 11982 — Legal reference guide for SENIORLINK staff.">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= $sidebarCss ?>?v=1.1">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: #f0f4f8;
            color: #1e293b;
            line-height: 1.6;
        }

        .main-content {
            padding: 30px;
            width: 100%;
            max-width: none;
            min-width: 0;
        }

        /* Page Header */
        .page-hero {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #0d3251 100%);
            border-radius: 16px;
            padding: 36px 40px;
            margin-bottom: 32px;
            display: flex;
            align-items: center;
            gap: 24px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(15,23,42,0.25);
        }
        .page-hero::before {
            content: '';
            position: absolute;
            top: -40px; right: -40px;
            width: 200px; height: 200px;
            background: rgba(255,255,255,0.04);
            border-radius: 50%;
        }
        .page-hero::after {
            content: '';
            position: absolute;
            bottom: -60px; right: 80px;
            width: 160px; height: 160px;
            background: rgba(240,192,96,0.08);
            border-radius: 50%;
        }
        .hero-icon {
            width: 72px; height: 72px;
            background: rgba(240,192,96,0.18);
            border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .hero-icon i { font-size: 1.9rem; color: #f0c060; }
        .hero-text h1 { font-size: 1.85rem; font-weight: 800; color: #fff; margin-bottom: 6px; }
        .hero-text p { font-size: .9rem; color: rgba(255,255,255,0.65); max-width: 600px; }
        .hero-badge {
            margin-left: auto;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.2);
            color: #fff;
            border-radius: 20px;
            padding: 6px 18px;
            font-size: .78rem;
            font-weight: 600;
            white-space: nowrap;
            flex-shrink: 0;
        }

        /* RA Cards Layout */
        .ra-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 24px; margin-bottom: 32px; }

        .ra-card {
            background: #fff;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 2px 16px rgba(0,0,0,0.07);
            transition: transform 0.25s, box-shadow 0.25s;
        }
        .ra-card:hover { transform: translateY(-4px); box-shadow: 0 8px 32px rgba(0,0,0,0.12); }

        .ra-card-header {
            padding: 20px 24px;
            display: flex;
            align-items: flex-start;
            gap: 16px;
        }
        .ra-num-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: 0.5px;
            white-space: nowrap;
            flex-shrink: 0;
            margin-top: 2px;
        }
        .ra-card-title { font-size: 1.05rem; font-weight: 800; margin-bottom: 3px; }
        .ra-card-subtitle { font-size: .78rem; color: #64748b; }

        .ra-card-body { padding: 0 24px 20px; }
        .ra-summary {
            font-size: .85rem;
            color: #475569;
            line-height: 1.75;
            margin-bottom: 16px;
            padding-top: 4px;
        }

        /* Accordion */
        .accordion { border-radius: 10px; overflow: hidden; border: 1px solid #e2e8f0; }
        .accordion-item { border-bottom: 1px solid #e2e8f0; }
        .accordion-item:last-child { border-bottom: none; }
        .accordion-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            cursor: pointer;
            background: #f8fafc;
            transition: background 0.2s;
            user-select: none;
        }
        .accordion-header:hover { background: #f1f5f9; }
        .accordion-header h4 { font-size: .82rem; font-weight: 700; color: #1e293b; flex: 1; }
        .accordion-header .chevron { color: #94a3b8; font-size: .7rem; transition: transform 0.3s; }
        .accordion-header.open .chevron { transform: rotate(180deg); }
        .accordion-body {
            display: none;
            padding: 14px 16px;
            font-size: .81rem;
            color: #475569;
            line-height: 1.75;
            background: #fff;
        }
        .accordion-body.open { display: block; }
        .accordion-body ul { padding-left: 16px; margin-top: 6px; }
        .accordion-body li { margin-bottom: 5px; }

        /* Highlight blocks */
        .highlight-box {
            border-radius: 8px;
            padding: 10px 14px;
            font-size: .78rem;
            font-weight: 500;
            display: flex;
            align-items: flex-start;
            gap: 9px;
            margin-top: 12px;
        }
        .highlight-box.info  { background: #eff6ff; color: #1d4ed8; border-left: 3px solid #3b82f6; }
        .highlight-box.warn  { background: #fffbeb; color: #92400e; border-left: 3px solid #f59e0b; }
        .highlight-box.ok    { background: #f0fdf4; color: #166534; border-left: 3px solid #22c55e; }
        .highlight-box.alert { background: #fef2f2; color: #991b1b; border-left: 3px solid #ef4444; }
        .highlight-box i { flex-shrink: 0; margin-top: 1px; }

        /* Eligibility Table */
        .elig-table-wrap { overflow-x: auto; margin-top: 12px; border-radius: 8px; border: 1px solid #e2e8f0; }
        .elig-table { width: 100%; border-collapse: collapse; font-size: .78rem; }
        .elig-table th { background: #0f172a; color: #fff; padding: 10px 14px; text-align: left; font-weight: 600; }
        .elig-table td { padding: 9px 14px; border-bottom: 1px solid #f1f5f9; color: #334155; }
        .elig-table tr:last-child td { border-bottom: none; }
        .elig-table tr:hover td { background: #f8fafc; }
        .badge-ok  { display: inline-block; background: #dcfce7; color: #166534; border-radius: 12px; padding: 2px 9px; font-weight: 700; font-size: .71rem; }
        .badge-no  { display: inline-block; background: #fef2f2; color: #991b1b; border-radius: 12px; padding: 2px 9px; font-weight: 700; font-size: .71rem; }

        /* Full-width comparison section */
        .comparison-section {
            background: #fff;
            border-radius: 14px;
            padding: 28px 32px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.07);
            margin-bottom: 32px;
        }
        .comparison-section h2 { font-size: 1.1rem; font-weight: 800; margin-bottom: 4px; color: #0f172a; display: flex; align-items: center; gap: 10px; }
        .comparison-section .section-sub { font-size: .82rem; color: #64748b; margin-bottom: 20px; }

        /* Cash Gift Timeline */
        .timeline { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 4px; }
        .timeline-step {
            flex: 1; min-width: 110px;
            background: linear-gradient(135deg, #fef9c3, #fde68a);
            border-radius: 12px;
            padding: 16px 14px;
            text-align: center;
            border: 1px solid #fcd34d;
            position: relative;
        }
        .timeline-step .age { font-size: 1.4rem; font-weight: 900; color: #92400e; }
        .timeline-step .years { font-size: .72rem; color: #92400e; font-weight: 600; margin-bottom: 6px; }
        .timeline-step .gift { font-size: .88rem; font-weight: 700; color: #78350f; }
        .timeline-step.centennial { background: linear-gradient(135deg, #1e3a5f, #0f172a); border-color: #1e3a5f; }
        .timeline-step.centennial .age,
        .timeline-step.centennial .years { color: #f0c060; }
        .timeline-step.centennial .gift { color: #fff; font-size: 1rem; }

        /* IRR Download section */
        .downloads-section {
            background: #fff;
            border-radius: 14px;
            padding: 28px 32px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.07);
            margin-bottom: 32px;
        }
        .downloads-section h2 { font-size: 1.1rem; font-weight: 800; margin-bottom: 4px; color: #0f172a; display: flex; align-items: center; gap: 10px; }
        .downloads-section .section-sub { font-size: .82rem; color: #64748b; margin-bottom: 20px; }
        .download-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
        .download-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px 18px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            text-decoration: none;
            color: inherit;
            transition: border-color 0.2s, background 0.2s, transform 0.2s;
        }
        .download-item:hover { border-color: #3b82f6; background: #eff6ff; transform: translateY(-2px); }
        .download-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
        .download-label { font-size: .8rem; font-weight: 700; color: #1e293b; margin-bottom: 2px; }
        .download-sub   { font-size: .72rem; color: #64748b; }

        @media (max-width: 768px) {
            .main-content { padding: 16px; }
            .page-hero { flex-direction: column; align-items: flex-start; padding: 24px; }
            .hero-badge { margin-left: 0; }
            .ra-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="container">
    <?php include $sidebarPartial; ?>

    <div class="main-content">

        <!-- Hero Banner -->
        <div class="page-hero">
            <div class="hero-icon"><i class="fas fa-balance-scale"></i></div>
            <div class="hero-text">
                <h1>Legal Reference & Compliance</h1>
                <p>Comprehensive overview of the key Philippine Senior Citizen Laws governing SENIORLINK's operations. Use this guide to verify applicant eligibility, benefit rates, and documentary requirements.</p>
            </div>
            <div class="hero-badge"><i class="fas fa-gavel"></i>&nbsp; Philippine Republic Acts</div>
        </div>

        <!-- RA Cards -->
        <div class="ra-grid">

            <!-- RA 9994 Card -->
            <div class="ra-card">
                <div class="ra-card-header" style="background: linear-gradient(135deg, #eff6ff, #dbeafe);">
                    <div>
                        <div class="ra-num-badge" style="background:#2563eb;color:#fff;">RA 9994</div>
                    </div>
                    <div>
                        <div class="ra-card-title" style="color:#1e3a8a;">Expanded Senior Citizens Act of 2010</div>
                        <div class="ra-card-subtitle">Baseline law — Defines 60+ as Senior Citizen</div>
                    </div>
                </div>
                <div class="ra-card-body">
                    <p class="ra-summary">
                        Amends RA 7432. Defines a <strong>Senior Citizen as any Filipino citizen aged 60 years or older</strong> and enumerates their core statutory benefits and privileges.
                    </p>

                    <div class="accordion">
                        <div class="accordion-item">
                            <div class="accordion-header" onclick="toggleAccordion(this)">
                                <i class="fas fa-percent" style="color:#2563eb;font-size:.85rem;width:18px;text-align:center;"></i>
                                <h4>Discount & Tax Privileges</h4>
                                <i class="fas fa-chevron-down chevron"></i>
                            </div>
                            <div class="accordion-body">
                                <ul>
                                    <li><strong>20% discount</strong> on medicine, food, accommodation, transport, entertainment, & funeral services</li>
                                    <li><strong>VAT exemption</strong> on the 20% discounted purchases (covered establishments only)</li>
                                    <li>Income tax exemption on retirement benefits under RA 7641</li>
                                    <li>Real estate tax privilege for properties used for senior housing</li>
                                </ul>
                                <div class="highlight-box info"><i class="fas fa-info-circle"></i><span>A valid Senior Citizen ID or OSCA-issued booklet is required when claiming the discount.</span></div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <div class="accordion-header" onclick="toggleAccordion(this)">
                                <i class="fas fa-hospital" style="color:#2563eb;font-size:.85rem;width:18px;text-align:center;"></i>
                                <h4>Medical & Health Benefits</h4>
                                <i class="fas fa-chevron-down chevron"></i>
                            </div>
                            <div class="accordion-body">
                                <ul>
                                    <li>Free medical and dental services in government hospitals</li>
                                    <li>Free diagnostic services (laboratory, X-ray) in gov't facilities</li>
                                    <li>Annual free flu and pneumococcal vaccine</li>
                                    <li>Priority access to National Health Insurance (PhilHealth)</li>
                                </ul>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <div class="accordion-header" onclick="toggleAccordion(this)">
                                <i class="fas fa-check-circle" style="color:#2563eb;font-size:.85rem;width:18px;text-align:center;"></i>
                                <h4>Eligibility Requirements for ID</h4>
                                <i class="fas fa-chevron-down chevron"></i>
                            </div>
                            <div class="accordion-body">
                                <ul>
                                    <li>Must be a <strong>Filipino citizen</strong></li>
                                    <li>Must be <strong>60 years of age or older</strong></li>
                                    <li>Must be a <strong>resident of the barangay</strong> where the application is filed</li>
                                    <li>PSA Birth Certificate or similar proof of age required</li>
                                    <li>Barangay clearance / proof of residency</li>
                                </ul>
                                <div class="highlight-box ok"><i class="fas fa-check"></i><span>SENIORLINK age check: Born on or before <strong>July 8, 1966</strong> qualifies for 2026 applications.</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RA 11916 Card -->
            <div class="ra-card">
                <div class="ra-card-header" style="background: linear-gradient(135deg, #f0fdf4, #dcfce7);">
                    <div>
                        <div class="ra-num-badge" style="background:#16a34a;color:#fff;">RA 11916</div>
                    </div>
                    <div>
                        <div class="ra-card-title" style="color:#14532d;">Social Pension for Indigent Senior Citizens Act</div>
                        <div class="ra-card-subtitle">₱1,000/month — 100% pension increase</div>
                    </div>
                </div>
                <div class="ra-card-body">
                    <p class="ra-summary">
                        Mandates a <strong>100% increase</strong> in the monthly social pension for indigent senior citizens, raising it from <del>₱500</del> to <strong>₱1,000 per month</strong>. Administered by DSWD through OSCA channels.
                    </p>

                    <div class="accordion">
                        <div class="accordion-item">
                            <div class="accordion-header" onclick="toggleAccordion(this)">
                                <i class="fas fa-user-check" style="color:#16a34a;font-size:.85rem;width:18px;text-align:center;"></i>
                                <h4>Indigent Senior Eligibility Criteria</h4>
                                <i class="fas fa-chevron-down chevron"></i>
                            </div>
                            <div class="accordion-body">
                                <ul>
                                    <li>Must be <strong>60 years old or older</strong></li>
                                    <li>Must be <strong>indigent</strong> — frail, sick, disabled, or without regular income</li>
                                    <li><strong>No other government pension</strong> (SSS, GSIS, AFP, etc.)</li>
                                    <li>Must not have a pension from private institutions equivalent to or higher than ₱1,000/month</li>
                                    <li>Must pass a <strong>Social Worker means test</strong></li>
                                </ul>
                                <div class="highlight-box warn"><i class="fas fa-exclamation-triangle"></i><span>Applicants with existing SSS or GSIS pensions are automatically disqualified for this benefit.</span></div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <div class="accordion-header" onclick="toggleAccordion(this)">
                                <i class="fas fa-file-alt" style="color:#16a34a;font-size:.85rem;width:18px;text-align:center;"></i>
                                <h4>Required Documents (Pension Application)</h4>
                                <i class="fas fa-chevron-down chevron"></i>
                            </div>
                            <div class="accordion-body">
                                <ul>
                                    <li>PSA Birth Certificate</li>
                                    <li>Valid government ID or Senior Citizen ID</li>
                                    <li>Barangay Indigency Certificate</li>
                                    <li><strong>Home Visitation Form</strong> (signed by DSWD/OSCA social worker)</li>
                                    <li><strong>Land Bank Enrollment Form</strong> (for cash card release)</li>
                                    <li>Photo with signature (2x2 or 1x1)</li>
                                </ul>
                                <div class="highlight-box info"><i class="fas fa-info-circle"></i><span>SENIORLINK pension applications collect the Home Visitation Form and Land Bank Card Form via the Option B benefit upload.</span></div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <div class="accordion-header" onclick="toggleAccordion(this)">
                                <i class="fas fa-route" style="color:#16a34a;font-size:.85rem;width:18px;text-align:center;"></i>
                                <h4>Process Flow</h4>
                                <i class="fas fa-chevron-down chevron"></i>
                            </div>
                            <div class="accordion-body">
                                <ul>
                                    <li><strong>Step 1:</strong> Barangay staff files application via SENIORLINK</li>
                                    <li><strong>Step 2:</strong> OSCA / DSWD social worker conducts home visitation</li>
                                    <li><strong>Step 3:</strong> Department Admin reviews uploaded documents</li>
                                    <li><strong>Step 4:</strong> Deduplication check — verified not registered elsewhere</li>
                                    <li><strong>Step 5:</strong> Eligibility confirmed → enrolled in disbursement pipeline</li>
                                    <li><strong>Step 6:</strong> Land Bank cash card released at designated counter</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RA 11982 Card -->
            <div class="ra-card">
                <div class="ra-card-header" style="background: linear-gradient(135deg, #fffbeb, #fef3c7);">
                    <div>
                        <div class="ra-num-badge" style="background:#ca8a04;color:#fff;">RA 11982</div>
                    </div>
                    <div>
                        <div class="ra-card-title" style="color:#78350f;">Expanded Centenarian Act</div>
                        <div class="ra-card-subtitle">Cash gifts from age 80 up to ₱100,000 at 100</div>
                    </div>
                </div>
                <div class="ra-card-body">
                    <p class="ra-summary">
                        Expands the Centenarian law to grant <strong>milestone cash gifts</strong> starting at age 80. The gift is awarded by the Office of the President upon OSCA endorsement.
                    </p>

                    <div class="accordion">
                        <div class="accordion-item">
                            <div class="accordion-header" onclick="toggleAccordion(this)">
                                <i class="fas fa-birthday-cake" style="color:#ca8a04;font-size:.85rem;width:18px;text-align:center;"></i>
                                <h4>Gift Amount Schedule</h4>
                                <i class="fas fa-chevron-down chevron"></i>
                            </div>
                            <div class="accordion-body">
                                <div class="elig-table-wrap">
                                    <table class="elig-table">
                                        <thead><tr><th>Milestone Age</th><th>Cash Gift</th><th>Award Source</th></tr></thead>
                                        <tbody>
                                            <tr><td>80 years old</td><td><strong>₱10,000</strong></td><td>Office of the President</td></tr>
                                            <tr><td>85 years old</td><td><strong>₱10,000</strong></td><td>Office of the President</td></tr>
                                            <tr><td>90 years old</td><td><strong>₱10,000</strong></td><td>Office of the President</td></tr>
                                            <tr><td>95 years old</td><td><strong>₱10,000</strong></td><td>Office of the President</td></tr>
                                            <tr><td>100+ (Centenarian)</td><td><strong>₱100,000</strong></td><td>Office of the President</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="highlight-box warn"><i class="fas fa-exclamation-triangle"></i><span>Cash gifts are one-time grants per milestone. The OSCA must certify and endorse the recipient's age before the award is released.</span></div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <div class="accordion-header" onclick="toggleAccordion(this)">
                                <i class="fas fa-file-alt" style="color:#ca8a04;font-size:.85rem;width:18px;text-align:center;"></i>
                                <h4>Documentary Requirements</h4>
                                <i class="fas fa-chevron-down chevron"></i>
                            </div>
                            <div class="accordion-body">
                                <ul>
                                    <li>PSA Birth Certificate (authenticated)</li>
                                    <li>OSCA endorsement letter</li>
                                    <li>Barangay Certification of residence</li>
                                    <li>Recent 2x2 photo</li>
                                    <li>Valid government-issued ID</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /ra-grid -->

        <!-- Centenarian Timeline -->
        <div class="comparison-section">
            <h2><i class="fas fa-birthday-cake" style="color:#ca8a04;"></i> RA 11982 — Centenarian Cash Gift Timeline</h2>
            <p class="section-sub">Visual milestone tracker for birthday cash gifts under the Expanded Centenarian Act.</p>
            <div class="timeline">
                <div class="timeline-step"><div class="age">80</div><div class="years">yrs old</div><div class="gift">₱10,000</div></div>
                <div class="timeline-step"><div class="age">85</div><div class="years">yrs old</div><div class="gift">₱10,000</div></div>
                <div class="timeline-step"><div class="age">90</div><div class="years">yrs old</div><div class="gift">₱10,000</div></div>
                <div class="timeline-step"><div class="age">95</div><div class="years">yrs old</div><div class="gift">₱10,000</div></div>
                <div class="timeline-step centennial"><div class="age">100+</div><div class="years">Centenarian</div><div class="gift">₱100,000 🎉</div></div>
            </div>
        </div>

        <!-- Eligibility Comparison Table -->
        <div class="comparison-section">
            <h2><i class="fas fa-table" style="color:#3b82f6;"></i> Eligibility Comparison — Regular vs. Indigent Senior</h2>
            <p class="section-sub">Quick guide for staff to determine which benefit category an applicant falls under.</p>
            <div class="elig-table-wrap">
                <table class="elig-table">
                    <thead>
                        <tr>
                            <th>Criteria</th>
                            <th>Regular Senior (RA 9994)</th>
                            <th>Indigent Senior (RA 11916)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>Minimum Age</td><td><span class="badge-ok">60 years old</span></td><td><span class="badge-ok">60 years old</span></td></tr>
                        <tr><td>Citizenship</td><td><span class="badge-ok">Filipino</span></td><td><span class="badge-ok">Filipino</span></td></tr>
                        <tr><td>Income Requirement</td><td>None (any income level)</td><td>Must be <strong>indigent</strong> / no regular income</td></tr>
                        <tr><td>Other Gov't Pension</td><td>Allowed</td><td><span class="badge-no">Disqualifying</span></td></tr>
                        <tr><td>Social Worker Visit</td><td>Not required</td><td><span class="badge-ok">Required</span> (Home Visitation Form)</td></tr>
                        <tr><td>Benefit</td><td>20% discount, VAT exemption, medical</td><td>+₱1,000/month cash pension</td></tr>
                        <tr><td>Processing Portal</td><td>Standard Application (new_application.php)</td><td>Benefit Portal — Option B</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- IRR Downloads Section -->
        <div class="downloads-section">
            <h2><i class="fas fa-download" style="color:#8b5cf6;"></i> Official IRR Document References</h2>
            <p class="section-sub">Downloadable references to the official Implementing Rules and Regulations (IRR) for each Republic Act.</p>
            <div class="download-grid">
                <a href="https://www.officialgazette.gov.ph/2010/08/26/republic-act-no-9994/" target="_blank" class="download-item">
                    <div class="download-icon" style="background:#dbeafe;"><i class="fas fa-file-pdf" style="color:#2563eb;"></i></div>
                    <div>
                        <div class="download-label">RA 9994 — Official Gazette</div>
                        <div class="download-sub">Full text of the Expanded Senior Citizens Act of 2010</div>
                    </div>
                    <i class="fas fa-external-link-alt" style="color:#94a3b8;font-size:.75rem;margin-left:auto;flex-shrink:0;"></i>
                </a>
                <a href="https://www.officialgazette.gov.ph/2022/07/19/republic-act-no-11916/" target="_blank" class="download-item">
                    <div class="download-icon" style="background:#dcfce7;"><i class="fas fa-file-pdf" style="color:#16a34a;"></i></div>
                    <div>
                        <div class="download-label">RA 11916 — Official Gazette</div>
                        <div class="download-sub">Social Pension for Indigent Senior Citizens Act</div>
                    </div>
                    <i class="fas fa-external-link-alt" style="color:#94a3b8;font-size:.75rem;margin-left:auto;flex-shrink:0;"></i>
                </a>
                <a href="https://www.officialgazette.gov.ph/2023/04/05/republic-act-no-11982/" target="_blank" class="download-item">
                    <div class="download-icon" style="background:#fef9c3;"><i class="fas fa-file-pdf" style="color:#ca8a04;"></i></div>
                    <div>
                        <div class="download-label">RA 11982 — Official Gazette</div>
                        <div class="download-sub">Expanded Centenarian Act — Full Text</div>
                    </div>
                    <i class="fas fa-external-link-alt" style="color:#94a3b8;font-size:.75rem;margin-left:auto;flex-shrink:0;"></i>
                </a>
                <a href="https://osca.gov.ph/" target="_blank" class="download-item">
                    <div class="download-icon" style="background:#f3e8ff;"><i class="fas fa-landmark" style="color:#7c3aed;"></i></div>
                    <div>
                        <div class="download-label">OSCA Official Portal</div>
                        <div class="download-sub">Office for Senior Citizens Affairs — Philippines</div>
                    </div>
                    <i class="fas fa-external-link-alt" style="color:#94a3b8;font-size:.75rem;margin-left:auto;flex-shrink:0;"></i>
                </a>
                <a href="https://www.dswd.gov.ph/programs-projects/social-pension-program/" target="_blank" class="download-item">
                    <div class="download-icon" style="background:#dcfce7;"><i class="fas fa-hand-holding-heart" style="color:#16a34a;"></i></div>
                    <div>
                        <div class="download-label">DSWD Social Pension Program</div>
                        <div class="download-sub">Official DSWD guidance on indigent senior pension</div>
                    </div>
                    <i class="fas fa-external-link-alt" style="color:#94a3b8;font-size:.75rem;margin-left:auto;flex-shrink:0;"></i>
                </a>
                <a href="https://www.philhealth.gov.ph/members/senior/" target="_blank" class="download-item">
                    <div class="download-icon" style="background:#fce7f3;"><i class="fas fa-shield-alt" style="color:#db2777;"></i></div>
                    <div>
                        <div class="download-label">PhilHealth Senior Coverage</div>
                        <div class="download-sub">Health insurance guide for senior citizen members</div>
                    </div>
                    <i class="fas fa-external-link-alt" style="color:#94a3b8;font-size:.75rem;margin-left:auto;flex-shrink:0;"></i>
                </a>
            </div>
        </div>

        <!-- Footer note -->
        <p style="font-size:.75rem;color:#94a3b8;text-align:center;margin-bottom:30px;">
            <i class="fas fa-info-circle"></i> This reference guide is compiled for SENIORLINK staff use. Always verify against the latest Official Gazette for legal accuracy.
            Last updated: July 2026.
        </p>

    </div><!-- /main-content -->
</div><!-- /container -->

<script src="../assets/js/sidebar-toggle.js"></script>
<script>
function toggleAccordion(header) {
    const body = header.nextElementSibling;
    const chevron = header.querySelector('.chevron');
    const isOpen = header.classList.contains('open');

    // Close all in the same accordion
    const parentAccordion = header.closest('.accordion');
    parentAccordion.querySelectorAll('.accordion-header').forEach(h => {
        h.classList.remove('open');
        h.querySelector('.chevron').style.transform = '';
    });
    parentAccordion.querySelectorAll('.accordion-body').forEach(b => b.classList.remove('open'));

    // Toggle this one
    if (!isOpen) {
        header.classList.add('open');
        chevron.style.transform = 'rotate(180deg)';
        body.classList.add('open');
    }
}
</script>
</body>
</html>
