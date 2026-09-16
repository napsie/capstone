<?php
// Applicant photos are private documents. The former token-only route must
// never bypass the role/barangay checks in get_document.php.
http_response_code(410);
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
exit('Applicant photos are available only through the authorized document viewer.');
