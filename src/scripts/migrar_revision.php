<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../include/config.php';

$db = new PDO(
    'mysql:host=' . hostBD . ';dbname=' . nombreBD . ';charset=utf8mb4',
    usuarioBD,
    claveBD,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$aplicar = in_array('--aplicar', $argv, true);
$tabla = static function (string $nombre) use ($db): bool {
    $q = $db->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $q->execute([$nombre]);
    return (bool)$q->fetchColumn();
};
$columna = static function (string $tabla, string $nombre) use ($db): ?array {
    $q = $db->prepare('SELECT DATA_TYPE,COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $q->execute([$tabla, $nombre]);
    $dato = $q->fetch(PDO::FETCH_ASSOC);
    return $dato ?: null;
};
$indice = static function (string $tabla, string $nombre) use ($db): bool {
    $q = $db->prepare('SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');
    $q->execute([$tabla, $nombre]);
    return (bool)$q->fetchColumn();
};
foreach (['operadores', 'documentos'] as $requiredTable) {
    if (!$tabla($requiredTable)) throw new RuntimeException("No existe la tabla {$requiredTable}.");
}
if (!$columna('operadores', 'session')) throw new RuntimeException('No existe operadores.session.');

$steps = [];
$sessionType = $columna('operadores', 'session');
if ($sessionType['DATA_TYPE'] !== 'tinyint' || $sessionType['COLUMN_TYPE'] !== 'tinyint unsigned') {
    $steps[] = [
        'description' => 'Convertir operadores.session a cantidad 1 o 2',
        'sql' => "UPDATE operadores SET `session`=1 WHERE `session` IS NULL OR `session`=0",
    ];
    $steps[] = [
        'description' => 'Ajustar tipo de operadores.session',
        'sql' => "ALTER TABLE operadores MODIFY `session` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Cantidad de sesiones configurada: 1 o 2'",
    ];
}
if (!$columna('operadores', 'revisador')) {
    $steps[] = ['description' => 'Agregar permiso revisador', 'sql' => "ALTER TABLE operadores ADD `revisador` BIT(1) NOT NULL DEFAULT b'0'"];
}
if (!$columna('documentos', 'revisado')) {
    $steps[] = ['description' => 'Agregar estado revisado', 'sql' => "ALTER TABLE documentos ADD `revisado` BIT(1) NOT NULL DEFAULT b'0'"];
}
if (!$columna('documentos', 'fecha_hora_revisado')) {
    $steps[] = ['description' => 'Agregar fecha de revisión', 'sql' => 'ALTER TABLE documentos ADD `fecha_hora_revisado` DATETIME NULL'];
}
if (!$columna('documentos', 'operador_rev')) {
    $steps[] = ['description' => 'Agregar operador revisor', 'sql' => 'ALTER TABLE documentos ADD `operador_rev` INT(10) UNSIGNED NULL'];
}
if (!$indice('documentos', 'idx_documentos_revisado')) {
    $steps[] = ['description' => 'Agregar índice para documentos revisados', 'sql' => 'CREATE INDEX idx_documentos_revisado ON documentos (revisado, orden_id)'];
}

if (!$steps) {
    echo "El esquema de Revisión ya está actualizado.\n";
    exit;
}
if (!$aplicar) {
    foreach ($steps as $step) echo "Pendiente: {$step['description']}.\n";
    echo "Ejecute php scripts/migrar_revision.php --aplicar para aplicar la migración.\n";
    exit;
}
foreach ($steps as $step) {
    $db->exec($step['sql']);
    echo "OK: {$step['description']}.\n";
}
