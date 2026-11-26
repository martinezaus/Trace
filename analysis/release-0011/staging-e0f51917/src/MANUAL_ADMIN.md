# Manual de Usuario — Administración (Trace)

> Este documento describe el funcionamiento del **modelo de Administración** del sistema. Incluye las pantallas principales accesibles desde el menú lateral, con foco en carga/gestión de **Ordenes**, administración de **Operarios**, y vistas de **Reportes / Estadísticas**.

---

## 1. Acceso al área de Administración

1. Ingresar al sistema desde la URL configurada (inicio del sistema).
2. En el menú lateral, seleccionar cualquiera de estas secciones:
   - **Tablero**
   - **Ordenes**
   - **Operarios**
   - **Reportes**
   - **Estadisticas**
3. El sistema valida la licencia/estado. Si la licencia está vencida, se muestra un overlay de **“Acceso denegado. Licencia vencida…”** y se bloquean las interacciones.

---

## 2. Menú superior (configuración)

En la barra superior aparece un ícono de **configuración (engranaje)** que despliega opciones:

- **Ayuda**
- **Configuracion**
- **Respaldos**

Estas opciones gestionan aspectos del sistema y no afectan directamente el flujo de carga/gestión de ordenes.

---

## 3. Sección “Tablero” (admin.php)

La pantalla **Tablero** centraliza:
- Botón para **cargar archivos** (alta de ordenes)
- Tarjetas con contadores
- Gráfico de evolución mensual de ordenes

### 3.1. Cargar archivos de una Orden

En la tarjeta **“Cargar”**:

1. Click en el botón **“Archivo”**.
2. Se abre un modal que contiene el formulario de carga.
3. Seleccionar:
   - **Archivo plano detallado [.dat]** (obligatorio)
   - **Archivo de Tipología OP/PLA [.pdf]** (obligatorio)
   - **Archivo PDF adicional (opcional)**
4. Click en **“Subir y Cargar”**.

#### Archivos requeridos
- **.dat / .txt**: archivo principal de detalle.
- **.pdf**: archivo(s) de tipología.

#### Qué se hace en el backend (comportamiento)
- El cliente envía los archivos.
- Luego, se registra la orden llamando.
- Si la orden ya existe (por número/presupuesto), el sistema responde con error.

#### Resultado esperado
- Mensaje de confirmación **“Archivos cargados correctamente”**.
- Se recarga la página automáticamente.

### 3.2. Tarjetas de estado (contadores)

En el tablero se muestran tarjetas con:
- **Operadores**: cantidad.
- **Tipologías**: cantidad total.
- **Cargadas**: cantidad de ordenes en estado cargada.
- **Terminadas**: cantidad de ordenes en estado terminada. 

Cada tarjeta incluye un enlace **“ver”** (cuando corresponde) hacia:
- **ordenes** (para ver ordenes)
- **operarios** (para ver operarios)

### 3.3. Gráfico: Evolución de Ordenes por mes

El tablero incluye un gráfico (Chart.js) con series:
- **Ordenes Cargadas**
- **Ordenes Procesando**
- **Ordenes Terminadas**
- **Ordenes Instaladas**

#### Actualización automática
- El gráfico se inicializa al cargar la página.
- Luego se actualiza **cada 5 segundos**.

---

## 4. Sección “Ordenes” (ordenes.php)

La pantalla **Ordenes** lista las ordenes registradas en una tabla:
- **Presupuesto**
- **Cliente**
- **Estado**
- **Tipologías**
- **Acciones**

> Nota: la tabla se completa mediante lógica de frontend/back-end (no se muestra aquí en el fragmento leído). En general, la operativa de cambios de estado y anexos de archivos se realiza a través de `api/ordenes.php`.

### 4.1. Acciones típicas sobre una orden

Según el backend (`api/ordenes.php`), el sistema soporta:
- **update**: cambiar estado de una orden (`c_estado`).
  - Si el estado es `2` (terminación ingresada), recalcula tiempos usando sumatorias desde `detalles`.
- **archive**: descargar archivo asociado:
  - Tipo **pdf** (archivo PDF)
  - Tipo **dat** (archivo plano)
- **delete**: eliminación de la orden y sus registros relacionados (detalles/lotes).
- **end**: finalización (actualiza duración acumulando tiempo).

---

## 5. Sección “Operarios” (operarios.php)

La pantalla **Operarios** muestra una tabla con columnas:
- **Apellido, Nombre**
- **Estado**
- **Token**
- **Acciones**

Incluye un botón/icono para agregar un operario:
- **Agregar** (id: `operario_add`)

### 5.1. Token de operario

Cada operario cuenta con un **Token**, utilizado para operaciones del flujo de producción/operación del sistema.

