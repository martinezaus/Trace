<?php
declare(strict_types=1);
require_once __DIR__ . "/productividad.php";
require_once __DIR__ . "/productividad_costos.php";

/** Lecturas de Reportes. No modifica datos de producción ni sus indicadores históricos. */
function reportDatabase(): PDO {
    require_once __DIR__ . '/config.php';
    return new PDO('mysql:host=' . hostBD . ';dbname=' . nombreBD . ';charset=utf8mb4',
        usuarioBD, claveBD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
}

function reportCatalog(): array {
    $col = static function (string $field, string $title, string $type = 'text'): array {
        return compact('field', 'title', 'type');
    };
    $efficiency = productividadLeyenda();
    $costColumns=[$col('moneda','Moneda'),$col('costo_estimado','Costo estimado comparable','money'),$col('costo_real','Costo real comparable','money'),$col('ahorro_costo','Ahorro de costo estimado','money'),$col('config_costos','Configuración económica')];
    return [
        'ordenes' => [
            'title' => 'Órdenes',
            'period' => 'Fecha de alta de la orden',
            'note' => 'Costos: estimación de mano de obra, no utilidad de venta; la tarifa se elige al cierre de cada orden final o al fin del período si es provisional. Estados actuales. El tiempo real corresponde a tipologías terminadas; puede ser parcial. ' . $efficiency,
            'statuses' => ['0' => 'Cargada', '1' => 'Procesando', '2' => 'Terminada', '3' => 'Instalada'],
            'columns' => [$col('id','ID','integer'), $col('codigo','Código'), $col('cliente','Cliente'),
                $col('estado','Estado'), $col('fecha','Alta','date'), $col('estimado','Estimado orden','duration'),
                $col('real','Real tip. terminadas','duration'), $col('comparable','Estimado comparable','duration'), $col('computable','Real computable comparable','duration'), $col('ahorro','Ahorro de tiempo','duration'), $col('medicion','Medición'), $col('tipologias','Tipologías','integer'),
                $col('items','Ítems','integer'), $col('terminadas','Tip. terminadas','integer'),
                $col('medibles','Tip. con tiempos válidos','integer'), $col('eficiencia','Productividad (%)','percent'), ...$costColumns],
        ],
        'operadores' => [
            'title' => 'Operarios',
            'period' => 'Finalización de procesos de tipologías terminadas; actualización si falta cierre',
            'note' => 'Costos y adicional propuesto con la tarifa y escala vigentes al fin del período. Si cruza un cambio de configuración, divida el período. Compara trabajos terminados en el período, incluyendo sus eventos aunque se registraran antes. Excluye del porcentaje atribuciones con ayudantes, varios responsables, retrabajos o cierres incompletos. No liquida pagos. ' . $efficiency,
            'statuses' => ['0' => 'Activo', '1' => 'Inactivo'],
            'columns' => [$col('id','ID','integer'), $col('nombre','Operario'), $col('estado','Estado'),
                $col('terminadas','Tip. terminadas','integer'), $col('medibles','Tip. medibles y atribuibles','integer'), $col('excluidas','Tip. excluidas','integer'),
                $col('estimado','Estimado comparable','duration'), $col('real','Real computable comparable','duration'), $col('eventos','Pausas agregadas','duration'), $col('ahorro','Ahorro de tiempo','duration'),
                $col('eficiencia','Productividad (%)','percent'), ...$costColumns, $col('adicional_tasa','Tasa de adicional (%)','percent'), $col('adicional','Adicional propuesto','money')],
        ],
        'tipologias' => [
            'title' => 'Tipologías',
            'period' => 'Finalización de procesos; última actualización si falta cierre',
            'note' => 'Estados actuales, no una reconstrucción histórica. ' . $efficiency,
            'statuses' => ['0' => 'Pendiente', '1' => 'Pausada', '2' => 'Suspendida', '3' => 'Terminada'],
            'columns' => [$col('id','ID','integer'), $col('orden','Orden'), $col('cliente','Cliente'),
                $col('tipologia','Tipología'), $col('operario','Operario'), $col('estado','Estado'),
                $col('fecha','Fecha de medición','date'), $col('estimado','Estimado','duration'),
                $col('real','Real computable','duration'), $col('eventos','Pausas agregadas','duration'), $col('eventos_incompletos','Eventos sin regla/duración','integer'), $col('fecha_origen','Origen de fecha'), $col('eficiencia','Productividad (%)','percent')],
        ],
        'procesos' => [
            'title' => 'Procesos',
            'period' => 'Fecha de finalización del proceso; inicio si está abierto',
            'note' => 'Una fila por proceso. Costo disponible para procesos finalizados con eventos determinables. Solo existe estimado verificable por tarea cuando el detalle tiene una única etapa, un único proceso terminado y no tiene retrabajos. En otros casos falta un estándar por tarea: no se distribuye arbitrariamente el estimado del detalle. ',
            'statuses' => ['0' => 'Pendiente', '1' => 'En proceso', '2' => 'Finalizado', '3' => 'Invalidado por reversión', '4' => 'Lectura descartada (hasta 60 s)'],
            'columns' => [$col('id','ID','integer'), $col('orden','Orden'), $col('tipologia','Tipología'),
                $col('tarea','Tarea'), $col('operario','Operario'), $col('ayudante','Ayudante'),
                $col('estado','Estado'), $col('fecha','Fecha de medición','date'), $col('fin','Fin','date'),
                $col('real','Tiempo computable','duration'), $col('estimado','Estimado verificable','duration'), $col('eficiencia','Productividad (%)','percent'), $col('medicion','Medición'),$col('moneda','Moneda'),$col('costo_real','Costo del tiempo computable','money'),$col('config_costos','Configuración económica')],
        ],
        'eventos' => [
            'title' => 'Eventos',
            'period' => 'Fecha de registro del evento',
            'note' => 'Incluye pausas, suspensiones, reanudaciones y cierres de jornada. Una duración ausente se muestra como Sin datos. Los motivos se conservan tal como fueron registrados.',
            'statuses' => ['pausa' => 'Pausa', 'suspension' => 'Suspensión', 'reanudar' => 'Reanudación', 'cierre_jornada' => 'Cierre de jornada', 'lectura_descartada' => 'Lectura descartada (hasta 60 s)'],
            'columns' => [$col('id','ID','integer'), $col('fecha','Registro','date'), $col('estado','Tipo de evento'),
                $col('operario','Operario'), $col('orden','Orden'), $col('tarea','Tarea'),
                $col('motivo','Motivo'), $col('real','Duración registrada','duration')],
        ],
        'clientes' => [
            'title' => 'Resumen por cliente',
            'period' => 'Fecha de alta de las órdenes',
            'note' => 'Agrupa por el nombre de cliente guardado en cada orden. Variantes de escritura se muestran separadas. Los estados son los actuales. Las cantidades de lotes se suman una sola vez por orden.',
            'statuses' => [],
            'columns' => [$col('cliente','Cliente'), $col('ordenes','Órdenes','integer'),
                $col('cargadas','Cargadas','integer'), $col('procesando','Procesando','integer'),
                $col('terminadas','Terminadas','integer'), $col('instaladas','Instaladas','integer'),
                $col('tipologias','Tipologías','integer'), $col('items','Ítems','integer'),
                $col('estimado','Estimado total','duration')],
        ],
    ];
}

function reportScalar(array $input, string $key, string $default = ''): string {
    if (!isset($input[$key])) return $default;
    if (!is_scalar($input[$key])) throw new InvalidArgumentException('Parámetro inválido: ' . $key);
    return trim((string)$input[$key]);
}

function reportConditionValue(string $value, string $type) {
    if ($value === '') throw new InvalidArgumentException('Complete el valor de la condición.');
    if ($type === 'duration') {
        if (!preg_match('/^(\d{1,7}):([0-5]\d):([0-5]\d)$/D', $value, $m)) {
            throw new InvalidArgumentException('Ingrese una duración HHH:MM:SS; por ejemplo 025:30:00.');
        }
        return (int)$m[1] * 3600 + (int)$m[2] * 60 + (int)$m[3];
    }
    if (in_array($type, ['integer','percent','money'], true)) {
        $value = str_replace(',', '.', $value);
        if (!preg_match($type === 'integer' ? '/^\d+$/D' : '/^[+-]?\d+(?:\.\d+)?$/D', $value) || !is_finite((float)$value)) {
            throw new InvalidArgumentException('Ingrese un número válido' . ($type === 'integer' ? ' entero y no negativo.' : '.'));
        }
        return (float)$value;
    }
    if (mb_strlen($value) > 191) throw new InvalidArgumentException('El texto de la condición es demasiado largo.');
    return $value;
}

function reportFilters(array $input): array {
    $catalog = reportCatalog();
    $type = reportScalar($input, 'reportType');
    if (!isset($catalog[$type])) throw new InvalidArgumentException('Seleccione un tipo de reporte válido.');
    $from = reportScalar($input, 'dateFrom');
    $to = reportScalar($input, 'dateTo');
    foreach ([$from, $to] as $value) {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value || $value < '2000-01-01' || $value > '2099-12-31') {
            throw new InvalidArgumentException('Ingrese fechas válidas entre 2000 y 2099.');
        }
    }
    if ($from > $to) throw new InvalidArgumentException('Desde no puede ser posterior a Hasta.');
    $status = reportScalar($input, 'statusFilter');
    if ($status !== '' && !array_key_exists($status, $catalog[$type]['statuses'])) {
        throw new InvalidArgumentException('Estado no válido para este reporte.');
    }
    $field = reportScalar($input, 'additionalCondition');
    $operator = reportScalar($input, 'conditionOperator', 'gt');
    $value = reportScalar($input, 'conditionValue');
    $valueTo = reportScalar($input, 'conditionValueTo');
    $parsed = $parsedTo = null;
    if ($field !== '') {
        $columns = array_column($catalog[$type]['columns'], null, 'field');
        if (!isset($columns[$field]) || in_array($field, ['estado','fecha','fin'], true)) {
            throw new InvalidArgumentException('Condición no válida para este reporte.');
        }
        $kind = $columns[$field]['type'];
        $allowed = in_array($kind, ['duration','integer','percent','money'], true)
            ? ['gt','gte','lt','lte','eq','between','missing','present'] : ['eq','contains','missing','present'];
        if (!in_array($operator, $allowed, true)) throw new InvalidArgumentException('Comparación no válida.');
        if (!in_array($operator, ['missing','present'], true)) {
            $parsed = reportConditionValue($value, $kind);
            if ($operator === 'between') {
                $parsedTo = reportConditionValue($valueTo, $kind);
                if ($parsed > $parsedTo) throw new InvalidArgumentException('El mínimo no puede superar al máximo.');
            }

        }
    }
    return compact('type','from','to','status','field','operator','value','valueTo','parsed','parsedTo');
}

