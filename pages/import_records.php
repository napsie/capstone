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
if (isset($_GET['template'])) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="seniorlink_record_import_template.csv"');
    echo "application_type,senior_id_no,full_name,birth_date,contact_number,complete_address,barangay,email_address,date_submitted\r\n";
    echo "senior,BGI-000664,Juan Dela Cruz,1950-01-31,09123456789,123 Example Street,Bagong Ilog,juan@example.com,2025-01-15\r\n";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireSameOriginMutation();
    if (isset($_POST['commit_import'])) {
        $preview = $_SESSION['record_import_preview'] ?? [];
        if (!$preview) $errors[] = 'The import preview expired. Upload the file again.';
        else {
            $inserted = 0; $skipped = 0;
            try {
                $conn->beginTransaction();
                $insert = $conn->prepare("INSERT INTO applications (id_number, full_name, application_type, birth_date, contact_number, complete_address, emergency_contact, barangay, date_submitted, status, workflow_state, senior_id_no, email_address, additional_notes) VALUES (?, ?, ?, ?, ?, ?, '', ?, ?, 'approved', 'Verified', ?, ?, ?)");
                $history = $conn->prepare("INSERT INTO application_history (application_id, previous_state, new_state, changed_by, comments) VALUES (?, 'Historical Record', 'Verified', ?, 'Imported from a validated previous-record spreadsheet.')");
                foreach ($preview as $row) {
                    if (!empty($row['errors'])) { $skipped++; continue; }
                    if ($row['application_type'] === 'senior') {
                        $duplicate = $conn->prepare("SELECT 1 FROM applications WHERE application_type = 'senior' AND senior_id_no = ? AND senior_id_no IS NOT NULL AND senior_id_no <> ? LIMIT 1");
                        $duplicate->execute([$row['senior_id_no'], '']);
                        if ($duplicate->fetchColumn()) { $skipped++; continue; }
                    }
                    $trackingToken = generateImportToken($conn);
                    $insert->execute([$trackingToken, $row['full_name'], $row['application_type'], $row['birth_date'], $row['contact_number'], $row['complete_address'], $row['barangay'], $row['date_submitted'], $row['senior_id_no'] ?: null, $row['email_address'] ?: null, 'Imported historical record']);
                    $history->execute([$trackingToken, $_SESSION['username'] ?? 'Department Admin']);
                    $inserted++;
                }
                logAudit($conn, 'IMPORT_RECORDS', "Imported {$inserted} historical record(s); skipped {$skipped} row(s).");
                $conn->commit(); unset($_SESSION['record_import_preview']);
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
                    $rowErrors = []; $type = strtolower($record['application_type'] ?? 'senior');
                    $seniorId = strtoupper(trim($record['senior_id_no'] ?? ''));
                    $birth = normalizeImportDate($record['birth_date'] ?? '');
                    $submitted = normalizeImportDate($record['date_submitted'] ?? '') ?: date('Y-m-d');
                    if (!in_array($type, $allowedTypes, true)) $rowErrors[] = 'Invalid application type';
                    if (trim($record['full_name'] ?? '') === '') $rowErrors[] = 'Full name is required';
                    if (!$birth || $birth > date('Y-m-d')) $rowErrors[] = 'Valid birth date is required';
                    if (trim($record['complete_address'] ?? '') === '') $rowErrors[] = 'Address is required';
                    if (!in_array($record['barangay'] ?? '', $barangays_list, true)) $rowErrors[] = 'Invalid barangay';
                    if ($type === 'senior' && ($seniorId === '' || !preg_match('/^[A-Z0-9][A-Z0-9 -]{2,49}$/', $seniorId))) $rowErrors[] = 'Official Senior ID is required';
                    if ($type === 'senior' && $seniorId !== '' && isset($seenIds[$seniorId])) $rowErrors[] = 'Duplicate Senior ID in file';
                    if ($type === 'senior' && $seniorId !== '') $seenIds[$seniorId] = true;
                    if ($type === 'senior' && $seniorId !== '') {
                        $existingId = $conn->prepare("SELECT 1 FROM applications WHERE application_type = 'senior' AND senior_id_no = ? LIMIT 1");
                        $existingId->execute([$seniorId]);
                        if ($existingId->fetchColumn()) $rowErrors[] = 'Senior ID already exists in the system';
                    }
                    if (($record['email_address'] ?? '') !== '' && !filter_var($record['email_address'], FILTER_VALIDATE_EMAIL)) $rowErrors[] = 'Invalid email';
                    $preview[] = ['row'=>(int)$record['_row'],'application_type'=>$type,'senior_id_no'=>$seniorId,'full_name'=>trim($record['full_name'] ?? ''),'birth_date'=>$birth ?: '','contact_number'=>trim($record['contact_number'] ?? ''),'complete_address'=>trim($record['complete_address'] ?? ''),'barangay'=>trim($record['barangay'] ?? ''),'email_address'=>trim($record['email_address'] ?? ''),'date_submitted'=>$submitted,'errors'=>$rowErrors];
                }
                $_SESSION['record_import_preview'] = $preview;
            } catch (Throwable $e) { $errors[] = $e->getMessage(); }
        }
    }
}
$validCount = count(array_filter($preview, fn($row) => empty($row['errors'])));
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Import Records — SENIORLINK</title><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"><link rel="stylesheet" href="../assets/css/department-sidebar.css?v=4"><link rel="stylesheet" href="../assets/css/system-sidebar.css?v=3"><link rel="stylesheet" href="../assets/css/system-header.css?v=1"><style>*{box-sizing:border-box}body{margin:0;background:#f4f7fb;color:#172033;font-family:Inter,"Segoe UI",Arial,sans-serif}.main-content{width:calc(100% - 220px);margin-left:220px;padding:30px;min-height:100vh}.shell{max-width:1100px;margin:auto}h1{margin:0}.page-header{display:flex;justify-content:space-between;align-items:center;gap:18px;margin-bottom:28px}.page-header-left .greeting{margin-bottom:6px;color:#6b7280;font-size:.98rem;font-weight:500}.page-header-left .greeting strong{color:#2563eb}.page-header-left h1{color:#0f2942;font-size:2rem;line-height:1.05}.page-header-left h1 span{color:#2563eb}.header-user{display:flex;align-items:center;gap:12px;padding:7px 13px;border:3px solid transparent;border-radius:30px;background:linear-gradient(#fff,#fff) padding-box,linear-gradient(135deg,#0f172a,#3498db) border-box;box-shadow:0 2px 8px rgba(0,0,0,.08)}.header-user img{width:40px;height:40px;object-fit:cover;border:2px solid #2563eb;border-radius:50%}.header-user h3,.header-user p{margin:0}.header-user h3{font-size:.9rem}.header-user p{color:#64748b;font-size:.75rem}.panel{padding:22px;border:1px solid #d9e3ee;border-radius:14px;background:#fff;box-shadow:0 8px 24px rgba(15,23,42,.06);margin-bottom:18px}.upload{display:flex;align-items:end;gap:12px;flex-wrap:wrap}.field{flex:1;min-width:260px}.field label{display:block;margin-bottom:7px;font-size:.75rem;font-weight:800;text-transform:uppercase;color:#64748b}.field input{width:100%;padding:11px;border:1px solid #cbd5e1;border-radius:8px}.btn{display:inline-flex;align-items:center;gap:7px;padding:11px 14px;border:0;border-radius:8px;background:#2563eb;color:#fff;font-weight:800;text-decoration:none;cursor:pointer}.btn.secondary{background:#e2e8f0;color:#334155}.alert{padding:12px 14px;border-radius:9px;margin-bottom:14px}.error{background:#fef2f2;color:#b91c1c}.success{background:#ecfdf5;color:#166534}.help{margin-top:14px;color:#64748b;font-size:.8rem;line-height:1.6}.table-wrap{overflow:auto}table{width:100%;min-width:900px;border-collapse:collapse}th,td{padding:10px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:.78rem}th{background:#f8fafc;color:#64748b;text-transform:uppercase}.bad{color:#b91c1c}.good{color:#15803d}.actions{display:flex;justify-content:flex-end;gap:9px;margin-top:16px}@media(max-width:800px){.main-content{width:100%;margin-left:0;padding:18px 10px}.page-header{align-items:flex-start;flex-direction:column}.header-user{align-self:stretch}}.shell{max-width:none}.panel{padding:26px 28px;border-radius:16px;box-shadow:0 10px 30px rgba(15,23,42,.065)}.panel-heading{display:flex;align-items:center;gap:13px;margin-bottom:22px}.panel-heading-icon{width:44px;height:44px;display:grid;place-items:center;flex:0 0 44px;color:#2563eb;background:#eff6ff;border-radius:12px;font-size:1.05rem}.panel-heading h2{margin:0 0 4px;color:#0f2942;font-size:1.08rem}.panel-heading p{margin:0;color:#64748b;font-size:.8rem}.upload{display:grid;grid-template-columns:minmax(280px,1fr) auto auto;align-items:end;gap:12px}.field{min-width:0}.field input[type=file]{min-height:50px;padding:6px;color:#64748b;background:#f8fafc;border:1px dashed #94a3b8;border-radius:10px;cursor:pointer}.field input[type=file]::file-selector-button{height:36px;margin-right:12px;padding:0 14px;border:0;border-radius:7px;color:#1e40af;background:#dbeafe;font:700 .78rem Inter,"Segoe UI",sans-serif;cursor:pointer}.field input[type=file]:hover{border-color:#2563eb;background:#f0f7ff}.field input[type=file]:focus{outline:3px solid rgba(37,99,235,.14);border-color:#2563eb}.btn{min-height:50px;justify-content:center;padding-inline:18px;border-radius:10px;white-space:nowrap;transition:transform .15s ease,background-color .15s ease}.btn:hover{transform:translateY(-1px);background:#1d4ed8}.btn.secondary{border:1px solid #d5deea;background:#eef2f7;color:#334155}.btn.secondary:hover{background:#e2e8f0}.help{margin-top:18px;padding:13px 15px;color:#536579;background:#f8fafc;border-left:3px solid #60a5fa;border-radius:0 9px 9px 0}.help strong{color:#334155}@media(max-width:1050px){.upload{grid-template-columns:1fr 1fr}.field{grid-column:1/-1}}@media(max-width:600px){.panel{padding:20px 16px}.upload{grid-template-columns:1fr}.field{grid-column:auto}.btn{width:100%}}</style></head><body><?php include '../partials/department_sidebar.php'; ?><main class="main-content"><header class="page-header"><div class="page-header-left"><div class="greeting" id="greetingMsg"></div><h1>Import <span>Records</span></h1></div><div class="header-user"><?php $profilePicPath="../images/profile_pictures/".($_SESSION["profile_picture"]??"default.jpg"); if(!file_exists($profilePicPath)||is_dir($profilePicPath))$profilePicPath="../images/profile_pictures/default.jpg"; ?><img src="<?= htmlspecialchars($profilePicPath) ?>" alt="Profile"><div class="header-user-info"><h3><?= htmlspecialchars(trim(($_SESSION["first_name"]??"")." ".($_SESSION["last_name"]??""))) ?></h3><p><?= htmlspecialchars(ucwords(str_replace("_"," ",$_SESSION["role"]??"department_admin"))) ?> · Pasig City</p></div></div></header><div class="shell"><?php foreach($errors as $error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endforeach; ?><?php if($notice): ?><div class="alert success"><?= htmlspecialchars($notice) ?></div><?php endif; ?><section class="panel"><div class="panel-heading"><span class="panel-heading-icon"><i class="fas fa-file-arrow-up"></i></span><div><h2>Upload historical records</h2><p>Select a CSV or Excel file to validate before importing.</p></div></div><form method="post" enctype="multipart/form-data" class="upload"><div class="field"><label for="recordsFile">CSV or Excel file</label><input id="recordsFile" name="records_file" type="file" accept=".csv,.xlsx" required></div><button class="btn" type="submit"><i class="fas fa-magnifying-glass"></i> Validate and Preview</button><a class="btn secondary" href="?template=1"><i class="fas fa-download"></i> Download CSV Template</a></form><div class="help"><strong>Required:</strong> application_type, full_name, birth_date, complete_address, barangay. Senior ID records also require senior_id_no. Dates may use YYYY-MM-DD or MM/DD/YYYY. Maximum: 1,000 rows or 5 MB.</div></section><?php if($preview): ?><section class="panel"><h2>Validation Preview</h2><p><strong><?= $validCount ?></strong> valid of <strong><?= count($preview) ?></strong> rows. Rows with errors will not be imported.</p><div class="table-wrap"><table><thead><tr><th>Row</th><th>Type</th><th>Senior ID</th><th>Name</th><th>Birth Date</th><th>Barangay</th><th>Validation</th></tr></thead><tbody><?php foreach($preview as $row): ?><tr><td><?= $row['row'] ?></td><td><?= htmlspecialchars($row['application_type']) ?></td><td><?= htmlspecialchars($row['senior_id_no']) ?></td><td><?= htmlspecialchars($row['full_name']) ?></td><td><?= htmlspecialchars($row['birth_date']) ?></td><td><?= htmlspecialchars($row['barangay']) ?></td><td class="<?= $row['errors'] ? 'bad':'good' ?>"><?= $row['errors'] ? htmlspecialchars(implode('; ',$row['errors'])):'Ready' ?></td></tr><?php endforeach; ?></tbody></table></div><form method="post" class="actions"><a class="btn secondary" href="import_records.php">Cancel</a><button class="btn" type="submit" name="commit_import" value="1" <?= $validCount===0?'disabled':'' ?>><i class="fas fa-database"></i> Import <?= $validCount ?> Valid Record(s)</button></form></section><?php endif; ?></div></main><script>const h=new Date().getHours(),g=h<12?"Good morning":h<18?"Good afternoon":"Good evening";document.getElementById("greetingMsg").innerHTML=g+", <strong><?= htmlspecialchars(trim(($_SESSION["first_name"]??"")." ".($_SESSION["last_name"]??"")),ENT_QUOTES) ?></strong>!";</script><script src="../assets/js/sidebar-toggle.js?v=3"></script></body></html>
