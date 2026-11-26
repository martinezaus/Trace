<?php
 
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}
 
function operadorId(): int
{
	return (int)($_SESSION['operador_id'] ?? 0);
}
 
function requerirOperadorPagina(): void
{
	if (operadorId() <= 0) {
		header('Location: index.php');
		exit;
	}
}
 
function requerirOperadorApi(): void
{
	if (operadorId() <= 0) {
		http_response_code(401);
		echo json_encode(['ok' => false, 'motivo' => 'sesion']);
		exit;
	}
}

function operadorDestruirSesion(): void
{
	$_SESSION = [];
	if (ini_get('session.use_cookies')) {
		$parametros = session_get_cookie_params();
		setcookie(session_name(), '', [
			'expires' => time() - 42000,
			'path' => $parametros['path'],
			'domain' => $parametros['domain'],
			'secure' => $parametros['secure'],
			'httponly' => $parametros['httponly'],
			'samesite' => $parametros['samesite'] ?? 'Lax',
		]);
	}
	session_destroy();
}