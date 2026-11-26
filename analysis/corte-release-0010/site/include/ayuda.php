<?php
// Descarga segura de MANUAL_ADMIN.md
// Uso: /desarrollos/trace/api/download_manual_admin_md.php
include_once 'config.php';
include_once 'log.php';

$baseDir = dirname(directorioCodigo); 
$requested = 'MANUAL_ADMIN.md';
$filePath = $baseDir . DIRECTORY_SEPARATOR . $requested;

if (!is_file($filePath)) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'mensaje' => 'Archivo no encontrado']);
    exit;
}

// Forzar descarga
header('Content-Type: text/markdown; charset=utf-8');
header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
header('Content-Length: ' . filesize($filePath));
header('X-Content-Type-Options: nosniff');

registrarLog('INFO','AYUDA',"Genero descarga de MANUAL_ADMIN.md");

// Limpieza de buffers
if (ob_get_length()) {
    ob_end_clean();
}

readfile($filePath);
exit;

