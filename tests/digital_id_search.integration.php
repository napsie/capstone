<?php

require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/digital_id_search.php';

$record = $conn->query("SELECT id_number, full_name FROM applications WHERE application_type = 'senior' AND workflow_state IN ('Verified', 'Approved', 'Released') AND COALESCE(is_archived, 0) = 0 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$record) {
    echo "Digital ID search integration test skipped: no eligible record.\n";
    exit(0);
}

foreach ([(string)$record['full_name'], str_replace('-', '', (string)$record['id_number'])] as $searchText) {
    $filter = buildDigitalIdSearch($searchText);
    $statement = $conn->prepare("SELECT COUNT(*) FROM applications WHERE id_number = ? AND {$filter['sql']}");
    $statement->execute(array_merge([(string)$record['id_number']], $filter['params']));
    if ((int)$statement->fetchColumn() !== 1) throw new RuntimeException("Search failed for {$searchText}.");
}

echo "Digital ID search integration test passed.\n";
