<?php
// Exercise the production INSERT column layout against the root-token rule.
$source = file_get_contents(__DIR__ . '/../includes/process_proxy_registration.php');
if (!preg_match('/\$sql = "INSERT INTO applications \((.*?)\) VALUES \(" \. implode/s', $source, $match)) throw new RuntimeException('INSERT not found');
$columns = array_map('trim', explode(',', $match[1]));
if (count($columns) !== 85 || !in_array('parent_senior_id', $columns, true)) throw new RuntimeException('Missing initial parent link');
$conn = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$definitions = array_map(static fn($column) => $column . ' TEXT', $columns);
$conn->exec('CREATE TABLE applications (' . implode(',', $definitions) . ", root_proxy_identity TEXT GENERATED ALWAYS AS (CASE WHEN parent_senior_id IS NULL THEN proxy_token ELSE NULL END) STORED UNIQUE)");
$insert = $conn->prepare('INSERT INTO applications (' . implode(',', $columns) . ') VALUES (' . implode(',', array_fill(0,count($columns),'?')) . ')');
$base = array_fill_keys($columns, null);
$root = array_replace($base, ['id_number'=>'ROOT','proxy_token'=>'PRX-TEST','senior_id_no'=>'00001']);
$insert->execute(array_values($root));
foreach (['senior','landbank','pension','burial'] as $type) {
    $row = array_replace($root,['id_number'=>'APP-'.$type,'application_type'=>$type,'parent_senior_id'=>'ROOT']);
    $insert->execute(array_values($row));
}
try { $insert->execute(array_values(array_replace($root,['id_number'=>'DUPLICATE']))); throw new RuntimeException('Duplicate root was allowed'); }
catch (PDOException $expected) { if ($expected->getCode() !== '23000') throw $expected; }
echo "Linked request inserts passed for senior updates and 3 benefits; duplicate root remains blocked.\n";
