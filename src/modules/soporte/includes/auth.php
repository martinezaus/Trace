<?php
require_once __DIR__ . '/config.php';

use RedBeanPHP\R;

/** Genera / recupera el token CSRF de la sesión actual */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Verifica un token CSRF recibido contra el de sesión */
function verify_csrf(?string $token): bool
{
    return !empty($token) && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function is_logged_in(): bool
{
    return !empty($_SESSION['auth_role']) && in_array($_SESSION['auth_role'], ['admin', 'master'], true);
}

function get_role(): ?string
{
    return $_SESSION['auth_role'] ?? null;
}

/**
 * Corta la ejecución si no hay sesión o el rol no está permitido.
 * $roles puede ser 'master', 'admin' o ['admin','master'].
 */
function require_role($roles): void
{
    if (!is_logged_in()) {
        header('Location: index.php');
        exit;
    }
    $roles = (array) $roles;
    if (!in_array(get_role(), $roles, true)) {
        http_response_code(403);
        die('Acceso denegado: esta sección requiere un nivel de acceso superior (master).');
    }
}

/**
 * Verifica una clave en texto plano contra un hash almacenado.
 * Compatible con:
 *  - Hash legado MD5 (32 caracteres) del esquema original.
 *  - Hash moderno password_hash() (bcrypt/argon2) si la columna fue
 *    ampliada a varchar(255) — ver sql/migration_security.sql.
 */
function verify_password(string $plain, string $stored): bool
{
    if ($stored === '') {
        return false;
    }
    if (strlen($stored) > 32) {
        // Hash moderno
        return password_verify($plain, $stored);
    }
    // Hash legado MD5 — mantenido por compatibilidad con el esquema actual
    return hash_equals(strtolower($stored), md5($plain));
}

/**
 * Genera el hash apropiado para guardar una clave nueva.
 * Detecta dinámicamente el ancho real de la columna en la base de datos:
 * si fue ampliada (>=60 caracteres) usa password_hash (bcrypt), si sigue
 * en varchar(32) usa MD5 para no romper el esquema existente.
 */
function hash_for_storage(string $plain, string $table, string $column): string
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (!isset($cache[$key])) {
        $len = R::getCell(
            "SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            [$table, $column]
        );
        $cache[$key] = (int) $len;
    }
    if ($cache[$key] >= 60) {
        return password_hash($plain, PASSWORD_BCRYPT);
    }
    return md5($plain);
}

/** Bloqueo temporal por intentos fallidos (throttle a nivel de sesión) */
function check_lockout(): void
{
    if (!empty($_SESSION['lock_until']) && time() < $_SESSION['lock_until']) {
        $wait = $_SESSION['lock_until'] - time();
        http_response_code(429);
        die("Demasiados intentos fallidos. Intente nuevamente en {$wait} segundos.");
    }
}

function register_failed_attempt(): void
{
    $_SESSION['fail_count'] = ($_SESSION['fail_count'] ?? 0) + 1;
    if ($_SESSION['fail_count'] >= MAX_LOGIN_ATTEMPTS) {
        $_SESSION['lock_until'] = time() + LOGIN_LOCKOUT_SECONDS;
        $_SESSION['fail_count'] = 0;
    }
    usleep(400000); // retardo anti fuerza bruta (0.4s)
}

function reset_failed_attempts(): void
{
    unset($_SESSION['fail_count'], $_SESSION['lock_until']);
}
