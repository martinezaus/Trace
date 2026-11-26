<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../include/procesos_fecha.php';
require __DIR__.'/../include/detalle_vidrio_service.php';

$checks = 0;
function checkVidrio(bool $condition, string $message): void {
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL '.$message);
    $checks++;
    echo 'PASS '.$message.PHP_EOL;
}
function rejectsVidrio(callable $action, string $message): void {
    try { $action(); } catch (Throwable $e) { checkVidrio(true, $message); return; }
    checkVidrio(false, $message);
}
function vidrioDb(): PDO {
    $db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec("
        CREATE TABLE operadores(id INTEGER PRIMARY KEY);
        CREATE TABLE tareas(id INTEGER PRIMARY KEY AUTOINCREMENT,codigo TEXT,descripcion TEXT,barcode BLOB,barcode2d BLOB,tipo INTEGER,enable INTEGER);
        CREATE TABLE detalles(id INTEGER PRIMARY KEY,orden_id INTEGER,operador_id INTEGER,tipologia TEXT,c_estado INTEGER,tiempo_real TEXT,tiempo_estimado TEXT,eficiencia REAL);
        CREATE TABLE procesos(id INTEGER PRIMARY KEY AUTOINCREMENT,detalle_id INTEGER,tarea_id INTEGER,estado INTEGER,operador_id INTEGER,inicio TEXT,fin TEXT,created TEXT,tiempo_real TEXT);
        CREATE TABLE eventos(id INTEGER PRIMARY KEY AUTOINCREMENT,proceso_id INTEGER,tipo TEXT,descripcion TEXT,tiempo_real TEXT,operador_id INTEGER,created TEXT);
        CREATE TABLE pendientes(id INTEGER PRIMARY KEY,detalle_id INTEGER,valided INTEGER);
    ");
    $db->exec("INSERT INTO operadores VALUES(1); INSERT INTO detalles VALUES(1,10,1,'V-DVH(4)',0,'00:00:00','00:30:00',NULL)");
    return $db;
}

$db = vidrioDb();
$service = new DetalleVidrioService($db);
$result = $service->registrarColocacion(1, 0);
checkVidrio($result['status'] === 'ok' && $result['repetido'] === false, 'Registra colocación de vidrio entero');
$detail = $db->query('SELECT c_estado,tiempo_real,tiempo_estimado,eficiencia FROM detalles WHERE id=1')->fetch(PDO::FETCH_ASSOC);
checkVidrio((int)$detail['c_estado'] === 3 && $detail['tiempo_real'] === '00:00:00' && $detail['tiempo_estimado'] === '00:00:00' && (float)$detail['eficiencia'] === 0.0, 'Marca detalle terminado con tiempos y eficiencia en cero');
$process = $db->query('SELECT * FROM procesos')->fetch(PDO::FETCH_ASSOC);
checkVidrio((int)$process['estado'] === 2 && $process['inicio'] === $process['fin'] && $process['tiempo_real'] === '00:00:00', 'Crea proceso terminado de duración cero');
$event = $db->query('SELECT * FROM eventos')->fetch(PDO::FETCH_ASSOC);
checkVidrio($event['tipo'] === 'lectura_descartada' && $event['descripcion'] === 'colocacion en obra' && $event['tiempo_real'] === '00:00:00' && (int)$event['proceso_id'] === (int)$process['id'], 'Registra evento de colocación ligado al proceso');
$again = $service->registrarColocacion(1, 0);
checkVidrio($again['repetido'] === true && (int)$db->query('SELECT COUNT(*) FROM procesos')->fetchColumn() === 1, 'Reintento no duplica proceso ni evento');

$db = vidrioDb();
$db->exec("UPDATE detalles SET tipologia='TELA-M(M1/M1)' WHERE id=1");
rejectsVidrio(fn() => (new DetalleVidrioService($db))->registrarColocacion(1, 0), 'Rechaza detalles que no son vidrio');
checkVidrio((int)$db->query('SELECT c_estado FROM detalles')->fetchColumn() === 0, 'No modifica detalle no elegible');

$db = vidrioDb();
$db->exec("INSERT INTO procesos(detalle_id,tarea_id,estado,operador_id,inicio,created,tiempo_real) VALUES(1,1,1,1,'2026-01-01 00:00:00','2026-01-01 00:00:00','00:00:00')");
rejectsVidrio(fn() => (new DetalleVidrioService($db))->registrarColocacion(1, 0), 'No cierra sobre proceso activo');
checkVidrio((int)$db->query('SELECT c_estado FROM detalles')->fetchColumn() === 0, 'Mantiene detalle pendiente cuando hay proceso activo');

$db = vidrioDb();
$db->exec("CREATE TRIGGER fail_event BEFORE INSERT ON eventos BEGIN SELECT RAISE(ABORT,'simulated event failure'); END");
rejectsVidrio(fn() => (new DetalleVidrioService($db))->registrarColocacion(1, 0), 'Fallo al crear evento revierte la operación');
checkVidrio((int)$db->query('SELECT c_estado FROM detalles')->fetchColumn() === 0, 'Rollback restaura estado y tiempos del detalle');
checkVidrio((int)$db->query('SELECT COUNT(*) FROM procesos')->fetchColumn() === 0 && (int)$db->query('SELECT COUNT(*) FROM tareas')->fetchColumn() === 0, 'Rollback elimina proceso y tarea especial creados');

echo 'TOTAL '.$checks.PHP_EOL;
