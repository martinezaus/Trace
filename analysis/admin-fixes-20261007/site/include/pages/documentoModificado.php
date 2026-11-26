<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../api/rb-mysql.php';

if (operadorId() <= 0) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Sesión de operador requerida.');
}

$documentoId = (int)($_GET['id'] ?? 0);
if ($documentoId <= 0) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Documento inválido.');
}

try {
    R::setup('mysql:host=' . hostBD . ';dbname=' . nombreBD, usuarioBD, claveBD);
    R::freeze(true);
    $documento = R::getRow('SELECT archivo_final FROM documentos WHERE id = ?', [$documentoId]);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('No se pudo consultar el documento.');
}

$nombre = basename((string)($documento['archivo_final'] ?? ''));
$directorio = realpath(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'modificados');
$ruta = ($directorio && $nombre !== '') ? $directorio . DIRECTORY_SEPARATOR . $nombre : '';

if ($ruta === '' || !is_file($ruta)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('El PDF modificado todavía no fue generado.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . rawurlencode($nombre) . '"');
header('Content-Length: ' . filesize($ruta));
header('X-Content-Type-Options: nosniff');
readfile($ruta);
exit;