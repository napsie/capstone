<?php
// Run only against a synthetic seniorlink_test_* database created by workflow_fixture.php.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$database = $argv[1] ?? '';
if (!preg_match('/^seniorlink_test_[a-f0-9]{12}$/D', $database)) {
    throw new RuntimeException('A synthetic test database is required.');
}
$conn = new PDO("mysql:host=localhost;dbname={$database};charset=utf8mb4", 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$conn->exec("UPDATE applications SET proxy_token = 'PRX-SYNTHETIC-A' WHERE id_number = 'VALID'");
$conn->exec("UPDATE applications SET proxy_token = 'PRX-SYNTHETIC-B' WHERE id_number = 'PRX-BENE'");
$expectDuplicate = static function (callable $write, string $label): void {
    try { $write(); } catch (PDOException $e) {
        if ((string)$e->getCode() === '23000') { echo "PASS {$label}\n"; return; }
        throw $e;
    }
    throw new RuntimeException("Duplicate accepted: {$label}");
};
$expectDuplicate(static fn() => $conn->exec("UPDATE applications SET proxy_token = 'prx-synthetic-a' WHERE id_number = 'PRX-BENE'"), 'Duplicate PRX is rejected, ignoring case');
$expectDuplicate(static fn() => $conn->exec("UPDATE applications SET senior_id_no = 'osca-test-benefits' WHERE id_number = 'VALID'"), 'Duplicate official ID is rejected, ignoring case');
$conn->exec("UPDATE applications SET proxy_token = 'PRX-SYNTHETIC-A', senior_id_no = 'OSCA-TEST-VALID' WHERE id_number = 'CHANGE-REQUEST'");
echo "PASS Linked service may reuse the permanent PRX and Senior ID\n";
