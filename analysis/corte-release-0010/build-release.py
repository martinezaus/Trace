from pathlib import Path
import hashlib,json,shutil
r=Path('.actualizaciones/release-0010');old=Path('.actualizaciones/release-0009')
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest().upper()
for rel in ['trace.bat','update.ps1','migrations/002_release_registry.php','tests/installer_test.ps1','tests/FakeDocker.cs']:
 p=r/rel;p.parent.mkdir(parents=True,exist_ok=True);s=(old/rel).read_text(encoding='utf-8-sig').replace('0009','0010').replace('1.0.9','1.0.10')
 if rel=='update.ps1':
  s=s.replace('005_delete_preflight.php','006_cortes.php').replace('Dependencias de administracion verificadas. No hay migraciones de datos.', 'Dependencias verificadas. Migracion prevista: $($preview.mensaje). Registros: $($preview.registros).').replace('eliminacion-validacion.json','cortes-migracion.json').replace('Validacion de eliminacion completada.','Migracion de cortes completada.')
 if rel=='tests/installer_test.ps1':s=s.replace('admin.php','proceso_corte.php')
 p.write_text(s,encoding='utf-8')
files=[]
for rel in ['proceso_corte.php','api/corte_pdf.php','api/documentos.php','include/corte_pdf_service.php','include/corte_auth.php','include/footer.php','include/orden_delete_service.php','plugins/js/jquery.corte_pdf.js','scripts/corte_pdf.sql']:
 p=r/'payload'/rel;p.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(Path('src')/rel,p);base=Path('backups/release-0010-base')/rel
 e={'path':rel,'sha256':sha(p),'previousSha256':sha(base) if base.exists() else None}
 if base.exists():dest=r/'tests/baseline'/rel;dest.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(base,dest)
 files.append(e)
