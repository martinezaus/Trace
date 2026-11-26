from pathlib import Path
import json,hashlib,zipfile
root=Path.cwd();r=root/'.actualizaciones/release-0011';area=root/'analysis/release-0011'
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest().upper()
m=json.loads((r/'manifest.json').read_text())
for e in m['files']:
 assert sha(root/'src'/e['path'])==e['sha256'],('Código cambió después de testear',e['path'])
 assert sha(r/'payload'/e['path'])==e['sha256']
 if e['previousSha256']:assert sha(r/'tests/baseline'/e['path'])==e['previousSha256']
stage=json.loads((area/'staging-results.json').read_text());assert stage['business_sha256_before']==stage['business_sha256_after']
syntax=json.loads((area/'syntax-checks.json').read_text());assert syntax['ok']
tests=['productividad_costos_test.php','reportes_test.php','admin_regression_test.php','procesos_service_test.php','procesos_mysql_test.php','orden_delete_service_test.php','orden_delete_mysql_test.php','codigos_vidrio_pla_test.php','procesos_frontend_test.cjs','orden_delete_frontend_test.cjs','procesos_browser_test.cjs','productividad_costos_browser_test.cjs']
evidence=[]
for name in tests:
 p=area/(name+'.log');assert p.is_file();evidence.append({'suite':name,'estado':'aprobada','log_sha256':sha(p)})
for name in ['reportes-pdf-test.log','migration-test.log','registry-test.log','installer-test.log','simulate-development.log']:
 assert (area/name).is_file()
(r/'PRUEBAS.md').write_text("""# Validación del release 0011 — 14/10/2026

Resultado: aprobado en desarrollo local. Payload de 21 archivos, comparado con base 0010; configuración de conexión, credenciales, documentos y vendor excluidos. Sintaxis PHP/JS verificada en los 18 archivos ejecutables del payload.

## Aplicación

| Suite | Resultado |
|---|---|
| Economía: tarifas, redondeo, tramos, vigencias, versiones y roles | 46 comprobaciones |
| Reportes, cobertura, signos, eventos, retrabajos y costos | 76; 77 incluyendo generación PDF |
| Regresión administrativa y dos reversiones | 21 |
| Servicio de procesos y corte | 69 |
| Procesos con MySQL y tablas temporales | 15 |
| Eliminación de órdenes: servicio / MySQL temporal | 58 / 15 |
| Códigos de vidrio PLA | Casos originales, grupos, material, TELAI, límites, pedido y multipágina aprobados |
| Frontend de procesos / eliminación | 34 / 13 |
| Navegador de procesos (JS real, API simulada) | 16 |
| Navegador de Soporte (PHP/MySQL local, sesión aislada) | 13; simulación sin guardar tarifas |

Las pruebas de aplicación son scripts de desarrollo y necesitan la distribución `src/tests` en una copia de prueba. Se conservan en `tests/application` para revisión y reproducción. No ejecutarlas en producción ni copiar fixtures sobre instalaciones reales.

## Migración y registro

- Migración MySQL: 12 comprobaciones en base desechable. Simulación sin DDL, creación vacía, idempotencia, conservación de versiones existentes y rechazo de columnas/tipos/motores incompatibles.
- Registro técnico: 12 comprobaciones en SQLite en memoria. Check sin crear tabla, ID/version correctos, checksum, estado en curso, fallo, reintento seguro y aplicación sin repetición.
- `tests/registry_test.php` es autónomo. `tests/migration_mysql_test.php` está limitado por guardia a la base de staging; requiere una copia de `007_productividad.php` en `/tmp` y se ejecuta únicamente en el Docker desechable.

## Instalador

Docker simulado y carpetas temporales, incluyendo destino con espacios: simulación sin cambios; copia y respaldo; segunda aplicación idempotente; rechazo de código ajeno; interrupción de migración conserva código/respaldo y bloquea reintento; rechazo de payload alterado.

Instalación real sobre Docker desechable con MySQL 8, base 0010 y datos ficticios: rechazo explícito sin 0010 aplicado; simulación sin DDL ni copia; respaldo SQL/código; aplicación y verificación de 21 archivos; datos de negocio conservados; configuración vacía; registro 0011 aplicado; segunda aplicación sin repetición. Se comparó SHA-256 del conjunto de datos ficticios antes y después: idéntico.

Simulación real contra el entorno de desarrollo: aprobada, sólo lectura. No se aplicó el release en producción. Los respaldos de staging y logs de pruebas se conservan en `analysis/release-0011` en desarrollo; no contienen documentos reales en el paquete.

## Límites

Estas pruebas no certifican todos los datos históricos del cliente, carga masiva, impresoras ni lectores físicos. No se asignan estándares que no existen por proceso. Los límites de atribución, eventos y vigencia económica están detallados en LEEME.md y MANUAL_ADMIN.md.
""",encoding='utf-8')
p=r/'LEEME.md';s=p.read_text(encoding='utf-8').replace('1. Descomprimir el ZIP completo como `.actualizaciones/release-0011` dentro de la instalación de TRACE.', '1. Descomprimir el ZIP completo dentro de `.actualizaciones` de TRACE. El ZIP ya contiene la carpeta `release-0011`: evitar anidarla dos veces.');p.write_text(s,encoding='utf-8')
(r/'VALIDACION.json').write_text(json.dumps({'release':'0011','version':'1.0.11','fecha':'2026-10-14','estado':'aprobado','application_suites':evidence,'syntax':syntax,'staging_checks':stage['checks'],'staging_business_sha256_before':stage['business_sha256_before'],'staging_business_sha256_after':stage['business_sha256_after'],'payload_sha256':{e['path']:e['sha256'] for e in m['files']},'production_applied':False},indent=2)+'\n',encoding='utf-8')
m['packageFiles']=[]
for p in sorted(r.rglob('*')):
 if p.is_file() and 'payload' not in p.relative_to(r).parts and p.name!='manifest.json':m['packageFiles'].append({'path':p.relative_to(r).as_posix(),'sha256':sha(p)})
(r/'manifest.json').write_text(json.dumps(m,indent=2)+'\n',encoding='utf-8')
archive=root/'.actualizaciones/release-0011.zip';assert not archive.exists(),'No sobrescribir un ZIP ya generado'
for p in r.rglob('*'):
 if p.is_file():assert p.name not in ['config.php','.env'] and 'vendor' not in p.relative_to(r).parts
with zipfile.ZipFile(archive,'w',zipfile.ZIP_DEFLATED,compresslevel=9) as z:
 for p in sorted(r.rglob('*')):
  if p.is_file():z.write(p,p.relative_to(r.parent).as_posix())
with zipfile.ZipFile(archive) as z:
 assert z.testzip() is None
 for e in m['files']:
  assert hashlib.sha256(z.read('release-0011/payload/'+e['path'])).hexdigest().upper()==e['sha256']
 for e in m['packageFiles']:
  assert hashlib.sha256(z.read('release-0011/'+e['path'])).hexdigest().upper()==e['sha256']
checksum=sha(archive);(root/'.actualizaciones/release-0011.zip.sha256').write_text(checksum+'  release-0011.zip\n',encoding='ascii')
(area/'final-package.json').write_text(json.dumps({'zip':str(archive),'bytes':archive.stat().st_size,'sha256':checksum,'manifest_sha256':sha(r/'manifest.json'),'payload_files':len(m['files']),'package_files':len(m['packageFiles']),'zip_integrity':'OK'},indent=2),encoding='utf-8')
print('ZIP:',archive.name,'Bytes:',archive.stat().st_size,'Payload:',len(m['files']))
print('SHA256:',checksum)
