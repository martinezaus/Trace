<?php

/** Devuelve una respuesta JSON y termina la ejecución */
function json_response($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Lee y recorta un campo $_POST */
function s(string $key, $default = null)
{
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : $default;
}

/** Convierte un checkbox recibido ("on"/ausente) en 0/1 */
function b(string $key): int
{
    return isset($_POST[$key]) && $_POST[$key] !== '0' && $_POST[$key] !== '' ? 1 : 0;
}

/** Exige que la petición sea AJAX/POST con método correcto */
function require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['ok' => false, 'error' => 'Método no permitido'], 405);
    }
}