---

## 6. Sección “Estadísticas” (estadisticas.php)

La pantalla **Estadísticas** presenta métricas relacionadas con el sistema.

En esta base de código se observa:
- `api/estadisticas.php` con funciones para conteos y estructuras de datos.

---

## 7. Sección “Reportes” (reportes.php)

## Reportes disponibles
- Órdenes: fecha de alta, estado actual, estimado importado, tiempo de tipologías terminadas, cantidades de lotes y eficiencia.
- Operarios: tipologías terminadas cuya última actualización cae en el período; incluye operarios sin actividad.
- Tipologías: última actualización, operario principal, estado, tiempos, eficiencia y desvío.
- Procesos: fecha de inicio, tarea, operario, ayudante, estado y tiempo registrado.
- Pausas y suspensiones: fecha de registro, tipo, motivo, duración, operario y proceso/orden. Incluye reanudaciones y cierres de jornada.
- Resumen por cliente: órdenes dadas de alta en el período, estados actuales y cantidades agregadas.

## Criterios de datos
Órdenes: 0 Cargada, 1 Procesando, 2 Terminada, 3 Instalada.
Tipologías y procesos tienen catálogos separados, compartidos por pantalla y exportación.

Eficiencia = suma estimada / suma real × 100.
Desvío = (suma real / suma estimada - 1) × 100.
Solo se comparan tipologías terminadas con ambos tiempos mayores que cero.
No se promedian porcentajes; se informa cuántas tipologías tienen tiempos válidos.
100 % de eficiencia equivale al estimado; más de 100 % significa menos tiempo real.
Un desvío positivo significa más tiempo que el estimado.
Se distingue Sin datos de cero.

En Órdenes el real de tipologías terminadas puede ser parcial y no equivale a duración calendario.
En Operarios la atribución corresponde al operario principal de la tipología; los ayudantes se identifican en Procesos.
La última actualización puede cambiar por ediciones: no es una fecha histórica inmutable de finalización.
Los clientes se agrupan por nombre guardado; variantes de escritura no se normalizan automáticamente.
No se cambiaron las fórmulas ni los valores almacenados por otros módulos. Las métricas se calculan al consultar.

## Filtros y exportación
Desde y Hasta son inclusivos.
Campo / Comparación / Valor permite rangos inclusivos, mayor, menor, igualdad y Con datos / Sin datos.
Los tiempos usan HHH:MM:SS y admiten más de 24 horas; los porcentajes aceptan coma o punto decimal.
Modificar filtros invalida los resultados anteriores.

PDF y CSV usan los resultados guardados de la consulta, sin volver a consultar producción.
Se exportan todas las páginas que coincidan con la búsqueda de tabla, en el orden aplicado.
Se conservan hasta tres consultas por sesión. Si una consulta venció, se debe repetir la búsqueda.
El límite es 10.000 registros del período antes de aplicar la condición adicional. Si se supera, se solicita acortar fechas; no se entrega un resultado truncado.

PDF real con mPDF, logo, fechas, filtros, cantidad, encabezados repetidos y páginas numeradas.
CSV (Excel) usa UTF-8 y separador punto y coma; incluye metadatos y estados legibles.
CSV no es XLSX. Para conservar ceros iniciales en códigos, importar la columna como texto en Excel.
Los textos se escapan en pantalla/PDF y se neutralizan fórmulas en CSV.

## 8. Recomendaciones operativas (buenas prácticas)

1. **Verificar el formato de archivos**:
   - .dat/.txt para detalle
   - .pdf para tipologías OP/PLA
2. **Evitar duplicados**:
   - Si una orden ya fue cargada, el sistema rechazará el alta.
3. **Uso correcto de estados**:
   - La transición de estados (cargada → procesando → terminada → instalada) alimenta contadores y el gráfico del tablero.
4. **Licencia**:
   - Si la licencia vence, el sistema bloquea interacciones con overlay.

# Productividad y eficiencia

Criterio `desvio-ponderado-costos-v3`: `(real computable - estimado comparable) / estimado comparable × 100`. Negativo significa ahorro; positivo significa exceso. Los conjuntos usan sumas de tiempos comparables, no el promedio simple de porcentajes. El ahorro en horas es estimado menos real y su signo es inverso al desvío.

La lectura usa los procesos finalizados y el historial de retrabajos cuando existen tiempos por proceso; si no existen usa `detalles.tiempo_real` como referencia histórica. Las reversiones antiguas guardaban acumulados que se duplicaban al sumarlos: se reconstruye el total sin modificar el historial. Las nuevas reversiones usan `reversion_tipologia_v2` y guardan solo el ciclo revertido. Añade eventos `pausa` cuya descripción coincide con `pausas.flag=1`, salvo procesos de `cortes`, donde el cronómetro ya los incorpora. Suspensiones, cierres y lecturas descartadas no se añaden. Una pausa fuera de corte sin motivo reconocido en el catálogo o sin duración invalida la medición de ese detalle; no se supone que cuesta cero. La lectura no modifica tiempos históricos. El total de eventos es informativo y no se suma otra vez.

