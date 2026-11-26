<?php
if(PHP_SAPI!=='cli')exit(1);
require '/var/www/html/include/config.php';
if(nombreBD!=='trace_release0011_test')throw new RuntimeException('Esta prueba sólo admite el staging desechable.');
define('TRACE_MIGRATION_TEST',true);require '/tmp/007_productividad.php';
$server=new PDO('mysql:host='.hostBD.';charset=utf8mb4',usuarioBD,claveBD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$created=false;$n=0;
function ck($v,$s){global $n;if(!$v)throw new RuntimeException($s);$n++;echo 'PASS '.$s.PHP_EOL;}
function rejects($fn,$s){try{$fn();}catch(RuntimeException $e){ck(true,$s);return;}ck(false,$s);}
try{
 $server->exec('CREATE DATABASE trace_release0011_migration_test CHARACTER SET utf8mb4');$created=true;
 $db=new PDO('mysql:host='.hostBD.';dbname=trace_release0011_migration_test;charset=utf8mb4',usuarioBD,claveBD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $m=new ProductividadMigration($db);
 ck($m->run(false)['accion']==='create','Plan de creación');
 ck(!$db->query("SHOW TABLES LIKE 'systemproductividad'")->fetchColumn(),'Simulación no crea tablas');
 ck($m->run(true)['accion']==='create','Crear configuración');
 ck((int)$db->query('SELECT COUNT(*) FROM systemproductividad')->fetchColumn()===0,'No cargar tarifas ni tramos');
 $db->exec("INSERT INTO systemproductividad(vigente_desde,moneda,escala_json,creado_por) VALUES('2026-10-14','ARS','[]','QA')");
 $before=$db->query('SELECT * FROM systemproductividad')->fetchAll();
 ck($m->run(false)['accion']==='none','Reconocer configuración existente');
 ck($m->run(true)['registros']===1,'Migración idempotente');
 ck($db->query('SELECT * FROM systemproductividad')->fetchAll()===$before,'Conservar versiones existentes');
 $db->exec('ALTER TABLE systemproductividad MODIFY tarifa_centavos VARCHAR(30) NULL');
 rejects(fn()=>$m->run(false),'Rechazar tipo incompatible en simulación');
 rejects(fn()=>$m->run(true),'Rechazar tipo incompatible al aplicar');
 ck($db->query('SELECT * FROM systemproductividad')->fetchAll()===$before,'No modificar datos ante incompatibilidad');
 $db->exec('ALTER TABLE systemproductividad MODIFY tarifa_centavos BIGINT UNSIGNED NULL; ALTER TABLE systemproductividad DROP COLUMN escala_json');
 rejects(fn()=>$m->run(false),'Rechazar columna faltante');
 $db->exec('ALTER TABLE systemproductividad ADD escala_json TEXT NULL; ALTER TABLE systemproductividad ENGINE=MyISAM');
 rejects(fn()=>$m->run(true),'Rechazar motor no transaccional');
 echo 'TOTAL '.$n.' pruebas MySQL de migración, en base desechable.'.PHP_EOL;
}finally{if($created)$server->exec('DROP DATABASE trace_release0011_migration_test');}
