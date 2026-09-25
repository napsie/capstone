<?php
require_once __DIR__ . '/../includes/record_import.php';

function expectImportValue($actual, $expected, string $message): void {
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

expectImportValue(normalizeImportHeader('firstName'), 'first_name', 'camel-case form header');
expectImportValue(normalizeImportHeader('Senior Citizen ID'), 'senior_id_no', 'Senior ID header alias');
expectImportValue(
    buildImportFullName(['first_name' => 'JUAN', 'middle_name' => 'SANTOS', 'last_name' => 'DELA CRUZ']),
    'Juan Santos Dela Cruz',
    'name assembled from form fields'
);
expectImportValue(
    buildImportAddress(['house_no' => '123', 'street' => 'Example Street', 'barangay' => 'Bagong Ilog', 'city' => 'Pasig City']),
    '123 Example Street, Bagong Ilog, Pasig City, Metro Manila',
    'address assembled from form fields'
);
expectImportValue(buildImportAddress([]), '', 'missing address is not filled with defaults');
expectImportValue(normalizeImportDate('02/29/2024'), '2024-02-29', 'valid leap-day date');
expectImportValue(normalizeImportDate('02/30/2024'), null, 'impossible date rejected');

echo "PASS: record import helpers\n";