El PDF de orden consulta los detalles en servidor, muestra cobertura, tiempos comparables, pausas agregadas y ahorro. El resultado es final solamente si todos sus detalles son medibles y la orden está terminada o instalada; de otro modo es provisional.

Operarios: trabajos terminados cuya fecha máxima de fin de procesos pertenece al período inclusive elegido. Si falta fecha de cierre se usa la última actualización únicamente como referencia histórica. Se excluyen del porcentaje los registros con ayudantes, distintos responsables, reversiones, procesos abiertos o cierres incompletos. Se informan las exclusiones. Es un indicador de trabajos atribuibles, no una liquidación de sueldo ni una medida de toda la jornada.

Tareas: la base no tiene estimado por proceso. Solo se muestra porcentaje cuando el detalle tiene una etapa, un único proceso terminado y no tiene reversiones; se usa su estimado verificable. En los demás casos se muestra Sin datos/Sin estimado. Para comparar todas las tareas se necesitan estándares por detalle y tarea, definidos antes de trabajar, que sumen el presupuesto del detalle. No se divide en partes iguales ni se estima usando el tiempo real.

Límites para adicionales: el esquema no conserva los cambios de operario por tramo ni la regla de pausa vigente en cada evento. Históricos con esos cambios necesitan revisión; cambiar `pausas.flag` puede recalcular históricos fuera de corte. Antes de liquidar, conservar la exportación del período y validar reglas, cobertura y responsables. Los costos y adicionales son estimaciones configurables desde Soporte → Productividad y costos; no se liquidan pagos automáticamente. No se reescriben porcentajes históricos, pero las API y los reportes recalculan con la versión vigente. El caché `detalles.eficiencia` admite DECIMAL(5,2); valores fuera de rango se guardan nulos y se recalculan sin recortar en la lectura.

## Configuración económica en Soporte

Acceso: `modules/soporte/productividad.php`, para admin y master, también desde el panel y la barra de navegación.

Instalación en otra base: ejecutar `php scripts/migrar_productividad_costos.php` antes de usar la sección. La migración crea únicamente `systemproductividad`; no agrega tarifas ni modifica producción. Puede repetirse sin perder configuración.

Definir moneda, costo por hora, vigencia y si se habilitan costos. El valor se guarda en centavos enteros. Sin tarifa activa el reporte muestra Sin datos; una tarifa cero guardada expresamente se distingue de una tarifa ausente.

Para adicionales, seleccionar la base: costo ahorrado, costo real comparable o costo estimado comparable. Agregar tramos de ahorro mínimo y tasa de adicional. El ahorro mínimo usa valor positivo: 20 % de ahorro corresponde a productividad -20 %. Se aplica únicamente el tramo de mayor umbral alcanzado, sin sumar tasas. El umbral se compara antes de redondear el porcentaje mostrado. Sin ahorro la propuesta es cero. Con trabajos excluidos o sin medición comparable, queda sin determinar.

La vista previa usa los campos sin guardar nada. Cada guardado crea una versión inmutable con rol y fecha, y rechaza formularios de una versión anterior si alguien guardó en otra sesión. La fecha de vigencia determina desde cuándo se usa esa versión. Una nueva versión con vigencia retroactiva cambia futuras consultas de ese período; las exportaciones ya generadas mantienen sus valores e identificación de configuración.

Los reportes de operarios usan tarifa y escala vigentes al fin del período. Si ese período cruza un cambio de configuración, debe dividirse. Las órdenes finales usan la configuración vigente al cierre de sus procesos; las provisionales, la vigente al fin del período consultado (el PDF de historial usa la fecha de emisión para su estimación provisional). Los procesos usan su fecha de fin o inicio si están abiertos.

Costo estimado = horas estimadas comparables × tarifa. Costo real = horas reales computables × tarifa. Ahorro de costo = costo estimado - costo real; negativo indica exceso. La propuesta de adicional se calcula sobre la base elegida con la tasa del tramo alcanzado. Ambos costos se redondean a centavos y el ahorro es su diferencia. El ahorro no equivale a utilidad de venta y no descuenta pagos adicionales realizados.

Los procesos multietapa solo tienen tiempo real: se informa su duración y costo cuando hay tarifa, sin inventar eficiencia por tarea. El estimado pertenece al detalle. Para calcular productividad por cada proceso haría falta definir previamente sus estándares.
