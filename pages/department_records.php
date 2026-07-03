<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/barangays_list.php'; // Ensure barangays_list.php is included

// --- BACKEND LOGIC ---
// Authenticate and authorize
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'department_admin') {
    header('Location: ../index.php');
    exit;
}

// 1. Get filter, search, and pagination parameters from URL
$search = $_GET['search'] ?? '';
$barangayFilter = $_GET['barangay'] ?? 'all';
$typeFilter = $_GET['type'] ?? 'all';
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 15; // Number of records to display per page
$offset = ($page - 1) * $recordsPerPage;

// 2. Build the database query dynamically
$baseQuery = "FROM applications";
$whereClauses = [];
$params = [];

if (!empty($search)) {
    $whereClauses[] = "(full_name LIKE :search OR id_number LIKE :search)";
    $params[':search'] = "%$search%";
}
if ($barangayFilter !== 'all') {
    $whereClauses[] = "barangay = :barangay";
    $params[':barangay'] = $barangayFilter;
}
if ($typeFilter !== 'all') {
    $whereClauses[] = "application_type = :type"; // Match the column name
    $params[':type'] = $typeFilter;
}

// Always filter by status = 'Approved'
$whereClauses[] = "status = :status_approved";
$params[':status_approved'] = 'Approved';


$whereSql = '';
if (!empty($whereClauses)) {
    $whereSql = " WHERE " . implode(' AND ', $whereClauses);
}

// 3. Get total number of records for pagination
$totalQuery = "SELECT COUNT(*) " . $baseQuery . $whereSql;
$totalStmt = $conn->prepare($totalQuery);
$totalStmt->execute($params);
$totalRecords = $totalStmt->fetchColumn();
$totalPages = ceil($totalRecords / $recordsPerPage);

// 4. Get the records for the current page
$recordsQuery = "SELECT id_number as id, full_name, application_type, barangay, date_submitted, status " . $baseQuery . $whereSql . " ORDER BY date_submitted DESC LIMIT :limit OFFSET :offset";
$recordsStmt = $conn->prepare($recordsQuery);

// Bind all parameters including limit and offset
foreach ($params as $key => &$val) {
    if (strpos($key, ':limit') === false && strpos($key, ':offset') === false) { // Don't bind limit/offset with foreach
        $recordsStmt->bindParam($key, $val);
    }
}
$recordsStmt->bindParam(':limit', $recordsPerPage, PDO::PARAM_INT);
$recordsStmt->bindParam(':offset', $offset, PDO::PARAM_INT);

$recordsStmt->execute();
$applications = $recordsStmt->fetchAll(PDO::FETCH_ASSOC);

