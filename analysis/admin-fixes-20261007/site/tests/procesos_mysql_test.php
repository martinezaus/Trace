<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../scripts/migrar_eventos_descartados.php';
require __DIR__.'/../include/procesos_fecha.php';
require __DIR__.'/../include/proceso_service.php';
$db=new PDO('mysql:host='.hostBD.';dbname='.nombreBD.';charset=utf8mb4',usuarioBD,claveBD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
// Las tablas temporales ocultan las reales SÓLO en esta conexión. No se copia ningún dato.
foreach(['detalles','procesos','eventos','tareas','pendientes','sesiones']as $t){
    $ddl=$db->query('SHOW CREATE TABLE `'.$t.'`')->fetch(PDO::FETCH_NUM)[1];
    $ddl=str_replace('CREATE TABLE','CREATE TEMPORARY TABLE',$ddl);
    $ddl=preg_replace('/^\s+CONSTRAINT[^\n]*\n/m','',$ddl);
    $ddl=preg_replace('/,\n\)/',"\n)",$ddl);
    $db->exec($ddl);
}
$checks=0;function check($v,$msg){global $checks;if(!$v)throw new RuntimeException('FAIL '.$msg);$checks++;echo "PASS $msg\n";}
$db->exec("INSERT INTO detalles(id,orden_id,codigo,tipologia,c_estado) VALUES(1,1,'0000000000001','PF1',0)");
// Campos mínimos reales de tareas: descubrir columnas sin default para no asumir el catálogo.
$fields=[];$values=[];foreach($db->query('SHOW COLUMNS FROM tareas')as $col){if($col['Field']==='id'){$fields[]='`id`';$values[]='7';continue;}if($col['Null']==='NO'&&$col['Default']===null&&!str_contains($col['Extra'],'auto_increment')){$fields[]='`'.$col['Field'].'`';$values[]=preg_match('/int|decimal|float|double|bit/',$col['Type'])?'0':$db->quote('T01');}}
$db->exec('INSERT INTO tareas ('.implode(',',$fields).') VALUES ('.implode(',',$values).')');
$db->exec("INSERT INTO sesiones(id,operador_id,estado) VALUES(1,1,'activa')");
// Reproducir siempre el esquema original, sólo en la tabla temporal vacía.
$db->exec("ALTER TABLE eventos MODIFY tipo ENUM('pausa','suspension','reanudar','cierre_jornada') NULL, MODIFY descripcion VARCHAR(100) NULL");
$s=new ProcesoService($db);$p=$s->start(7,1,1,0);
$before=$db->query("SHOW COLUMNS FROM eventos LIKE 'tipo'")->fetch()['Type'];
if(!str_contains($before,"'lectura_descartada'")){
 try{$s->close($p['id'],60000,2,'armado');throw new RuntimeException('Debió fallar con ENUM original');}catch(PDOException $e){check(true,'Reproducido: ENUM original rechaza descarte');}
 check((int)$db->query('SELECT estado FROM procesos')->fetchColumn()===1,'Fallo original conserva proceso abierto');
}
migrarEventosDescartados($db,true);check(migrarEventosDescartados($db,true)===[],'Migración repetible sin cambios adicionales');
$r=$s->close($p['id'],60000,2,'armado');check($r['descartada']===true,'MySQL estricto descarta exactamente 60 segundos');
$s->close($p['id'],90000,2,'armado');check((int)$db->query('SELECT COUNT(*) FROM eventos')->fetchColumn()===1,'MySQL cierre repetido no duplica evento');
$p=$s->start(7,1,1,0);check($p['tiempo_ms']===0,'MySQL permite reiniciar descartada');
$data=['ordenId'=>1,'detalleId'=>1,'procesoId'=>$p['id'],'sesionId'=>1,'operadorId'=>1,'tiempoTareaMs'=>65001,'tiempoOrdenMs'=>70000,'tiempoTareaTotalMs'=>0,'tiempoOperadorMs'=>0,'menu'=>1];
$r=$s->savePending($data);check((int)$db->query('SELECT estado FROM procesos')->fetchColumn()===0,'Suspender persiste proceso pendiente');
check((int)$db->query('SELECT c_estado FROM detalles')->fetchColumn()===2,'Suspender bloquea tipología hasta reactivación');
$s->savePending($data);check((int)$db->query('SELECT COUNT(*) FROM pendientes')->fetchColumn()===1,'Suspensión repetida no duplica pendiente');
$db->exec('UPDATE detalles SET c_estado=0');$p=$s->start(7,1,1,0,$r['pendienteId']);check($p['tiempo_ms']===65001,'Reanudar conserva milisegundos del pendiente');
check((int)$db->query('SELECT valided FROM pendientes')->fetchColumn()===1,'Reanudación consume pendiente');
$r=$s->close($p['id'],65001,2,'armado');check(!$r['descartada'],'Trabajo acumulado supera umbral tras reanudar');
$data['procesoId']=0;$data['tiempoTareaMs']=999;$s->savePending($data,true);check($db->query('SELECT estado FROM sesiones')->fetchColumn()==='cerrada_con_actividad','Cierre entre tareas confirma jornada');
check((int)$db->query('SELECT tiempo_tarea_ms FROM pendientes ORDER BY id DESC LIMIT 1')->fetchColumn()===0,'Cierre entre tareas no inventa duración');
$s->savePending($data,true);check((int)$db->query('SELECT COUNT(*) FROM pendientes')->fetchColumn()===2,'Reintento de cierre no duplica pendiente');
echo "TOTAL $checks comprobaciones MySQL correctas; tablas temporales destruidas al cerrar conexión.\n";
