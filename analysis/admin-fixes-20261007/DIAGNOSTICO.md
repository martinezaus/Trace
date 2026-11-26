# Diagnóstico y validación — TRACE 0008 revisión 2
Fecha: 07/10/2026. Código local: D:/desarrollos/trace/src. No aplicado en el cliente.

## Por qué aparecía HTTP 200 / parsererror

Se reprodujo el problema con el DAT y los PDF NAVARRO ALFREDO14448(OPC2) proporcionados. La respuesta original tenía 13.157 bytes: comenzaba con avisos HTML de PHP «Implicit conversion from float ... to int loses precision» en parse_pdf.php y terminaba con JSON. jQuery recibía HTTP 200 pero no podía interpretar todo el cuerpo como JSON. La orden, cuatro detalles y catorce ítems de corte ya habían sido guardados. Esto coincide con los logs y con la captura: un fallo de confirmación no demuestra que el alta no se haya realizado.

El parser agrupaba palabras usando coordenadas verticales decimales como claves de un array. PHP convierte esas claves a enteros y puede emitir avisos deprecados según versión/configuración. Se reemplazó por grupos indexados que conservan Y decimal y tolerancia real. También se hizo explícita la conversión de tiempos fraccionarios del DAT. No se resolvió únicamente ocultando avisos: se corrigieron ambas conversiones. El endpoint, además, dirige diagnósticos al log y retorna JSON controlado.

La importación corregida respondió JSON válido con resultado OK y generó los 14 ítems esperados, distribuidos 8 en página 1 y 6 en página 2. Se probaron duplicados y errores con rollback. La importación de registros relacionados ahora se confirma en una transacción: ante un error, no queda un alta parcial en esas tablas. La subida de archivos ocurre antes; un archivo sin orden confirmada puede permanecer para revisión/reintento.

## ¿El PDF externo tiene un problema?

Sí, los dos archivos suministrados presentan una advertencia estructural: «No valid XRef size in trailer». En su trailer aparece `/size` en lugar de `/Size`. Es un defecto del PDF generado que conviene comunicar al proveedor con los originales. No se modificaron esos documentos.

Sin embargo, Poppler devuelve código 0, permite renderizarlos y extrae el contenido del PLA. Se revisaron las tres páginas (OP: una; PLA: dos). Por lo tanto, en este caso el defecto del PDF no explica por sí solo el parsererror: la causa reproducida de la respuesta inválida está en TRACE. No puede asegurarse que todos los PDF de ese proveedor sean legibles sólo a partir de esta muestra.

La variación entre órdenes es compatible con coordenadas/contenido distintos que exponen conversiones diferentes. El efecto visible depende también de la versión de PHP y de display_errors/error_reporting. No se afirma que toda diferencia entre órdenes tenga necesariamente esta única causa.

## Correcciones de la auditoría anterior

1. Tipologías: contexto de orden correcto al reactivar/revertir, mensajes existentes, refresco del modal y bloqueo de doble envío. Reversión con transacción, bloqueos, validación de estado e historial único por operación. Una orden terminada vuelve a procesando al revertir; una colocada no se revierte por esta acción.
2. Operarios: validación de nombre, ID, permisos y sesión; respuestas uniformes, errores visibles sin cerrar el formulario y baja/reset sin excepciones JS. El esquema actual y el control de acceso trabajan con una sesión: se muestra ese límite sin modificar columnas.
3. Reportes: filtros y etiquetas para procesos invalidados por reversión, lecturas descartadas y sus eventos. Se preservan exportaciones CSV/PDF.
4. Tablero: terminadas usa estado 2 y una sola instancia de Chart. Estadísticas utiliza configuración de Chart.js 2.7.1; cuenta tipologías creadas y terminadas con títulos coherentes. Productividad utiliza `detalles.update`, la columna existente.
5. Exportaciones de Órdenes/Operarios: copiar, CSV para Excel, PDF e imprimir, sobre filas filtradas/ordenadas. CSV no equivale a XLSX. Copiar dispone de alternativa para navegadores sin Clipboard API.
6. Carga de procesos: cierre del indicador con finally, manejo de red/lista vacía y validación de PDF. Ajuste de tablas limitado a las inicializadas en el modal.
7. Interfaz: traducción española, rutas de iconos, tamaños de página y pie sin superposición. Logística queda deshabilitada; Asistencia no se incorpora porque son módulos no implementados.

