<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../include/reportes_service.php';
require_once __DIR__ . '/../include/reportes_export.php';
$checks = 0;
function check($condition, string $message): void {
    global $checks; $checks++;
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
}
function rejects(callable $call, string $message): void {
    try { $call(); } catch (InvalidArgumentException | JsonException $e) { check(true,$message); return; }
    check(false,$message);
}
function filters(string $type = 'ordenes', array $extra = []): array {
    return reportFilters(array_merge(['reportType'=>$type,'dateFrom'=>'2026-09-01','dateTo'=>'2026-09-30'], $extra));
}
function put(PDO $db, string $table, array $row): void {
    $sql='INSERT INTO '.$table.' ('.implode(',',array_map(static fn($c)=>'`'.$c.'`',array_keys($row))).') VALUES ('.implode(',',array_fill(0,count($row),'?')).')';
    $db->prepare($sql)->execute(array_values($row));
}
// Base efímera en memoria: ninguna escritura en la base del negocio.
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->sqliteCreateFunction('TIME_TO_SEC', static function($value) {
    if ($value === null) return null;
    if (!preg_match('/^(\d+):([0-5]\d):([0-5]\d)$/',$value,$m)) return null;
    return (int)$m[1]*3600+(int)$m[2]*60+(int)$m[3];
},1);
foreach ([
    'ordenes'=>'id INTEGER,codigo TEXT,cliente TEXT,c_estado INTEGER,f_alta TEXT,tiempo_orden TEXT',
    'lotes'=>'orden_id INTEGER,tipologias INTEGER,items INTEGER',
    'detalles'=>'id INTEGER,orden_id INTEGER,operador_id INTEGER,tipologia TEXT,c_estado INTEGER,tiempo_estimado TEXT,tiempo_real TEXT,`update` TEXT',
    'operadores'=>'id INTEGER,nombre TEXT,estado INTEGER',
    'procesos'=>'id INTEGER,detalle_id INTEGER,tarea_id INTEGER,estado INTEGER,inicio TEXT,fin TEXT,tiempo_real TEXT,operador_id INTEGER,operador_aux INTEGER',
    'tareas'=>'id INTEGER,descripcion TEXT',
    'eventos'=>'id INTEGER,proceso_id INTEGER,tipo TEXT,descripcion TEXT,tiempo_real TEXT,operador_id INTEGER,created TEXT',
] as $name=>$columns) $db->exec('CREATE TABLE '.$name.' ('.$columns.')');
put($db,'ordenes',['id'=>1,'codigo'=>'00123','cliente'=>'Álvarez <script> & Hijos','c_estado'=>2,'f_alta'=>'2026-09-30 23:59:59','tiempo_orden'=>'30:00:00']);
put($db,'ordenes',['id'=>2,'codigo'=>'00000','cliente'=>'Sin actividad','c_estado'=>0,'f_alta'=>'2026-09-01 00:00:00','tiempo_orden'=>'00:00:00']);
put($db,'ordenes',['id'=>3,'codigo'=>'FUERA','cliente'=>'Fuera','c_estado'=>3,'f_alta'=>'2026-10-01 00:00:00','tiempo_orden'=>'01:00:00']);
put($db,'lotes',['orden_id'=>1,'tipologias'=>2,'items'=>5]);
put($db,'lotes',['orden_id'=>1,'tipologias'=>3,'items'=>7]);
put($db,'operadores',['id'=>1,'nombre'=>'Operario Uno','estado'=>0]);
put($db,'operadores',['id'=>2,'nombre'=>'Sin actividad','estado'=>1]);
foreach ([
    [1,3,'01:00:00','02:00:00'], [2,3,'09:00:00','03:00:00'], [3,3,'00:00:00','00:00:00'], [4,1,'01:00:00',null]
] as [$id,$status,$est,$real]) {
    put($db,'detalles',['id'=>$id,'orden_id'=>1,'operador_id'=>1,'tipologia'=>'T-'.$id,'c_estado'=>$status,
        'tiempo_estimado'=>$est,'tiempo_real'=>$real,'update'=>'2026-09-30 23:59:59']);
}
put($db,'tareas',['id'=>1,'descripcion'=>'Armado']);
put($db,'procesos',['id'=>1,'detalle_id'=>1,'tarea_id'=>1,'estado'=>2,'inicio'=>'2026-09-01 00:00:00',
    'fin'=>'2026-09-01 02:00:00','tiempo_real'=>'02:00:00','operador_id'=>1,'operador_aux'=>2]);
