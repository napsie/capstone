<?php
session_start();
require_once '../includes/db_connect.php';

// Redirect if not logged in or not barangay staff
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'barangay_staff') {
    header('Location: ../index.php');
    exit;
}

$barangayName = htmlspecialchars($_SESSION['barangay'] ?? 'Unknown Barangay');
error_log("DEBUG: Logged-in barangay: " . $_SESSION['barangay']);

// 1. Get filter, search, and pagination parameters from URL (these will be used by JavaScript for client-side filtering)
$search = $_GET['search'] ?? '';
$typeFilter = $_GET['type'] ?? 'all';
$yearFilter = $_GET['year'] ?? 'all'; // Default to 'all' for client-side
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;

// Fetch all APPROVED applications for the specific barangay to enable client-side filtering
$baseQuery = "FROM applications WHERE barangay = :barangay AND status = 'approved'";
$params = [':barangay' => $_SESSION['barangay']];

// For client-side filtering, we'll fetch more records initially, or ideally all if performance allows.
// Let's set a very high limit to simulate fetching all for client-side filtering.
$recordsPerPage = 10000; // Assuming 10k records is a reasonable upper limit for a single barangay's display
$offset = 0; // Fetch from the beginning

// No other WHERE clauses for initial fetch.

// 3. Get total number of records for pagination (though mostly client-side now, this count might still be useful for initial load info)
$totalQuery = "SELECT COUNT(*) " . $baseQuery; // Count without other filters
$totalStmt = $conn->prepare($totalQuery);
$totalStmt->execute($params);
$totalRecords = $totalStmt->fetchColumn();
$totalPages = ceil($totalRecords / $recordsPerPage); // This will likely be 1 now with high recordsPerPage

