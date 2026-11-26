# Productividad y costos — 14/10/2026

Sección nueva: Soporte → Productividad y costos (`modules/soporte/productividad.php`). Acceso admin/master, CSRF y validación del servidor.

- Costo por hora, moneda y vigencia; guardado en centavos.
- Tramos de ahorro mínimo y adicional sobre una base elegida: ahorro, costo real o costo estimado.
- Vista previa sin guardar valores.
- Versiones conservadas y protección contra guardados sobre una versión desactualizada.
- Costos comparables y ahorro en reportes y PDF de orden; adicional propuesto por operario solo con cobertura atribuible completa.
- Exportación PDF separada en bloques de producción/economía para evitar columnas ilegibles.
- Se mantiene negativo = productividad favorable, positivo = exceso.

Migración aplicada a la base local: `systemproductividad`. La tabla está vacía: no se cargaron tarifas ni escalas, no se activaron adicionales y no se modificaron tiempos de producción. Para otras instalaciones: `php scripts/migrar_productividad_costos.php`.

Confirmado por el usuario: los procesos tienen tiempos reales, sin estimado individual. No se asignan porcentajes de productividad a tareas multietapa; sí duración y costo. Los porcentajes se calculan en detalle/orden/operario sobre conjuntos comparables, y en procesos de una sola etapa cuando su estimado es verificable.

El costo ahorrado es una valoración de tiempo, no utilidad comercial. El adicional es una propuesta, no una liquidación automática. Períodos de operarios que cruzan cambios de tarifa deben dividirse; la configuración utilizada se identifica en cada resultado. Una vigencia retroactiva modifica futuras consultas, no los valores de exportaciones ya generadas.

Pruebas aisladas cubren cálculos, redondeos, tramos, fechas de vigencia, versiones, roles, CSRF, simulación sin guardar y datos incompletos. Se verificó la pantalla en navegador y la estructura con MySQL real. La captura usa valores de ejemplo sin guardar.
