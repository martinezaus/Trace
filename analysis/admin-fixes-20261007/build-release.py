from pathlib import Path
import shutil,json,hashlib
root=Path('.actualizaciones/release-0008'); backup=Path('backups/release-0008-original')
if not backup.exists(): shutil.copytree(root,backup)
z=Path('.actualizaciones/release-0008.zip')
if z.exists() and not Path('backups/release-0008-original.zip').exists(): shutil.copy2(z,'backups/release-0008-original.zip')
def sha(p): return hashlib.sha256(p.read_bytes()).hexdigest().upper()
baseline=json.loads(Path('analysis/admin-fixes-20261007/baseline.json').read_text(encoding='utf-8-sig'))
changed=[]
for p in Path('src').rglob('*'):
 if not p.is_file(): continue
 rel=p.relative_to('src').as_posix()
 if any(part in ['vendor','tmp','logs'] for part in p.relative_to('src').parts): continue
 if sha(p)!=baseline.get(rel): changed.append(rel)
Path('analysis/admin-fixes-20261007/changed.json').write_text(json.dumps(changed,indent=2))
files=[]
for rel in changed:
 if rel.startswith('tests/'): continue
 p=Path('src')/rel; dest=root/'payload'/rel;dest.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(p,dest)
 entry={'path':rel,'sha256':sha(p),'previousSha256':baseline.get(rel)}
 if baseline.get(rel):
  b=root/'tests/baseline'/rel;b.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(Path('backups/admin-fixes-20261007')/rel,b)
 if rel=='api/ordenes.php':
  old=json.loads((backup/'manifest.json').read_text(encoding='utf-8-sig'))['files'][0]
  entry['previousSha256Alternatives']=list(set([old['sha256'],old['previousSha256']]))
 files.append(entry)