El servicio de eliminación del 0007 se conserva: sólo Cargado (0). La API utiliza R::getPDO() y mantiene la protección de archivos. El release requiere 0007; no reemplaza ni reinstala sus migraciones. También admite el 0008 original ya aplicado.

## Pruebas ejecutadas

| Cobertura | Resultado |
|---|---|
| Servicio armado/corte | 61 comprobaciones |
| Procesos en MySQL temporal | 15 comprobaciones |
| Frontend de procesos | 34 comprobaciones |
| Navegador de procesos con API simulada y JS real | 16 comprobaciones |
| Servicio eliminación / MySQL temporal / frontend | 58 / 15 / 13 comprobaciones |
| Servicio de reportes en memoria | 57 comprobaciones |
| Regresión nueva: PDF real, decimales, reversión y rollback | 22 comprobaciones |
| Flujos administrativos en navegador, BD desechable | 18 comprobaciones |
| Reportes desde navegador | Seis consultas, seis CSV, seis PDF; filtros y rechazos controlados |
| Contratos de Operarios | Alta/edición/baja/reset/productividad; nombre vacío/duplicado, rangos e IDs inexistentes |
| Importación real aislada | Fallo original reproducido; éxito corregido, duplicado rechazado y rollback |
| Sintaxis de archivos modificados PHP/JS | 24 archivos sin errores |
| Exportaciones adicionales | Copia, alternativa de copia, opciones de paginación y PDF de tabla Operarios |
| Instalador simulado | Simulación sin cambios, aplicación/respaldos, segunda ejecución, rechazo de archivos ajenos/paquete alterado, fallo posterior a copia y bloqueo de reintento incierto |
| Registro SQLite | Conserva 0008, registra 0008-r2, rechaza repetición/checksum diferente |
| Simulación Docker local | Esquema, motores, dependencias y hashes compatibles; sin aplicar |

Las mutaciones de integración se realizaron en la base separada trace_admin_test_20261007 y un sitio copiado en analysis/admin-fixes-20261007/site; los otros tests usan memoria/tablas temporales o respuestas interceptadas. No se modificaron órdenes reales de negocio para probar. La ejecución web genera las sesiones y cachés habituales.

Evidencias locales: analysis/admin-fixes-20261007/{import-before.txt,import-after.txt,import-duplicate.txt,parser-before.txt,parser-after.json,regressions.json,browser-tests.json,reportes-browser.json,api-tests.json,lint.json,dry-run.log,installer-r2-tests.log}; capturas y exportaciones en la misma carpeta. Auditoría original: analysis/admin-audit-20261006/INFORME.md. Los documentos del cliente y la base de prueba no se distribuyen en el ZIP.

No se probó impresión física, carga masiva, caída del servidor del cliente ni todas las combinaciones posibles de documentos/concurrencia. Las exportaciones se comprobaron por descarga/firma y contenido básico; no se certificó su maquetación página por página. Estas pruebas no garantizan ausencia absoluta de errores.

## Datos e instalación

El paquete contiene 27 archivos de aplicación. No realiza migraciones de esquema de negocio ni reparación retroactiva de datos. Escribe únicamente el registro técnico de instalación systemreleases (0008-r2), con creación de esa tabla si faltara. Conserva los registros anteriores y genera respaldos de código y BD. Durante el uso posterior, las acciones del panel sí modifican los datos que corresponden a cada operación.

Antes de reintentar una importación que mostró parsererror, verificar si la orden ya está en el listado. Este release no elimina automáticamente órdenes posiblemente duplicadas. Para instalar, seguir LEEME.md y ejecutar primero --simulate sobre la ruta real del servidor.
