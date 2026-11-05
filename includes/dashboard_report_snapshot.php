<?php
declare(strict_types=1);

/** Keep only aggregates delivered to this user's dashboard, never notifications. */
function rememberDashboardReportSnapshot(array $response,array $session):void {
    $data=$response['data']??[];unset($data['notifications']);
    $_SESSION['dashboard_report_snapshot']=[
        'user_id'=>(int)($session['user_id']??0),'role'=>$session['role']??'',
        'barangay'=>($session['role']??'')==='barangay_staff'?($session['barangay']??''):null,
        'captured_at'=>$response['timestamp']??date('Y-m-d H:i:s'),'data'=>$data,
    ];
}
