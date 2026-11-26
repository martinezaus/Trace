<?php
// backup_download.php
// Envía el ZIP más reciente al browser para que lo descargue en la PC del usuario.
// Se llama desde el JS cuando el polling detecta done=true.

ob_start();
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/log.php";
ob_end_clean();

$jobId = $_GET['jobId'] ?? '';
if ($jobId === '' || !preg_match('/^[a-zA-Z0-9_-]{6,}$/', $jobId)) {
    http_response_code(400);
    echo json_encode(['error' => 'jobId inválido']);
    exit;
}

// Leer el status para saber el nombre del archivo (o buscarlo por jobId)
$statusPath = __DIR__ . DIRECTORY_SEPARATOR . "backup_status_{$jobId}.json";
$data = @json_decode(@file_get_contents($statusPath), true);

// Descargar solamente el archivo completado por este proceso.
$archivoZIP = !empty($data['done']) && empty($data['error']) && !empty($data['zipNombre'])
    ? realpath($data['zipNombre']) : false;
$backupDir = realpath(carpetaBackup);
if (!$archivoZIP || !$backupDir || !is_file($archivoZIP)
    || strcasecmp(dirname($archivoZIP), $backupDir) !== 0) {
    http_response_code(404);
    echo json_encode(['error' => 'El respaldo solicitado no está disponible']);
    exit;
}
registrarLog('INFO', 'BACKUPS', 'Descarga solicitada: ' . basename($archivoZIP));

// Headers para forzar descarga en el browser del cliente
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . basename($archivoZIP) . '"');
header('Content-Length: ' . filesize($archivoZIP));
header('Cache-Control: no-cache, no-store');
header('Pragma: no-cache');

readfile($archivoZIP);

// Opcional: borrar el ZIP del servidor después de descargarlo
// @unlink($archivoZIP);
// @unlink($statusPath);

exit;