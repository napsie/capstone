<?php
/**
 * SENIORLINK — QR Token Generator Utility
 * Open via: http://localhost/capstone/gen_token.php
 */
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/crypto.php';

$sql = "SELECT id_number, full_name, birth_date, contact_number, complete_address, barangay,
               application_type, middle_name, suffix, sss_number, workflow_state, date_submitted,
               psa_birth_cert, proof_of_life
        FROM applications
        ORDER BY date_submitted DESC
        LIMIT 10";
$stmt = $conn->prepare($sql);
$stmt->execute();
$apps = $stmt->fetchAll(PDO::FETCH_ASSOC);

$results = [];
foreach ($apps as $app) {
    $nameParts = explode(',', $app['full_name']);
    $lastName  = trim($nameParts[0] ?? '');
    $firstName = trim($nameParts[1] ?? '');

    $payload = [
        'transactionId'          => $app['id_number'],
        'lastName'               => $lastName,
        'firstName'              => $firstName,
        'middleName'             => $app['middle_name'] ?? '',
        'suffix'                 => $app['suffix'] ?? '',
        'birthDate'              => $app['birth_date'],
        'contactNumber'          => $app['contact_number'],
        'completeAddress'        => $app['complete_address'],
        'barangay'               => $app['barangay'],
        'applicationType'        => $app['application_type'],
        'sssNumber'              => $app['sss_number'] ?? '',
        'dateOfDeath'            => '',
        'relationshipToDeceased' => '',
        'proxyName'              => '',
        'proxyRelationship'      => '',
        'created_at'             => $app['date_submitted'],
    ];

    $token   = ProxyCrypto::encrypt($payload);
    $scanUrl = 'http://localhost/capstone/pages/scan_proxy_qr_redirect.php?token=' . urlencode($token);
    $qrUrl   = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . urlencode($scanUrl);

    $results[] = [
        'app'   => $app,
        'id'    => $app['id_number'],
        'token' => $token,
        'scan'  => $scanUrl,
        'qr'    => $qrUrl,
        'state' => $app['workflow_state'] ?: 'Received',
        'type'  => $app['application_type'],
    ];
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>SENIORLINK — QR Token Generator</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Segoe UI',sans-serif;background:#f1f5f9;padding:30px;color:#1e293b}
  h1{font-size:1.6rem;margin-bottom:4px;color:#0f172a}
  .sub{color:#64748b;font-size:.88rem;margin-bottom:24px}
  .note{background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:12px 16px;font-size:.82rem;color:#92400e;margin-bottom:22px}
  .card{background:#fff;border-radius:12px;padding:24px;margin-bottom:22px;box-shadow:0 2px 10px rgba(0,0,0,.07);border-left:5px solid #10b981}
  .card-head{display:flex;align-items:center;gap:10px;margin-bottom:14px}
  .card-head h2{font-size:.97rem;color:#0f172a}
  .badge{font-size:.72rem;padding:3px 9px;border-radius:20px;font-weight:700;background:#0f172a;color:#fff}
  .badge.blue{background:#3b82f6}.badge.purple{background:#8b5cf6}.badge.green{background:#10b981}
  .row{display:flex;gap:24px;flex-wrap:wrap}
  .info-table{font-size:.83rem;flex:0 0 auto}
  .info-table td{padding:4px 10px;border:1px solid #e2e8f0}
  .info-table td:first-child{background:#f8fafc;font-weight:600;white-space:nowrap}
  .tok-box{background:#f8fafc;border:1px solid #cbd5e1;border-radius:6px;padding:9px 12px;font-family:monospace;font-size:.72rem;word-break:break-all;margin:10px 0}
  .btn{display:inline-flex;align-items:center;gap:6px;border:none;border-radius:6px;padding:7px 14px;font-size:.8rem;cursor:pointer;font-weight:600}
  .btn-blue{background:#3b82f6;color:#fff}.btn-blue:hover{background:#2563eb}
  .btn-green{background:#10b981;color:#fff}.btn-green:hover{background:#059669}
  .no-data{background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:20px;color:#b91c1c;text-align:center}
  .qr-img{border-radius:8px;border:1px solid #e2e8f0}
</style>
</head>
<body>
<h1><i class="fas fa-qrcode"></i> QR Token Generator</h1>
<p class="sub">Generates valid AES-256-CBC encrypted proxy tokens from existing database records. For staff testing only.</p>

<?php if (empty($results)): ?>
<div class="no-data">
  <i class="fas fa-exclamation-triangle" style="font-size:2rem;display:block;margin-bottom:8px"></i>
  <strong>No applications found.</strong><br>
  Submit an Option A (Pre-Registration) form first to create a record, then reload this page.
</div>
<?php else: ?>
<div class="note">
  <i class="fas fa-info-circle"></i>
  <strong>How to use:</strong> Copy an <strong>Encrypted Token</strong> below and paste it into the
  <em>"Scan Token"</em> dialog on <code>submit_application.php</code>. Or open the Scan URL directly.
</div>

<?php foreach ($results as $r):
    $a = $r['app'];
    $hasProxyDocs = $a['psa_birth_cert'] || $a['proof_of_life'];
?>
<div class="card">
  <div class="card-head">
    <h2><i class="fas fa-user-shield"></i> <?=htmlspecialchars($a['full_name'])?></h2>
    <span class="badge"><?=htmlspecialchars($r['id'])?></span>
    <span class="badge blue"><?=htmlspecialchars($r['state'])?></span>
    <span class="badge purple"><?=htmlspecialchars($r['type'])?></span>
    <?php if($hasProxyDocs): ?><span class="badge green">Has Proxy Docs</span><?php endif;?>
  </div>
  <div class="row">
    <div>
      <img src="<?=htmlspecialchars($r['qr'])?>" class="qr-img" width="180" height="180" alt="QR Code">
    </div>
    <div style="flex:1;min-width:260px">
      <table class="info-table">
        <tr><td>Transaction ID</td><td><?=htmlspecialchars($r['id'])?></td></tr>
        <tr><td>Barangay</td><td><?=htmlspecialchars($a['barangay'])?></td></tr>
        <tr><td>Birth Date</td><td><?=htmlspecialchars($a['birth_date'])?></td></tr>
        <tr><td>FSM State</td><td><?=htmlspecialchars($r['state'])?></td></tr>
        <tr><td>Proof of Life</td><td><?=$a['proof_of_life']?'<span style="color:#10b981;font-weight:700">✔ Uploaded</span>':'<span style="color:#ef4444">✘ None</span>'?></td></tr>
      </table>

      <p style="font-size:.78rem;font-weight:700;color:#475569;margin:12px 0 3px">🔐 Encrypted Token (paste into Scan Token dialog):</p>
      <div class="tok-box" id="tok-<?=htmlspecialchars($r['id'])?>"><?=htmlspecialchars($r['token'])?></div>
      <button class="btn btn-blue" onclick="cp('tok-<?=htmlspecialchars($r['id'])?>')"><i class="fas fa-copy"></i> Copy Token</button>

      <p style="font-size:.78rem;font-weight:700;color:#475569;margin:14px 0 3px">🪪 Transaction ID only (also works):</p>
      <div class="tok-box" id="txid-<?=htmlspecialchars($r['id'])?>" style="font-size:.95rem;font-weight:700;color:#0f172a"><?=htmlspecialchars($r['id'])?></div>
      <button class="btn btn-blue" onclick="cp('txid-<?=htmlspecialchars($r['id'])?>')"><i class="fas fa-copy"></i> Copy ID</button>
      &nbsp;
      <a href="<?=htmlspecialchars($r['scan'])?>" target="_blank" class="btn btn-green"><i class="fas fa-external-link-alt"></i> Open Scan URL</a>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<script>
function cp(id) {
  const t = document.getElementById(id).textContent.trim();
  navigator.clipboard.writeText(t).then(() => alert('Copied!')).catch(() => {
    const el = document.getElementById(id);
    const r = document.createRange(); r.selectNodeContents(el);
    window.getSelection().removeAllRanges(); window.getSelection().addRange(r);
    document.execCommand('copy'); alert('Copied!');
  });
}
</script>
</body>
</html>