put($db,'eventos',['id'=>1,'proceso_id'=>1,'tipo'=>'pausa','descripcion'=>'Falta de material',
    'tiempo_real'=>'00:30:00','operador_id'=>1,'created'=>'2026-09-30 23:59:59']);
$orders=reportData($db,filters());
check(count($orders)===2,'Límites de fecha inclusivos sin incluir el día siguiente');
$order=array_values(array_filter($orders,static fn($r)=>$r['id']==1))[0];
check($order['estado']==='Terminada','Estado 2 es terminada');
check($order['items']===12.0 && $order['tipologias']===5.0,'Sumar lotes sin multiplicar detalles');
check($order['eficiencia']===-16.67,'Promedio simple de -100 y +66,67, excluyendo ítems no medibles');
check($order['medibles']===2.0 && $order['terminadas']===3.0,'Informar cobertura de tiempos válidos');
check($order['estimado']===108000.0,'Duraciones superiores a 24 horas');
check(count(reportData($db,filters('ordenes',['statusFilter'=>'0'])))===1,'Estado cero válido');
check(count(reportData($db,filters('ordenes',['additionalCondition'=>'estimado','conditionOperator'=>'eq','conditionValue'=>'00:00:00'])))===1,'Filtro de tiempo cero');
check(count(reportData($db,filters('ordenes',['additionalCondition'=>'items','conditionOperator'=>'gt','conditionValue'=>'0'])))===1,'Filtro numérico mayor que cero');
check(count(reportData($db,filters('ordenes',['additionalCondition'=>'cliente','conditionOperator'=>'contains','conditionValue'=>'ÁLVAREZ'])))===1,'Texto UTF-8 sin distinción de mayúsculas');
$operators=reportData($db,filters('operadores'));
check(count($operators)===2,'Operarios sin actividad incluidos');
check($operators[0]['eficiencia']===-16.67 && !isset($operators[0]['desvio']),'Fórmula y desvío coherentes');
check($operators[1]['eficiencia']===null && $operators[1]['terminadas']===0.0,'Sin datos es diferente de 0 %');
check(count(reportData($db,filters('operadores',['additionalCondition'=>'eficiencia','conditionOperator'=>'missing'])))===1,'Condición sin datos');
check(count(reportData($db,filters('operadores',['additionalCondition'=>'eficiencia','conditionOperator'=>'between','conditionValue'=>'-16,68','conditionValueTo'=>'-16,66'])))===1,'Rango decimal inclusivo');
check(count(reportData($db,filters('tipologias')) )===4,'Reporte tipologías');
check(reportData($db,filters('tipologias',['statusFilter'=>'1']))[0]['eficiencia']===null,'No calcular eficiencia de tipologías abiertas');
check(reportData($db,filters('procesos'))[0]['ayudante']==='Sin actividad','Ayudante identificado sin duplicar procesos');
check(reportData($db,filters('eventos'))[0]['estado']==='Pausa','Tipo de evento');
$clients=reportData($db,filters('clientes'));
check(count($clients)===2,'Resumen por cliente');
$client=array_values(array_filter($clients,static fn($r)=>$r['cliente']===$order['cliente']))[0];
check($client['ordenes']===1.0 && $client['items']===12.0,'Resumen sin duplicar órdenes por lotes');
check(reportCell(0,'integer')==='0','Mostrar ceros');
check(reportCell(90061,'duration')==='25:01:01','Formato de duración sin reinicio a 24 h');
check(reportCell(null,'percent')==='Sin datos','Nulos explícitos');
check(reportMetric(1,0)===null,'Sin división por cero');
foreach ([
    ['dateFrom'=>'2026-02-30'], ['dateFrom'=>'2026-10-01'], ['statusFilter'=>'4'],
    ['reportType'=>'desconocido'], ['additionalCondition'=>'items','conditionValue'=>''],
    ['additionalCondition'=>'items','conditionValue'=>'-1'], ['additionalCondition'=>'estimado','conditionValue'=>'24:99:00'],
    ['additionalCondition'=>'items','conditionOperator'=>'between','conditionValue'=>'5','conditionValueTo'=>'2'],
    ['additionalCondition'=>'id; DROP TABLE ordenes','conditionValue'=>'1'], ['dateTo'=>['x']]
] as $bad) rejects(static fn()=>filters('ordenes',$bad),'Validar entradas');
check(reportMetric(100,80)===20.0,'Ahorro positivo');
check(reportMetric(100,120)===-20.0,'Demora negativa');
check(reportMetric(100,100)===0.0,'Igual al estimado');
check(reportMetric(100,350)===-250.0,'Sin recortar demoras a -100');
check(reportMetric(100,1)===99.0,'Sin umbral arbitrario del 5 %');
check(reportMetric(0,10)===null,'Sin estimado válido');
check(reportCell(20,'percent')==='+20,00 %','Signo positivo explícito');
check(reportCell(-20,'percent')==='-20,00 %','Signo negativo');
check(reportCell(0,'percent')==='0,00 %','Cero sin signo');
check(productividadPromedio([])===null,'Promedio vacío');
check(productividadItem(['estado'=>1,'tiempo_estimado'=>'01:00:00','tiempo_real'=>'00:30:00'])===null,'Excluir abiertos');
check(count(reportData($db,filters('tipologias',['additionalCondition'=>'eficiencia','conditionOperator'=>'lt','conditionValue'=>'0'])))===1,'Filtro negativo');
check(reportConditionValue('+20,5','percent')===20.5,'Filtro con signo más');
$snapshot=['metricVersion'=>'ahorro-v1','id'=>'test','filters'=>filters(),'rows'=>$orders,'created'=>'2026-09-24 12:00:00'];
rejects(static fn()=>reportExportSelection(['rows'=>$orders], '[0]'),'Rechazar consultas con criterio anterior');
$selected=reportExportSelection($snapshot,'[1,0]');
check($selected[0]['id']===$orders[1]['id'],'Exportar en el orden de la tabla');
rejects(static fn()=>reportExportSelection($snapshot,'[0,0]'),'No duplicar filas');
rejects(static fn()=>reportExportSelection($snapshot,'[999]'),'No aceptar índices ajenos');
rejects(static fn()=>reportExportSelection($snapshot,'[]'),'No exportar vacíos');
$csv=reportCsv($snapshot,$selected);
check(str_starts_with($csv,"\xEF\xBB\xBF"),'CSV UTF-8 BOM');
check(str_contains($csv,'Terminada'),'CSV estados legibles');
check(str_starts_with(reportCsvValue('=HYPERLINK("x")'),"'"),'Neutralizar fórmulas CSV');
check(reportEscape('<script>')==='&lt;script&gt;','Escapar HTML');
if (in_array('--pdf',$argv,true)) {
    $many=[];
    for($i=0;$i<85;$i++) {
        $row=$order; $row['id']=$i+1;
        $row['cliente']='Álvarez & Hijos - Carpintería de aluminio, sucursal con nombre extenso';
        $many[]=$row;
    }
    $snapshot['rows']=$many;
    $pdf=reportPdf($snapshot,$many,'Álvarez',false);
    check(str_starts_with($pdf,'%PDF-'),'PDF binario real');
    file_put_contents(sys_get_temp_dir().'/trace-reportes-test.pdf',$pdf);
}
echo "OK: $checks comprobaciones. Base de prueba en memoria.\n";
