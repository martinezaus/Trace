<?php
require_once __DIR__ . '/../include/auth.php';
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/revision_service.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
ini_set('display_errors', '0');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        throw new RuntimeException('Método no permitido.');
    }
    $csrf = (string)($_POST['csrf'] ?? '');
    if (empty($_SESSION['admin_export_csrf']) || !hash_equals($_SESSION['admin_export_csrf'], $csrf)) {
        http_response_code(403);
        throw new RuntimeException('La solicitud venció. Actualice el Tablero e intente nuevamente.');
    }
    $documentoId = filter_var($_POST['documento_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$documentoId || $documentoId < 1) {
        http_response_code(422);
        throw new InvalidArgumentException('Documento inválido.');
    }
    $db = new PDO(
        'mysql:host=' . hostBD . ';dbname=' . nombreBD . ';charset=utf8mb4',
        usuarioBD,
        claveBD,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $revertido = revisionRevertirDocumento($db, $documentoId);
    echo json_encode(['ok' => true, 'revertido' => $revertido], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    if (http_response_code() < 400) {
        if ($e instanceof OutOfBoundsException) http_response_code(404);
        elseif ($e instanceof InvalidArgumentException) http_response_code(422);
        else http_response_code(500);
    }
    if (http_response_code() >= 500) error_log('Revertir revision: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'mensaje' => http_response_code() >= 500 ? 'No se pudo revertir la revisión.' : $e->getMessage()]);
}
