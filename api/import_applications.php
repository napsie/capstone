<?php
session_start();
require_once '../includes/db_connect.php';

header('Content-Type: application/json');

// Auth check — only barangay_staff can import
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'barangay_staff') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Only barangay staff can import applications.']);
    exit;
}

$imported_count = 0;
$errors         = [];
$skipped_count  = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    // Check for PHP-level upload errors
    $uploadError = $_FILES['csv_file']['error'];
    if ($uploadError !== UPLOAD_ERR_OK) {
        $uploadMessages = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload_max_filesize limit.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds form MAX_FILE_SIZE limit.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the upload.',
        ];
        $msg = $uploadMessages[$uploadError] ?? 'Unknown upload error.';
        echo json_encode(['success' => false, 'message' => $msg]);
        exit;
    }

    // Validate CSV MIME type
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($_FILES['csv_file']['tmp_name']);
    $allowedMimes = ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'];
    if (!in_array($mimeType, $allowedMimes)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Please upload a .csv file.']);
        exit;
    }

    $file   = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, 'r');

    if ($handle === false) {
        echo json_encode(['success' => false, 'message' => 'Failed to open CSV file.']);
        exit;
    }

    // Get the header row (strip BOM if present)
    $rawHeader = fgetcsv($handle);
    if ($rawHeader === false) {
        echo json_encode(['success' => false, 'message' => 'CSV file is empty or unreadable.']);
        fclose($handle);
        exit;
    }
    // Strip UTF-8 BOM from first header if present
    $rawHeader[0] = ltrim($rawHeader[0], "\xEF\xBB\xBF");
    $header = array_map('trim', $rawHeader);

    // CSV header → DB column map
    // Mark critical (NOT NULL in DB) columns
    $csv_to_db_map = [
        'Full Name(LN, FN MN.)'         => ['db_column' => 'full_name',                 'critical' => true],
        'Application Type'               => ['db_column' => 'application_type',           'critical' => true],
        'Birthdate'                      => ['db_column' => 'birth_date',                 'critical' => true],
        'Contact Number'                 => ['db_column' => 'contact_number',             'critical' => true],
        'Complete Address'               => ['db_column' => 'complete_address',           'critical' => true],
        'Emergency Contact Number'       => ['db_column' => 'emergency_contact',          'critical' => true],
        'Email Address'                  => ['db_column' => 'email_address',              'critical' => false],
        'Emergency Contact Person'       => ['db_column' => 'emergency_contact_name',     'critical' => false],
        'Medical Conditions (Optional)'  => ['db_column' => 'medical_conditions',         'critical' => false],
        'Birth Certificate'              => ['db_column' => 'birth_certificate_type',     'critical' => false],
        'Medical Certificate'            => ['db_column' => 'medical_certificate_type',   'critical' => false],
        'Client ID'                      => ['db_column' => 'client_identification_type', 'critical' => false],
        'Proof of Address'               => ['db_column' => 'proof_of_address_type',      'critical' => false],
        'Updated ID Image(1x1)'          => ['db_column' => 'id_image_type',             'critical' => false],
        'Additional Notes'               => ['db_column' => 'additional_notes',           'critical' => false],
    ];

    // Map CSV column headers to their index positions
    $column_indexes          = [];
    $missing_critical_headers = [];
    foreach ($csv_to_db_map as $csv_header => $map_details) {
        $index = array_search($csv_header, $header);
        if ($index !== false) {
            $column_indexes[$map_details['db_column']] = $index;
        } else {
            $column_indexes[$map_details['db_column']] = null;
            if ($map_details['critical']) {
                $missing_critical_headers[] = $csv_header;
            }
        }
    }

    if (!empty($missing_critical_headers)) {
        fclose($handle);
        echo json_encode([
            'success' => false,
            'message' => 'Missing required CSV columns: ' . implode(', ', $missing_critical_headers)
        ]);
        exit;
    }

    // Build INSERT SQL dynamically
    $db_columns_in_order = [];
    $placeholders        = [];
    foreach ($csv_to_db_map as $map_details) {
        $db_columns_in_order[] = $map_details['db_column'];
        $placeholders[]        = '?';
    }
    // Add auto-generated id_number + fixed metadata from session
    $db_columns_in_order[] = 'id_number';
    $placeholders[]        = '?';
    $db_columns_in_order[] = 'status';
    $placeholders[]        = '?';
    $db_columns_in_order[] = 'date_submitted';
    $placeholders[]        = '?';
    $db_columns_in_order[] = 'barangay';
    $placeholders[]        = '?';
    $db_columns_in_order[] = 'workflow_state';
    $placeholders[]        = '?';
    $db_columns_in_order[] = 'priority_level';
    $placeholders[]        = '?';

    $sql  = "INSERT INTO applications (" . implode(', ', $db_columns_in_order) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $stmt = $conn->prepare($sql);

    // Prepare history insert
    $stmtHist = $conn->prepare("INSERT INTO application_history (application_id, previous_state, new_state, changed_by, comments) VALUES (?, ?, ?, ?, ?)");
    $operator = $_SESSION['username'] ?? 'barangay_staff';

    $row_number = 1;
    while (($data = fgetcsv($handle)) !== false) {
        $row_number++;
        $execute_params = [];
        $skip_row       = false;

        foreach ($csv_to_db_map as $csv_header => $map_details) {
            $db_column = $map_details['db_column'];
            $critical  = $map_details['critical'];
            $index     = $column_indexes[$db_column];

            $value = ($index !== null && isset($data[$index])) ? trim($data[$index]) : null;

            if ($critical && ($value === null || $value === '')) {
                $errors[]  = "Row {$row_number}: Required field '{$csv_header}' is empty. Row skipped.";
                $skip_row  = true;
                $skipped_count++;
                break;
            }

            // Normalise application_type to lowercase accepted values
            if ($db_column === 'application_type' && $value !== null) {
                $typeMap = [
                    'senior'  => 'senior',
                    'senior citizen' => 'senior',
                    'f1' => 'senior',
                    'pension' => 'pension',
                    'local social pension' => 'pension',
                    'national pension' => 'national_pension',
                    'national_pension' => 'national_pension',
                    'milestone' => 'milestone_gift',
                    'milestone_gift' => 'milestone_gift',
                    'octogenarian' => 'milestone_gift',
                    'f5' => 'milestone_gift',
                    'burial'  => 'burial',
                    'burial assistance' => 'burial',
                    'f7' => 'burial',
                    'landbank' => 'landbank',
                    'f2' => 'landbank',
                    'home visit' => 'home_visit',
                    'home_visit' => 'home_visit',
                    'f8' => 'home_visit',
                ];
                $normalised = strtolower(trim($value));
                $value = $typeMap[$normalised] ?? $normalised;

                if ($value === 'pwd') {
                    $errors[]  = "Row {$row_number}: PWD applications are no longer supported. Row skipped.";
                    $skip_row  = true;
                    $skipped_count++;
                    break;
                }
            }

            $execute_params[] = ($value === null || $value === '') ? null : $value;
        }

        if ($skip_row) continue;

        // Generate unique id_number for this import row
        $generatedId = 'IMP-' . strtoupper(bin2hex(random_bytes(5)));

        // Add fixed/generated values
        $execute_params[] = $generatedId;
        $execute_params[] = 'pending';
        $execute_params[] = date('Y-m-d H:i:s');
        $execute_params[] = $_SESSION['barangay'];
        $execute_params[] = 'Received';
        $execute_params[] = 'normal';

        try {
            $conn->beginTransaction();
            $stmt->execute($execute_params);
            $stmtHist->execute([$generatedId, 'None', 'Received', $operator, 'Imported via CSV batch upload.']);
            $conn->commit();
            $imported_count++;
        } catch (PDOException $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            $errors[] = "Row {$row_number}: Database error — " . $e->getMessage();
        }
    }

    fclose($handle);

    $response = [
        'success'        => true,
        'imported_count' => $imported_count,
        'skipped_count'  => $skipped_count,
        'errors'         => $errors,
        'message'        => "Import complete. {$imported_count} record(s) imported, {$skipped_count} skipped."
    ];
    if ($imported_count === 0 && empty($errors)) {
        $response['message'] = 'No records imported. The CSV may be empty or all rows were skipped.';
    }

    // Store message in session for redirect flow (keep backward compat)
    $htmlMessage = "<p class='success'>Successfully imported {$imported_count} applications.</p>";
    if (!empty($errors)) {
        $htmlMessage .= "<p class='error'>Errors:</p><ul>";
        foreach ($errors as $err) {
            $htmlMessage .= '<li>' . htmlspecialchars($err) . '</li>';
        }
        $htmlMessage .= '</ul>';
    }
    $_SESSION['import_message'] = $htmlMessage;

    echo json_encode($response);
    exit;
}

// Fallback — redirect if accessed without POST
$_SESSION['import_message'] = "<p class='error'>No file was uploaded.</p>";
header('Location: ../pages/submit_application.php');
exit;
?>