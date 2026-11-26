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

Ambos nombres se refieren al mismo indicador de ahorro de tiempo:

`(estimado - real) / estimado * 100`

- +20 %: se usó un 20 % menos de tiempo que lo estimado.
- -20 %: se usó un 20 % más de tiempo que lo estimado.
- 0 %: se cumplió exactamente el tiempo estimado.
- Sin datos: ítem no terminado o tiempos ausentes, nulos o menores o iguales a cero.

Órdenes y operarios muestran el promedio aritmético de los porcentajes de sus ítems terminados medibles, sin ponderar por duración. Se redondea cada ítem y el promedio a dos decimales. Los filtros del reporte determinan los ítems incluidos. Los no medibles se excluyen; no se sustituyen por 0 %. Las demoras no se limitan a -100 %.

El cálculo se centraliza en `include/productividad.php`. Los informes y las API recalculan desde los tiempos existentes, sin depender de porcentajes guardados con reglas anteriores. Los cierres futuros guardan el nuevo criterio. No se reescriben tiempos, estados ni historiales previos. La antigua columna Desvío se retiró para evitar mostrar el mismo concepto con signo contrario.

Los procesos individuales informan su tiempo registrado; el porcentaje se evalúa en la tipología, donde existe un tiempo estimado comparable. El contador mensual de piezas/ítems por operario es una cantidad, no este porcentaje.

Pruebas: `php tests/reportes_test.php --pdf` (SQLite en memoria).

Fin del Manual de Usuario — Administración

