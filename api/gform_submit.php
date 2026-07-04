<?php
// Deprecated - Google Form integration is no longer used in the system.
http_response_code(410);
echo json_encode(["success" => false, "error" => "Endpoint deprecated and removed."]);
?>