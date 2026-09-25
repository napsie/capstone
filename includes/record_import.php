<?php
require_once __DIR__ . '/data_normalizer.php';

function normalizeImportHeader(string $value): string {
    $value = strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value));
    $header = trim(preg_replace('/[^a-z0-9]+/', '_', $value), '_');
    $aliases = [
        'applicationtype' => 'application_type', 'requestedbenefit' => 'requested_benefit',
        'benefit_applied' => 'requested_benefit', 'approved_benefit' => 'requested_benefit',
        'senioridno' => 'senior_id_no', 'senior_id' => 'senior_id_no', 'senior_citizen_id' => 'senior_id_no', 'seniorcitizenid' => 'senior_id_no',
        'fullname' => 'full_name', 'firstname' => 'first_name', 'middlename' => 'middle_name', 'lastname' => 'last_name',
        'birthdate' => 'birth_date', 'contactnumber' => 'contact_number', 'emailaddress' => 'email_address',
        'contact_number_fictional_placeholder' => 'contact_number',
        'completeaddress' => 'complete_address', 'houseno' => 'house_no',
        'house_unit_no' => 'house_no', 'street_subdivision' => 'street',
        'placeofbirth' => 'place_of_birth', 'civilstatus' => 'civil_status', 'mothersmaidenname' => 'mothers_maiden_name',
        'mother_s_maiden_name' => 'mothers_maiden_name', 'sex' => 'gender',
        'zipcode' => 'zip_code', 'healthstatus' => 'health_status', 'healthcondition' => 'health_condition',
        'nearest_landmark' => 'landmark', 'senior_s_email_address' => 'email_address',
        'id_application_purpose' => 'id_purpose', 'frail_sick_or_pwd_details' => 'health_condition',
        'emergencycontactname' => 'emergency_contact_name', 'emergencycontact' => 'emergency_contact',
        'emergency_contact_number_fictional_placeholder' => 'emergency_contact', 'relationship' => 'emergency_contact_relationship',
        'emergencycontactrelationship' => 'emergency_contact_relationship', 'idpurpose' => 'id_purpose',
        'datesubmitted' => 'date_submitted', 'additionalnotes' => 'additional_notes',
    ];
    return $aliases[$header] ?? $header;
}

function buildImportFullName(array $record): string {
    $fullName = normalizePersonName($record['full_name'] ?? '');
    if ($fullName !== '') return $fullName;
    return normalizePersonName(implode(' ', array_filter([
        $record['first_name'] ?? '', $record['middle_name'] ?? '',
        $record['last_name'] ?? '', $record['suffix'] ?? '',
    ], static fn($value) => trim((string)$value) !== '')));
}

function buildImportAddress(array $record): string {
    $address = normalizeWhitespace($record['complete_address'] ?? '');
    if ($address !== '') return $address;
    $hasAddressInformation = array_filter([
        $record['house_no'] ?? '', $record['street'] ?? '', $record['barangay'] ?? '',
        $record['city'] ?? '', $record['province'] ?? '',
    ], static fn($value) => trim((string)$value) !== '');
    if (!$hasAddressInformation) return '';
    $city = trim((string)($record['city'] ?? '')) ?: 'Pasig City';
    $province = trim((string)($record['province'] ?? '')) ?: 'Metro Manila';
    return normalizeWhitespace(implode(', ', array_filter([
        trim(implode(' ', array_filter([$record['house_no'] ?? '', $record['street'] ?? ''], static fn($value) => trim((string)$value) !== ''))),
        $record['barangay'] ?? '', $city, $province,
    ], static fn($value) => trim((string)$value) !== '')));
}

function isValidImportContactNumber(?string $value): bool {
    $value = trim((string)$value);
    if ($value === '') return true;
    if (preg_match('/^[0-9+().\-\s]+$/', $value) !== 1) return false;
    $digits = normalizePhoneNumber($value);
    return preg_match('/^[0-9]{7,15}$/', $digits) === 1;
}

function parseCsvRecords(string $path): array {
    $handle = fopen($path, 'rb');
    if (!$handle) throw new RuntimeException('Unable to read the CSV file.');
    $first = fgets($handle);
    if ($first === false) { fclose($handle); return []; }
    $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
    rewind($handle);
    $rows = [];
    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) $rows[] = $row;
    fclose($handle);
    return $rows;
}

