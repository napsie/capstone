<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/account_creation.php';
require_once '../includes/dashboard_data.php';
require_once '../includes/dashboard_report_snapshot.php';
require_once '../includes/dashboard_report.php';
header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');
try {
    $actor=accountCreator($conn,$_SESSION);$format=(string)($_GET['format']??'');
    if(!in_array($format,['pdf','excel'],true))throw new InvalidArgumentException('Choose PDF or Excel.');
    $barangay=$actor['role']==='barangay_staff'?$actor['barangay']:null;
    $snapshot=$_SESSION['dashboard_report_snapshot']??null;
    if(!$snapshot||$snapshot['user_id']!==(int)$actor['id']||$snapshot['role']!==$actor['role']||$snapshot['barangay']!==$barangay){
        $data=$barangay===null?departmentDashboardData($conn,$driver):barangayDashboardData($conn,$barangay);
        rememberDashboardReportSnapshot(['data'=>$data,'timestamp'=>date('Y-m-d H:i:s')],$_SESSION);$snapshot=$_SESSION['dashboard_report_snapshot'];
    }
    $sections=dashboardReportSections($snapshot);
    $data=$format==='pdf'?dashboardReportPdf($sections,$snapshot,$conn):dashboardReportExcel($sections,$snapshot,$conn);
    $extension=$format==='pdf'?'pdf':'xlsx';
    header('Content-Type: '.($format==='pdf'?'application/pdf':'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'));
    header('Content-Disposition: attachment; filename="dashboard_summary_'.date('Ymd').'.'.$extension.'"');header('Content-Length: '.strlen($data));echo $data;
}catch(Throwable $e){$code=$e instanceof InvalidArgumentException?400:($e->getCode()===403?403:500);http_response_code($code);header('Content-Type: text/plain; charset=utf-8');if($code===500){error_log('Dashboard export: '.$e->getMessage());echo 'Unable to generate the dashboard report. Reload the dashboard and try again.';}else echo $e->getMessage();}
