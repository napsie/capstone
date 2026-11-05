<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/senior_classification.php';
require_once '../includes/classification_export.php';
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
try {
    $actor=classificationActor($_SESSION);
    $filters=classificationFilters($_GET,$actor);
    $format=is_string($_GET['format']??null)?$_GET['format']:'';
    if(!in_array($format,['pdf','xlsx'],true))throw new InvalidArgumentException('Choose PDF or Excel.');
    // A consistent snapshot keeps the totals and exported rows in agreement.
    $conn->beginTransaction();
    $summary=classificationSummary($conn,$filters);
    if($summary['total']>5000)throw new InvalidArgumentException('This report exceeds 5,000 seniors. Narrow the barangay, classification, or search filters.');
    $rows=classificationRows($conn,$filters,5000);
    $conn->commit();
    $contents=$format==='pdf'?classificationPdf($rows,$filters,$summary,$conn):classificationExcel($rows,$filters,$summary,$conn);
    header('Content-Type: '.($format==='pdf'?'application/pdf':'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'));
    header('Content-Disposition: attachment; filename="senior_classifications_'.date('Ymd').'.'.$format.'"');
    header('Content-Length: '.strlen($contents));echo $contents;
} catch(Throwable $e){
    if($conn->inTransaction())$conn->rollBack();
    $code=$e instanceof InvalidArgumentException?400:($e->getCode()===403?403:500);
    http_response_code($code);header('Content-Type: text/plain; charset=utf-8');
    if($code===500){error_log('Classification export: '.$e->getMessage());echo 'Unable to generate the classification report. Please try again.';}
    else echo $e->getMessage();
}
