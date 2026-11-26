from pathlib import Path
import subprocess,json,hashlib
root=Path.cwd();area=root/'analysis/release-0011';spec=json.loads((area/'staging.json').read_text());stage=Path(spec['path']);project=spec['project']
assert stage.resolve().is_relative_to(area.resolve()) and project.startswith('trace-release0011-qa-')
manifest=json.loads((root/'.actualizaciones/release-0011/manifest.json').read_text())
compose=['docker','compose','--project-directory',str(stage),'-f',str(stage/'docker-compose.yml'),'--project-name',project]
def php(code):return subprocess.run(compose+['exec','-T','php','php','-r',code],capture_output=True,text=True,encoding='utf-8',check=True).stdout.strip()
def snapshot():
 code='require "/var/www/html/include/config.php";$d=new PDO("mysql:host=".hostBD.";dbname=".nombreBD,usuarioBD,claveBD,[PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$all=[];foreach($d->query("SHOW TABLES") as $r){$t=array_values($r)[0];if(in_array($t,["systemreleases","systemproductividad"]))continue;$all[$t]=$d->query("SELECT * FROM `".$t."`")->fetchAll();}echo hash("sha256",json_encode($all,JSON_THROW_ON_ERROR));'
 return php(code)
def installer(name,apply=False,success=True,message=None):
 command=['powershell.exe','-NoProfile','-ExecutionPolicy','Bypass','-File',str(root/'.actualizaciones/release-0011/update.ps1'),'-Target',str(stage)]
 if apply:command+=['-Apply']
 r=subprocess.run(command,capture_output=True,text=True,encoding='utf-8',errors='replace');(area/(name+'.log')).write_text(r.stdout+r.stderr,encoding='utf-8')
 assert (r.returncode==0)==success,(name,r.returncode,(r.stdout+r.stderr)[-1800:])
 if message:assert message in r.stdout+r.stderr,(name,'Motivo incorrecto',r.stdout+r.stderr)
 print('PASS',name,flush=True)
before=snapshot()
basehash={e['path']:hashlib.sha256((stage/'src'/e['path']).read_bytes()).hexdigest() for e in manifest['files'] if e['previousSha256']}
php('require "/var/www/html/include/config.php";$d=new PDO("mysql:host=".hostBD.";dbname=".nombreBD,usuarioBD,claveBD);$d->exec("UPDATE systemreleases SET status=\'failed\' WHERE release_id=\'0010\'");')
installer('staging-requires-0010',success=False,message='Requiere release 0010 aplicado')
php('require "/var/www/html/include/config.php";$d=new PDO("mysql:host=".hostBD.";dbname=".nombreBD,usuarioBD,claveBD);$d->exec("UPDATE systemreleases SET status=\'applied\' WHERE release_id=\'0010\'");')
installer('staging-simulate')
assert before==snapshot()
assert all(hashlib.sha256((stage/'src'/p).read_bytes()).hexdigest()==h for p,h in basehash.items())
assert php('require "/var/www/html/include/config.php";$d=new PDO("mysql:host=".hostBD.";dbname=".nombreBD,usuarioBD,claveBD);echo $d->query("SHOW TABLES LIKE \'systemproductividad\'")->fetchColumn()?:"ausente";')=='ausente'
installer('staging-apply',apply=True)
assert before==snapshot(),'La aplicación cambió datos de negocio'
for e in manifest['files']:assert hashlib.sha256((stage/'src'/e['path']).read_bytes()).hexdigest().upper()==e['sha256'],e['path']
assert php('require "/var/www/html/include/reportes_service.php";$d=reportDatabase();echo $d->query("SELECT COUNT(*) FROM systemproductividad")->fetchColumn();')=='0'
assert php('require "/var/www/html/include/reportes_service.php";$d=reportDatabase();echo $d->query("SELECT status FROM systemreleases WHERE release_id=\'0011\'")->fetchColumn();')=='applied'
backups=list((stage/'update-backups').glob('release-0011_*'));assert len(backups)==1
assert (backups[0]/'database.sql').stat().st_size>100
assert (backups[0]/'new-files.json').exists()
installer('staging-reapply',apply=True)
assert len(list((stage/'update-backups').glob('release-0011_*')))==1
assert before==snapshot()
(area/'staging-results.json').write_text(json.dumps({'project':project,'checks':['rechazo sin 0010','simulación sin DDL ni cambios de código','respaldo de código y BD','aplicación real sobre base 0010','21 archivos verificados','datos de negocio ficticios conservados','configuración creada sin tarifas','registro 0011 aplicado','reaplicación idempotente'],'business_sha256_before':before,'business_sha256_after':snapshot()},indent=2),encoding='utf-8')
print('OK: aplicación real verificada en Docker aislado; producción intacta.',flush=True)
