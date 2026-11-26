<?php
declare(strict_types=1);
require_once __DIR__ . "/productividad.php";

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
    return [
        'ordenes' => [
            'title' => 'Órdenes',
            'period' => 'Fecha de alta de la orden',
            'note' => 'Estados actuales. El tiempo real corresponde a tipologías terminadas; puede ser parcial. ' . $efficiency,
            'statuses' => ['0' => 'Cargada', '1' => 'Procesando', '2' => 'Terminada', '3' => 'Instalada'],
            'columns' => [$col('id','ID','integer'), $col('codigo','Código'), $col('cliente','Cliente'),
                $col('estado','Estado'), $col('fecha','Alta','date'), $col('estimado','Estimado orden','duration'),
                $col('real','Real tip. terminadas','duration'), $col('tipologias','Tipologías','integer'),
                $col('items','Ítems','integer'), $col('terminadas','Tip. terminadas','integer'),
                $col('medibles','Tip. con tiempos válidos','integer'), $col('eficiencia','Productividad (%)','percent')],
        ],
        'operadores' => [
            'title' => 'Operarios',
            'period' => 'Última actualización de tipologías terminadas',
            'note' => 'Actividad atribuida al operario principal registrado en la tipología. Incluye operarios sin actividad. La última actualización no es una fecha histórica inmutable de finalización. ' . $efficiency,
            'statuses' => ['0' => 'Activo', '1' => 'Inactivo'],
            'columns' => [$col('id','ID','integer'), $col('nombre','Operario'), $col('estado','Estado'),
                $col('terminadas','Tip. terminadas','integer'), $col('medibles','Tip. con tiempos válidos','integer'),
                $col('estimado','Estimado comparable','duration'), $col('real','Real comparable','duration'),
                $col('eficiencia','Productividad (%)','percent')],
        ],
        'tipologias' => [
            'title' => 'Tipologías',
            'period' => 'Última actualización de la tipología',
            'note' => 'Estados actuales, no una reconstrucción histórica. ' . $efficiency,
            'statuses' => ['0' => 'Pendiente', '1' => 'Pausada', '2' => 'Suspendida', '3' => 'Terminada'],
            'columns' => [$col('id','ID','integer'), $col('orden','Orden'), $col('cliente','Cliente'),
                $col('tipologia','Tipología'), $col('operario','Operario'), $col('estado','Estado'),
                $col('fecha','Actualización','date'), $col('estimado','Estimado','duration'),
                $col('real','Real registrado','duration'), $col('eficiencia','Productividad (%)','percent')],
        ],
        'procesos' => [
            'title' => 'Procesos',
            'period' => 'Fecha de inicio del proceso',
            'note' => 'Una fila por proceso registrado. Tiempo registrado, no cronómetro en vivo. El ayudante se informa por separado para no duplicar la producción.',
            'statuses' => ['0' => 'Pendiente', '1' => 'En proceso', '2' => 'Finalizado', '3' => 'Invalidado por reversión', '4' => 'Lectura descartada (hasta 60 s)'],
            'columns' => [$col('id','ID','integer'), $col('orden','Orden'), $col('tipologia','Tipología'),
                $col('tarea','Tarea'), $col('operario','Operario'), $col('ayudante','Ayudante'),
                $col('estado','Estado'), $col('fecha','Inicio','date'), $col('fin','Fin','date'),
                $col('real','Tiempo registrado','duration')],
        ],
        'eventos' => [
            'title' => 'Pausas y suspensiones',
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
    if (in_array($type, ['integer','percent'], true)) {
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
        $allowed = in_array($kind, ['duration','integer','percent'], true)
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
    $metric = productividadSql('d');
    $valid = 'd.c_estado = 3 AND TIME_TO_SEC(d.tiempo_estimado) > 0 AND TIME_TO_SEC(d.tiempo_real) > 0';
    $aggregate = "SELECT d.orden_id, SUM(d.c_estado = 3) terminadas,
        SUM(CASE WHEN $valid THEN 1 ELSE 0 END) medibles,
        SUM(CASE WHEN d.c_estado = 3 THEN TIME_TO_SEC(d.tiempo_real) END) `real`,
        SUM(CASE WHEN $valid THEN TIME_TO_SEC(d.tiempo_estimado) END) comparable_estimado,
        SUM(CASE WHEN $valid THEN TIME_TO_SEC(d.tiempo_real) END) comparable_real, AVG($metric) productividad
        FROM detalles d GROUP BY d.orden_id";
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
                a.comparable_estimado _estimated, a.comparable_real _actual, a.productividad _productividad
                FROM ordenes o LEFT JOIN ($lots) l ON l.orden_id=o.id
                LEFT JOIN ($aggregate) a ON a.orden_id=o.id WHERE o.f_alta >= ? AND o.f_alta < ?";
            $statusField = 'o.c_estado'; $order = 'o.id DESC'; break;
        case 'operadores':
            $sql = "SELECT o.id, o.nombre, CAST(o.estado AS UNSIGNED) _estado,
                COALESCE(a.terminadas,0) terminadas, COALESCE(a.medibles,0) medibles,
                a.estimado, a.real, a.estimado _estimated, a.real _actual, a.productividad _productividad FROM operadores o
                LEFT JOIN (SELECT d.operador_id, COUNT(*) terminadas,
                SUM(CASE WHEN $valid THEN 1 ELSE 0 END) medibles,
                SUM(CASE WHEN $valid THEN TIME_TO_SEC(d.tiempo_estimado) END) estimado,
                SUM(CASE WHEN $valid THEN TIME_TO_SEC(d.tiempo_real) END) `real`, AVG($metric) productividad
                FROM detalles d WHERE d.c_estado=3 AND d.`update` >= ? AND d.`update` < ?
                GROUP BY d.operador_id) a ON a.operador_id=o.id WHERE 1=1";
            $statusField = 'o.estado'; $order = 'o.nombre, o.id'; break;
        case 'tipologias':
            $sql = "SELECT d.id, o.codigo orden, o.cliente, d.tipologia, op.nombre operario,
                d.c_estado _estado, d.`update` fecha, TIME_TO_SEC(d.tiempo_estimado) estimado,
                TIME_TO_SEC(d.tiempo_real) `real`,
                CASE WHEN $valid THEN TIME_TO_SEC(d.tiempo_estimado) END _estimated,
                CASE WHEN $valid THEN TIME_TO_SEC(d.tiempo_real) END _actual
                FROM detalles d JOIN ordenes o ON o.id=d.orden_id LEFT JOIN operadores op ON op.id=d.operador_id
                WHERE d.`update` >= ? AND d.`update` < ?";
            $statusField = 'd.c_estado'; $order = 'd.id DESC'; break;
        case 'procesos':
            $sql = "SELECT p.id, o.codigo orden, d.tipologia, t.descripcion tarea, op.nombre operario,
                aux.nombre ayudante, p.estado _estado, p.inicio fecha, p.fin fin, TIME_TO_SEC(p.tiempo_real) `real`
                FROM procesos p LEFT JOIN detalles d ON d.id=p.detalle_id LEFT JOIN ordenes o ON o.id=d.orden_id
                LEFT JOIN tareas t ON t.id=p.tarea_id LEFT JOIN operadores op ON op.id=p.operador_id
                LEFT JOIN operadores aux ON aux.id=p.operador_aux WHERE p.inicio >= ? AND p.inicio < ?";
            $statusField = 'p.estado'; $order = 'p.inicio DESC, p.id DESC'; break;
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
    $result = [];
    foreach ($rows as $row) {
        if (array_key_exists('_estado', $row)) {
            $row['estado'] = $config['statuses'][(string)$row['_estado']] ?? ('Estado no reconocido: ' . ($row['_estado'] ?? 'sin dato'));
        }
        if (array_key_exists('_estimated', $row)) {
            $row['eficiencia'] = array_key_exists('_productividad', $row)
                ? ($row['_productividad'] === null ? null : round((float)$row['_productividad'], 2))
                : reportMetric($row['_estimated'], $row['_actual']);
        }
        foreach ($config['columns'] as $c) {
            if (isset($row[$c['field']]) && in_array($c['type'], ['integer','duration','percent'], true)) {
                $row[$c['field']] = (float)$row[$c['field']];
            }
        }
        unset($row['_estado'], $row['_estimated'], $row['_actual'], $row['_productividad']);
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
