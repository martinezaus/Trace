# Análisis previo de armado y corte — 2026-10-05

Regla confirmada: descartar cierres de armado de hasta 60.000 ms inclusive y permitir una nueva lectura. Corte queda excluido.

Se inspeccionaron fotogramas de los videos de 27,48 y 28,13 segundos. En el segundo se lee «No hay una tarea activa para finalizar» con una tarea visible y cronómetro en marcha. No se transcribió el audio.

Hallazgos previos a pruebas:
1. Releer la orden pone tareaEnCurso=false conservando reloj y tarea visible: compatible con el reclamo.
2. Releer la misma tarea reinicia su timestamp sin acumular el tramo anterior, pudiendo provocar un descarte incorrecto.
3. Transiciones concurrentes pueden sobrescribirse al llegar respuestas tardías.
4. Selección sin pendiente pierde el contador de completadas; el cierre conserva timestamps/identificadores antiguos.
5. Pausa y suspensión detienen relojes antes de persistir. Errores o cancelación dejan estados inconsistentes.
6. Cierre API verifica idempotencia fuera de la transacción y no devuelve la duración persistida para reconciliar reintentos.
7. Corte actual usa CortePdfService y jquery.corte_pdf.js, no el controlador antiguo. Debe probarse la convivencia del heartbeat, guardado de ítems y cierre automático.

Solución: proteger transiciones, conservar el trabajo activo en relecturas, iniciar reloj tras alta confirmada, limpiar completamente tareas cerradas, recuperar contadores, reconciliar reintentos y bloquear el cierre del servidor dentro de la transacción.

Pruebas previstas: JavaScript real con reloj y red controlados; PDO con datos sintéticos aislados; umbral 0/59.999/60.000/60.001, relecturas, concurrencia, pausas, suspensión, jornada, recuperación, PDF parcial/completo, propietario y tokens, heartbeat, regresiones y sintaxis. Sin escrituras de pruebas en tablas de negocio.

Respaldo: backups/procesos_20261005_revision. Se documentarán resultados y límites; las pruebas no equivalen a una garantía absoluta de ausencia de errores.
