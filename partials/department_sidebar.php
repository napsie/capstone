<?php
$current_page = basename($_SERVER['PHP_SELF']);
require_once __DIR__ . '/../includes/system_branding.php';
?>
<!-- Loaded here so mobile-only overrides follow each page's inline styles. -->
<link rel="stylesheet" href="../assets/css/system-sidebar.css?v=12">
<link rel="stylesheet" href="../assets/css/department-mobile.css?v=9">
<div class="sidebar system-sidebar">
    <div class="mobile-nav-bar">
        <button class="mobile-nav-toggle" type="button" aria-expanded="false" aria-controls="mobileSystemNavigation"><i class="fas fa-bars" aria-hidden="true"></i><span>Menu</span></button>
        <span class="mobile-nav-current" aria-live="polite">Navigation</span>
    </div>
    <div class="sidebar-header">
        <div class="logo">
            <img src="<?php echo htmlspecialchars(systemLogoUrl($conn)); ?>" alt="SENIORLINK system logo" class="logo-image">
            <h1 class="logo-text"><span style="color: #00B050;">SENIOR</span><span>LINK</span></h1>
        </div>
    </div>
    <button class="sidebar-toggle-control" type="button" aria-expanded="true" aria-label="Collapse sidebar" title="Collapse sidebar">
        <i class="fas fa-angles-left" aria-hidden="true"></i>
        <span class="toggle-label">Collapse sidebar</span>
    </button>
    <div class="sidebar-menu" id="mobileSystemNavigation">
        <div class="mobile-nav-heading"><div><strong>Main Navigation</strong><span>Department Admin</span></div><button class="mobile-nav-close" type="button" aria-label="Close navigation"><i class="fas fa-xmark" aria-hidden="true"></i></button></div>
        <ul class="sidebar-root-menu">
            <li class="<?php echo ($current_page == 'department_dashboard.php') ? 'active' : ''; ?>"><a href="department_dashboard.php" data-tooltip="Dashboard" aria-label="Dashboard"><i class="fas fa-tachometer-alt"></i> <span class="link-text">Dashboard</span></a></li>

            <?php $seniorRecordsOpen = in_array($current_page, ['department_records.php', 'digital_ids.php', 'digital_id.php', 'deceased_records.php', 'department_archive.php'], true); ?>
            <li class="sidebar-nav-group<?php echo $seniorRecordsOpen ? ' is-open has-active-child' : ''; ?>">
                <button class="sidebar-group-toggle" type="button" aria-expanded="<?php echo $seniorRecordsOpen ? 'true' : 'false'; ?>" aria-controls="departmentSeniorRecordsMenu" data-tooltip="Senior Records">
                    <i class="fas fa-folder-open" aria-hidden="true"></i><span class="link-text">Senior Records</span><i class="fas fa-chevron-down sidebar-group-chevron" aria-hidden="true"></i>
                </button>
                <ul class="sidebar-submenu" id="departmentSeniorRecordsMenu">
                    <li class="<?php echo ($current_page == 'department_records.php') ? 'active' : ''; ?>"><a href="department_records.php"><i class="fas fa-database"></i><span class="link-text">Records</span></a></li>
                    <li class="<?php echo ($current_page == 'digital_ids.php' || $current_page == 'digital_id.php') ? 'active' : ''; ?>"><a href="digital_ids.php"><i class="fas fa-id-card"></i><span class="link-text">Digital IDs</span></a></li>
                    <li class="<?php echo ($current_page == 'deceased_records.php') ? 'active' : ''; ?>"><a href="deceased_records.php"><i class="fas fa-ribbon"></i><span class="link-text">Deceased Records</span></a></li>
                    <li class="<?php echo ($current_page == 'department_archive.php') ? 'active' : ''; ?>"><a href="department_archive.php"><i class="fas fa-archive"></i><span class="link-text">Archive</span></a></li>
                </ul>
            </li>

            <?php $applicationsOpen = in_array($current_page, ['verify_document.php', 'import_records.php'], true); ?>
            <li class="sidebar-nav-group<?php echo $applicationsOpen ? ' is-open has-active-child' : ''; ?>">
                <button class="sidebar-group-toggle" type="button" aria-expanded="<?php echo $applicationsOpen ? 'true' : 'false'; ?>" aria-controls="departmentApplicationsMenu" data-tooltip="Applications">
                    <i class="fas fa-file-circle-check" aria-hidden="true"></i><span class="link-text">Applications</span><i class="fas fa-chevron-down sidebar-group-chevron" aria-hidden="true"></i>
                </button>
                <ul class="sidebar-submenu" id="departmentApplicationsMenu">
                    <li class="<?php echo ($current_page == 'verify_document.php') ? 'active' : ''; ?>"><a href="verify_document.php"><i class="fas fa-check-circle"></i><span class="link-text">Verify Documents</span></a></li>
                    <li class="<?php echo ($current_page == 'import_records.php') ? 'active' : ''; ?>"><a href="import_records.php"><i class="fas fa-file-import"></i><span class="link-text">Import Records</span></a></li>
                </ul>
            </li>

            <li class="<?php echo ($current_page == 'field_operations.php') ? 'active' : ''; ?>"><a href="field_operations.php" data-tooltip="Home Visits" aria-label="Home Visits"><i class="fas fa-house-medical"></i> <span class="link-text">Home Visits</span></a></li>
            <li class="<?php echo ($current_page == 'announcements.php') ? 'active' : ''; ?>"><a href="announcements.php" data-tooltip="Announcements & Benefits" aria-label="Announcements and Benefits"><i class="fas fa-bullhorn"></i> <span class="link-text">Announcements</span></a></li>

            <?php $administrationOpen = in_array($current_page, ['user_management.php', 'edit_user.php', 'signup.php', 'system_settings.php'], true); ?>
            <li class="sidebar-nav-group<?php echo $administrationOpen ? ' is-open has-active-child' : ''; ?>">
                <button class="sidebar-group-toggle" type="button" aria-expanded="<?php echo $administrationOpen ? 'true' : 'false'; ?>" aria-controls="departmentAdministrationMenu" data-tooltip="Settings">
                    <i class="fas fa-gear" aria-hidden="true"></i><span class="link-text">Settings</span><i class="fas fa-chevron-down sidebar-group-chevron" aria-hidden="true"></i>
                </button>
                <ul class="sidebar-submenu" id="departmentAdministrationMenu">
                    <li class="<?php echo ($current_page == 'user_management.php' || $current_page == 'edit_user.php' || $current_page == 'signup.php') ? 'active' : ''; ?>"><a href="user_management.php"><i class="fas fa-user-cog"></i><span class="link-text">User Management</span></a></li>
                    <li class="<?php echo ($current_page == 'system_settings.php') ? 'active' : ''; ?>"><a href="system_settings.php"><i class="fas fa-cog"></i><span class="link-text">System Settings</span></a></li>
                </ul>
            </li>

            <li class="logout-item"><a href="../index.php?logout=true" data-logout-confirm data-tooltip="Logout" aria-label="Logout"><i class="fas fa-sign-out-alt"></i> <span class="link-text">Logout</span></a></li>
        </ul>
    </div>