function reportMatches(array $row, array $f): bool {
    if ($f['field'] === '') return true;
    $v = $row[$f['field']] ?? null;
    $missing = $v === null || $v === '';
    if ($f['operator'] === 'missing') return $missing;
    if ($f['operator'] === 'present') return !$missing;
    if ($missing) return false;
    $n = $f['parsed'];
    switch ($f['operator']) {
        case 'gt': return $v > $n;
        case 'gte': return $v >= $n;
        case 'lt': return $v < $n;
        case 'lte': return $v <= $n;
        case 'between': return $v >= $n && $v <= $f['parsedTo'];
        case 'contains': return mb_stripos((string)$v, (string)$n) !== false;
        case 'eq': return is_float($n) || is_int($n) ? (float)$v === (float)$n : mb_strtolower((string)$v) === mb_strtolower((string)$n);
    }
    return false;
}

function reportMetric($estimated, $actual): ?float {
    return productividadSegundos($estimated, $actual);
}

function reportQuery(array $f): array {
    $source = productividadDetallesSql();
    $valid = 'd.c_estado = 3 AND TIME_TO_SEC(d.tiempo_estimado) > 0 AND d.real_segundos > 0 AND d.medicion_incompleta=0';
    $aggregate = "SELECT d.orden_id,MAX(d.fecha_medicion) fecha_costos,COUNT(*) total,SUM(d.c_estado=3) terminadas,
        SUM(CASE WHEN $valid THEN 1 ELSE 0 END) medibles,
        SUM(CASE WHEN d.c_estado=3 THEN d.real_segundos END) `real`,
        SUM(CASE WHEN $valid THEN TIME_TO_SEC(d.tiempo_estimado) END) comparable_estimado,
        SUM(CASE WHEN $valid THEN d.real_segundos END) comparable_real
        FROM ($source) d GROUP BY d.orden_id";
    $lots = 'SELECT orden_id, SUM(tipologias) tipologias, SUM(items) items FROM lotes GROUP BY orden_id';
    $from = $f['from'] . ' 00:00:00';
    $until = (new DateTimeImmutable($f['to']))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
    $params = [$from, $until];
    $statusField = '';
    switch ($f['type']) {
        case 'ordenes':
            $sql = "SELECT o.id, o.codigo, o.cliente, o.c_estado _estado, o.f_alta fecha,
                TIME_TO_SEC(o.tiempo_orden) estimado, a.real,
                COALESCE(l.tipologias,0) tipologias, COALESCE(l.items,0) items,
                COALESCE(a.terminadas,0) terminadas, COALESCE(a.medibles,0) medibles,
                a.comparable_estimado _estimated, a.comparable_real _actual,
                a.comparable_estimado comparable,a.comparable_real computable,a.fecha_costos _cost_date,
                a.comparable_estimado-a.comparable_real ahorro,
                CASE WHEN a.total=a.medibles AND o.c_estado IN (2,3) THEN 'Final' ELSE 'Provisional / datos incompletos' END medicion
                FROM ordenes o LEFT JOIN ($lots) l ON l.orden_id=o.id
                LEFT JOIN ($aggregate) a ON a.orden_id=o.id WHERE o.f_alta >= ? AND o.f_alta < ?";
            $statusField = 'o.c_estado'; $order = 'o.id DESC'; break;
        case 'operadores':
            $operatorValid = "$valid AND d.atribuible=1";
            $sql = "SELECT o.id,o.nombre,CAST(o.estado AS UNSIGNED) _estado,
                COALESCE(a.terminadas,0) terminadas,COALESCE(a.medibles,0) medibles,
                COALESCE(a.terminadas,0)-COALESCE(a.medibles,0) excluidas,
                a.estimado,a.real,a.eventos,a.estimado-a.real ahorro,a.estimado _estimated,a.real _actual
                FROM operadores o LEFT JOIN (
                    SELECT d.operador_id,COUNT(*) terminadas,
                    SUM(CASE WHEN $operatorValid THEN 1 ELSE 0 END) medibles,
                    SUM(CASE WHEN $operatorValid THEN TIME_TO_SEC(d.tiempo_estimado) END) estimado,
                    SUM(CASE WHEN $operatorValid THEN d.real_segundos END) `real`,
                    SUM(CASE WHEN $operatorValid THEN d.eventos_segundos END) eventos
                    FROM ($source) d WHERE d.c_estado=3 AND d.fecha_medicion>=? AND d.fecha_medicion<?
                    GROUP BY d.operador_id) a ON a.operador_id=o.id WHERE 1=1";
            $statusField = 'o.estado'; $order = 'o.nombre, o.id'; break;
        case 'tipologias':
            $sql = "SELECT d.id, o.codigo orden, o.cliente, d.tipologia, op.nombre operario,
                d.c_estado _estado,d.fecha_medicion fecha,d.fecha_origen,d.eventos_incompletos,d.eventos_segundos eventos,TIME_TO_SEC(d.tiempo_estimado) estimado,
                d.real_segundos `real`,
                CASE WHEN $valid THEN TIME_TO_SEC(d.tiempo_estimado) END _estimated,
                CASE WHEN $valid THEN d.real_segundos END _actual
                FROM ($source) d JOIN ordenes o ON o.id=d.orden_id LEFT JOIN operadores op ON op.id=d.operador_id
                WHERE d.fecha_medicion >= ? AND d.fecha_medicion < ?";
            $statusField = 'd.c_estado'; $order = 'd.id DESC'; break;
        case 'procesos':
            $tasks=productividadTareasSql();
            $sql="SELECT p.* FROM ($tasks) p WHERE p.fecha>=? AND p.fecha<?";
            $statusField='p._estado';$order='p.fecha DESC,p.id DESC';break;
        case 'eventos':
            $sql = "SELECT e.id, e.created fecha, e.tipo _estado, op.nombre operario, o.codigo orden,
                t.descripcion tarea, e.descripcion motivo, TIME_TO_SEC(e.tiempo_real) `real` FROM eventos e
                LEFT JOIN procesos p ON p.id=e.proceso_id LEFT JOIN detalles d ON d.id=p.detalle_id
                LEFT JOIN ordenes o ON o.id=d.orden_id LEFT JOIN tareas t ON t.id=p.tarea_id
                LEFT JOIN operadores op ON op.id=e.operador_id WHERE e.created >= ? AND e.created < ?";
            $statusField = 'e.tipo'; $order = 'e.created DESC, e.id DESC'; break;
        case 'clientes':
            $sql = "SELECT COALESCE(NULLIF(TRIM(o.cliente),''),'Sin cliente') cliente, COUNT(*) ordenes,
                SUM(o.c_estado=0) cargadas, SUM(o.c_estado=1) procesando, SUM(o.c_estado=2) terminadas,
                SUM(o.c_estado=3) instaladas, SUM(COALESCE(l.tipologias,0)) tipologias,
                SUM(COALESCE(l.items,0)) items, SUM(TIME_TO_SEC(o.tiempo_orden)) estimado
                FROM ordenes o LEFT JOIN ($lots) l ON l.orden_id=o.id WHERE o.f_alta >= ? AND o.f_alta < ?
                GROUP BY COALESCE(NULLIF(TRIM(o.cliente),''),'Sin cliente')";
            $order = 'cliente'; break;
        default: throw new InvalidArgumentException('Reporte inválido.');
    }
    if ($f['status'] !== '' && $statusField !== '') {
        $sql .= " AND $statusField = ?"; $params[] = $f['status'];
    }
    return [$sql . " ORDER BY $order LIMIT 10001", $params];
}

