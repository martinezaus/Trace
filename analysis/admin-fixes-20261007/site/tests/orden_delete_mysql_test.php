<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../include/config.php';require __DIR__.'/../include/orden_delete_service.php';
$db=new PDO('mysql:host='.hostBD.';dbname='.nombreBD.';charset=utf8mb4',usuarioBD,claveBD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
// Sólo tablas temporales en esta conexión; tipos del esquema real, sin copiar registros.
foreach(['ordenes','detalles','lotes','documentos','documentositems','procesos','pendientes','cortepdf','detallehistorial']as $t){$ddl=$db->query('SHOW CREATE TABLE `'.$t.'`')->fetch(PDO::FETCH_NUM)[1];$ddl=str_replace('CREATE TABLE','CREATE TEMPORARY TABLE',$ddl);$ddl=preg_replace('/^\s+CONSTRAINT[^\n]*\n/m','',$ddl);$ddl=preg_replace('/,\n\)/',"\n)",$ddl);$db->exec($ddl);}
function put(PDO $db,string $table,array $row): void {
 foreach($db->query('SHOW COLUMNS FROM `'.$table.'`')as $c){if(array_key_exists($c['Field'],$row)||$c['Null']==='YES'||$c['Default']!==null||str_contains($c['Extra'],'auto_increment'))continue;
 $type=$c['Type'];$row[$c['Field']]=preg_match('/int|decimal|float|double|bit/',$type)?0:(str_starts_with($type,'enum(')?explode("'",$type)[1]:(str_starts_with($type,'json')?'{}':'x'));}
 $db->prepare('INSERT INTO `'.$table.'` (`'.implode('`,`',array_keys($row)).'`) VALUES ('.implode(',',array_fill(0,count($row),'?')).')')->execute(array_values($row));
}
put($db,'ordenes',['id'=>1,'c_estado'=>0,'archivo'=>'fixture.dat']);put($db,'detalles',['id'=>1,'orden_id'=>1]);put($db,'lotes',['id'=>1,'orden_id'=>1]);put($db,'documentos',['id'=>1,'orden_id'=>1,'nombre'=>'fixture.pdf','archivo_final'=>'fixture_1.pdf']);put($db,'documentositems',['id'=>1,'documento_id'=>1]);
$dir=sys_get_temp_dir().'/trace-delete-mysql-'.bin2hex(random_bytes(8));mkdir($dir);mkdir($dir.'/modificados');foreach(['fixture.dat','fixture.pdf','modificados/fixture_1.pdf']as $f)file_put_contents($dir.'/'.$f,'fixture');
$count=0;function check($v,$m){global $count;if(!$v)throw new RuntimeException('FAIL '.$m);$count++;echo "PASS $m\n";}
try{
 $s=new OrdenDeleteService($db,$dir);
 foreach([1,2,3,4]as $state){$db->exec('UPDATE ordenes SET c_estado='.$state.' WHERE id=1');try{$s->delete(1);throw new RuntimeException('No bloqueó estado');}catch(OrdenDeleteDenied $e){check(true,'MySQL bloquea estado '.$state);}check(file_exists($dir.'/fixture.pdf'),'MySQL conserva archivos bloqueados');}
 $db->exec('UPDATE ordenes SET c_estado=0 WHERE id=1');check($s->delete(1)['resultado']==='ok','MySQL elimina cargada');
 foreach(['ordenes','detalles','lotes','documentos','documentositems']as $t)check((int)$db->query('SELECT COUNT(*) FROM `'.$t.'`')->fetchColumn()===0,'MySQL limpia '.$t);
 check(!file_exists($dir.'/fixture.dat')&&!file_exists($dir.'/fixture.pdf')&&!file_exists($dir.'/modificados/fixture_1.pdf'),'MySQL y filesystem coinciden');
 echo "TOTAL $count comprobaciones correctas; ninguna orden real eliminada\n";
}finally{foreach(['fixture.dat','fixture.pdf','modificados/fixture_1.pdf']as $f)if(is_file($dir.'/'.$f))unlink($dir.'/'.$f);rmdir($dir.'/modificados');rmdir($dir);}
