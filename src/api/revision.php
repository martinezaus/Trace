<?php
require_once __DIR__ . '/../include/auth.php';
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/operator_access.php';
require_once __DIR__ . '/../include/revision_service.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
ini_set('display_errors', '0');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        throw new RuntimeException('Método no permitido.');
    }
    requerirOperadorApi();
    $db = new PDO(
        'mysql:host=' . hostBD . ';dbname=' . nombreBD . ';charset=utf8mb4',
        usuarioBD,
        claveBD,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $operador = operadorRequerirPermiso($db, 'revision');
    $accion = (string)($_POST['accion'] ?? '');
    if ($accion === 'lista') {
        $q = $db->query(
            'SELECT d.id,d.nombre AS documento,o.codigo AS orden,o.cliente,
                    CAST(d.revisado AS UNSIGNED) AS revisado,d.fecha_hora_revisado,
                    d.operador_rev,op.nombre AS revisor
             FROM documentos d
             JOIN ordenes o ON o.id=d.orden_id
             LEFT JOIN operadores op ON op.id=d.operador_rev
             ORDER BY d.id DESC'
        );
        echo json_encode(['ok' => true, 'operador' => $operador['nombre'], 'documentos' => $q->fetchAll()], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
    if ($accion === 'marcar') {
        $documentoId = filter_var($_POST['documento_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$documentoId || $documentoId < 1) {
            http_response_code(422);
            throw new InvalidArgumentException('Documento inválido.');
        }
        $detalle = revisionMarcarDocumento($db, $documentoId, (int)$operador['id']);
        echo json_encode(['ok' => true, 'fecha_hora_revisado' => $detalle['fecha_hora_revisado']], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
    http_response_code(400);
    throw new InvalidArgumentException('Acción no válida.');
} catch (Throwable $e) {
    if (http_response_code() < 400) {
        if ($e instanceof OutOfBoundsException) http_response_code(404);
        elseif ($e instanceof DomainException) http_response_code(409);
        elseif ($e instanceof InvalidArgumentException) http_response_code(422);
        else http_response_code(500);
    }
    if (http_response_code() >= 500) error_log('Revision API: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'mensaje' => http_response_code() >= 500 ? 'No se pudo completar la operación.' : $e->getMessage()], JSON_INVALID_UTF8_SUBSTITUTE);
}