text='''# Release 0010 — 1.0.10

Actualización incremental de corte para una instalación con release 0009 (y 0008-r2) aplicado.

## Cambios

- La tabla de estado por PDF pasa a llamarse `cortes`. Se conserva la relación con documentos, detalles, procesos, eventos y sesiones.
- Inicialización: errores explícitos, reintento y salida del acceso disponibles. No quedan pendientes indefinidamente en Cargando.
- Lector exclusivo de corte: reconoce la credencial también en Número de orden, números cortos y códigos O+número; informa códigos desconocidos. No cambia initBarcodeScanner ni las llamadas de armado. El lector de corte tolera hasta 250 ms entre caracteres fuera del campo; dentro del campo se puede escribir o escanear y pulsar Enter. El lector debe enviar Enter. Después de interactuar con el visor PDF nativo, volver al campo de orden, porque el visor puede capturar el teclado.
- Cierre de jornada: espera guardados, preserva pendientes y destruye la autenticación PHP al confirmar. Salir del acceso, ofrecido ante un fallo inicial, no finaliza tareas: quedan para recuperar al ingresar.
- Validación de operador habilitado, contexto de corte y sesión vigente. Si se inicia otro acceso, la sesión anterior no puede seguir guardando.
- Recuperación de otro operario: requiere confirmación y al menos dos minutos sin heartbeat; conserva tiempo, pausa y avances, rota el token y registra un evento. Una pausa conectada no se considera abandonada.
- Corte no descarta tareas por durar 60 segundos o menos. Se verificaron 0, 1, 59,999 y 60 segundos.
- Sólo el contexto de Proceso de Corte con tarea activa puede guardar tildes/observaciones en la API de documentos. Las llamadas actuales de armado se conservan y reciben un rechazo de sólo lectura. No se cambió proceso_armado ni sus scripts, autenticación compartida, cronómetro o lector.
- Las observaciones de ítems tildados se imprimen en un anexo por página/perfil/barra, sin superponerse a las medidas del plano. Se verificó un PDF de dos páginas más su anexo.
- PDF: cada guardado publica una versión nueva. Un fallo de BD no sobrescribe el archivo confirmado anterior. Pueden conservarse versiones anteriores y archivos sin referencia ante un fallo de confirmación; no se borran automáticamente, para evitar perder trabajo.
- Eliminación de órdenes consulta `cortes` para seguir protegiendo las que tienen trabajo registrado.
- No se renombran variables o funciones repetidas sin relación entre ambos procesos.

## Instalación en el servidor

Extraer como `.actualizaciones/release-0010`. Mantener Docker iniciado. Durante la aplicación, todos los operarios deben salir de corte y armado: se actualizan código y esquema. Desde esta carpeta, usando la ruta real:

```bat
trace.bat --simulate "D:\\desarrollos\\trace"
trace.bat --apply "D:\\desarrollos\\trace"
```

La simulación no cambia archivos ni base de datos. Debe finalizar sin errores antes de aplicar. No modificar los hashes ni forzar archivos distintos. Se genera respaldo completo de BD y de los archivos reemplazados en update-backups y registro en update-logs. El identificador técnico es 0010; una segunda aplicación del mismo paquete sólo verifica, sin repetir.

## Migración de base de datos: este release SÍ la incluye

1. Sólo existe cortepdf compatible: `RENAME TABLE cortepdf TO cortes`, preservando todas las filas, tiempos, tokens y asociaciones.
2. No existe ninguna: crea cortes vacía con el esquema requerido.
3. Sólo existe cortes compatible: la conserva sin recrearla.
4. Existen ambas, la estructura es incompatible o faltan dependencias: detiene la simulación/instalación, sin combinar ni eliminar datos.

No se recalculan tiempos, no se eliminan órdenes y no se transforma el histórico de armado. Requiere las tablas existentes de sesiones y producción con InnoDB y la columna operadores.session_token que utiliza el login vigente. Verifica también Ghostscript y escritura en tmp. Al finalizar, volver a ingresar con la credencial. El cambio de nombre puede requerir adaptar consultas externas ajenas al código incluido si usan cortepdf directamente.

DDL de MySQL no se revierte como una transacción normal. Si la aplicación se interrumpe tras iniciar la migración, no forzar reintentos: revisar registro y respaldo. Para una restauración completa, coordinar código y base de datos; no restaurar sólo código después de renombrar la tabla.

## Pruebas

- Servicio de procesos/corte: 69 comprobaciones, incluyendo duración corta, pausas, recuperación y tokens antiguos.
- Armado frontend: 34 comprobaciones; navegador combinado: 16 con APIs simuladas y JS real.
- MySQL temporal procesos: 15; eliminación: 58 de servicio y 15 MySQL; reportes: 57; regresión administrativa: 19.
- Navegador contra MySQL aislado: inicio fallido/reintento, credencial en el campo, orden corta, inicio real, bloqueo de escritura sin contexto, guardado de PDF en versiones distintas y cierre real de autenticación.
- Fallo SQL posterior a generar PDF: rollback conserva tilde/ruta y contenido del PDF anterior; sesión sustituida rechazada en ambas APIs.
- Migración MySQL aislada: creación, simulación sin cambios, renombrado con conservación exacta de datos, idempotencia y rechazo de tablas simultáneas/esquema incompatible.
- Instalador aislado y simulación contra Docker local. Integridad de payload y archivos de armado verificada.

No se certificó un lector físico ni la configuración del servidor del cliente. Los tests de escritura se ejecutaron en bases desechables y tablas temporales, no sobre órdenes reales. Evidencias y scripts de prueba: analysis/corte-release-0010 en desarrollo.
'''
(r/'LEEME.md').write_text(text,encoding='utf-8')
m={'version':'release-0010','release_id':'0010','release_version':'1.0.10','files':files,'packageFiles':[{'path':rel,'sha256':sha(r/rel)} for rel in ['trace.bat','update.ps1','migrations/002_release_registry.php','migrations/006_cortes.php','LEEME.md']]};(r/'manifest.json').write_text(json.dumps(m,indent=2)+'\n',encoding='utf-8')
protected=json.loads(Path('analysis/corte-release-0010/protected.json').read_text());assert all(hashlib.sha256((Path('src')/p).read_bytes()).hexdigest()==h for p,h in protected.items())
a=(Path('backups/release-0010-base/include/footer.php').read_text(encoding='utf-8').split("case 'corte':")[0]);b=Path('src/include/footer.php').read_text(encoding='utf-8').split("case 'corte':")[0];assert a==b
print('Payload',len(files),'archivos; protegidos',len(protected),'identicos y llamadas de armado intactas.')
