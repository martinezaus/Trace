<?php
require_once __DIR__ . '/auth.php';

function operadorAccesoActual(PDO $db): array
{
    $id = operadorId();
    if ($id <= 0) {
        http_response_code(401);
        throw new RuntimeException('La sesión venció. Ingrese nuevamente.');
    }
    $q = $db->prepare('SELECT id,nombre,CAST(estado AS UNSIGNED) estado,session_token,CAST(corte AS UNSIGNED) corte,CAST(revisador AS UNSIGNED) revisador FROM operadores WHERE id=?');
    $q->execute([$id]);
    $operador = $q->fetch(PDO::FETCH_ASSOC);
    if (!$operador || (int)$operador['estado'] !== 0 ||
        !is_string($operador['session_token']) || !hash_equals($operador['session_token'], session_id())) {
        http_response_code(401);
        throw new RuntimeException('El acceso cambió o fue deshabilitado. Ingrese nuevamente.');
    }
    return $operador;
}

function operadorRequerirPermiso(PDO $db, string $permiso): array
{
    $operador = operadorAccesoActual($db);
    $permitido = match ($permiso) {
        'corte' => (int)$operador['corte'] === 0,
        'revision' => (int)$operador['revisador'] === 1,
        'armado' => true,
        default => throw new InvalidArgumentException('Sector inválido.')
    };
    if (!$permitido) {
        http_response_code(403);
        throw new RuntimeException('No tiene permiso para ingresar a este sector.');
    }
    return $operador;
}
