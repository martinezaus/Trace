<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role(['master']);
require_post();
use RedBeanPHP\R;

if (!verify_csrf($_POST['csrf'] ?? null)) {
    json_response(['ok' => false, 'error' => 'Token de seguridad inválido, recargue la página.'], 400);
}

$nombre   = s('nombre');
$username = s('username');
$version  = s('version', '');
$licencia = s('licencia', '');

if ($nombre === '' || strlen($nombre) > 100) {
    json_response(['ok' => false, 'error' => 'Nombre inválido.'], 422);
}
if ($username === '' || strlen($username) > 10) {
    json_response(['ok' => false, 'error' => 'Usuario inválido (máximo 10 caracteres).'], 422);
}
if (strlen($version) > 7) {
    json_response(['ok' => false, 'error' => 'Versión inválida (máximo 7 caracteres).'], 422);
}
if ($licencia !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $licencia)) {
    json_response(['ok' => false, 'error' => 'Fecha de licencia inválida.'], 422);
}

$row = R::findOne('systemusage');
if (!$row) {
    json_response(['ok' => false, 'error' => 'No existe registro de sistema para actualizar.'], 500);
}

$row->nombre   = $nombre;
$row->username = $username;
$row->version  = $version;
$row->licencia = $licencia !== '' ? $licencia : null;
$row->enabled  = b('enabled');
$row->soporte    = b('soporte');
$row->ia         = b('ia');
$row->personal   = b('personal');
$row->instalacion = b('instalacion');

$newPassword = (string) ($_POST['password'] ?? '');
$newMaster   = (string) ($_POST['pwmaster'] ?? '');

if ($newPassword !== '') {
    if (strlen($newPassword) < 6) {
        json_response(['ok' => false, 'error' => 'La clave admin debe tener al menos 6 caracteres.'], 422);
    }
    $row->password = hash_for_storage($newPassword, 'systemusage', 'password');
}
if ($newMaster !== '') {
    if (strlen($newMaster) < 6) {
        json_response(['ok' => false, 'error' => 'La clave master debe tener al menos 6 caracteres.'], 422);
    }
    $row->pwmaster = hash_for_storage($newMaster, 'systemusage', 'pwmaster');
}

R::store($row);
json_response(['ok' => true]);
