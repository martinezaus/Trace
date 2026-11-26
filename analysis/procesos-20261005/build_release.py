from pathlib import Path
import shutil,json,hashlib,zipfile
root=Path('.actualizaciones/release-0006');root.mkdir(exist_ok=True)
old=Path('.actualizaciones/release-0005')
for d in ['payload','migrations','tests/baseline']:(root/d).mkdir(parents=True,exist_ok=True)
files=[e['path'] for e in json.loads(Path('analysis/procesos-20261005/manifest.json').read_text())]+['include/procesos_fecha.php','include/corte_pdf_service.php']
hashfile=lambda p:hashlib.sha256(p.read_bytes()).hexdigest().upper()
entries=[]
for f in files:
 dest=root/'payload'/f;dest.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(Path('src')/f,dest)
 baseline=Path('backups/procesos_20261005_revision')/f
 if not baseline.exists() and (old/'payload'/f).exists():baseline=old/'payload'/f
 entry={'path':f,'sha256':hashfile(dest),'previousSha256':hashfile(baseline) if baseline.exists()else None}
 if baseline.exists():
  b=root/'tests/baseline'/f;b.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(baseline,b)
 alternatives=set()
 if (old/'payload'/f).exists():alternatives.add(hashfile(old/'payload'/f))
 for zpath in Path('.actualizaciones').glob('release-*.zip'):
  with zipfile.ZipFile(zpath)as z:
   for n in z.namelist():
    if n.replace('\\','/').endswith('/payload/'+f)or n.replace('\\','/')=='payload/'+f:alternatives.add(hashlib.sha256(z.read(n)).hexdigest().upper())
 alternatives.discard(entry['previousSha256']);alternatives.discard(entry['sha256'])
 if alternatives:entry['previousSha256Alternatives']=sorted(alternatives)
 entries.append(entry)
for f in ['trace.bat','migrations/002_release_registry.php']:
 s=(old/f).read_text(encoding='utf-8-sig').replace('0005','0006').replace('1.0.4','1.0.5');(root/f).write_text(s,encoding='utf-8')
s=(old/'update.ps1').read_text(encoding='utf-8-sig').replace('0005','0006')
s=s.replace("$DatesCode=Get-Content", "$SchemaCode=Get-Content").replace('migrations\\003_process_dates.php','migrations\\004_process_events.php').replace('$DatesCode','$SchemaCode').replace('TRACE_APPLY_DATES','TRACE_APPLY_SCHEMA')
s=s.replace('Write-Host "Fechas historicas: $($preview.corregidos) corregibles; $($preview.revision) para revision sin modificar."','Write-Host "Esquema de eventos: $(@($preview.cambios).Count) cambios necesarios."')
s=s.replace('$datesResult','$schemaResult').replace('fechas-procesos.json','eventos-esquema.json').replace('$dates=$schemaResult', '$schema=$schemaResult')
s=s.replace('Write-Host "Fechas: $($dates.corregidos) corregidas; $($dates.revision) conservadas para revision. Detalle en $Backup\\eventos-esquema.json"','Write-Host "Migracion de eventos aplicada. Detalle en $Backup\\eventos-esquema.json"')
(root/'update.ps1').write_text(s,encoding='utf-8')
s=Path('src/scripts/migrar_eventos_descartados.php').read_text(encoding='utf-8');a=s.index('function migrarEventosDescartados');b=s.index("if(realpath",a)
s='<?php\n'+s[a:b]+'''if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
try {
 require '/var/www/html/include/config.php';
 $db=new PDO('mysql:host='.hostBD.';dbname='.nombreBD.';charset=utf8mb4',usuarioBD,claveBD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $apply=getenv('TRACE_APPLY_SCHEMA')==='1';
 echo json_encode(['aplicado'=>$apply,'cambios'=>migrarEventosDescartados($db,$apply)],JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);
} catch(Throwable $e){fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
'''
(root/'migrations/004_process_events.php').write_text(s,encoding='utf-8')
for f in ['installer_test.ps1','FakeDocker.cs']:
 s=(old/'tests'/f).read_text(encoding='utf-8-sig').replace('0005','0006')
 if f.endswith('.cs'):
  s=s.replace('TRACE_APPLY_DATES=', 'TRACE_APPLY_SCHEMA=')
  start=s.index('  if(args.Any(a=>a.StartsWith("TRACE_APPLY_SCHEMA=")))');end=s.index('\n  var actionArg',start)
  s=s[:start]+'''  if(args.Any(a=>a.StartsWith("TRACE_APPLY_SCHEMA="))){
   if(args.Contains("TRACE_APPLY_SCHEMA=1")&&Environment.GetEnvironmentVariable("FAKE_FAIL")=="1"){Console.Error.WriteLine("Fallo simulado de migracion");return 1;}
   Console.WriteLine("{\\"aplicado\\":true,\\"cambios\\":[]}");return 0;
  }'''+s[end:]
 (root/'tests'/f).write_text(s,encoding='utf-8')
manifest={'version':'release-0006','release_id':'0006','release_version':'1.0.5','files':entries,'packageFiles':[{'path':f,'sha256':hashfile(root/f)}for f in ['update.ps1','trace.bat','migrations/002_release_registry.php','migrations/004_process_events.php']]}
(root/'manifest.json').write_text(json.dumps(manifest,indent=2)+'\n',encoding='utf-8')
print('Release construido:',len(entries),'archivos de producción')
