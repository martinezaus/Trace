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