</div>
<script src="../assets/js/mobile-navigation.js?v=1"></script>
<script src="../assets/js/sidebar-toggle.js?v=5"></script>
<script src="../assets/js/sidebar-groups.js?v=1"></script>
<script src="../assets/js/session-timeout.js?v=6"></script>
<script src="../assets/js/form-language.js?v=2" defer></script>
<?php if ($current_page === 'import_records.php'): ?>
<link rel="stylesheet" href="../assets/css/table-pagination.css?v=1">
<script src="../assets/js/table-pagination.js?v=2" defer></script>
<script src="../assets/js/seniorlink-feedback.js?v=1" defer></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const importForm = document.querySelector('form.actions');
    if (!importForm) return;
    importForm.addEventListener('submit', (event) => {
        event.preventDefault();
        const importButton = importForm.querySelector('[name="commit_import"]');
        if (!importButton || importButton.disabled) return;
        const count = importButton.textContent.replace(/[^0-9]/g, '');
        window.showCarelinkConfirm(
            `Import ${count || 'these'} validated record(s)? This adds records to the system and cannot be undone from this page.`,
            () => {
                let commitField = importForm.querySelector('input[name="commit_import"]');
                if (!commitField) {
                    commitField = document.createElement('input');
                    commitField.type = 'hidden';
                    commitField.name = 'commit_import';
                    importForm.appendChild(commitField);
                }
                commitField.value = '1';
                importForm.submit();
            }
        );
    });
});
</script>
<?php endif; ?>
<?php include_once __DIR__ . '/legal_quick_access.php'; ?>
