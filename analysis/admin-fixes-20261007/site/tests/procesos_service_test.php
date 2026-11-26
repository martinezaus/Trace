<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../include/procesos_fecha.php';
require_once __DIR__.'/../include/proceso_service.php';
require_once __DIR__.'/../include/corte_pdf_service.php';
$checks=0;
function ok($v,$label){global $checks;if(!$v)throw new RuntimeException('FAIL '.$label);$checks++;echo "PASS $label\n";}
function rejects($fn,$label){try{$fn();}catch(Throwable $e){ok(true,$label);return;}ok(false,$label);}
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
foreach([
'operadores'=>'id INTEGER PRIMARY KEY,nombre TEXT',
'sesiones'=>'id INTEGER PRIMARY KEY,operador_id INTEGER,estado TEXT,fecha_fin TEXT',
'ordenes'=>'id INTEGER PRIMARY KEY,codigo TEXT,c_estado INTEGER',
'documentos'=>'id INTEGER PRIMARY KEY,orden_id INTEGER,nombre TEXT,descripcion TEXT,tipo INTEGER,archivo_final TEXT',
'documentositems'=>'id INTEGER PRIMARY KEY,documento_id INTEGER,tilde INTEGER',
'detalles'=>'id INTEGER PRIMARY KEY AUTOINCREMENT,orden_id INTEGER,operador_id INTEGER,codigo TEXT,tipologia TEXT,descripcion TEXT,etapas INTEGER,tiempo_estimado TEXT,tiempo_real TEXT,c_estado INTEGER',
'procesos'=>'id INTEGER PRIMARY KEY AUTOINCREMENT,detalle_id INTEGER,tarea_id INTEGER,estado INTEGER,operador_id INTEGER,operador_aux INTEGER DEFAULT 0,inicio TEXT,created TEXT,fin TEXT,tiempo_real TEXT',
'eventos'=>'id INTEGER PRIMARY KEY AUTOINCREMENT,proceso_id INTEGER,tipo TEXT,descripcion TEXT,tiempo_real TEXT,operador_id INTEGER,created TEXT',
'pausas'=>'id INTEGER PRIMARY KEY,descripcion TEXT,flag INTEGER',
'systemnegocio'=>'id INTEGER PRIMARY KEY,tiempo_cortep TEXT,tiempo_cortev TEXT',
'cortepdf'=>"documento_id INTEGER PRIMARY KEY,detalle_id INTEGER,proceso_id INTEGER,estado TEXT,operador_id INTEGER,sesion_id INTEGER,elapsed_ms INTEGER DEFAULT 0,active_since INTEGER DEFAULT 0,heartbeat_ms INTEGER DEFAULT 0,pause_since INTEGER DEFAULT 0,pause_suma INTEGER DEFAULT 0,evento_id INTEGER,token TEXT DEFAULT ''",
'pendientes'=>'id INTEGER PRIMARY KEY AUTOINCREMENT,orden_id INTEGER,detalle_id INTEGER,proceso_id INTEGER,sesion_id INTEGER,tarea_actual_id INTEGER,tiempo_orden_ms INTEGER,tiempo_tarea_ms INTEGER,tiempo_tarea_total_ms INTEGER,tiempo_operador_ms INTEGER,menu INTEGER,valided INTEGER DEFAULT 0,updated TEXT',
 'tareas'=>'id INTEGER PRIMARY KEY'
]as $table=>$cols)$db->exec("CREATE TABLE $table ($cols)");
$db->exec("INSERT INTO operadores VALUES (1,'A'),(2,'B'); INSERT INTO sesiones VALUES(1,1,'activa',NULL),(2,2,'activa',NULL),(3,1,'activa',NULL); INSERT INTO ordenes VALUES(1,'15048',1),(2,'15049',2); INSERT INTO tareas VALUES(7),(8); INSERT INTO pausas VALUES(1,'Descanso',0),(2,'Preparación',1); INSERT INTO systemnegocio VALUES(1,'00:10:00','00:20:00');");
for($i=1;$i<=8;$i++)$db->exec("INSERT INTO documentos(id,orden_id,nombre,tipo) VALUES($i,1,'PDF $i',2)");
$db->exec("INSERT INTO documentos VALUES(9,2,'Terminada','',2,NULL); INSERT INTO documentos VALUES(10,1,'OP','',1,NULL); INSERT INTO documentositems VALUES(1,1,0),(2,1,0),(3,3,1)");
$service=new ProcesoService($db);
foreach([0,59999,60000,60001,90000,90000000]as $ms){
 $db->exec("INSERT INTO detalles(c_estado) VALUES(0)");$detail=(int)$db->lastInsertId();
 $p=$service->start(7,$detail,1,0);$r=$service->close($p['id'],$ms,2,'armado');
 ok($r['descartada']===($ms<=60000),"Armado límite $ms ms");
 $before=$db->query('SELECT COUNT(*) FROM eventos')->fetchColumn();
 $again=$service->close($p['id'],999999,2,'armado');ok($again['descartada']===$r['descartada'],'Reintento conserva resultado');
 ok($before===$db->query('SELECT COUNT(*) FROM eventos')->fetchColumn(),'Reintento no duplica evento');
 if($r['descartada']){$restart=$service->start(7,$detail,1,0);ok($restart['tiempo_ms']===0 && !$restart['retomada'],'Reinicio de descartada desde cero');}
 else rejects(fn()=>$service->start(7,$detail,1,0),'No reiniciar completada');
}
$db->exec('INSERT INTO detalles(c_estado) VALUES(0)');$detail=(int)$db->lastInsertId();$p=$service->start(7,$detail,1,0);
rejects(fn()=>$service->start(8,$detail,1,0),'Una tarea activa por tipología');
rejects(fn()=>$service->start(7,$detail,2,0),'No apropiarse de tarea de otro operador');
foreach([-1,'NaN',null,'12.3']as $bad)rejects(fn()=>$service->close($p['id'],$bad,2,'armado'),'Rechazar duración inválida '.json_encode($bad));
ok($service->close($p['id'],1000,2,'corte')['descartada']===false,'Corte nunca aplica descarte de 60 s');
// Fallo al persistir evento: tampoco debe quedar el proceso cerrado.
$db->exec('INSERT INTO detalles(c_estado) VALUES(0)');$detail=(int)$db->lastInsertId();$p=$service->start(7,$detail,1,0);
$db->exec("CREATE TRIGGER fail_event BEFORE INSERT ON eventos BEGIN SELECT RAISE(ABORT,'simulated'); END");
rejects(fn()=>$service->close($p['id'],1000,2,'armado'),'Fallo de evento provoca rollback');
ok((int)$db->query('SELECT estado FROM procesos WHERE id='.$p['id'])->fetchColumn()===1,'Rollback conserva tarea activa');$db->exec('DROP TRIGGER fail_event');
// Suspensión atómica: incluso el fallo del evento revierte pendiente y estado.
$db->exec('INSERT INTO detalles(orden_id,c_estado) VALUES(1,0)');$detail=(int)$db->lastInsertId();$p=$service->start(7,$detail,1,0);
$data=['ordenId'=>1,'detalleId'=>$detail,'procesoId'=>$p['id'],'sesionId'=>1,'operadorId'=>1,'tiempoTareaMs'=>65001];
$db->exec("CREATE TRIGGER fail_event BEFORE INSERT ON eventos BEGIN SELECT RAISE(ABORT,'simulated'); END");
rejects(fn()=>$service->savePending($data),'Suspensión con fallo de evento revierte todo');
ok((int)$db->query('SELECT estado FROM procesos WHERE id='.$p['id'])->fetchColumn()===1,'Suspensión fallida conserva proceso activo');
ok((int)$db->query('SELECT COUNT(*) FROM pendientes')->fetchColumn()===0,'Suspensión fallida no deja pendiente huérfano');
$db->exec('DROP TRIGGER fail_event');
$now=1000000;$c=new CortePdfService($db,function()use(&$now){return $now;});
$t=$c->action('iniciar',1,1,1)['tarea'];$token=$t['token'];
ok($t['estado']==='activa','Corte inicia PDF');
rejects(fn()=>$c->action('iniciar',1,1,2),'No abrir dos PDF activos');
rejects(fn()=>$c->action('iniciar',2,2,1),'No tomar PDF de otro operador');
rejects(fn()=>$c->action('guardar',1,1,1,'old'),'Rechazar token obsoleto');
rejects(fn()=>$c->action('guardar',1,3,1,$token),'Rechazar otra jornada');
$now+=10000;$t=$c->action('guardar',1,1,1,$token)['tarea'];ok((int)$t['elapsed_ms']===10000,'Heartbeat acumula 10 s');
$now+=10000;$t=$c->action('pausa',1,1,1,$token,1)['tarea'];ok((int)$t['elapsed_ms']===20000 && $t['estado']==='pausa','Pausa guarda tramo');
rejects(fn()=>$c->action('finalizar',1,1,1,$token,0,true),'No finalizar en pausa');
rejects(fn()=>$c->action('jornada',1,1,1,$token),'No cerrar jornada en pausa');
$now+=60000;$t=$c->action('reanudar',1,1,1,$token)['tarea'];ok((int)$t['elapsed_ms']===20000,'Pausa sin suma excluida');
$t=$c->action('reanudar',1,1,1,$token)['tarea'];ok((int)$t['elapsed_ms']===20000,'Reanudar repetido no duplica tiempo');
$now+=5000;$c->action('pausa',1,1,1,$token,2);$now+=15000;$t=$c->action('reanudar',1,1,1,$token)['tarea'];ok((int)$t['elapsed_ms']===40000,'Pausa con suma incluida');
$now+=5000;rejects(fn()=>$c->action('finalizar',1,1,1,$token),'Exigir confirmación de parcial');
$t=$c->action('finalizar',1,1,1,$token,0,true)['tarea'];ok($t['estado']==='finalizada'&&(int)$t['elapsed_ms']===45000,'Cierre parcial confirmado');
$now+=100000;$t=$c->action('finalizar',1,1,1,$token,0,true)['tarea'];ok((int)$t['elapsed_ms']===45000,'Cierre repetido idempotente');
ok($c->action('iniciar',1,1,1)['soloLectura']===true,'PDF finalizado solo consulta');
ok($c->action('iniciar',1,1,3)['soloLectura']===true,'PDF ya tildado no genera tarea nueva');
$t=$c->action('iniciar',1,1,2)['tarea'];$token=$t['token'];$now+=3600000;$t=$c->action('guardar',1,1,2,$token)['tarea'];ok((int)$t['elapsed_ms']===30000,'Desconexión no suma una hora');
$c->action('jornada',1,1,2,$token);ok($db->query('SELECT estado FROM sesiones WHERE id=1')->fetchColumn()==='cerrada_con_actividad','Jornada persiste pendiente y cierra sesión');
$t=$c->action('iniciar',1,3,2)['tarea'];ok((int)$t['elapsed_ms']===30000 && $t['token']!==$token,'Recuperar otra jornada conserva tiempo y rota token');
$token=$t['token'];$c->action('pendiente',1,3,2,$token);$t=$c->action('iniciar',2,2,2)['tarea'];ok((int)$t['operador_id']===2,'Otro operador puede retomar pendiente');
rejects(fn()=>$c->action('iniciar',1,3,9),'Orden terminada no admite corte');
rejects(fn()=>$c->action('iniciar',1,3,10),'Documento OP no admite tarea de corte');
$t=$c->action('iniciar',1,3,4)['tarea'];$db->exec('INSERT INTO documentositems VALUES(4,4,1)');$done=$c->completeMarked(4);ok($done['estado']==='finalizada','Último tilde finaliza PDF');
ok($c->action('guardar',1,3,4,$t['token'])['tarea']['estado']==='finalizada','Heartbeat posterior no reactiva PDF finalizado');
echo "TOTAL $checks comprobaciones correctas\n";
