<?php
if(PHP_SAPI!=='cli')exit;
define('TRACE_MIGRATION_TEST',true);require '/tmp/006_cortes.php';
$db=new PDO('mysql:host=db;dbname=trace_corte_migration_test_0010;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$n=0;function ck($v,$s){global $n;if(!$v)throw new RuntimeException($s);$n++;echo "PASS $s\n";}
$m=new CorteTableMigration($db);
$r=$m->run(false);ck($r['accion']==='create','Plan de creacion');ck(!$db->query("SHOW TABLES LIKE 'cortes'")->fetchColumn(),'Simulacion no crea tabla');
$m->run(true);ck($m->run(false)['accion']==='none','Creacion e idempotencia');
$db->exec("INSERT INTO cortes(documento_id,detalle_id,proceso_id,operador_id,sesion_id,elapsed_ms,token) VALUES(9,2,3,4,5,123456,'token-preservado'); RENAME TABLE cortes TO cortepdf");
$before=$db->query('SELECT * FROM cortepdf')->fetchAll(PDO::FETCH_ASSOC);
ck($m->run(false)['accion']==='rename','Plan renombrado');ck((bool)$db->query("SHOW TABLES LIKE 'cortepdf'")->fetchColumn(),'Simulacion conserva nombre');
$m->run(true);ck($db->query('SELECT * FROM cortes')->fetchAll(PDO::FETCH_ASSOC)===$before,'Renombrado preserva todos los datos');
ck(!$db->query("SHOW TABLES LIKE 'cortepdf'")->fetchColumn(),'No queda tabla duplicada');
$db->exec('CREATE TABLE cortepdf LIKE cortes');$denied=false;try{$m->run(true);}catch(RuntimeException $e){$denied=true;}ck($denied,'Dos tablas bloquean migracion');ck($db->query('SELECT * FROM cortes')->fetchAll(PDO::FETCH_ASSOC)===$before,'Conflicto conserva datos');
$db->exec('RENAME TABLE cortes TO cortes_prueba_respaldo; ALTER TABLE cortepdf DROP COLUMN token');$denied=false;try{$m->run(true);}catch(RuntimeException $e){$denied=true;}ck($denied,'Esquema incompatible bloquea migracion');
echo "TOTAL $n\n";
