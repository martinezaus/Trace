<?php
declare(strict_types=1);
// Sesión técnica para filtros y descargas; Reportes no solicita credenciales.
if (session_status() === PHP_SESSION_NONE) {
    session_name('TRACE_REPORTES');
    session_start(['cookie_httponly'=>true, 'cookie_samesite'=>'Lax',
        'cookie_secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'use_strict_mode'=>true]);
}
function reportCsrf(): string {
    if (empty($_SESSION['reportes_csrf'])) $_SESSION['reportes_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['reportes_csrf'];
}
function reportCheckRequest(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405); header('Allow: POST');
        throw new RuntimeException('Use una solicitud POST.');
    }
    $csrf = $_POST['csrf'] ?? '';
    if (!is_string($csrf) || !hash_equals(reportCsrf(), $csrf)) {
        http_response_code(403);
        throw new RuntimeException('La página venció. Recargue Reportes.');
    }
}
