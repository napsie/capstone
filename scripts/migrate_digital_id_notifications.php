<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/includes/db_connect.php';
require_once dirname(__DIR__) . '/includes/sql_script_runner.php';
executeSqlScript($conn, (string)file_get_contents(dirname(__DIR__) . '/database/migrations/20261104_digital_id_notifications.sql'));
echo "Digital ID notification storage and existing eligible records are ready.\n";
