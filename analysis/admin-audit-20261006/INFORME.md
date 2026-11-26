# Auditoría de Administración TRACE — 06/10/2026

## Resultado

Las cinco páginas abren, pero no se puede dar por correcto todo el panel: hay errores reproducidos en acciones y en indicadores. No se aplicaron correcciones ni releases. Se compararon 182 archivos fuente antes y después: ninguno cambió. No se modificaron registros de negocio; las escrituras de prueba se hicieron en tablas temporales por conexión o SQLite en memoria, y las mutaciones del navegador se interceptaron con respuestas simuladas. La navegación genera las sesiones/cachés habituales.

Entorno: instalación local D:/desarrollos/trace, Docker, MySQL y Edge sin interfaz visible. No es una certificación del servidor del cliente. La herramienta de control del escritorio falló; se usó Playwright con Edge instalado.

## Cobertura y resultados satisfactorios

| Prueba | Resultado |
|---|---|
| Tablero, Órdenes, Operarios, Reportes, Estadísticas | HTTP 200; carga inicial sin excepciones JS |
| Consultas de Reportes: órdenes, operarios, tipologías, procesos, eventos, clientes | Las 6 responden correctamente contra MySQL local |
| Exportación de los seis reportes | 6 CSV y 6 PDF descargados; CSV con BOM y PDF con firma %PDF- |
| Fechas invertidas, fecha inválida, tipo inválido, duración inválida y CSRF ausente | Rechazos controlados; filtros por estado y número funcionan |
| Período futuro sin registros | Respuesta correcta con 0 filas |
| Pruebas existentes de Reportes | 57 comprobaciones en memoria aprobadas |
| Eliminación de órdenes | 58 comprobaciones del servicio, 15 MySQL temporales y 13 frontend aprobadas |
| Alta, edición, baja y reset de operarios | Camino exitoso probado contra tabla temporal con esquema real |
| Modal de edición y campos obligatorios vacíos | Carga y validación inicial correctas |
| Consulta de archivos DAT/PDF y tipologías | Los datos cargan; incidencia intermitente de tabla anotada abajo |
| PDF histórico de orden y PDF de procesos | HTTP 200, application/pdf y contenido con firma válida |
| Credencial de operario | HTTP 200 y código de barras embebido |
| Sintaxis PHP | 31 archivos revisados, 31 correctos |

Se mantuvieron los datos reales. No se realizaron importaciones DAT/PDF reales, impresiones físicas, pruebas de caída del servidor ni cargas masivas. Las exportaciones se verificaron por descarga/contenido básico, no página por página. No se probó cada combinación imaginable ni se garantiza ausencia total de errores.

## Hallazgos confirmados y solución propuesta

### 1. Alta — Reactivar, revertir y cambiar ayudante pueden fallar después de guardar

**Ubicación:** src/plugins/js/jquery.ordenes.js, funciones activarTipologia (línea 462 aprox.), revertirTipologia y cargarAyudante.

**Reproducción:** con la respuesta exitosa de `status` simulada (`0`), el navegador registra `estado is not defined` al intentar usar `estado.ordenId`. Luego falla también `mostrarMensajeModal is not defined`. La reversión exitosa simulada reproduce el segundo error. Esa función de mensajes tampoco está disponible para el cambio de ayudante. Además, las recargas de activar/revertir llaman `inicializarTablaTipologias(detalles)` aunque la firma exige `(idOrden, items)`.

**Impacto:** la acción puede haberse guardado en el servidor mientras la pantalla queda vieja o informa error. Reintentar una reversión puede volver a generar historial.

**Solución propuesta:** pasar explícitamente el ID de orden, unificar los mensajes con la función que sí existe y llamar siempre `inicializarTablaTipologias(idOrden, detalles)`. Separar error de guardado de error de recarga y evitar solicitudes simultáneas. No se aplicó.

### 2. Alta — Errores de Operarios se ocultan o generan otra excepción

**Ubicación:** src/api/operadores.php:105,157,191; src/plugins/js/jquery.operarios.js:187,195,209,220.

**Reproducción:** una alta duplicada real en tabla temporal responde sólo `{error:...}`. El frontend espera `resultado`, no muestra el error y cierra el modal al segundo; se reprodujo usando esa misma respuesta interceptada. Para edición fallida el servidor entrega `mensaje`, pero JS consulta `respuesta.error`. Baja/reset con `{resultado:"ERROR"}` producen `ReferenceError: error is not defined`.

