<?php
function normalizeWhitespace(?string $value): string {
    return trim(preg_replace('/\s+/u', ' ', (string)$value) ?? (string)$value);
}

function normalizePersonName(?string $value): string {
    $value = normalizeWhitespace($value);
    return $value === '' ? '' : mb_convert_case(mb_strtolower($value, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
}

function isValidPersonName(?string $value): bool {
    $value = normalizeWhitespace($value);
    return $value !== ''
        && preg_match("/^[\\p{L}\\p{M}]+(?:[ .'-][\\p{L}\\p{M}]+)*$/u", $value) === 1;
}

function normalizePhoneNumber(?string $value): string {
    $digits = preg_replace('/\D+/', '', (string)$value) ?? '';
    if (str_starts_with($digits, '63') && strlen($digits) === 12) $digits = '0' . substr($digits, 2);
    if (strlen($digits) === 10 && str_starts_with($digits, '9')) $digits = '0' . $digits;
    return $digits;
}

function isValidPhilippineMobileNumber(?string $value, bool $allowEmpty = false): bool {
    $phone = normalizePhoneNumber($value);
    return ($allowEmpty && $phone === '') || preg_match('/^09\d{9}$/', $phone) === 1;
}

function normalizeSeniorId(?string $value): string {
    $value = strtoupper(normalizeWhitespace($value));
    return trim(preg_replace('/[^A-Z0-9-]+/', '-', $value) ?? $value, '-');
}

function normalizeBarangayName(?string $value, array $barangays): string {
    $needle = mb_strtolower(normalizeWhitespace($value), 'UTF-8');
    foreach ($barangays as $barangay) if (mb_strtolower($barangay, 'UTF-8') === $needle) return $barangay;
    return normalizeWhitespace($value);
}

function normalizeApplicationInput(array $input, array $barangays = []): array {
    $nameKeys = ['full_name','fullName','firstName','middleName','lastName','mothersMaidenName','emergencyContactName','proxyName','claimantName','applicantName'];
    $phoneKeys = ['contact_number','contactNumber','emergencyContact','proxyContactNumber','claimantContact'];
    $addressKeys = ['complete_address','completeAddress','houseNo','street','city','province','landmark'];
    foreach ($nameKeys as $key) if (array_key_exists($key, $input)) $input[$key] = normalizePersonName($input[$key]);
    foreach ($phoneKeys as $key) if (array_key_exists($key, $input)) $input[$key] = normalizePhoneNumber($input[$key]);
    foreach ($addressKeys as $key) if (array_key_exists($key, $input)) $input[$key] = normalizeWhitespace($input[$key]);
    foreach (['senior_id_no','seniorCitizenId'] as $key) if (array_key_exists($key, $input)) $input[$key] = normalizeSeniorId($input[$key]);
    if ($barangays && array_key_exists('barangay', $input)) $input['barangay'] = normalizeBarangayName($input['barangay'], $barangays);
    return $input;
}
