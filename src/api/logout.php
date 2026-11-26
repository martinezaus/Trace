<?php
require_once __DIR__ . '/../include/auth.php';
require_once __DIR__ . '/../include/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
ini_set('display_errors', '0');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido.']);
    exit;
}

requerirOperadorApi();
$operadorId = operadorId();
$sessionId = session_id();
$tokenEscaneado = trim((string)($_POST['token'] ?? ''));

try {
    $db = new PDO(
        'mysql:host=' . hostBD . ';dbname=' . nombreBD . ';charset=utf8mb4',
        usuarioBD,
        claveBD,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $consulta = $db->prepare('SELECT token,CAST(estado AS UNSIGNED) estado,session_token FROM operadores WHERE id=?');
    $consulta->execute([$operadorId]);
    $operador = $consulta->fetch();
    if (!$operador || (int)$operador['estado'] !== 0 ||
        !is_string($operador['session_token']) || !hash_equals($operador['session_token'], $sessionId)) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'mensaje' => 'La sesión ya no es válida. Ingrese nuevamente.']);
        exit;
    }

    if ($tokenEscaneado !== '' &&
        (!is_string($operador['token']) || !hash_equals($operador['token'], $tokenEscaneado))) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'mensaje' => 'La tarjeta no corresponde al operador conectado.']);
        exit;
    }

    $actualizar = $db->prepare('UPDATE operadores SET session_token=NULL WHERE id=? AND session_token=?');
    $actualizar->execute([$operadorId, $sessionId]);
    if ($actualizar->rowCount() !== 1) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'mensaje' => 'La sesión ya no es válida. Ingrese nuevamente.']);
        exit;
    }
} catch (Throwable $e) {
    error_log('Operator logout failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'No se pudo cerrar la sesión. Intente nuevamente.']);
    exit;
}

operadorDestruirSesion();
echo json_encode(['ok' => true]);
