<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/barangays_list.php';
require_once '../includes/application_types.php';
require_once '../includes/record_import.php';
require_once '../includes/request_security.php';
require_once '../includes/audit_logger.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['department_admin', 'super_admin'], true)) {
    header('Location: ../index.php'); exit;
}

$errors = []; $preview = []; $notice = '';
$importToken = trim((string)($_GET['resume'] ?? $_POST['import_token'] ?? $_SESSION['record_import_token'] ?? ''));
if (isset($_GET['error_report']) && $importToken !== '') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="seniorlink_import_errors.csv"');
    $report = $conn->prepare('SELECT r.`row_number`,r.normalized_payload,r.validation_errors FROM import_job_rows r INNER JOIN import_jobs j ON j.id=r.import_job_id WHERE j.job_token=? AND r.validation_errors IS NOT NULL ORDER BY r.`row_number`');
    $report->execute([$importToken]);
    $out = fopen('php://output', 'wb'); fputcsv($out, ['row','senior_id','name','errors']);
    foreach ($report as $item) { $payload=json_decode($item['normalized_payload'],true) ?: []; $rowErrors=json_decode($item['validation_errors'],true) ?: []; fputcsv($out, [$item['row_number'],$payload['senior_id_no']??'',$payload['full_name']??'',implode('; ',$rowErrors)]); }
    fclose($out); exit;
}
if ($importToken !== '' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $preview = loadImportJobRows($conn, $importToken);
    if ($preview) $_SESSION['record_import_token'] = $importToken;
}
if (isset($_GET['template'])) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="seniorlink_record_import_template.csv"');
    echo "application_type,requested_benefit,senior_id_no,first_name,middle_name,last_name,suffix,birth_date,gender,civil_status,place_of_birth,contact_number,email_address,house_no,street,barangay,city,province,zip_code,landmark,mothers_maiden_name,health_status,health_condition,emergency_contact_name,emergency_contact,emergency_contact_relationship,id_purpose,date_submitted,additional_notes\r\n";
    echo "senior,Senior Citizen ID Registration,BGI-000664,Juan,Santos,Dela Cruz,,1950-01-31,Male,Married,Pasig City,09123456789,juan@example.com,123,Example Street,Bagong Ilog,Pasig City,Metro Manila,1600,Near City Hall,Maria Santos,Good,None,Juana Dela Cruz,09987654321,Spouse,new,2025-01-15,Imported historical information only\r\n";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireSameOriginMutation();
    if (isset($_POST['commit_import'])) {
        if ($importToken !== '') {
            $jobState = $conn->prepare('SELECT status FROM import_jobs WHERE job_token=?');
            $jobState->execute([$importToken]);
            if ($jobState->fetchColumn() === 'completed') $errors[] = 'This import job has already been completed.';
        }
        $preview = $_SESSION['record_import_preview'] ?? [];
        if (!$preview && $importToken !== '') $preview = loadImportJobRows($conn, $importToken);
        if (!$preview) $errors[] = 'The import preview expired. Upload the file again.';
        elseif ($errors) { /* Do not run an already completed job. */ }
        else {
            $inserted = 0; $skipped = 0;
            try {
                $conn->beginTransaction();
                $insert = $conn->prepare("INSERT INTO applications (
                    id_number, full_name, application_type, requested_benefit,
                    lastName, firstName, middleName, suffix, birth_date, gender, civil_status, place_of_birth,
                    contact_number, email_address, complete_address, house_no, street, barangay, city, province, zip_code, landmark,
                    mothers_maiden_name, health_status, health_condition, emergency_contact_name, emergency_contact, claimant_relationship,
                    id_purpose, date_submitted, status, workflow_state, senior_id_no, additional_notes
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'approved','Verified',?,?)");
                $history = $conn->prepare("INSERT INTO application_history (application_id, previous_state, new_state, changed_by, comments) VALUES (?, 'Historical Record', 'Verified', ?, 'Imported from a validated previous-record spreadsheet.')");
                foreach ($preview as $row) {
                    if (!empty($row['errors'])) { $skipped++; continue; }
                    if ($row['application_type'] === 'senior') {
                        $duplicate = $conn->prepare("SELECT 1 FROM applications WHERE application_type = 'senior' AND senior_id_no = ? AND senior_id_no IS NOT NULL AND senior_id_no <> ? LIMIT 1");
                        $duplicate->execute([$row['senior_id_no'], '']);
                        if ($duplicate->fetchColumn()) { $skipped++; continue; }
                    }
                    $trackingToken = generateImportToken($conn);
                    $insert->execute([
                        $trackingToken, $row['full_name'], $row['application_type'], $row['requested_benefit'] ?: null,
                        $row['last_name'] ?: null, $row['first_name'] ?: null, $row['middle_name'] ?: null, $row['suffix'] ?: null,
                        $row['birth_date'], $row['gender'] ?: null, $row['civil_status'] ?: null, $row['place_of_birth'] ?: null,
                        $row['contact_number'], $row['email_address'] ?: null, $row['complete_address'], $row['house_no'] ?: null,
                        $row['street'] ?: null, $row['barangay'], $row['city'] ?: 'Pasig City', $row['province'] ?: 'Metro Manila',
                        $row['zip_code'] ?: null, $row['landmark'] ?: null, $row['mothers_maiden_name'] ?: null,
                        $row['health_status'] ?: null, $row['health_condition'] ?: null, $row['emergency_contact_name'] ?: null,
                        $row['emergency_contact'], $row['emergency_contact_relationship'] ?: null, $row['id_purpose'] ?: null,
                        $row['date_submitted'], $row['senior_id_no'] ?: null,
                        $row['additional_notes'] ?: 'Imported historical information-only record',
                    ]);
                    $history->execute([$trackingToken, $_SESSION['username'] ?? 'Department Admin']);
                    $inserted++;
                }
                logAudit($conn, 'IMPORT_RECORDS', "Imported {$inserted} historical record(s); skipped {$skipped} row(s).");
                if ($importToken !== '') {
                    $jobUpdate = $conn->prepare("UPDATE import_jobs SET status='completed',processed_rows=?,completed_at=NOW() WHERE job_token=?");
                    $jobUpdate->execute([$inserted + $skipped, $importToken]);
                }
                $conn->commit(); unset($_SESSION['record_import_preview'], $_SESSION['record_import_token']);
                $notice = "Import complete: {$inserted} record(s) inserted; {$skipped} skipped.";
                $preview = [];
            } catch (Throwable $e) {
                if ($conn->inTransaction()) $conn->rollBack();
                error_log('Record import failed: ' . $e->getMessage()); $errors[] = 'The records could not be imported. No rows were inserted.';
            }
        }
    } else {
        unset($_SESSION['record_import_preview']);
        $file = $_FILES['records_file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) $errors[] = 'Choose a CSV or Excel file to upload.';
        elseif ($file['size'] > 5 * 1024 * 1024) $errors[] = 'The file must not exceed 5 MB.';
        else {
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            try {
                if (!in_array($extension, ['csv', 'xlsx'], true)) throw new RuntimeException('Only .csv and .xlsx files are supported.');
                $rawRows = $extension === 'csv' ? parseCsvRecords($file['tmp_name']) : parseXlsxRecords($file['tmp_name']);
                $records = importRowsToAssociative($rawRows);
                if (count($records) > 1000) throw new RuntimeException('Import a maximum of 1,000 records at a time.');
                if (!$records) throw new RuntimeException('The file contains no data rows.');
                $allowedTypes = array_keys(getApplicationTypeOptions()); $seenIds = [];
                foreach ($records as $record) {
                    $record = normalizeApplicationInput($record, $barangays_list);
                    $rowErrors = []; $type = strtolower($record['application_type'] ?? 'senior');
                    $seniorId = strtoupper(trim($record['senior_id_no'] ?? ''));
                    $fullName = buildImportFullName($record);
                    $completeAddress = buildImportAddress($record);
                    $birth = normalizeImportDate($record['birth_date'] ?? '');
                    $submitted = normalizeImportDate($record['date_submitted'] ?? '') ?: date('Y-m-d');
                    if (!in_array($type, $allowedTypes, true)) $rowErrors[] = 'Invalid application type';
                    if ($fullName === '') $rowErrors[] = 'Full name or first and last name is required';
                    if (!$birth || $birth > date('Y-m-d')) $rowErrors[] = 'Valid birth date is required';
                    if ($completeAddress === '') $rowErrors[] = 'Complete address or address components are required';
                    if (!isValidImportContactNumber($record['contact_number'] ?? '')) $rowErrors[] = 'Contact number must contain 7 to 15 digits';
                    if (!isValidImportContactNumber($record['emergency_contact'] ?? '')) $rowErrors[] = 'Emergency contact must contain 7 to 15 digits';
                    if (!in_array($record['barangay'] ?? '', $barangays_list, true)) $rowErrors[] = 'Invalid barangay';
                    if ($seniorId !== '' && !preg_match('/^[A-Z0-9][A-Z0-9 -]{2,49}$/', $seniorId)) $rowErrors[] = 'Invalid Senior ID format';
                    if ($type === 'senior' && $seniorId !== '' && isset($seenIds[$seniorId])) $rowErrors[] = 'Duplicate Senior ID in file';
                    if ($type === 'senior' && $seniorId !== '') $seenIds[$seniorId] = true;
                    if ($type === 'senior' && $seniorId !== '') {
                        $existingId = $conn->prepare("SELECT 1 FROM applications WHERE application_type = 'senior' AND senior_id_no = ? LIMIT 1");
                        $existingId->execute([$seniorId]);
                        if ($existingId->fetchColumn()) $rowErrors[] = 'Senior ID already exists in the system';
                    }
                    if (($record['email_address'] ?? '') !== '' && !filter_var($record['email_address'], FILTER_VALIDATE_EMAIL)) $rowErrors[] = 'Invalid email';
                    $preview[] = [
                        'row' => (int)$record['_row'], 'application_type' => $type,
                        'requested_benefit' => normalizeWhitespace($record['requested_benefit'] ?? ''),
                        'senior_id_no' => normalizeSeniorId($seniorId), 'full_name' => $fullName,
                        'first_name' => normalizePersonName($record['first_name'] ?? ''), 'middle_name' => normalizePersonName($record['middle_name'] ?? ''),
                        'last_name' => normalizePersonName($record['last_name'] ?? ''), 'suffix' => normalizeWhitespace($record['suffix'] ?? ''),
                        'birth_date' => $birth ?: '', 'gender' => normalizeWhitespace($record['gender'] ?? ''),
                        'civil_status' => normalizeWhitespace($record['civil_status'] ?? ''), 'place_of_birth' => normalizeWhitespace($record['place_of_birth'] ?? ''),
                        'contact_number' => normalizePhoneNumber($record['contact_number'] ?? ''),
                        'email_address' => strtolower(trim($record['email_address'] ?? '')), 'complete_address' => $completeAddress,
                        'house_no' => normalizeWhitespace($record['house_no'] ?? ''), 'street' => normalizeWhitespace($record['street'] ?? ''),
                        'barangay' => normalizeBarangayName($record['barangay'] ?? '', $barangays_list),
                        'city' => normalizeWhitespace($record['city'] ?? ''), 'province' => normalizeWhitespace($record['province'] ?? ''),
                        'zip_code' => normalizeWhitespace($record['zip_code'] ?? ''), 'landmark' => normalizeWhitespace($record['landmark'] ?? ''),
                        'mothers_maiden_name' => normalizePersonName($record['mothers_maiden_name'] ?? ''),
                        'health_status' => normalizeWhitespace($record['health_status'] ?? ''), 'health_condition' => normalizeWhitespace($record['health_condition'] ?? ''),
                        'emergency_contact_name' => normalizePersonName($record['emergency_contact_name'] ?? ''),
                        'emergency_contact' => normalizePhoneNumber($record['emergency_contact'] ?? ''),
                        'emergency_contact_relationship' => normalizeWhitespace($record['emergency_contact_relationship'] ?? ''),
                        'id_purpose' => strtolower(normalizeWhitespace($record['id_purpose'] ?? '')), 'date_submitted' => $submitted,
                        'additional_notes' => normalizeWhitespace($record['additional_notes'] ?? ''), 'errors' => $rowErrors,
                    ];
                }
                $_SESSION['record_import_preview'] = $preview;
                $importToken = createImportJob($conn, $file['name'], hash_file('sha256', $file['tmp_name']), $preview, ['id'=>$_SESSION['user_id']??null,'username'=>$_SESSION['username']??'Department Admin']);
                $_SESSION['record_import_token'] = $importToken;
            } catch (Throwable $e) { $errors[] = $e->getMessage(); }
        }
    }
}
$validCount = count(array_filter($preview, fn($row) => empty($row['errors'])));
$historyStmt = $conn->prepare('SELECT job_token,original_filename,status,total_rows,valid_rows,error_rows,duplicate_rows,processed_rows,created_at FROM import_jobs WHERE created_by=? OR created_by_username=? ORDER BY created_at DESC LIMIT 10');
$historyStmt->execute([$_SESSION['user_id'] ?? 0, $_SESSION['username'] ?? '']);
$importHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
ob_start(static fn(string $html): string => str_replace(
    [
        'Upload historical records',
        'Select a CSV or Excel file to validate before importing.',
        '<strong>Required:</strong> application_type, full_name, birth_date, complete_address, barangay. Senior ID records also require senior_id_no.',
    ],
    [
        'Upload information-only records',
        'Select a CSV or Excel file based on the Senior Application form. Photos and document files are not required.',
        '<strong>Required:</strong> application_type, birth_date, barangay, a name (full_name or first_name and last_name), and an address (complete_address or address components). Senior ID, contact details, photos, and document files are optional.',
    ],
    $html
));
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Import Records — SENIORLINK</title><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"><link rel="stylesheet" href="../assets/css/department-sidebar.css?v=4"><link rel="stylesheet" href="../assets/css/system-sidebar.css?v=3"><link rel="stylesheet" href="../assets/css/system-header.css?v=1"><link rel="stylesheet" href="../assets/css/seniorlink-ui.css?v=20"><style>*{box-sizing:border-box}body{margin:0;background:#f4f7fb;color:#172033;font-family:Inter,"Segoe UI",Arial,sans-serif}.main-content{width:calc(100% - 220px);margin-left:220px;padding:30px;min-height:100vh}.shell{max-width:1100px;margin:auto}h1{margin:0}.page-header{display:flex;justify-content:space-between;align-items:center;gap:18px;margin-bottom:28px}.page-header-left .greeting{margin-bottom:6px;color:#6b7280;font-size:.98rem;font-weight:500}.page-header-left .greeting strong{color:#2563eb}.page-header-left h1{color:#0f2942;font-size:2rem;line-height:1.05}.page-header-left h1 span{color:#2563eb}.header-user{display:flex;align-items:center;gap:12px;padding:7px 13px;border:3px solid transparent;border-radius:30px;background:linear-gradient(#fff,#fff) padding-box,linear-gradient(135deg,#0f172a,#3498db) border-box;box-shadow:0 2px 8px rgba(0,0,0,.08)}.header-user img{width:40px;height:40px;object-fit:cover;border:2px solid #2563eb;border-radius:50%}.header-user h3,.header-user p{margin:0}.header-user h3{font-size:.9rem}.header-user p{color:#64748b;font-size:.75rem}.panel{padding:22px;border:1px solid #d9e3ee;border-radius:14px;background:#fff;box-shadow:0 8px 24px rgba(15,23,42,.06);margin-bottom:18px}.upload{display:flex;align-items:end;gap:12px;flex-wrap:wrap}.field{flex:1;min-width:260px}.field label{display:block;margin-bottom:7px;font-size:.75rem;font-weight:800;text-transform:uppercase;color:#64748b}.field input{width:100%;padding:11px;border:1px solid #cbd5e1;border-radius:8px}.btn{display:inline-flex;align-items:center;gap:7px;padding:11px 14px;border:0;border-radius:8px;background:#2563eb;color:#fff;font-weight:800;text-decoration:none;cursor:pointer}.btn.secondary{background:#e2e8f0;color:#334155}.alert{padding:12px 14px;border-radius:9px;margin-bottom:14px}.error{background:#fef2f2;color:#b91c1c}.success{background:#ecfdf5;color:#166534}.help{margin-top:14px;color:#64748b;font-size:.8rem;line-height:1.6}.table-wrap{overflow:auto}table{width:100%;min-width:900px;border-collapse:collapse}th,td{padding:10px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:.78rem}th{background:#f8fafc;color:#64748b;text-transform:uppercase}.bad{color:#b91c1c}.good{color:#15803d}.actions{display:flex;justify-content:flex-end;gap:9px;margin-top:16px}@media(max-width:800px){.main-content{width:100%;margin-left:0;padding:18px 10px}.page-header{align-items:flex-start;flex-direction:column}.header-user{align-self:stretch}}.shell{max-width:none}.panel{padding:26px 28px;border-radius:16px;box-shadow:0 10px 30px rgba(15,23,42,.065)}.panel-heading{display:flex;align-items:center;gap:13px;margin-bottom:22px}.panel-heading-icon{width:44px;height:44px;display:grid;place-items:center;flex:0 0 44px;color:#2563eb;background:#eff6ff;border-radius:12px;font-size:1.05rem}.panel-heading h2{margin:0 0 4px;color:#0f2942;font-size:1.08rem}.panel-heading p{margin:0;color:#64748b;font-size:.8rem}.upload{display:grid;grid-template-columns:minmax(280px,1fr) auto auto;align-items:end;gap:12px}.field{min-width:0}.field input[type=file]{min-height:50px;padding:6px;color:#64748b;background:#f8fafc;border:1px dashed #94a3b8;border-radius:10px;cursor:pointer}.field input[type=file]::file-selector-button{height:36px;margin-right:12px;padding:0 14px;border:0;border-radius:7px;color:#1e40af;background:#dbeafe;font:700 .78rem Inter,"Segoe UI",sans-serif;cursor:pointer}.field input[type=file]:hover{border-color:#2563eb;background:#f0f7ff}.field input[type=file]:focus{outline:3px solid rgba(37,99,235,.14);border-color:#2563eb}.btn{min-height:50px;justify-content:center;padding-inline:18px;border-radius:10px;white-space:nowrap;transition:transform .15s ease,background-color .15s ease}.btn:hover{transform:translateY(-1px);background:#1d4ed8}.btn.secondary{border:1px solid #d5deea;background:#eef2f7;color:#334155}.btn.secondary:hover{background:#e2e8f0}.help{margin-top:18px;padding:13px 15px;color:#536579;background:#f8fafc;border-left:3px solid #60a5fa;border-radius:0 9px 9px 0}.help strong{color:#334155}@media(max-width:1050px){.upload{grid-template-columns:1fr 1fr}.field{grid-column:1/-1}}@media(max-width:600px){.panel{padding:20px 16px}.upload{grid-template-columns:1fr}.field{grid-column:auto}.btn{width:100%}}</style></head><body><?php include '../partials/department_sidebar.php'; ?><main class="main-content"><header class="page-header"><div class="page-header-left"><div class="greeting" id="greetingMsg"></div><h1>Import <span>Records</span></h1></div><div class="header-user"><?php $profilePicPath="../images/profile_pictures/".($_SESSION["profile_picture"]??"default.jpg"); if(!file_exists($profilePicPath)||is_dir($profilePicPath))$profilePicPath="../images/profile_pictures/default.jpg"; ?><img src="<?= htmlspecialchars($profilePicPath) ?>" alt="Profile"><div class="header-user-info"><h3><?= htmlspecialchars(trim(($_SESSION["first_name"]??"")." ".($_SESSION["last_name"]??""))) ?></h3><p><?= htmlspecialchars(ucwords(str_replace("_"," ",$_SESSION["role"]??"department_admin"))) ?> · Pasig City</p></div></div></header><div class="shell"><?php foreach($errors as $error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endforeach; ?><?php if($notice): ?><div class="alert success"><?= htmlspecialchars($notice) ?></div><?php endif; ?><section class="panel"><div class="panel-heading"><span class="panel-heading-icon"><i class="fas fa-file-arrow-up"></i></span><div><h2>Upload historical records</h2><p>Select a CSV or Excel file to validate before importing.</p></div></div><form method="post" enctype="multipart/form-data" class="upload"><div class="field"><label for="recordsFile">CSV or Excel file</label><input id="recordsFile" name="records_file" type="file" accept=".csv,.xlsx" required></div><button class="btn" type="submit"><i class="fas fa-magnifying-glass"></i> Validate and Preview</button><a class="btn secondary" href="?template=1"><i class="fas fa-download"></i> Download CSV Template</a></form><div class="help"><strong>Required:</strong> application_type, full_name, birth_date, complete_address, barangay. Senior ID records also require senior_id_no. Dates may use YYYY-MM-DD or MM/DD/YYYY. Maximum: 1,000 rows or 5 MB.</div></section><?php if($preview): ?><section class="panel"><h2>Validation Preview</h2><p><strong><?= $validCount ?></strong> valid of <strong><?= count($preview) ?></strong> rows. Rows with errors will not be imported.</p><div class="table-wrap"><table><thead><tr><th>Row</th><th>Type</th><th>Senior ID</th><th>Name</th><th>Birth Date</th><th>Barangay</th><th>Validation</th></tr></thead><tbody><?php foreach($preview as $row): ?><tr><td><?= $row['row'] ?></td><td><?= htmlspecialchars($row['application_type']) ?></td><td><?= htmlspecialchars($row['senior_id_no']) ?></td><td><?= htmlspecialchars($row['full_name']) ?></td><td><?= htmlspecialchars($row['birth_date']) ?></td><td><?= htmlspecialchars($row['barangay']) ?></td><td class="<?= $row['errors'] ? 'bad':'good' ?>"><?= $row['errors'] ? htmlspecialchars(implode('; ',$row['errors'])):'Ready' ?></td></tr><?php endforeach; ?></tbody></table></div><form method="post" class="actions"><a class="btn secondary" href="import_records.php">Cancel</a><button class="btn" type="submit" name="commit_import" value="1" <?= $validCount===0?'disabled':'' ?>><i class="fas fa-database"></i> Import <?= $validCount ?> Valid Record(s)</button></form></section><?php endif; ?></div></main><script>const h=new Date().getHours(),g=h<12?"Good morning":h<18?"Good afternoon":"Good evening";document.getElementById("greetingMsg").innerHTML=g+", <strong><?= htmlspecialchars(trim(($_SESSION["first_name"]??"")." ".($_SESSION["last_name"]??"")),ENT_QUOTES) ?></strong>!";</script><script src="../assets/js/sidebar-toggle.js?v=3"></script></body></html>
