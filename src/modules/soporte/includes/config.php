<?php
/**
 * config.php
 * Configuración central del módulo "Configuraciones".
 * Ajustar los valores marcados con [AJUSTAR] al instalar en cada sistema.
 */

// ============ [AJUSTAR] Conexión a la base de datos ============
define('DB_HOST', 'db');
define('DB_NAME', 'ferroalumtrace');
define('DB_USER', 'root');
define('DB_PASS', '');

// [AJUSTAR] Ruta al directorio de logs del sistema principal.
// Por defecto asume que este módulo vive en /configuraciones dentro
// de la raíz del sistema, y que los logs están en /include/logs.
// Si la instalación es distinta, poner una ruta absoluta directamente.
define('LOG_DIR_RAW', __DIR__ . '/../../../include/logs');

// [AJUSTAR] Usuario fijo del sistema (no editable desde el login)
define('SYSTEM_USER', 'root');

// Tiempo de inactividad antes de expirar sesión (segundos)
define('SESSION_TIMEOUT', 900); // 15 minutos

// Intentos fallidos permitidos antes de bloqueo temporal
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_SECONDS', 300); // 5 minutos

// ============ No es necesario modificar debajo de esta línea ============

// En producción: no mostrar errores en pantalla (revisar logs de PHP en su lugar)
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/../vendor/autoload.php';

use RedBeanPHP\R;

if (!R::testConnection()) {
    R::setup('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS);
    // No permitir que RedBean modifique el esquema en producción.
    R::freeze(true);
}

define('LOG_DIR', realpath(LOG_DIR_RAW) ?: LOG_DIR_RAW);

// ============ Sesión segura ============
ini_set('session.cookie_httponly', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Strict');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}
session_name('cfg_sess');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Expiración de sesión por inactividad
if (!empty($_SESSION['auth_role']) && isset($_SESSION['last_activity'])
    && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    if (basename($_SERVER['PHP_SELF']) !== 'index.php') {
        header('Location: index.php?timeout=1');
        exit;
    }
}
$_SESSION['last_activity'] = time();