// 4. Get the records for the current page (effectively all for client-side filtering)
$recordsQuery = "SELECT id_number as id, full_name, application_type, date_submitted, status " . $baseQuery . " ORDER BY date_submitted DESC LIMIT :limit OFFSET :offset";
$recordsStmt = $conn->prepare($recordsQuery);
$recordsStmt->bindParam(':barangay', $_SESSION['barangay']); // Re-bind for clarity, though it's already in $params
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
        case 'sent to city hall': return 'status-sent';
        default: return 'status-default';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Records - Barangay <?php echo $barangayName; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/barangay-sidebar.css">
    <link rel="stylesheet" href="../assets/css/main-dark-mode.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../assets/css/barangay-records.css">
    <style>
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
        <!-- Sidebar -->
        <?php include '../partials/barangay_sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <div class="header-content">
                    <div class="welcome-message">Welcome back, <strong><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></strong>!</div>
                    <h1>Barangay <?php echo $barangayName; ?> Records</h1>
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
                        </div>
                        <div class="user-details">
                            <h2><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></h2>
                            <p><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $_SESSION['role']))) . ' • ' . htmlspecialchars($_SESSION['barangay']); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Records Section -->
            <div class="records-section">
                <div class="records-header">
                    <h2>Application Records</h2>
                </div>
                
                <!-- Filter Section -->
                <div class="filter-section">
                    <div class="filter-group">
                        <label for="search-input">Search:</label>
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input id="search-input" type="text" placeholder="Search by name or ID..." oninput="applyFilters()">
                        </div>
                    </div>
                    <div class="filter-group">
                        <label for="year-filter">Year:</label>
                        <select id="year-filter" onchange="applyFilters()">
                            <option value="all">All Years</option>
                            <?php
                                $currentYear = date("Y");
                                for ($y = $currentYear; $y >= 2020; $y--) {
                                    $selected = ($yearFilter == $y) ? 'selected' : ''; // Use $yearFilter from PHP
                                    echo "<option value=\"$y\" $selected>$y</option>";
                                }
                            ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="type-filter">Application Type:</label>
                        <select id="type-filter" onchange="applyFilters()">
                            <option value="all">All Types</option>
                            <option value="PWD">PWD</option>
                            <option value="Senior Citizen">Senior Citizen</option>
                        </select>
                    </div>
                </div>
                
                <div class="records-table">
                    <div class="table-container">
                        <div class="table-header">
                            <div>Applicant Name</div>
                            <div>Application Type</div>
                            <div>Date Submitted</div>
                            <div>Status</div>
                            <div>Actions</div>
                        </div>
                        
                        <div class="table-data">
                            <?php if (empty($applications)): ?>
                                <div class="no-results">No applications found for Barangay <?php echo $barangayName; ?>.</div>
                            <?php else: ?>
                                <?php foreach ($applications as $app): ?>
                                    <div class="table-row <?php echo getStatusClass($app['status']); ?>" 
                                         data-id="<?php echo htmlspecialchars($app['id']); ?>"
                                         data-name="<?php echo htmlspecialchars($app['full_name']); ?>"
                                         data-date="<?php echo htmlspecialchars($app['date_submitted']); ?>"
                                         data-type="<?php echo htmlspecialchars($app['application_type']); ?>">
                                        <div><?php echo htmlspecialchars($app['full_name']); ?></div>
                                        <div><?php echo htmlspecialchars($app['application_type']); ?></div>
                                        <div><?php echo htmlspecialchars(date('m/d/Y', strtotime($app['date_submitted']))); ?></div>
                                        <div>
                                            <span class="application-status"><?php echo htmlspecialchars(ucfirst($app['status'])); ?></span>
                                        </div>
                                        <div>
                                            <button class="btn btn-small view-application-btn" data-id="<?php echo $app['id']; ?>"><i class="fas fa-eye"></i> View</button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="footer">
                <p>Centralized Profiling and Record Authentication System | Barangay <?php echo $barangayName; ?> &copy; 2024</p>
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
                <form id="applicationDetailForm" method="POST" action="../api/update_application.php" enctype="multipart/form-data">
                    <input type="hidden" id="applicationId" name="applicationId">
                    <div class="form-section">
                        <h3><i class="fas fa-user"></i> Basic Information</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="applicationType">Application Type</label>
                                <select id="applicationType" name="applicationType" required>
                                    <option value="pwd">PWD</option>
                                    <option value="senior">Senior Citizen</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="lastName">Last Name</label>
                                <input type="text" id="lastName" name="lastName" required>
                            </div>
                            <div class="form-group">
                                <label for="firstName">First Name</label>
                                <input type="text" id="firstName" name="firstName" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="middleName">Middle Name</label>
                                <input type="text" id="middleName" name="middleName">
                            </div>
                            <div class="form-group">
                                <label for="suffix">Suffix</label>
                                <input type="text" id="suffix" name="suffix">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="birthDate">Birth Date</label>
                                <input type="date" id="birthDate" name="birthDate" required>
                            </div>
                            <div class="form-group">
                                <label for="contactNumber">Contact Number</label>
                                <input type="text" id="contactNumber" name="contactNumber" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="completeAddress">Complete Address</label>
                            <textarea id="completeAddress" name="completeAddress" required></textarea>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="emailAddress">Email Address</label>
                                <input type="email" id="emailAddress" name="emailAddress">
                            </div>
                            <div class="form-group">
                                <label for="religion">Religion</label>
                                <input type="text" id="religion" name="religion">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="sex">Sex</label>
                                <select id="sex" name="sex">
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="civilStatus">Civil Status</label>
                                <select id="civilStatus" name="civilStatus">
                                    <option value="Single">Single</option>
                                    <option value="Married">Married</option>
                                    <option value="Widow/er">Widow/er</option>
                                    <option value="Cohabitation (Live-in)">Cohabitation (Live-in)</option>
                                </select>
                            </div>
                        </div>
                         <div class="form-row">
                            <div class="form-group">
                                <label for="bloodType">Blood Type</label>
                                <input type="text" id="bloodType" name="bloodType">
                            </div>
                        </div>
                    </div>

                    <div id="pwd-fields-modal">
                        <div class="form-section">
                            <h3><i class="fas fa-wheelchair"></i> PWD Specific Information</h3>
                            <div class="form-group">
                                <label>Type of Disability</label>
                                <div>
                                    <input type="checkbox" name="disabilityType[]" value="Deaf/Hard of Hearing"> Deaf/Hard of Hearing<br>
                                    <input type="checkbox" name="disabilityType[]" value="Intellectual Disability"> Intellectual Disability<br>
                                    <input type="checkbox" name="disabilityType[]" value="Learning Disability"> Learning Disability<br>
                                    <input type="checkbox" name="disabilityType[]" value="Mental Disability"> Mental Disability<br>
                                    <input type="checkbox" name="disabilityType[]" value="Orthopedic"> Orthopedic<br>
                                    <input type="checkbox" name="disabilityType[]" value="Physical Disability"> Physical Disability<br>
                                    <input type="checkbox" name="disabilityType[]" value="Psychosocial Disability"> Psychosocial Disability<br>
                                    <input type="checkbox" name="disabilityType[]" value="Speech and Language Impairment"> Speech and Language Impairment<br>
                                    <input type="checkbox" name="disabilityType[]" value="Visual Disability"> Visual Disability<br>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Cause of Disability</label>
                                <div>
                                    <input type="checkbox" name="disabilityCause[]" value="Acquired"> Acquired<br>
                                    <input type="checkbox" name="disabilityCause[]" value="Cancer"> Cancer<br>
                                    <input type="checkbox" name="disabilityCause[]" value="Chronic Illness"> Chronic Illness<br>
                                    <input type="checkbox" name="disabilityCause[]" value="Congenital/Inborn"> Congenital/Inborn<br>
                                    <input type="checkbox" name="disabilityCause[]" value="Injury"> Injury<br>
                                    <input type="checkbox" name="disabilityCause[]" value="Autism"> Autism<br>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Educational Attainment</label>
                                <div>
                                    <input type="radio" name="educationalAttainment" value="None"> None<br>
                                    <input type="radio" name="educationalAttainment" value="Elementary Education"> Elementary Education<br>
                                    <input type="radio" name="educationalAttainment" value="High School Education"> High School Education<br>
                                    <input type="radio" name="educationalAttainment" value="College"> College<br>
                                    <input type="radio" name="educationalAttainment" value="Post Graduate Program"> Post Graduate Program<br>
                                    <input type="radio" name="educationalAttainment" value="Non-Formal Education"> Non-Formal Education<br>
                                    <input type="radio" name="educationalAttainment" value="Vocational"> Vocational<br>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Status of Employment</label>
                                <div>
                                    <input type="radio" name="employmentStatus" value="Employed"> Employed<br>
                                    <input type="radio" name="employmentStatus" value="Unemployed"> Unemployed<br>
                                    <input type="radio" name="employmentStatus" value="Self-employed"> Self-employed<br>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="occupation">Occupation</label>
                                <select id="occupation" name="occupation">
                                    <option value="Managers">Managers</option>
                                    <option value="Professionals">Professionals</option>
                                    <option value="Technician and Associate Professionals">Technician and Associate Professionals</option>
                                    <option value="Clerical Support Workers">Clerical Support Workers</option>
                                    <option value="Service and Sales Workers">Service and Sales Workers</option>
                                    <option value="Skilled Agricultural, Forestry & Fishery Workers">Skilled Agricultural, Forestry & Fishery Workers</option>
                                    <option value="Plant and Machine Operators & Assemblers">Plant and Machine Operators & Assemblers</option>
                                    <option value="Elementary Occupations">Elementary Occupations</option>
                                    <option value="Armed Forces Occupations">Armed Forces Occupations</option>
                                    <option value="Others">Others, specify</option>
                                </select>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="sssNo">SSS No.</label>
                                    <input type="text" id="sssNo" name="sssNo" oninput="this.value = this.value.replace(/[^a-zA-Z0-9-]/g, '')">
                                </div>
                                <div class="form-group">
                                    <label for="gsisNo">GSIS No.</label>
                                    <input type="text" id="gsisNo" name="gsisNo" oninput="this.value = this.value.replace(/[^a-zA-Z0-9-]/g, '')">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="pagibigNo">Pag-ibig No.</label>
                                    <input type="text" id="pagibigNo" name="pagibigNo" oninput="this.value = this.value.replace(/[^a-zA-Z0-9-]/g, '')">
                                </div>
                                <div class="form-group">
                                    <label for="philhealthNo">Philhealth No.</label>
                                    <input type="text" id="philhealthNo" name="philhealthNo" oninput="this.value = this.value.replace(/[^a-zA-Z0-9-]/g, '')">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="fatherName">Father's Name</label>
                                <input type="text" id="fatherName" name="fatherName" oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')">
                            </div>
                            <div class="form-group">
                                <label for="motherName">Mother's Name</label>
                                <input type="text" id="motherName" name="motherName" oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')">
                            </div>
                        </div>
                    </div>

                    <div id="senior-fields-modal" style="display: none;">
                        <div class="form-section">
                            <h3><i class="fas fa-user-friends"></i> Senior Citizen Specific Information</h3>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="placeOfBirth">Place of Birth</label>
                                    <input type="text" id="placeOfBirth" name="placeOfBirth">
                                </div>
                                <div class="form-group">
                                    <label for="yearsInPasig">No. of Years in Pasig</label>
                                    <input type="number" id="yearsInPasig" name="yearsInPasig">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="citizenship">Citizenship</label>
                                <input type="text" id="citizenship" name="citizenship">
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3><i class="fas fa-file-alt"></i> Required Documents</h3>
                        <div class="form-group">
                            <label for="birthCertificate">Birth Certificate</label>
                            <input type="file" id="birthCertificate" name="birthCertificate">
                            <p id="currentBirthCertificate"></p>
                        </div>
                        <div class="form-group">
                            <label for="medicalCertificate">Medical Certificate</label>
                            <input type="file" id="medicalCertificate" name="medicalCertificate">
                            <p id="currentMedicalCertificate"></p>
                        </div>
                        <div class="form-group">
                            <label for="clientIdentification">Client Identification</label>
                            <input type="file" id="clientIdentification" name="clientIdentification">
                            <p id="currentClientIdentification"></p>
                        </div>
                        <div class="form-group">
                            <label for="proofOfAddress">Proof of Address</label>
                            <input type="file" id="proofOfAddress" name="proofOfAddress">
                            <p id="currentProofOfAddress"></p>
                        </div>
                        <div class="form-group">
                            <label for="idImage">Updated ID Image</label>
                            <input type="file" id="idImage" name="idImage">
                            <p id="currentIdImage"></p>
                        </div>
                    </div>
                    <div class="form-section">
                        <h3><i class="fas fa-info-circle"></i> Additional Information</h3>
                        <div class="form-group">
                            <label for="additionalNotes">Additional Notes</label>
                            <textarea id="additionalNotes" name="additionalNotes"></textarea>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn"><i class="fas fa-save"></i> Save Changes</button>
                        <button type="button" class="btn btn-accent" onclick="exportApplicationDetails(document.getElementById('applicationId').value)"><i class="fas fa-download"></i> Export</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Import Applications Modal -->
    <div id="importModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Import Applications from CSV</h2>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Download the responses from your Google Form as a CSV file and and upload it here to import the applications.</p>
                <div class="message">
                    <?php
                        if (isset($_SESSION['import_message'])) {
                            echo $_SESSION['import_message'];
                            unset($_SESSION['import_message']);
                        }
                    ?>
                </div>
                <form action="../api/import_applications.php" method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="csv_file">Select CSV File</label>
                        <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                    </div>
                    <button type="submit" class="btn">Import Applications</button>
                </form>
            </div>
        </div>
    </div>


    <script src="../assets/js/sidebar-toggle.js"></script>
    <script src="../assets/js/dark-mode.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add click event to navigation items
            const navItems = document.querySelectorAll('.nav-links li');
            navItems.forEach(item => {
                item.addEventListener('click', function() {
                    navItems.forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                });
            });

            // Event delegation for View Details buttons
            const tableBody = document.querySelector('.table-data');
            tableBody.addEventListener('click', function(event) {
                const clickedElement = event.target.closest('.view-application-btn');
                if (clickedElement) {
                    const appId = clickedElement.dataset.id;
                    openApplicationModal(appId);
                }
            });

            const closeModalBtn = document.querySelector('#applicationModal .close-modal');
            closeModalBtn.addEventListener('click', () => {
                document.getElementById('applicationModal').style.display = 'none';
            });

            // Year selection functionality
            const yearBtns = document.querySelectorAll('.year-btn');
            const currentYearElement = document.getElementById('current-year');
            
            yearBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const year = this.getAttribute('data-year');
                    
                    // Update active year button
                    yearBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Update current year display
                    currentYearElement.textContent = year;
                    
                    // Update table data with new year records
                    updateTableData(year);
                });
            });
            
            // Auto-filter functionality (client-side filtering for now)
            const typeFilter = document.getElementById('type-filter');
            
            // Add event listeners for automatic filtering
            typeFilter.addEventListener('change', applyFilters);
            
            // Update welcome message based on time of day
            const welcomeMessage = document.querySelector('.welcome-message');
            const hour = new Date().getHours();
            let greeting;
            
            if (hour < 12) {
                greeting = "Good morning";
            } else if (hour < 18) {
                greeting = "Good afternoon";
            } else {
                greeting = "Good evening";
            }
            
            welcomeMessage.innerHTML = `${greeting}, <strong><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></strong>!`;
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
                                        <input type="text" id="lastName" name="lastName" value="${application.lastName || ''}" oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="firstName">First Name</label>
                                        <input type="text" id="firstName" name="firstName" value="${application.firstName || ''}" oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')" required>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="middleName">Middle Name</label>
                                        <input type="text" id="middleName" name="middleName" value="${application.middleName || ''}" oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')">
                                    </div>
                                    <div class="form-group">
                                        <label for="suffix">Suffix</label>
                                        <input type="text" id="suffix" name="suffix" value="${application.suffix || ''}" oninput="this.value = this.value.replace(/[^a-zA-Z\s.]/g, '')">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="birthDate">Birth Date</label>
                                        <input type="date" id="birthDate" name="birthDate" value="${application.birth_date || ''}" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="contactNumber">Contact Number</label>
                                        <input type="text" id="contactNumber" name="contactNumber" value="${application.contact_number || ''}" oninput="this.value = this.value.replace(/[^0-9]/g, '')" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="completeAddress">Complete Address</label>
                                    <textarea id="completeAddress" name="completeAddress" required>${application.complete_address || ''}</textarea>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="emergencyContactName">Emergency Contact Name</label>
                                        <input type="text" id="emergencyContactName" name="emergencyContactName" value="${application.emergency_contact_name || ''}" oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')">
                                    </div>
                                    <div class="form-group">
                                        <label for="emergencyContact">Emergency Contact Number</label>
                                        <input type="text" id="emergencyContact" name="emergencyContact" value="${application.emergency_contact || ''}" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                                    </div>
                                </div>
                            </div>

                            <div id="pwd-fields-modal" style="display: ${application.application_type === 'pwd' ? 'block' : 'none'}">
                                <div class="form-section">
                                    <h3><i class="fas fa-wheelchair"></i> PWD Specific Information</h3>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="pwdIdNumber">ID Number</label>
                                            <input type="text" id="pwdIdNumber" name="idNumber" value="${application.id_number || ''}" oninput="this.value = this.value.replace(/[^a-zA-Z0-9]/g, '')">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Type of Disability</label>
                                        <div>
                                            <input type="checkbox" name="disabilityType[]" value="Deaf/Hard of Hearing" ${application.disability_type && application.disability_type.includes('Deaf/Hard of Hearing') ? 'checked' : ''}> Deaf/Hard of Hearing<br>
                                            <input type="checkbox" name="disabilityType[]" value="Intellectual Disability" ${application.disability_type && application.disability_type.includes('Intellectual Disability') ? 'checked' : ''}> Intellectual Disability<br>
                                            <input type="checkbox" name="disabilityType[]" value="Learning Disability" ${application.disability_type && application.disability_type.includes('Learning Disability') ? 'checked' : ''}> Learning Disability<br>
                                            <input type="checkbox" name="disabilityType[]" value="Mental Disability" ${application.disability_type && application.disability_type.includes('Mental Disability') ? 'checked' : ''}> Mental Disability<br>
                                            <input type="checkbox" name="disabilityType[]" value="Orthopedic" ${application.disability_type && application.disability_type.includes('Orthopedic') ? 'checked' : ''}> Orthopedic<br>
                                            <input type="checkbox" name="disabilityType[]" value="Physical Disability" ${application.disability_type && application.disability_type.includes('Physical Disability') ? 'checked' : ''}> Physical Disability<br>
                                            <input type="checkbox" name="disabilityType[]" value="Psychosocial Disability" ${application.disability_type && application.disability_type.includes('Psychosocial Disability') ? 'checked' : ''}> Psychosocial Disability<br>
                                            <input type="checkbox" name="disabilityType[]" value="Speech and Language Impairment" ${application.disability_type && application.disability_type.includes('Speech and Language Impairment') ? 'checked' : ''}> Speech and Language Impairment<br>
                                            <input type="checkbox" name="disabilityType[]" value="Visual Disability" ${application.disability_type && application.disability_type.includes('Visual Disability') ? 'checked' : ''}> Visual Disability<br>
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
                                            <input type="text" id="seniorIdNumber" name="idNumber" value="${application.id_number || ''}" oninput="this.value = this.value.replace(/[^a-zA-Z0-9]/g, '')">
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


        function applyFilters() {
            const searchValue = document.getElementById('search-input').value.toLowerCase();
            const yearFilterValue = document.getElementById('year-filter').value;
            const typeFilterValue = document.getElementById('type-filter').value.toLowerCase();
            const tableRows = document.querySelectorAll('.table-data .table-row');
            
            let hasResults = false;

            tableRows.forEach(row => {
                const id = row.dataset.id.toLowerCase();
                const name = row.dataset.name.toLowerCase();
                const date = row.dataset.date;
                const type = row.dataset.type.toLowerCase();

                const searchMatch = name.includes(searchValue) || id.includes(searchValue);
                const yearMatch = yearFilterValue === 'all' || (date && new Date(date).getFullYear().toString() === yearFilterValue);
                const typeMatch = typeFilterValue === 'all' || type.toLowerCase() === typeFilterValue;

                if (searchMatch && yearMatch && typeMatch) {
                    row.style.display = 'grid';
                    hasResults = true;
                } else {
                    row.style.display = 'none';
                }
            });

            const noResultsDiv = document.querySelector('.no-results');
            if (noResultsDiv) {
                if (hasResults) {
                    noResultsDiv.style.display = 'none';
                } else {
                    noResultsDiv.style.display = 'block';
                }
            }
        }
    </script>
</body>
</html>