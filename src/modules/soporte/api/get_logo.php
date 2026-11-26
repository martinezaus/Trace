<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin', 'master']);
use RedBeanPHP\R;

$row = R::findOne('systemnegocio', ' ORDER BY id');

if (!$row || empty($row->logo)) {
    http_response_code(404);
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_buffer($finfo, $row->logo) ?: 'application/octet-stream';
finfo_close($finfo);

header('Content-Type: ' . $mime);
header('Cache-Control: private, max-age=60');
echo $row->logo;