p=root/'migrations/002_release_registry.php';s=p.read_text(encoding='utf-8-sig').replace("'0008'","'0008-r2'").replace("'1.0.7'","'1.0.8'");p.write_text(s,encoding='utf-8')
p=root/'update.ps1';s=p.read_text(encoding='utf-8-sig').replace("if($Apply){throw 'No se sobrescriben versiones diferentes. Revisar el paquete contra esa instalacion.'}","throw 'No se sobrescriben versiones diferentes. Revisar el paquete contra esa instalacion.'")
s=s.replace('release 0008 ya','release 0008-r2 ya').replace('Release 0008 ya','Release 0008-r2 ya').replace('TRACE $Version - Destino','TRACE $Version revision 2 - Destino').replace('Dependencias de eliminacion verificadas','Dependencias de administracion verificadas')
p.write_text(s,encoding='utf-8')
p=root/'tests/installer_test.ps1';s=p.read_text(encoding='utf-8-sig').replace("Set-Content $dest 'dependency fixture'","if(!(Test-Path -LiteralPath $dest)){Set-Content $dest 'dependency fixture'}");p.write_text(s,encoding='utf-8')
p=root/'migrations/005_delete_preflight.php';s=p.read_text(encoding='utf-8-sig').replace("'SELECT id,orden_id FROM pendientes LIMIT 0'", "'SELECT id,orden_id FROM pendientes LIMIT 0',\n  'SELECT id,nombre,sesion,corte,token,estado,`update` FROM operadores LIMIT 0',\n  'SELECT tipo,orden_id,detalle_id,operador_id,revertido_por,tiempo_real_anterior,eficiencia_anterior,motivo FROM detallehistorial LIMIT 0'")
s=s.replace(" if(!is_dir", " foreach(['ordenes','detalles','lotes','documentos','documentositems','procesos','detallehistorial','operadores'] as $table){\n  $q=$db->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$table]);\n  if(strcasecmp((string)$q->fetchColumn(),'InnoDB')!==0)throw new RuntimeException('Se requiere InnoDB para transacciones: '.$table);\n }\n if(!is_dir")
p.write_text(s,encoding='utf-8')
readme='''# Release 0008 revisión 2 — 1.0.8

Correcciones del panel de administración e importación DAT/PDF. Requiere release 0007 instalado; admite también el 0008 original. Registro independiente `0008-r2`: conserva los registros de 0007 y 0008, sin alterar sus checksums.

## Instalación remota

Extraer el ZIP completo como `.actualizaciones/release-0008`. Conservar cualquier paquete anterior fuera de esa carpeta antes de reemplazarlo. Mantener Docker iniciado y los usuarios fuera del sistema durante la aplicación. Ejecutar en el servidor, usando la ruta real:

```bat
trace.bat --simulate "D:\\desarrollos\\trace"
trace.bat --apply "D:\\desarrollos\\trace"
```

La simulación no modifica archivos ni base de datos. Debe terminar sin errores antes de aplicar. Se rechazan versiones de archivos desconocidas, incluso en simulación: no forzar ni modificar hashes para saltar este control. La aplicación respalda código y base de datos en `update-backups`, registra un log y verifica los archivos copiados. Una segunda aplicación del mismo paquete verifica y no lo repite. Si queda un intento en curso por interrupción, requiere revisar su respaldo/registro antes de reintentar.

## Cambios

- Importación: corrige conversiones decimales que contaminaban JSON con avisos PHP; transacción y rollback ante errores, detección de duplicados y validación de documentos. Evita reemplazar un archivo subido existente con otro contenido. Los archivos subidos sin orden confirmada pueden permanecer para permitir revisión/reintento.
- Órdenes: mensajes y recargas de tipologías, reactivación, ayudante, reversión transaccional y protección contra repetición; cierre de indicador de carga en errores. Mantiene eliminación sólo en Cargado, servicio de eliminación del 0007 y conexión `R::getPDO()`.
- Operarios: respuestas coherentes, validación en servidor, formularios y errores sin cerrar el modal; productividad usa la columna real. El esquema actual admite una sesión por operario: el formulario lo refleja sin migrar la columna.
- Tablero/Estadísticas: estado correcto de terminadas, una instancia de gráfico, configuración Chart.js 2.7.1, métricas y títulos coherentes, pie sin superposición.
- Reportes: estados de proceso invalidado/descartado y evento lectura_descartada, también filtros/exportaciones.
- Tablas: español, iconos y exportaciones Copiar, CSV compatible con Excel, PDF e impresión. CSV no es un archivo XLSX. Logística permanece deshabilitada porque el módulo no está implementado; Asistencia no se incorpora.

## Base de datos

No agrega ni cambia columnas de negocio y no repara ni elimina órdenes al instalar. Valida esquema y motores InnoDB. Sí escribe el registro técnico `systemreleases` (`0008-r2`); crea esa tabla sólo si no existe. Los cambios de negocio ocurren al utilizar las acciones habituales después de instalar. No reimportar a ciegas las órdenes que antes mostraron HTTP 200/parsererror: pueden estar ya guardadas.

## Validación y alcance

Pruebas en entorno local aislado, nunca en el servidor del cliente. Se reprodujo el fallo con los archivos NAVARRO, se confirmó la importación corregida con 14 ítems y se probaron errores/rollback y duplicados. Regresión de procesos, eliminación, reportes, reversión, navegador y contratos API. El instalador se prueba con Docker simulado, registro real en SQLite y simulación contra Docker local. No implica ausencia absoluta de errores ni cubre impresión física, carga masiva o cada combinación de documentos. Ver DIAGNOSTICO.md para causas y detalle de evidencias.
'''
(root/'LEEME.md').write_text(readme,encoding='utf-8')
manifest={'version':'release-0008','revision':2,'release_id':'0008-r2','release_version':'1.0.8','files':files,'packageFiles':[]}
for rel in ['trace.bat','update.ps1','migrations/002_release_registry.php','migrations/005_delete_preflight.php','LEEME.md']:
 manifest['packageFiles'].append({'path':rel,'sha256':sha(root/rel)})
(root/'manifest.json').write_text(json.dumps(manifest,indent=2)+'\n',encoding='utf-8')
print('Payload:',len(files),'archivos. Original preservado:',backup)
