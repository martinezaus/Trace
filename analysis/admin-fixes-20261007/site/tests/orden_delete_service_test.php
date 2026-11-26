<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../include/orden_delete_service.php';
$count=0;function check($v,$label){global $count;if(!$v)throw new RuntimeException('FAIL '.$label);$count++;echo "PASS $label\n";}
function fixture(): array {
 $db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$db->exec('PRAGMA foreign_keys=ON');
 foreach([
 'ordenes'=>'id INTEGER PRIMARY KEY,c_estado INTEGER,archivo TEXT',
 'detalles'=>'id INTEGER PRIMARY KEY,orden_id INTEGER REFERENCES ordenes(id)',
 'lotes'=>'id INTEGER PRIMARY KEY,orden_id INTEGER REFERENCES ordenes(id)',
 'documentos'=>'id INTEGER PRIMARY KEY,orden_id INTEGER REFERENCES ordenes(id),nombre TEXT,archivo_final TEXT',
 'documentositems'=>'id INTEGER PRIMARY KEY,documento_id INTEGER REFERENCES documentos(id)',
 'procesos'=>'id INTEGER PRIMARY KEY,detalle_id INTEGER REFERENCES detalles(id)',
 'pendientes'=>'id INTEGER PRIMARY KEY,orden_id INTEGER REFERENCES ordenes(id)',
 'cortepdf'=>'documento_id INTEGER PRIMARY KEY',
 'detallehistorial'=>'id INTEGER PRIMARY KEY,orden_id INTEGER'
 ]as $t=>$cols)$db->exec("CREATE TABLE $t ($cols)");
 $db->exec("INSERT INTO ordenes VALUES(1,0,'orden.dat'); INSERT INTO detalles VALUES(1,1); INSERT INTO lotes VALUES(1,1); INSERT INTO documentos VALUES(1,1,'orden.pdf','orden_1.pdf'); INSERT INTO documentositems VALUES(1,1)");
 $dir=sys_get_temp_dir().'/trace-delete-test-'.bin2hex(random_bytes(8));mkdir($dir);mkdir($dir.'/modificados');
 foreach(['orden.dat','orden.pdf','modificados/orden_1.pdf']as $f)file_put_contents($dir.'/'.$f,'fixture '.$f);
 return [$db,$dir,new OrdenDeleteService($db,$dir)];
}
function cleanup(string $dir): void { // Sólo el directorio efímero creado por fixture.
 if(!str_starts_with($dir,sys_get_temp_dir().'/trace-delete-test-'))throw new RuntimeException('Ruta de prueba inválida');
 $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
 foreach($it as $p){if($p->isDir()&&!$p->isLink())rmdir($p->getPathname());else unlink($p->getPathname());}rmdir($dir);
}
foreach([1,2,3,4,-1,null]as $state){[$db,$dir,$s]=fixture();try{$q=$db->prepare('UPDATE ordenes SET c_estado=?');$q->execute([$state]);try{$s->delete(1);check(false,'Debe bloquear estado');}catch(OrdenDeleteDenied $e){check(true,'Rechaza estado '.json_encode($state));}check((int)$db->query('SELECT COUNT(*) FROM ordenes')->fetchColumn()===1,'Conserva orden bloqueada');check(file_exists($dir.'/orden.dat')&&file_exists($dir.'/modificados/orden_1.pdf'),'Conserva archivos de orden bloqueada');}finally{cleanup($dir);}}
[$db,$dir,$s]=fixture();try{$r=$s->delete(1);check($r['resultado']==='ok','Elimina cargada');foreach(['ordenes','detalles','lotes','documentos','documentositems']as $t)check((int)$db->query("SELECT COUNT(*) FROM $t")->fetchColumn()===0,'Elimina dependencias '.$t);check(!file_exists($dir.'/orden.dat')&&!file_exists($dir.'/orden.pdf')&&!file_exists($dir.'/modificados/orden_1.pdf'),'Elimina DAT, PDF original y modificado');try{$s->delete(1);check(false,'No existe');}catch(OrdenDeleteDenied $e){check(true,'Segundo intento informa orden inexistente');}}finally{cleanup($dir);}
foreach(['procesos'=>'INSERT INTO procesos VALUES(1,1)','pendientes'=>'INSERT INTO pendientes VALUES(1,1)','cortepdf'=>'INSERT INTO cortepdf VALUES(1)','detallehistorial'=>'INSERT INTO detallehistorial VALUES(1,1)']as $name=>$sql){[$db,$dir,$s]=fixture();try{$db->exec($sql);try{$s->delete(1);check(false,'Actividad debe bloquear');}catch(OrdenDeleteDenied $e){check(true,'Bloquea actividad '.$name);}check(file_exists($dir.'/orden.pdf'),'No toca archivo con actividad');}finally{cleanup($dir);}}
[$db,$dir,$s]=fixture();try{$db->exec("CREATE TRIGGER fail_delete BEFORE DELETE ON ordenes BEGIN SELECT RAISE(ABORT,'fallo inducido'); END");try{$s->delete(1);check(false,'Debe fallar SQL');}catch(PDOException $e){check(true,'Fallo SQL propagado');}foreach(['ordenes','detalles','lotes','documentos','documentositems']as $t)check((int)$db->query("SELECT COUNT(*) FROM $t")->fetchColumn()===1,'Rollback '.$t);check(file_get_contents($dir.'/orden.pdf')==='fixture orden.pdf'&&file_exists($dir.'/modificados/orden_1.pdf'),'Fallo SQL restaura archivos apartados');check(count(glob($dir.'/.trace-delete-*'))===0,'Rollback limpia staging');}finally{cleanup($dir);}
[$db,$dir,$s]=fixture();try{unlink($dir.'/orden.pdf');check($s->delete(1)['resultado']==='ok','Archivo ausente no impide borrar cargada');}finally{cleanup($dir);}
[$db,$dir,$s]=fixture();try{$db->exec("INSERT INTO ordenes VALUES(2,0,'orden.dat'); INSERT INTO documentos VALUES(2,2,'orden.pdf','orden_1.pdf')");$s->delete(1);check(file_exists($dir.'/orden.dat')&&file_exists($dir.'/orden.pdf')&&file_exists($dir.'/modificados/orden_1.pdf'),'Preserva archivos usados por otra orden');check((int)$db->query('SELECT COUNT(*) FROM ordenes WHERE id=2')->fetchColumn()===1,'No toca otra orden');}finally{cleanup($dir);}
foreach(['../escape.dat','sub/f.dat','..\\escape.dat','C:archivo.dat']as $bad){[$db,$dir,$s]=fixture();try{$db->prepare('UPDATE ordenes SET archivo=?')->execute([$bad]);try{$s->delete(1);check(false,'Ruta debe bloquear');}catch(OrdenDeleteDenied $e){check(true,'Rechaza ruta '.$bad);}check(file_exists($dir.'/orden.pdf'),'Ruta inválida no borra otros archivos');}finally{cleanup($dir);}}
[$db,$dir,$s]=fixture();try{unlink($dir.'/orden.pdf');mkdir($dir.'/orden.pdf');try{$s->delete(1);check(false,'Directorio debe bloquear');}catch(OrdenDeleteDenied $e){check(true,'Rechaza directorio en lugar de archivo');}check(file_exists($dir.'/orden.dat'),'No aparta archivos antes de validar todo');}finally{cleanup($dir);}
[$db,$dir,$s]=fixture();try{
 $db->sqliteCreateFunction('nuevo_archivo',function()use($dir){file_put_contents($dir.'/orden.pdf','nuevo archivo');return 1;});
 $db->exec("CREATE TRIGGER fail_recovery BEFORE DELETE ON ordenes BEGIN SELECT nuevo_archivo(); SELECT RAISE(ABORT,'fallo con archivo nuevo'); END");
 try{$s->delete(1);check(false,'Debe fallar recuperación');}catch(RuntimeException $e){check(str_contains($e->getMessage(),'recuperación'),'Informa recuperación pendiente');}
 check(file_get_contents($dir.'/orden.pdf')==='nuevo archivo','Recuperación no sobrescribe archivo nuevo');
 $stages=glob($dir.'/.trace-delete-*');check(count($stages)===1 && is_file($stages[0].'/recuperacion.json'),'Conserva plan y archivos apartados para soporte');
}finally{cleanup($dir);}
echo "TOTAL $count comprobaciones correctas\n";