**Solución propuesta:** contrato único de respuesta (`resultado`, `mensaje`), validación de ID existente y `.fail`/manejador de red en cada operación. Mantener el formulario abierto si falla y cerrarlo sólo tras éxito.

### 3. Media — El servidor permite crear un operario sin nombre

**Ubicación:** src/api/operadores.php, rama add.

**Reproducción:** en tabla MySQL temporal con esquema real, enviar `nombre=''` crea un registro y responde `OK`. El control del navegador no protege el endpoint. Los IDs inexistentes en edit/del/reset terminan en errores SQL en lugar de un rechazo explícito de registro inexistente.

**Solución propuesta:** validar trim/nombre obligatorio, rangos de sesión y corte e ID existente en el servidor antes de construir/guardar el bean. Mantener las mismas reglas en la interfaz.

### 4. Media — Reportes no reconoce los nuevos descartes de hasta 60 segundos

**Ubicación:** src/include/reportes_service.php:47 y catálogo de eventos.

**Reproducción:** incorporando filas sólo a la base en memoria de pruebas, un proceso con estado 4 aparece como `Estado no reconocido: 4` y un evento como `Estado no reconocido: lectura_descartada`. Ambos filtros son rechazados como estado inválido. El catálogo de procesos tampoco contempla el estado 3 usado por la reversión.

**Solución propuesta:** ampliar catálogo/etiquetas/filtros de procesos y eventos, y mantener esas mismas etiquetas en CSV/PDF. Aclarar que un descarte no cuenta como producción válida.

### 5. Media — El contador Terminadas del Tablero consulta el estado equivocado

**Ubicación:** src/api/estadisticas.php:48; src/admin.php:135,188.

**Evidencia de código:** el contador consulta `c_estado=4`; Órdenes, Reportes y el gráfico del propio Tablero usan `2` para Terminada. En los datos locales actuales no hay órdenes terminadas, por eso el cero mostrado no permite notar la diferencia todavía.

**Solución propuesta:** usar el estado 2 y una definición compartida de estados. Añadir una prueba aislada con órdenes en estados 0,1,2,3 para comparar tarjeta, gráfico y reporte.

### 6. Media — La actualización horaria de productividad falla en SQL

**Ubicación:** src/api/operadores.php:225–230; src/include/footer.php, intervalo de 3600000 ms.

**Reproducción:** ejecutar sólo la consulta de lectura devuelve `Unknown column 'updated' in 'where clause'`. El esquema contiene `update`, no `updated`. No se ejecutó la escritura periódica real; se comprobó la consulta exacta que la precede.

**Solución propuesta:** usar la columna correcta entre backticks, gestionar errores y revisar que la tabla mensual refleje la definición de productividad vigente. Si la fecha debe ser la de finalización, usar una fecha dedicada y no la última modificación genérica.

### 7. Media — Gráficos con configuración de una versión diferente y doble creación

**Ubicación:** src/plugins/charts-c3/js/chartjs.min.js (cabecera: 2.7.1); src/plugins/js/jquery.grafico.js; src/admin.php:269,292.

**Reproducción:** el navegador registra dos instancias de Chart para `graficoOrdenes`. El inicializador se llama dos veces. En Estadísticas se pide leyenda a la derecha/abajo mediante `plugins.legend`, pero la opción efectiva sigue siendo `legend.position='top'`: el archivo cargado es Chart.js 2.7.1 y se configuró con la API de versiones posteriores. Los callbacks de tooltip se encuentran también bajo la estructura incorrecta.

**Solución propuesta:** inicializar una sola vez y destruir la instancia si se necesita recrear. Elegir una única versión de Chart.js: adaptar a v2 o migrar todas las configuraciones conjuntamente, probando ejes, leyendas y porcentajes.

### 8. Media — Exportaciones de las tablas de Órdenes/Operarios no están disponibles

**Ubicación:** src/plugins/js/jquery.ordenes.js:20 y jquery.operarios.js:28; src/include/header.php y paquete DataTables.

**Reproducción:** las tablas configuran copy/excel/pdf/print, pero los botones no aparecen. `typeof $.fn.dataTable.Buttons` devuelve `undefined`; el paquete incluido contiene sólo DataTables 1.10.16. Las exportaciones propias de Reportes sí funcionaron.