// Helper function to get status class for styling
function getStatusClass($status) {
    switch (strtolower($status)) {
        case 'pending': return 'status-pending';
        case 'verified': return 'status-verified';
        case 'rejected': return 'status-rejected';
        case 'approved': return 'status-approved';
        case 'sent to city hall': return 'status-sent'; // Add this if 'sent' is a status
        default: return 'status-default';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Records - CARELINK</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/department-sidebar.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --accent: #e74c3c;
            --success: #2ecc71;
            --warning: #f39c12;
            --light: #ecf0f1;
            --dark: #34495e;
            --gray: #95a5a6;
        }

        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
            height: 100vh;
            overflow: auto;
        }

        .main-content {
            flex: 1;
            padding: 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }

        .header-content {
            display: flex;
            flex-direction: column;
        }

        .welcome-message {
            font-size: 1.2rem;
            color: var(--gray);
            margin-bottom: 5px;
        }

        .header h1 {
            color: var(--primary);
            font-size: 1.8rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }


        .records-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 30px;
            margin-bottom: 30px;
        }

        .btn {
            display: inline-block;
            background: var(--secondary);
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.3s;
            border: none;
            cursor: pointer;
        }

        .btn:hover {
            background: #2980b9;
        }

        .btn-accent {
            background: var(--accent);
        }

        .btn-accent:hover {
            background: #c0392b;
        }

        .btn-small {
            padding: 5px 10px;
            font-size: 12px;
        }

        .records-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .records-actions {
            display: flex;
            gap: 10px;
        }

        .search-box {
            display: flex;
            align-items: center;
            background: white;
            border-radius: 5px;
            padding: 8px 15px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .search-box input {
            border: none;
            outline: none;
            padding: 5px;
            width: 200px;
        }

        .records-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .table-container {
            max-height: 500px;
            overflow-y: auto;
        }

        .table-header {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr 1fr 1fr 1fr; /* Adjusted for ID Number */
            padding: 15px 20px;
            background: var(--primary);
            color: white;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .table-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr 1fr 1fr 1fr; /* Adjusted for ID Number */
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
            transition: background 0.3s;
            font-size: 14px; /* Increased font size */
            font-weight: 500; /* Made text bolder */
            color: #212529; /* Darker color for better contrast */
        }

        .table-row:hover {
            background-color: #f5f7fa;
        }

        .filter-section {
            background: white;
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .filter-group label {
            font-size: 14px;
            color: var(--dark);
            font-weight: 500;
        }

        .filter-group select, .filter-group input {
            padding: 8px 12px;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
            min-width: 150px;
        }

        /* Pagination styles */
        .pagination {
            display: flex;
            justify-content: center; /* Center pagination */
            padding: 20px;
            gap: 10px;
            align-items: center;
        }

        .pagination-links a,
        .pagination-links span {
            padding: 8px 12px;
            border: 1px solid var(--gray);
            border-radius: 5px;
            text-decoration: none;
            color: var(--primary);
            transition: background-color 0.3s, color 0.3s;
        }

        .pagination-links a:hover {
            background-color: var(--secondary);
            color: white;
            border-color: var(--secondary);
        }

        .pagination-links .current-page {
            background-color: var(--secondary);
            color: white;
            border-color: var(--secondary);
        }

        .pagination-links .disabled {
            color: var(--gray);
            cursor: not-allowed;
        }
        .no-records { padding: 20px; text-align: center; color: var(--gray); }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            overflow-y: auto;
        }

        .modal-content {
            background-color: white;
            margin: 50px auto;
            width: 90%;
            max-width: 900px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            position: relative;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 30px;
            border-bottom: 1px solid #e0e0e0;
        }

        .modal-header h2 {
            color: var(--primary);
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--gray);
        }

        .close-modal:hover {
            color: var(--accent);
        }

        .modal-body {
            padding: 30px;
            max-height: 70vh;
            overflow-y: auto;
        }

        /* Form Styles */
        .form-section {
            margin-bottom: 30px;
        }

        .form-section h3 {
            font-size: 20px;
            color: var(--primary);
            margin-bottom: 20px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--primary);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 30px;
        }

        .table-row.status-pending {
            background-color: #fef9e7; /* Light yellow */
        }

        .table-row.status-verified {
            background-color: #eaf2f8; /* Light blue */
        }

        .table-row.status-rejected {
            background-color: #fdedec; /* Light red */
        }

        .table-row.status-approved {
            background-color: #e8f8f5; /* Light green */
        }

        .application-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: 600;
            font-size: 0.85em;
            text-transform: capitalize;
            color: white; /* Default text color for status */
        }

        .status-pending .application-status {
            background-color: var(--warning); /* Orange/Yellow */
        }

        .status-verified .application-status {
            background-color: var(--secondary); /* Blue */
        }

        .status-rejected .application-status {
            background-color: var(--accent); /* Red */
        }

        .status-approved .application-status {
            background-color: var(--success); /* Green */
        }

        .status-default .application-status {
            background-color: var(--gray); /* Gray fallback */
        }
        .image-placeholder {
            margin-top: 10px;
            width: 100%;
            height: 200px; /* Or any other desired height */
            border: 2px dashed #ccc;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }

        .image-placeholder img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
    </style>
</head>
<body>
   <div class="container">
        <?php include '../partials/department_sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <div class="header-content">
                    <div class="welcome-message" data-first-name="<?php echo htmlspecialchars($_SESSION['first_name']); ?>" data-last-name="<?php echo htmlspecialchars($_SESSION['last_name']); ?>" data-role="<?php echo htmlspecialchars($_SESSION['role']); ?>"></div>
                    <h1>Department Records</h1>
                </div>
                <div class="header-actions">
                    <div class="user-info">
                                            <div class="user-avatar">
                                                <?php
                                                    $profilePic = isset($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : 'default.jpg';
                                                    $profilePicPath = '../images/profile_pictures/' . $profilePic;
                                                    if (!file_exists($profilePicPath) || is_dir($profilePicPath)) {
                                                        $profilePicPath = '../images/profile_pictures/default.jpg'; // Fallback to default if file doesn't exist
                                                    }
                                                ?>
                                                <img src="<?php echo $profilePicPath; ?>" alt="Profile Picture" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                                            </div>                        <div class="user-details">
                            <h2><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></h2>
                            <p><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $_SESSION['role']))); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Records Section -->
            <div class="records-section">
                <form id="filterForm" method="GET" action="department_records.php">
                    <div class="records-header">
                        <h2>All Records for Pasig City</h2>
                        <div class="records-actions">
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" id="searchInput" name="search" placeholder="Search records..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filter Section -->
                    <div class="filter-section">
                        <div class="filter-group">
                            <label for="barangay-filter">Barangay</label>
                            <select id="barangayFilter" name="barangay">
                                <option value="all">All Barangays</option>
                                <?php foreach ($barangays_list as $b): ?>
                                    <option value="<?php echo htmlspecialchars($b); ?>" <?php echo ($barangayFilter === $b) ? 'selected' : ''; ?>><?php echo htmlspecialchars($b); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="type-filter">Application Type</label>
                            <select id="typeFilter" name="type">
                                <option value="all">All Types</option>
                                <option value="PWD" <?php echo ($typeFilter === 'PWD') ? 'selected' : ''; ?>>PWD</option>
                                <option value="Senior Citizen" <?php echo ($typeFilter === 'Senior Citizen') ? 'selected' : ''; ?>>Senior Citizen</option>
                            </select>
                        </div>
                    </div>
                </form>
                
                <div class="records-table">
                    <div class="table-container">
                        <div class="table-header">
                            <div>Applicant Name</div>
                            <div>Application Type</div>
                            <div>Barangay</div>
                            <div>Date Submitted</div>
                            <div>Status</div>
                            <div>ID Number</div>
                            <div>Actions</div>
                        </div>
                        
                        <div class="table-data">
                            <?php if (empty($applications)): ?>
                                <div class="no-records">No records found matching your criteria.</div>
                            <?php else: ?>
                                <?php foreach ($applications as $app): ?>
                                    <div class="table-row <?php echo getStatusClass($app['status']); ?>">
                                        <div><?php echo htmlspecialchars($app['full_name']); ?></div>
                                        <div><?php echo htmlspecialchars($app['application_type']); ?></div>
                                        <div><?php echo htmlspecialchars($app['barangay']); ?></div>
                                        <div><?php echo date("M d, Y", strtotime($app['date_submitted'])); ?></div>
                                        <div><span class="application-status <?php echo getStatusClass($app['status']); ?>"><?php echo htmlspecialchars($app['status']); ?></span></div>
                                        <div><?php echo htmlspecialchars($app['id']); ?></div> <?php // Assuming ID Number refers to application ID ?>
                                        <div><button class="btn btn-small view-details-btn" data-id="<?php echo $app['id']; ?>"><i class="fas fa-eye"></i> View</button></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <div class="pagination-info">
                            Page <?php echo $page; ?> of <?php echo $totalPages; ?> &bull; Total: <?php echo $totalRecords; ?> records
                        </div>
                        <div class="pagination-links">
                            <?php
                            $queryString = http_build_query(array_filter(['search' => $search, 'barangay' => $barangayFilter, 'type' => $typeFilter]));
                            
                            if ($page > 1) { echo '<a href="?page=' . ($page - 1) . '&' . $queryString . '">Previous</a>'; } else { echo '<span class="disabled">Previous</span>'; }

                            for ($i = 1; $i <= $totalPages; $i++) {
                                if ($i == $page) { echo '<span class="current-page">' . $i . '</span>'; }
                                else { echo '<a href="?page=' . $i . '&' . $queryString . '">' . $i . '</a>'; }
                            }

                            if ($page < $totalPages) { echo '<a href="?page=' . ($page + 1) . '&' . $queryString . '">Next</a>'; } else { echo '<span class="disabled">Next</span>'; }
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Application Detail Modal -->
    <div id="applicationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Application Details</h2>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                
            </div>
        </div>
    </div>

    <script src="../assets/js/sidebar-toggle.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const welcomeMessage = document.querySelector('.welcome-message');
            const firstName = welcomeMessage.dataset.firstName;
            const lastName = welcomeMessage.dataset.lastName;
            const role = welcomeMessage.dataset.role;
            const hour = new Date().getHours();
            let greeting;
            
            if (hour < 12) {
                greeting = "Good morning";
            } else if (hour < 18) {
                greeting = "Good afternoon";
            } else {
                greeting = "Good evening";
            }
            
            welcomeMessage.innerHTML = `${greeting}, <strong>${firstName} ${lastName}</strong>!`;

            // Event delegation for View Details buttons
            const tableBody = document.querySelector('.table-data');
            tableBody.addEventListener('click', function(event) {
                const clickedElement = event.target.closest('.view-details-btn');
                if (clickedElement) {
                    const appId = clickedElement.dataset.id;
                    openApplicationModal(appId);
                }
            });

            const closeModalBtn = document.querySelector('#applicationModal .close-modal');
            closeModalBtn.addEventListener('click', () => {
                document.getElementById('applicationModal').style.display = 'none';
            });
        });

        function openApplicationModal(appId) {
            const modalBody = document.querySelector('#applicationModal .modal-body');
            modalBody.innerHTML = '<p>Loading...</p>';
            document.getElementById('applicationModal').style.display = 'block';

            fetch(`../api/get_application_details.php?id=${appId}`)
                .then(response => response.json())
                .then(application => {
                    if (application.error) {
                        modalBody.innerHTML = `<p>Error: ${application.error}</p>`;
                        return;
                    }

                    if (!application) {
                        modalBody.innerHTML = '<p>Error loading application details.</p>';
                        return;
                    }

                    modalBody.innerHTML = `
                        <form id="applicationDetailForm" method="POST" action="../api/update_application.php" enctype="multipart/form-data">
                            <input type="hidden" id="applicationId" name="applicationId" value="${application.id}">
                            <div class="form-section">
                                <h3><i class="fas fa-user"></i> Basic Information</h3>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="applicationType">Application Type</label>
                                        <select id="applicationType" name="applicationType" required>
                                            <option value="pwd" ${application.application_type === 'pwd' ? 'selected' : ''}>PWD</option>
                                            <option value="senior" ${application.application_type === 'senior' ? 'selected' : ''}>Senior Citizen</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="lastName">Last Name</label>
                                        <input type="text" id="lastName" name="lastName" value="${application.lastName || ''}" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="firstName">First Name</label>
                                        <input type="text" id="firstName" name="firstName" value="${application.firstName || ''}" required>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="middleName">Middle Name</label>
                                        <input type="text" id="middleName" name="middleName" value="${application.middleName || ''}">
                                    </div>
                                    <div class="form-group">
                                        <label for="suffix">Suffix</label>
                                        <input type="text" id="suffix" name="suffix" value="${application.suffix || ''}">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="birthDate">Birth Date</label>
                                        <input type="date" id="birthDate" name="birthDate" value="${application.birth_date || ''}" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="contactNumber">Contact Number</label>
                                        <input type="text" id="contactNumber" name="contactNumber" value="${application.contact_number || ''}" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="completeAddress">Complete Address</label>
                                    <textarea id="completeAddress" name="completeAddress" required>${application.complete_address || ''}</textarea>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="emergencyContactName">Emergency Contact Name</label>
                                        <input type="text" id="emergencyContactName" name="emergencyContactName" value="${application.emergency_contact_name || ''}">
                                    </div>
                                    <div class="form-group">
                                        <label for="emergencyContact">Emergency Contact Number</label>
                                        <input type="text" id="emergencyContact" name="emergencyContact" value="${application.emergency_contact || ''}">
                                    </div>
                                </div>
                            </div>

                            <div id="pwd-fields-modal" style="display: ${application.application_type === 'pwd' ? 'block' : 'none'}">
                                <div class="form-section">
                                    <h3><i class="fas fa-wheelchair"></i> PWD Specific Information</h3>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="pwdIdNumber">ID Number</label>
                                            <input type="text" id="pwdIdNumber" name="idNumber" value="${application.id_number || ''}">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Type of Disability</label>
                                        <div>
                                            <input type="checkbox" name="disabilityType[]" value="Deaf/Hard of Hearing" ${application.disabilityType && application.disabilityType.includes('Deaf/Hard of Hearing') ? 'checked' : ''}> Deaf/Hard of Hearing<br>
                                            <input type="checkbox" name="disabilityType[]" value="Intellectual Disability" ${application.disabilityType && application.disabilityType.includes('Intellectual Disability') ? 'checked' : ''}> Intellectual Disability<br>
                                            <input type="checkbox" name="disabilityType[]" value="Learning Disability" ${application.disabilityType && application.disabilityType.includes('Learning Disability') ? 'checked' : ''}> Learning Disability<br>
                                            <input type="checkbox" name="disabilityType[]" value="Mental Disability" ${application.disabilityType && application.disabilityType.includes('Mental Disability') ? 'checked' : ''}> Mental Disability<br>
                                            <input type="checkbox" name="disabilityType[]" value="Orthopedic" ${application.disabilityType && application.disabilityType.includes('Orthopedic') ? 'checked' : ''}> Orthopedic<br>
                                            <input type="checkbox" name="disabilityType[]" value="Physical Disability" ${application.disabilityType && application.disabilityType.includes('Physical Disability') ? 'checked' : ''}> Physical Disability<br>
                                            <input type="checkbox" name="disabilityType[]" value="Psychosocial Disability" ${application.disabilityType && application.disabilityType.includes('Psychosocial Disability') ? 'checked' : ''}> Psychosocial Disability<br>
                                            <input type="checkbox" name="disabilityType[]" value="Speech and Language Impairment" ${application.disabilityType && application.disabilityType.includes('Speech and Language Impairment') ? 'checked' : ''}> Speech and Language Impairment<br>
                                            <input type="checkbox" name="disabilityType[]" value="Visual Disability" ${application.disabilityType && application.disabilityType.includes('Visual Disability') ? 'checked' : ''}> Visual Disability<br>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="pwdIdIssueDate">ID Issue Date</label>
                                            <input type="date" id="pwdIdIssueDate" name="pwdIdIssueDate" value="${application.pwd_id_issue_date || ''}">
                                        </div>
                                        <div class="form-group">
                                            <label for="pwdIdExpiryDate">ID Expiry Date</label>
                                            <input type="date" id="pwdIdExpiryDate" name="pwdIdExpiryDate" value="${application.pwd_id_expiry_date || ''}">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div id="senior-fields-modal" style="display: ${application.application_type === 'senior' ? 'block' : 'none'}">
                                <div class="form-section">
                                    <h3><i class="fas fa-user-friends"></i> Senior Citizen Specific Information</h3>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="seniorIdNumber">ID Number</label>
                                            <input type="text" id="seniorIdNumber" name="idNumber" value="${application.id_number || ''}">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h3><i class="fas fa-file-alt"></i> Required Documents</h3>
                                <div class="form-group">
                                    <label for="proofOfAddress">Proof of Address</label>
                                    <input type="file" id="proofOfAddress" name="proofOfAddress">
                                     <div class="image-placeholder" id="proofOfAddressPreview">
                                        ${application.has_proof_of_address ? `<img src="../api/get_document.php?id=${appId}&doc_type=proof_of_address" alt="Proof of Address">` : '<p>No document uploaded.</p>'}
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="idImage">ID Image</label>
                                    <input type="file" id="idImage" name="idImage">
                                    <div class="image-placeholder" id="idImagePreview">
                                        ${application.has_id_image ? `<img src="../api/get_document.php?id=${appId}&doc_type=id_image" alt="ID Image">` : '<p>No document uploaded.</p>'}
                                    </div>
                                </div>
                            </div>
                            <div class="form-section">
                                <h3><i class="fas fa-info-circle"></i> Additional Information</h3>
                                <div class="form-group">
                                    <label for="additionalNotes">Additional Notes</label>
                                    <textarea id="additionalNotes" name="additionalNotes">${application.additional_notes || ''}</textarea>
                                </div>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn"><i class="fas fa-save"></i> Save Changes</button>
                                <button type="button" class="btn btn-accent" onclick="exportApplicationDetails(${appId})"><i class="fas fa-download"></i> Export</button>
                            </div>
                        </form>
                    `;

                    
                    // Add event listener for application type change within the modal
                    document.getElementById('applicationType').addEventListener('change', function () {
                        if (this.value === 'pwd') {
                            document.getElementById('pwd-fields-modal').style.display = 'block';
                            document.getElementById('senior-fields-modal').style.display = 'none';
                        } else if (this.value === 'senior') {
                            document.getElementById('pwd-fields-modal').style.display = 'none';
                            document.getElementById('senior-fields-modal').style.display = 'block';
                        }
                    });

                    // Handle form submission for updating application
                    document.getElementById('applicationDetailForm').addEventListener('submit', function(e) {
                        e.preventDefault();
                        const form = e.target;
                        const formData = new FormData(form);

                        fetch(form.action, {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert(data.message);
                                document.getElementById('applicationModal').style.display = 'none';
                                // Optionally refresh the table or update the specific row
                                location.reload(); // Reload the page to reflect changes
                            } else {
                                alert(data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error updating application:', error);
                            alert('An error occurred while updating the application.');
                        });
                    });
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    modalBody.innerHTML = '<p>Error loading application details. Please check the console for more information.</p>';
                });
        }

        // Real-time filtering logic
        document.addEventListener('DOMContentLoaded', function() {
            const filterForm = document.getElementById('filterForm');
            const searchInput = document.getElementById('searchInput');
            const barangayFilter = document.getElementById('barangayFilter');
            const typeFilter = document.getElementById('typeFilter');

            function applyFilters() {
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set('search', searchInput.value);
                currentUrl.searchParams.set('barangay', barangayFilter.value);
                currentUrl.searchParams.set('type', typeFilter.value);
                currentUrl.searchParams.set('page', 1); // Reset to first page on filter change
                window.location.href = currentUrl.toString();
            }

            searchInput.addEventListener('input', applyFilters);
            barangayFilter.addEventListener('change', applyFilters);
            typeFilter.addEventListener('change', applyFilters);
        });
    </script>
</body>
</html>