<?php
/** Shared OSCA form field sections — used by new_application.php and submit_application.php */
$prefix = $formFieldPrefix ?? '';
$id = fn($name) => $prefix . $name;
?>