function reportData(PDO $db, array $f): array {
    [$sql, $params] = reportQuery($f);
    $stmt = $db->prepare($sql); $stmt->execute($params);
    $rows = $stmt->fetchAll();
    if (count($rows) > 10000) throw new InvalidArgumentException('El período supera 10.000 registros. Acorte las fechas para consultar y exportar sin recortes.');
    $config = reportCatalog()[$f['type']];
    $result = []; $economicConfigs=[];
    foreach ($rows as $row) {
        if (array_key_exists('_estado', $row)) {
            $row['estado'] = $config['statuses'][(string)$row['_estado']] ?? ('Estado no reconocido: ' . ($row['_estado'] ?? 'sin dato'));
        }
        if (array_key_exists('_estimated', $row)) {
            $row['eficiencia'] = array_key_exists('_productividad', $row)
                ? ($row['_productividad'] === null ? null : round((float)$row['_productividad'], 2))
                : reportMetric($row['_estimated'], $row['_actual']);
        }
        if (in_array($f['type'],['ordenes','operadores','procesos'],true)) {
            // Órdenes finales: tarifa a su cierre. Operarios: tarifa al cierre del período.
            $fechaCostos=$f['type']==='operadores' ? $f['to'] : substr((string)($row['_cost_date']??$row['fecha']??$f['to']),0,10);
            if ($f['type']==='ordenes' && ($row['medicion']??'')!=='Final') $fechaCostos=$f['to'];
            if (!array_key_exists($fechaCostos,$economicConfigs)) $economicConfigs[$fechaCostos]=productividadConfigLeer($db,$fechaCostos);
            $est=$row['_estimated']??null; $real=$row['_actual']??null;
            if ($f['type']==='procesos') $real=(int)($row['_cost_incomplete']??0)===0 && (int)($row['_estado']??-1)===2 ? ($row['real']??null) : null;
            $permitido=$f['type']==='operadores' && (int)($row['excluidas']??0)===0 && (int)($row['medibles']??0)>0;
            $row=array_merge($row,productividadEconomia($est,$real,$economicConfigs[$fechaCostos],$permitido));
        }
        foreach ($config['columns'] as $c) {
            if (isset($row[$c['field']]) && in_array($c['type'], ['integer','duration','percent','money'], true)) {
                $row[$c['field']] = (float)$row[$c['field']];
            }
        }
        unset($row['_cost_incomplete'], $row['_cost_date'], $row['_estado'], $row['_estimated'], $row['_actual'], $row['_productividad']);
        if (reportMatches($row, $f)) $result[] = $row;
    }
    return $result;
}

