# Productividad uniforme: cambios y límites

Implementado en el código local el 14/10/2026. Se consultó el esquema MySQL real. No se ejecutaron migraciones ni se modificaron registros de producción durante las verificaciones.

## Criterio

`(real computable - estimado comparable) / estimado comparable * 100`.
Negativo: favorable. Positivo: exceso. Se pondera por tiempo estimado, sumando duraciones antes de dividir. El ahorro de tiempo tiene signo inverso: estimado menos real.

## Alcance implementado

- Una lectura central para API, reportes y PDF, con procesos finalizados y retrabajos sin duplicación. Los históricos sin procesos conservan la referencia guardada.
- Pausas de armado con `pausas.flag=1` agregadas una sola vez. En corte ya están incluidas en el cronómetro y no se vuelven a agregar.
- Pausas sin regla identificable/duración y procesos terminados sin duración no producen un porcentaje supuesto.
- PDF de orden con datos del servidor, cobertura, cierre final/provisional, real computable y ahorro de tiempo.
- Operarios: trabajos terminados en el período por fecha de fin, con referencia histórica explícita cuando falta cierre. Los porcentajes excluyen atribuciones con ayudantes, varios responsables, reversiones o cierres incompletos.
- Tareas: porcentaje verificable para detalles de una etapa con un solo proceso terminado y sin reversiones. El resto muestra sin estimado; no se inventan estándares.
- Exportaciones anteriores invalidadas por versión del criterio.
- Reversiones nuevas guardan solo el ciclo revertido (`reversion_tipologia_v2`). Se reconstruyen los acumulados antiguos bajo la regla del código anterior sin reescribir el historial.

## Pendiente de información productiva

1. Estándares por tarea y detalle para medir tareas multietapa.
2. Costo por hora y escala de adicional por porcentaje para calcular dinero.
3. Revisar los históricos con motivos no reconocidos, ayudantes o cambio de responsables. La base no conserva quién trabajó cada tramo ni la regla de pausa vigente en cada evento. Cambiar el catálogo puede alterar resultados históricos fuera de corte; una exportación del período conserva los valores consultados, pero no reemplaza un cierre de liquidación inmutable.

El reporte actual sirve para revisar tiempos comparables y cobertura. No es una liquidación automática de adicionales ni garantiza que todos los trabajos de una persona estén representados cuando hay exclusiones.

## Verificación

- Pruebas de reportes: signos, ponderación, eventos, cortes sin duplicación, cobertura, tareas sin estándar, fecha de fin, historial antiguo y nuevo, exportaciones.
- Pruebas de reversión: transacciones, rollback y dos ciclos sin duplicación.
- Pruebas de cronómetros/corte y sintaxis PHP.
- Consultas de los seis reportes ejecutadas con MySQL real en modo lectura.
- PDF de orden y tareas generados y revisados visualmente. Son muestras de la base local, no una reemisión de la orden 14438 del archivo recibido.
