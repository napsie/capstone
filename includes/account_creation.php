<?php
declare(strict_types=1);
require_once __DIR__.'/barangays_list.php';

function accountCreator(PDO $conn,array $session): array {
    if(empty($session['user_id'])) throw new RuntimeException('Sign in with an authorized staff account to create another account.',403);
    $stmt=$conn->prepare('SELECT id,role,barangay FROM users WHERE id=? AND COALESCE(is_archived,0)=0');
    $stmt->execute([(int)$session['user_id']]);$actor=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$actor || !in_array($actor['role'],['department_admin','super_admin','barangay_staff'],true)
        || ($actor['role']==='barangay_staff' && trim((string)$actor['barangay'])==='')) {
        throw new RuntimeException('An active authorized staff account is required.',403);
    }
    return $actor;
}

function accountCreationScope(array $creator,string $role,?string $barangay): ?string {
    global $barangays_list;
    if(!in_array($creator['role'],['department_admin','super_admin'],true)) {
        throw new RuntimeException('Only Main OSCA administrators can create staff accounts.',403);
    }
    if(!in_array($role,['department_admin','barangay_staff'],true))throw new RuntimeException('Select an allowed account role.',403);
    if($role==='barangay_staff' && !in_array($barangay,$barangays_list,true))throw new RuntimeException('Select a valid assigned barangay.',403);
    return $role==='barangay_staff'?$barangay:null;
}