**Solución propuesta:** cargar una combinación compatible de Buttons y sus dependencias para cada formato, o reutilizar las exportaciones del servidor. Comprobar que se exportan las filas filtradas y no sólo la página visible.

### 9. Media — Procesos puede dejar el indicador de carga indefinidamente

**Ubicación:** src/plugins/js/jquery.ordenes.js:595 en adelante.

**Reproducción:** al devolver una lista vacía de detalles, `viewProcesos()` invoca mostrarCarga una vez y ocultarCarga cero veces. Hay salidas de error adicionales sin cerrar la carga; el Promise.all exterior carece de un cierre común para los rechazos.

**Solución propuesta:** convertir el flujo a async/await con catch y finally, cerrando la carga en todos los caminos y validando tipo/firma del PDF antes de abrirlo.

### 10. Baja — Traducción vacía y recursos de ordenamiento inexistentes

**Ubicación:** src/plugins/js/es-ES.json y estilos de DataTables.

**Reproducción:** el archivo de idioma tiene 0 bytes, y Órdenes/Operarios muestran Search/Showing/Previous en inglés. Los recursos sort_both.png y sort_asc/sort_desc.png devuelven 404.

**Solución propuesta:** incluir un JSON válido compatible con la versión usada y corregir las rutas de los iconos o reemplazarlas por estilos sin imágenes externas.

### 11. Baja — El pie fijo tapa parte del gráfico

**Ubicación:** src/css/footer.css:1.

**Reproducción:** captura `estadisticas.png`, viewport 1280x720: el bloque del pie está superpuesto sobre la parte inferior derecha del gráfico y sus etiquetas.

**Solución propuesta:** ubicar el pie dentro del flujo normal del panel o reservar espacio real; verificar también resoluciones pequeñas. Reportes ya tiene una regla específica para evitar este problema.

## Inconsistencias y riesgos que requieren decisión o pruebas adicionales

- **Definición de indicadores:** Estadísticas muestra 119 “Asignadas”, mientras existen 121 tipologías creadas en el año. La consulta cuenta sólo las que siguen pendientes (`c_estado=0`). “Tareas p/Operarios” muestra 3 mientras sólo 2 tipologías están terminadas: incluye cualquier tiempo no nulo, incluso cero. Debe decidirse si se quiere mostrar pendientes, asignaciones históricas o producción finalizada y alinear nombres/consultas. No es correcto comparar esas series como si midieran la misma población.
- **Tipologías, fallo intermitente:** en una ejecución apareció `Cannot read properties of undefined (reading 'style')` al abrir el modal. En una repetición independiente no apareció y la tabla cargó. Revisaría el temporizador global de ajuste de columnas, la reinicialización de tablas y los selectores compartidos de modales; no atribuyo una causa definitiva sin aislar la carrera.
- **Reversión sin transacción:** la rama revertir de api/detalles.php guarda historial, cambia procesos y después guarda detalle sin una transacción común. Una caída intermedia podría dejar un estado parcial; riesgo identificado en revisión, no se provocó en la base real. Solución: transacción, bloqueo de detalle, validar que esté terminado e idempotencia/prevención de doble envío.
- **Transporte/colocación y Asistencia:** las funciones respectivas indican que los módulos no están desarrollados. No se pueden considerar funciones operativas. Definir alcance y ocultar/deshabilitar el acceso hasta implementarlas.
- **Importaciones y permisos:** no se subieron archivos DAT/PDF reales ni se cambiaron credenciales reales. Falta una copia completa desechable para probar importaciones, errores de disco, concurrencia de varios usuarios y recuperación de fallos de extremo a extremo.

## Orden propuesto para corregir

1. Acciones de tipologías y manejo de errores de Operarios.
2. Validaciones de servidor y transacción de reversión.
3. Estados de Reportes y consistencia de indicadores/productividad.
4. Librerías de gráficos/exportaciones y cierre de indicadores de carga.
5. Idioma, recursos, pie y pruebas completas en una instalación desechable.

## Evidencias locales

En esta carpeta: browser-smoke.json, flows-more.json/flows.json, extra.json, operadores-isolated.log, lint.json, source-before.json, source-verification.json, scripts de reproducción, capturas y archivos exportados. Los scripts pertenecen a la auditoría; no se copiaron al código de la aplicación. No ejecutar scripts de pruebas contra el cliente sin revisar su aislamiento y rutas.