function spreadsheetMainChildren(SimpleXMLElement $element): SimpleXMLElement {
    $namespaces = $element->getNamespaces(true);
    $mainNamespace = $namespaces[''] ?? $namespaces['x'] ?? null;
    if ($mainNamespace === null && $namespaces) $mainNamespace = reset($namespaces);
    return $mainNamespace ? $element->children($mainNamespace) : $element;
}

function excelCellText(SimpleXMLElement $cell, array $sharedStrings): string {
    $type = (string)$cell->attributes()['t'];
    $cellChildren = spreadsheetMainChildren($cell);
    if ($type === 'inlineStr') {
        $inline = spreadsheetMainChildren($cellChildren->is);
        $parts = [];
        if (isset($inline->t)) $parts[] = (string)$inline->t;
        foreach ($inline->r as $run) {
            $runChildren = spreadsheetMainChildren($run);
            if (isset($runChildren->t)) $parts[] = (string)$runChildren->t;
        }
        return trim(implode('', $parts));
    }
    $value = (string)$cellChildren->v;
    return $type === 's' ? trim((string)($sharedStrings[(int)$value] ?? '')) : trim($value);
}

function parseImportXml(string $contents): ?SimpleXMLElement {
    if (str_starts_with($contents, "\xFF\xFE")) $contents = mb_convert_encoding(substr($contents, 2), 'UTF-8', 'UTF-16LE');
    elseif (str_starts_with($contents, "\xFE\xFF")) $contents = mb_convert_encoding(substr($contents, 2), 'UTF-8', 'UTF-16BE');
    $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
    if (!preg_match('//u', $contents)) {
        $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $contents);
        if ($converted !== false) $contents = $converted;
    }
    $cleaned = preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $contents);
    if ($cleaned !== null) $contents = $cleaned;

    $previous = libxml_use_internal_errors(true);
    $xml = simplexml_load_string($contents, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE);
    if ($xml === false && class_exists('DOMDocument')) {
        $document = new DOMDocument();
        $document->recover = true;
        if ($document->loadXML($contents, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE)) {
            $recovered = simplexml_import_dom($document);
            if ($recovered !== false) $xml = $recovered;
        }
    }
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    return $xml === false ? null : $xml;
}

function xlsxWorksheetEntries(ZipArchive $zip): array {
    $entries = [];
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $name = (string)$zip->getNameIndex($index);
        if (preg_match('#^xl/worksheets/[^/]+\.xml$#i', $name)) $entries[] = $name;
    }
    natsort($entries);
    return array_values($entries);
}

function parseXlsxRecords(string $path): array {
    if (!class_exists('ZipArchive')) throw new RuntimeException('Excel import requires the PHP ZIP extension.');
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) throw new RuntimeException('Unable to open the Excel workbook.');
    $sharedStrings = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        $xml = parseImportXml($sharedXml);
        if ($xml !== null) foreach (spreadsheetMainChildren($xml)->si as $item) {
            $itemChildren = spreadsheetMainChildren($item);
            $parts = [];
            if (isset($itemChildren->t)) $parts[] = (string)$itemChildren->t;
            foreach ($itemChildren->r as $run) {
                $runChildren = spreadsheetMainChildren($run);
                if (isset($runChildren->t)) $parts[] = (string)$runChildren->t;
            }
            $sharedStrings[] = implode('', $parts);
        }
    }
    $sheet = null;
    foreach (xlsxWorksheetEntries($zip) as $worksheetEntry) {
        $sheetXml = $zip->getFromName($worksheetEntry);
        if ($sheetXml === false) continue;
        $candidate = parseImportXml($sheetXml);
        if ($candidate !== null && isset(spreadsheetMainChildren($candidate)->sheetData)) { $sheet = $candidate; break; }
    }
    $zip->close();
    if ($sheet === null) throw new RuntimeException('No readable worksheet was found. Open the file in Excel, save it as .xlsx, and try again.');
    $rows = [];
    $sheetChildren = spreadsheetMainChildren($sheet);
    $sheetData = spreadsheetMainChildren($sheetChildren->sheetData);
    foreach ($sheetData->row as $row) {
        $values = [];
        foreach (spreadsheetMainChildren($row)->c as $cell) {
            preg_match('/^[A-Z]+/', (string)$cell->attributes()['r'], $match);
            $letters = $match[0] ?? 'A';
            $index = 0;
            foreach (str_split($letters) as $letter) $index = ($index * 26) + (ord($letter) - 64);
            $values[$index - 1] = excelCellText($cell, $sharedStrings);
        }
        if ($values) { ksort($values); $rows[] = $values; }
    }
    return $rows;
}

