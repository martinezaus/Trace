# Resultado de revisión y corrección — armado y corte

Fecha: 5 de octubre de 2026. Proyecto local: `D:\desarrollos\trace`.

## Resultado

Se corrigieron los fallos reproducidos de estado y tiempo de armado, se mantuvo el descarte inclusivo de hasta 60.000 ms confirmado por el usuario, y se serializaron los guardados periódicos y de ítems de corte. La migración requerida se aplicó a la base **local**. No se desplegó en la instalación del cliente.

## Causas y evidencia

| Hallazgo | Evidencia | Corrección |
|---|---|---|
| MySQL rechazaba `lectura_descartada` | El ENUM real sólo admitía pausa, suspensión, reanudación y cierre. Se reprodujo la excepción en MySQL estricto y el rollback del cierre. | Migración aditiva del ENUM y descripción de 100 a 255 caracteres. |
| Releer orden desactivaba la tarea sin apagar el reloj | Prueba fallida sobre código original; compatible con el mensaje visible del segundo video. | Conservar tarea en relectura; impedir cambiar de orden con trabajo activo. |
| Releer la misma tarea perdía tiempo | Prueba original reducía 90 segundos trabajados al reiniciar el timestamp. | Relectura idempotente; reanudación sólo en su transición correspondiente. |
| Respuestas simultáneas duplicaban avances | Dos cierres concurrentes incrementaban dos veces el contador original. | Exclusión de transiciones en la pantalla y bloqueo transaccional de filas en el servicio. |
| Limpieza y recuperación incompletas | Quedaban tarea y timestamp anteriores; selección sin pendiente perdía completadas. | Limpieza centralizada, conteo persistido y recuperación atómica del pendiente. |
| Suspensión/jornada podían dejar escrituras parciales | Revisión del flujo anterior y pruebas de fallo forzado. | Guardar proceso, pendiente, evento y estado dentro de una transacción; reintentos idempotentes. |
| Corte podía recibir un heartbeat anterior al último tilde | Prueba de navegador con heartbeat retrasado y finalización automática. | Una misma cola para esas mutaciones, conservando espera de observaciones y reintento de cambios fallidos. |

Los videos se revisaron mediante fotogramas: el segundo muestra «No hay una tarea activa para finalizar» con una tarea visible y cronómetro en marcha. No se transcribió el audio. La secuencia exacta del cliente no se puede afirmar sólo a partir de esos fotogramas.

## Verificación final

**126 comprobaciones nuevas aprobadas**, distribuidas en:

- 34 de JavaScript real con reloj y respuestas controlados: relecturas, concurrencia, límites del minuto, errores de red, pausas, suspensión, recuperación, jornada y cierre completo de orden.
- 61 de servicios PHP/PDO en SQLite efímero: duraciones, descarte, reintentos, rollback, propietarios, sesiones, tokens y ciclo de corte por PDF.
- 15 sobre MySQL estricto con tablas temporales creadas desde el esquema real: reproduce el ENUM original, valida migración repetible y confirma descarte, suspensión, recuperación y cierre entre tareas. Las tablas temporales excluyen claves foráneas, que MySQL no permite en ellas; no copian datos del negocio.
- 16 en Edge sin interfaz, con scripts reales y API simulada: eventos del lector, DOM, intervalos reales, guardados fallidos, observaciones, cierre y heartbeat concurrente con el último tilde.

También pasaron las **57 comprobaciones existentes de reportes**, la suite existente de códigos de vidrio y las verificaciones de sintaxis PHP/JavaScript. El registro final tiene **18/18 comandos correctos**.

La automatización inicial enviaba caracteres por CDP y ocasionalmente excedía el umbral de 50 ms del lector. Se ajustó el ensayo a una ráfaga de eventos de teclado dentro del navegador; las dos ejecuciones posteriores aprobaron. No se cambió el umbral del lector de producción.

Archivos de evidencia: `verification.json`, `verification.log`, `armado-browser.png`, `corte-browser.png` y `cambios.diff`, junto a este informe. `ANALISIS.md` conserva el análisis previo a las pruebas.

## Archivos de producción modificados o añadidos

- `src/api/procesos.php`
- `src/api/sesiones.php`
- `src/include/proceso_service.php` (nuevo)
- `src/include/footer.php` (versiones de caché)
- `src/plugins/js/jquery.procesoV1.0.3.js`
- `src/plugins/js/jquery.procesopausaV1.0.3.js`
- `src/plugins/js/jquery.procesosuspenderV1.0.3.js`
- `src/plugins/js/jquery.corte_pdf.js`
- `src/plugins/js/jquery.corte_documentos.js`
- `src/scripts/migrar_eventos_descartados.php` (nuevo)

Pruebas añadidas: `src/tests/procesos_frontend_test.cjs`, `procesos_service_test.php`, `procesos_mysql_test.php` y `procesos_browser_test.cjs`.

Respaldo de archivos originales y esquema anterior de eventos: `backups/procesos_20261005_revision/`. No había repositorio Git en esta carpeta, por eso se conservó respaldo y diff.

## Reproducción

Desde la raíz del proyecto, con sus contenedores levantados:

```powershell
node src/tests/procesos_frontend_test.cjs
docker compose exec -T php php /var/www/html/tests/procesos_service_test.php
docker compose exec -T php php /var/www/html/tests/procesos_mysql_test.php
node src/tests/procesos_browser_test.cjs
```

El ensayo de navegador requiere Playwright y Edge. Busca Playwright en las dependencias normales o en el runtime local de Codex; `TRACE_NODE_MODULES` permite indicar otra carpeta de módulos.

## Instalación en otro entorno

Publicar los diez archivos de producción de la lista como un conjunto y ejecutar la migración con respaldo previo. El comando sin `--apply` sólo informa cambios necesarios:

```powershell
docker compose exec -T php php /var/www/html/scripts/migrar_eventos_descartados.php
docker compose exec -T php php /var/www/html/scripts/migrar_eventos_descartados.php --apply
```

La base local ya está migrada y una segunda ejecución de diagnóstico no propone cambios. Los navegadores deben cargar la nueva versión de scripts al comenzar una sesión; cerrar o suspender el trabajo antes de recargar una pantalla activa.

## Alcance

Las pruebas cubren los escenarios enumerados y no garantizan ausencia absoluta de errores. No se probó el lector físico, la red ni el servidor del cliente. El navegador utilizó API simulada y los servicios se ejercitaron por separado; no se ejecutó una jornada real de punta a punta contra datos del negocio ni pruebas de carga prolongada. No se modificaron órdenes o procesos reales para probar, ni se corrigieron registros históricos de forma automática. La única escritura sobre la base local del negocio fue la migración aditiva del esquema de eventos.
