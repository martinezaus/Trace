<?php
require_once __DIR__ . '/../include/revision_service.php';

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "SKIP: PDO SQLite no está disponible.\n";
    exit(0);
}
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE documentos (id INTEGER PRIMARY KEY, revisado INTEGER NOT NULL DEFAULT 0, fecha_hora_revisado TEXT NULL, operador_rev INTEGER NULL)');
$db->exec('INSERT INTO documentos (id) VALUES (10)');
$q = $db->query('SELECT revisado FROM documentos WHERE id=10');
if ((int)$q->fetchColumn() !== 0) throw new RuntimeException('Estado inicial incorrecto.');

$marcado = revisionMarcarDocumento($db, 10, 7);
if (empty($marcado['fecha_hora_revisado']) || (int)$marcado['operador_rev'] !== 7) throw new RuntimeException('No se guardaron los datos de revisión.');
$q = $db->query('SELECT revisado FROM documentos WHERE id=10');
if ((int)$q->fetchColumn() !== 1) throw new RuntimeException('El documento no quedó revisado.');

try {
    revisionMarcarDocumento($db, 10, 8);
    throw new RuntimeException('Se permitió marcar dos veces el mismo documento.');
} catch (DomainException $e) {
    if ($e->getMessage() !== 'El documento ya fue revisado.') throw $e;
}

if (!revisionRevertirDocumento($db, 10)) throw new RuntimeException('No se revirtió la revisión.');
$q = $db->query('SELECT revisado,fecha_hora_revisado,operador_rev FROM documentos WHERE id=10');
$limpio = $q->fetch(PDO::FETCH_ASSOC);
if ((int)$limpio['revisado'] !== 0 || $limpio['fecha_hora_revisado'] !== null || $limpio['operador_rev'] !== null) {
    throw new RuntimeException('La reversión no limpió el estado y sus datos.');
}
if (revisionRevertirDocumento($db, 10)) throw new RuntimeException('La revisión se revirtió más de una vez.');
echo "OK: revisión irreversible desde producción y reversible desde administración.\n";