function importRowsToAssociative(array $rows): array {
    if (!$rows) return [];
    $headers = array_map(fn($value) => normalizeImportHeader((string)$value), array_shift($rows));
    $result = [];
    foreach ($rows as $index => $values) {
        $record = [];
        foreach ($headers as $column => $header) if ($header !== '') $record[$header] = trim((string)($values[$column] ?? ''));
        if (count(array_filter($record, fn($value) => $value !== '')) > 0) {
            $record['_row'] = $index + 2;
            $result[] = $record;
        }
    }
    return $result;
}

function normalizeImportDate(string $value): ?string {
    $value = trim($value);
    if ($value === '') return null;
    if (is_numeric($value)) return gmdate('Y-m-d', ((int)$value - 25569) * 86400);
    foreach (['!Y-m-d', '!m/d/Y', '!m/d/y', '!d/m/Y'] as $format) {
        $date = DateTimeImmutable::createFromFormat($format, $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date && ($errors === false || ((int)$errors['warning_count'] === 0 && (int)$errors['error_count'] === 0))) return $date->format('Y-m-d');
    }
    return null;
}

function generateImportToken(PDO $conn): string {
    do {
        $token = 'PRX-' . strtoupper(bin2hex(random_bytes(3)));
        $stmt = $conn->prepare('SELECT 1 FROM applications WHERE id_number = ? LIMIT 1');
        $stmt->execute([$token]);
    } while ($stmt->fetchColumn());
    return $token;
}

function createImportJob(PDO $conn, string $filename, string $checksum, array $rows, array $actor): string {
    $token = sprintf('%08x-%04x-%04x-%04x-%012x', random_int(0, 0xffffffff), random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffffffffffff));
    $valid = count(array_filter($rows, static fn($row) => empty($row['errors'])));
    $duplicates = count(array_filter($rows, static fn($row) => count(array_filter($row['errors'] ?? [], static fn($error) => stripos($error, 'duplicate') !== false || stripos($error, 'exists') !== false)) > 0));
    $stmt = $conn->prepare('INSERT INTO import_jobs (job_token,original_filename,file_checksum,status,total_rows,valid_rows,error_rows,duplicate_rows,created_by,created_by_username) VALUES (?,?,?,\'ready\',?,?,?,?,?,?)');
    $stmt->execute([$token, $filename, $checksum, count($rows), $valid, count($rows) - $valid, $duplicates, $actor['id'] ?? null, $actor['username'] ?? 'Department Admin']);
    $jobId = (int)$conn->lastInsertId();
    $rowStmt = $conn->prepare('INSERT INTO import_job_rows (import_job_id,`row_number`,normalized_payload,validation_errors,duplicate_matches,status) VALUES (?,?,?,?,?,?)');
    foreach ($rows as $row) {
        $errors = $row['errors'] ?? [];
        $duplicateErrors = array_values(array_filter($errors, static fn($error) => stripos($error, 'duplicate') !== false || stripos($error, 'exists') !== false));
        $rowStmt->execute([$jobId, $row['row'], json_encode($row, JSON_UNESCAPED_UNICODE), $errors ? json_encode($errors) : null, $duplicateErrors ? json_encode($duplicateErrors) : null, $errors ? 'invalid' : 'pending']);
    }
    return $token;
}

function loadImportJobRows(PDO $conn, string $token): array {
    $stmt = $conn->prepare('SELECT r.normalized_payload FROM import_job_rows r INNER JOIN import_jobs j ON j.id=r.import_job_id WHERE j.job_token=? ORDER BY r.`row_number`');
    $stmt->execute([$token]);
    return array_values(array_filter(array_map(static fn($json) => json_decode($json, true), $stmt->fetchAll(PDO::FETCH_COLUMN))));
}
