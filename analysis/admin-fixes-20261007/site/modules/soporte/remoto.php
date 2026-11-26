<?php

require_once __DIR__ . '/includes/auth.php';

require_role(['admin', 'master']);

$remoteSupportUrl = 'https://www.helpwire.app/builds/?token=EW5eo7TX3bRR31kgPEJ1k85JapvVq7G39UF4VfEM';

header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
header('Location: ' . $remoteSupportUrl, true, 302);
exit;
