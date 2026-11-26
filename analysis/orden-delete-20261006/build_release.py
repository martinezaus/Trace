from pathlib import Path
import json,hashlib,shutil
r=Path('.actualizaciones/release-0007');old=Path('.actualizaciones/release-0006');r.mkdir(exist_ok=True)
for d in ['payload','migrations','tests/baseline']:(r/d).mkdir(parents=True,exist_ok=True)
files=['api/ordenes.php','include/orden_delete_service.php','plugins/js/jquery.ordenes.js','include/footer.php'];h=lambda p:hashlib.sha256(p.read_bytes()).hexdigest().upper();entries=[]
for f in files:
 dest=r/'payload'/f;dest.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(Path('src')/f,dest)
 base=Path('backups/orden_delete_20261006')/f;e={'path':f,'sha256':h(dest),'previousSha256':h(base)if base.exists()else None}
 if base.exists():
  b=r/'tests/baseline'/f;b.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(base,b)
 entries.append(e)
for f in ['trace.bat','update.ps1','migrations/002_release_registry.php','tests/installer_test.ps1','tests/FakeDocker.cs']:
 s=(old/f).read_text(encoding='utf-8-sig').replace('0006','0007').replace('1.0.5','1.0.6')
 if f=='update.ps1':
  s=s.replace('004_process_events.php','005_delete_preflight.php').replace('Esquema de eventos: $(@($preview.cambios).Count) cambios necesarios.','Dependencias de eliminacion verificadas. No hay migraciones de datos.')
  s=s.replace('eventos-esquema.json','eliminacion-validacion.json').replace('Migracion de eventos aplicada.','Validacion de eliminacion completada.')
 if f=='tests/installer_test.ps1':
  s=s.replace("'api/procesos.php'","'api/ordenes.php'").replace("'payload/api/procesos.php'","'payload/api/ordenes.php'")
 (r/f).write_text(s,encoding='utf-8')
(r/'migrations/005_delete_preflight.php').write_text('''<?php
// Sólo lectura, tanto en simulación como durante instalación.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
try {
 require '/var/www/html/include/config.php';
 $db=new PDO('mysql:host='.hostBD.';dbname='.nombreBD.';charset=utf8mb4',usuarioBD,claveBD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 foreach([
  'SELECT id,c_estado,archivo FROM ordenes LIMIT 0',
  'SELECT id,orden_id FROM detalles LIMIT 0',
  'SELECT id,orden_id FROM lotes LIMIT 0',
  'SELECT id,orden_id,nombre,archivo_final FROM documentos LIMIT 0',
  'SELECT id,documento_id FROM documentositems LIMIT 0',
  'SELECT id,detalle_id FROM procesos LIMIT 0',
  'SELECT id,orden_id FROM pendientes LIMIT 0'
 ]as $sql)$db->query($sql);
 if(!is_dir('/var/www/html/tmp'))throw new RuntimeException('No existe el directorio tmp de la instalación.');
 echo json_encode(['ok'=>true,'cambios'=>[],'mensaje'=>'Dependencias verificadas. Sin modificaciones de datos.'],JSON_THROW_ON_ERROR);
}catch(Throwable $e){fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
''',encoding='utf-8')
for f in ['orden_delete_service_test.php','orden_delete_mysql_test.php','orden_delete_frontend_test.cjs']:
 s=(Path('src/tests')/f).read_text(encoding='utf-8-sig')
 s=s.replace("../include/orden_delete_service.php","../payload/include/orden_delete_service.php")
 if f.endswith('.cjs'):s=s.replace("'src/plugins/js/jquery.ordenes.js'","require('node:path').join(__dirname,'../payload/plugins/js/jquery.ordenes.js')")
 (r/'tests'/f).write_text(s,encoding='utf-8')
m={'version':'release-0007','release_id':'0007','release_version':'1.0.6','files':entries,'packageFiles':[{'path':f,'sha256':h(r/f)}for f in ['trace.bat','update.ps1','migrations/002_release_registry.php','migrations/005_delete_preflight.php']]}
(r/'manifest.json').write_text(json.dumps(m,indent=2)+'\n',encoding='utf-8')
print('Release 0007:',len(entries),'archivos')
