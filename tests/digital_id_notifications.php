<?php
require_once __DIR__ . '/../includes/digital_id_notifications.php';
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE applications (id_number TEXT PRIMARY KEY, proxy_token TEXT, application_type TEXT, workflow_state TEXT, senior_id_no TEXT, is_archived INTEGER DEFAULT 0, parent_senior_id TEXT)');
$db->exec('CREATE TABLE digital_id_notifications (application_id TEXT PRIMARY KEY, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
$passed = 0;
function checkNotice(bool $ok, string $label): void { global $passed; if (!$ok) throw new RuntimeException($label); $passed++; echo "PASS {$label}\n"; }
$insert = $db->prepare('INSERT INTO applications (id_number,proxy_token,application_type,workflow_state,senior_id_no,parent_senior_id) VALUES (?,?,?,?,?,?)');
$insert->execute(['BASE','PRX-TEST','senior','For Review',null,null]);
recordDigitalIdReady($db,'BASE');
checkNotice(trackerDigitalIdNotice($db,'PRX-TEST') === null,'Pending application has no alert');
$db->exec("UPDATE applications SET senior_id_no='00099' WHERE id_number='BASE'");
recordDigitalIdReady($db,'BASE');
checkNotice(trackerDigitalIdNotice($db,'PRX-TEST') === null,'ID assigned before approval does not announce early');
$db->exec("UPDATE applications SET workflow_state='Verified' WHERE id_number='BASE'");
recordDigitalIdReady($db,'BASE');
checkNotice(trackerDigitalIdNotice($db,'PRX-TEST')['title'] === 'Your digital ID is ready','Approval after ID assignment creates alert');
$first = trackerDigitalIdNotice($db,'PRX-TEST');
recordDigitalIdReady($db,'BASE');
checkNotice((int)$db->query('SELECT COUNT(*) FROM digital_id_notifications')->fetchColumn()===1 && trackerDigitalIdNotice($db,'PRX-TEST')===$first,'Repeat updates preserve one dated history entry');
checkNotice(trackerDigitalIdNotice($db,'PRX-OTHER')===null,'Another tracker cannot read this notice');
checkNotice(trackerDigitalIdNotice($db,"' OR 1=1 --")===null,'Invalid tracking input is rejected');
foreach (['Rejected','Deceased','Needs Correction'] as $state) {
    $db->prepare('UPDATE applications SET workflow_state=?')->execute([$state]);
    checkNotice(trackerDigitalIdNotice($db,'PRX-TEST')===null,"{$state} profile does not announce an available ID");
}
$db->exec("UPDATE applications SET workflow_state='Verified',is_archived=1");
checkNotice(trackerDigitalIdNotice($db,'PRX-TEST')===null,'Archived ID alert is hidden');
$db->exec("UPDATE applications SET is_archived=0,senior_id_no='OSCA-2026-ABCDEF'");
checkNotice(trackerDigitalIdNotice($db,'PRX-TEST')===null,'Placeholder ID is not ready');
$db->exec("UPDATE applications SET senior_id_no='00099'");
$insert->execute(['SECOND','PRX-NEXT','senior','Verified',null,null]);
recordDigitalIdReady($db,'SECOND');
checkNotice(trackerDigitalIdNotice($db,'PRX-NEXT')===null,'Approved application without official ID has no alert');
$db->exec("UPDATE applications SET senior_id_no='00100' WHERE id_number='SECOND'");
$db->beginTransaction();recordDigitalIdReady($db,'SECOND');$db->rollBack();
checkNotice(trackerDigitalIdNotice($db,'PRX-NEXT')===null,'Rollback does not leak an availability event');
recordDigitalIdReady($db,'SECOND');
checkNotice(trackerDigitalIdNotice($db,'PRX-NEXT')!==null,'ID assigned after approval creates alert');
$insert->execute(['BENEFIT','PRX-BENE','pension','Verified','00100','SECOND']);
$insert->execute(['CHANGE','PRX-CHNG','senior','Verified','00100','SECOND']);
recordDigitalIdReady($db,'BENEFIT');recordDigitalIdReady($db,'CHANGE');
checkNotice((int)$db->query('SELECT COUNT(*) FROM digital_id_notifications')->fetchColumn()===2,'Linked benefits and change requests do not create profile alerts');
echo "{$passed} checks passed.\n";
