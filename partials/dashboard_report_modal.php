<?php
require_once __DIR__.'/../includes/application_types.php';
require_once __DIR__.'/../includes/barangays_list.php';
$dashboardReportDepartment=($_SESSION['role']??'')!=='barangay_staff';
$dashboardReportYear=(int)date('Y');
$reportEscape=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');
?>
<link rel="stylesheet" href="../assets/css/dashboard-report.css?v=2">
<dialog class="dashboard-report-dialog" id="dashboardReportDialog" aria-labelledby="dashboardReportTitle">
<header class="dashboard-report-heading"><h2 id="dashboardReportTitle"><i class="fas fa-file-export" aria-hidden="true"></i> Generate Report</h2><button type="button" class="dashboard-report-close" data-dashboard-report-close aria-label="Close report dialog" autofocus>&times;</button></header>
<form id="exportReportForm" action="../api/export_dashboard_report.php" method="get">
<div class="dashboard-report-body"><p>Export the summary figures and chart data shown on this dashboard. The report uses the latest dashboard data loaded for your account.</p>
<div class="dashboard-report-grid">
<label>File Format<select name="format" required><option value="">Choose a format</option><option value="pdf">PDF</option><option value="excel">Excel (.xlsx)</option></select></label>
<label>Report Coverage<input class="dashboard-report-scope" value="<?= $dashboardReportDepartment?'Main OSCA — Pasig City':$reportEscape($_SESSION['barangay']??'') ?>" readonly></label>
</div>
<section class="dashboard-report-inclusions"><h3>Included in this report</h3><ul>
<li>Dashboard summary figures and pending counts</li>
<?php if($dashboardReportDepartment): ?><li>Records by barangay and yearly records</li><?php else: ?><li>Workflow status distribution and monthly Senior Citizen applications</li><?php endif; ?>
<li>Female and male senior trend for the last 12 displayed months</li>
</ul><p>Summary figures cover all dates, matching the dashboard. Individual application records are not included.</p></section>
<p class="dashboard-report-message" id="dashboardReportMessage" role="status" hidden></p>
</div><footer class="dashboard-report-footer"><button class="dashboard-report-cancel" type="button" data-dashboard-report-close>Cancel</button><button class="dashboard-report-button" type="submit"><i class="fas fa-file-export" aria-hidden="true"></i> Generate Report</button></footer>
</form></dialog>

<script src="../assets/js/dashboard-report.js?v=2" defer></script>
