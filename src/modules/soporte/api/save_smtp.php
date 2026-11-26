<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role(['admin', 'master']);
require_post();
use RedBeanPHP\R;

if (!verify_csrf($_POST['csrf'] ?? null)) {
    json_response(['ok' => false, 'error' => 'Token de seguridad inválido, recargue la página.'], 400);
}

$host  = s('host');
$port  = filter_input(INPUT_POST, 'port', FILTER_VALIDATE_INT);
$email = s('email');
$server = (string) ($_POST['server'] ?? ''); // clave / server, opcional
$encryption = s('encryption', 'tls');

$allowedEnc = ['none', 'ssl', 'tls', 'starttls'];
$destinos = s('email_a');
if ($host === '' || strlen($host) > 255) {
    json_response(['ok' => false, 'error' => 'Host inválido.'], 422);
}
if ($port === false || $port === null || $port < 1 || $port > 65535) {
    json_response(['ok' => false, 'error' => 'Puerto inválido.'], 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['ok' => false, 'error' => 'Correo inválido.'], 422);
}
if (!in_array($encryption, $allowedEnc, true)) {
    json_response(['ok' => false, 'error' => 'Tipo de cifrado inválido.'], 422);
}

$row = R::findOne('systemsmtp') ?: R::dispense('systemsmtp');
$row->host  = $host;
$row->port  = $port;
$row->email = $email;
$row->encryption = $encryption;
$row->to = $destinos;
// El campo "server" en el esquema original guarda la clave del correo.
// Solo se actualiza si el usuario ingresó un valor nuevo.
if (trim($server) !== '') {
    $row->server = $server; // Recomendado: cifrar/almacenar de forma segura según el sistema
}
if (empty($row->server)) {
    json_response(['ok' => false, 'error' => 'Debe indicar la clave del correo la primera vez.'], 422);
}

R::store($row);
json_response(['ok' => true]);
