<?php
require_once __DIR__ . '/../include/negocio_logo.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
    echo json_encode(['ok' => true, 'logo' => negocio_logo_data_uri()]);
} catch (Throwable $e) {
    error_log('Logo de reportes: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo obtener el logo del negocio.']);
}
