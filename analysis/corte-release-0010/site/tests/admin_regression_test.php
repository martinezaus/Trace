<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../include/detalle_admin_service.php';require __DIR__.'/../include/reportes_service.php';require __DIR__.'/../include/functions.php';require __DIR__.'/../include/parse_pdf.php';
function registrarLog(...$args){}
$n=0;function ck($v,$m){global $n;if(!$v)throw new RuntimeException($m);$n++;echo 'PASS '.$m.PHP_EOL;}
set_error_handler(static function($s,$m){throw new ErrorException($m,0,$s);});
$lines=agruparEnLineas([['text'=>'a','xMin'=>0,'yMin'=>10.9],['text'=>'b','xMin'=>10,'yMin'=>12.8],['text'=>'c','xMin'=>0,'yMin'=>15.1]]);
ck(count($lines)===2,'Agrupación decimal sin avisos');ck($lines[0]['y']===10.9,'Conserva precisión Y');ck($lines[0]['texto']==='a b','Agrupa dentro de tolerancia real');ck(segundosToTime(11679.659999)==='03:14:39','Duración fraccionaria explícita');
ck(isset(reportCatalog()['procesos']['statuses'][4]),'Estado proceso descartado');ck(isset(reportCatalog()['procesos']['statuses'][3]),'Estado proceso revertido');ck(isset(reportCatalog()['eventos']['statuses']['lectura_descartada']),'Evento de descarte');
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec("CREATE TABLE ordenes(id INTEGER PRIMARY KEY,c_estado INTEGER);CREATE TABLE detalles(id INTEGER PRIMARY KEY,orden_id INTEGER,operador_id INTEGER,c_estado INTEGER,tiempo_real TEXT,eficiencia REAL);CREATE TABLE procesos(id INTEGER,detalle_id INTEGER,estado INTEGER);CREATE TABLE detallehistorial(tipo TEXT,orden_id INTEGER,detalle_id INTEGER,operador_id INTEGER,revertido_por INTEGER,tiempo_real_anterior INTEGER,eficiencia_anterior REAL,motivo TEXT);");
$db->exec("INSERT INTO ordenes VALUES(1,2);INSERT INTO detalles VALUES(1,1,1,3,'01:02:03',10);INSERT INTO procesos VALUES(1,1,2);");$s=new DetalleAdminService($db);$r=$s->revertir(1,null,'prueba');
ck($r['ordenId']===1,'Reversión devuelve orden');ck((int)$db->query('SELECT c_estado FROM detalles')->fetchColumn()===0,'Tipología pendiente');ck((int)$db->query('SELECT c_estado FROM ordenes')->fetchColumn()===1,'Orden terminada vuelve a procesando');ck((int)$db->query('SELECT estado FROM procesos')->fetchColumn()===3,'Proceso invalidado');ck((int)$db->query('SELECT tiempo_real_anterior FROM detallehistorial')->fetchColumn()===3723,'Tiempo anterior preservado');
try{$s->revertir(1,null,'duplicada');throw new Exception('Debió rechazar');}catch(InvalidArgumentException $e){ck(true,'Rechaza segunda reversión');}ck((int)$db->query('SELECT count(*) FROM detallehistorial')->fetchColumn()===1,'Sin historial duplicado');
$db->exec("UPDATE detalles SET c_estado=3;UPDATE procesos SET estado=2;UPDATE ordenes SET c_estado=2;CREATE TRIGGER fallo BEFORE UPDATE ON detalles BEGIN SELECT RAISE(ABORT,'fallo simulado');END;");
try{$s->revertir(1,null,'fallo');throw new Exception('Debió fallar');}catch(PDOException $e){ck(true,'Fallo SQL propagado');}
ck((int)$db->query('SELECT count(*) FROM detallehistorial')->fetchColumn()===1,'Rollback historial');ck((int)$db->query('SELECT estado FROM procesos')->fetchColumn()===2,'Rollback procesos');ck((int)$db->query('SELECT c_estado FROM detalles')->fetchColumn()===3,'Rollback detalle');ck((int)$db->query('SELECT c_estado FROM ordenes')->fetchColumn()===2,'Rollback orden');
$pdf=getenv('TRACE_TEST_PDF');if($pdf){$items=parsearPdf($pdf);ck(count($items)===14,'PLA NAVARRO: 14 ítems');ck(array_column($items,'perfil')===['C8144','C8144','C8220','C8222','C8222','C8222','C8266','C8266','CAMA15','CAMA15','RA4263','RA4263','C8215','C8219'],'Perfiles exactos');ck(array_count_values(array_column($items,'pagina'))===[1=>8,2=>6],'Distribución 8+6 por página');}
echo 'TOTAL '.$n.PHP_EOL;