function reportCell($value, string $type): string {
    if ($value === null || $value === '') return 'Sin datos';
    if ($type === 'duration') {
        $seconds = (int)$value;
        return ($seconds < 0 ? '-' : '') . sprintf('%02d:%02d:%02d', intdiv(abs($seconds),3600), intdiv(abs($seconds)%3600,60), abs($seconds)%60);
    }
    if ($type === 'money') return number_format((float)$value,2,',','.');
    if ($type === 'percent') return productividadFormato($value);
    if ($type === 'integer') return number_format((float)$value, 0, ',', '.');
    if ($type === 'date') {
        $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string)$value);
        return $date ? $date->format('d/m/Y H:i') : (string)$value;
    }
    return (string)$value;
}

function reportDescription(array $f): string {
    $config = reportCatalog()[$f['type']];
    $text = $config['period'] . ': ' . $f['from'] . ' a ' . $f['to'] . '.';
    if ($f['status'] !== '') $text .= ' Estado: ' . $config['statuses'][$f['status']] . '.';
    if ($f['field'] !== '') {
        $columns = array_column($config['columns'], null, 'field');
        $ops = ['gt'=>'mayor que','gte'=>'mayor o igual que','lt'=>'menor que','lte'=>'menor o igual que','eq'=>'igual a','between'=>'entre','contains'=>'contiene','missing'=>'sin datos','present'=>'con datos'];
        $text .= ' ' . $columns[$f['field']]['title'] . ' ' . $ops[$f['operator']];
        if (!in_array($f['operator'], ['missing','present'], true)) $text .= ' ' . $f['value'];
        if ($f['operator'] === 'between') $text .= ' y ' . $f['valueTo'];
        $text .= '.';
    }
    return $text;
}
