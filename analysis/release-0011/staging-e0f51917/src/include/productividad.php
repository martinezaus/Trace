<?php
/** Desvío ponderado: negativo es ahorro; positivo es exceso. */
const PRODUCTIVIDAD_VERSION = 'desvio-ponderado-costos-v3';
function productividadSegundos($estimado, $real): ?float {
    if (!is_numeric($estimado) || !is_numeric($real) || $estimado <= 0 || $real <= 0) return null;
    return round(((float)$real - (float)$estimado) / (float)$estimado * 100, 2);
}
function productividadTiempo($tiempo): ?int {
    if (!is_string($tiempo) || !preg_match('/^(\d+):([0-5]\d):([0-5]\d)$/D', $tiempo, $m)) return null;
    return (int)$m[1] * 3600 + (int)$m[2] * 60 + (int)$m[3];
}
function productividadItem(array $item): ?float {
    if ((int)($item['medicion_incompleta'] ?? $item['eventos_incompletos'] ?? 0)>0) return null;
    if ((int)($item['estado'] ?? $item['c_estado'] ?? -1) !== 3) return null;
    return productividadSegundos(productividadTiempo($item['tiempo_estimado'] ?? null), productividadTiempo($item['tiempo_real'] ?? null));
}
function productividadPromedio(array $items): ?float {
    return productividadResumen($items)['porcentaje'];
}
function productividadResumen(array $items): array {
    $estimado = $real = $medibles = $terminadas = 0;
    foreach ($items as $item) {
        if ((int)($item['estado'] ?? $item['c_estado'] ?? -1) === 3) $terminadas++;
        if (productividadItem($item) === null) continue;
        $estimado += productividadTiempo($item['tiempo_estimado']);
        $real += productividadTiempo($item['tiempo_real']);
        $medibles++;
    }
    return ['estimado'=>$estimado, 'real'=>$real, 'ahorro'=>$estimado-$real,
        'medibles'=>$medibles, 'terminadas'=>$terminadas, 'total'=>count($items),
        'completo'=>count($items)>0 && $medibles===count($items),
        'porcentaje'=>productividadSegundos($estimado,$real)];
}

function productividadFormato($valor): string {
    if ($valor === null || $valor === '') return 'Sin datos';
    $valor = round((float)$valor, 2);
    return ($valor > 0 ? '+' : '') . number_format($valor == 0 ? 0 : $valor, 2, ',', '.') . ' %';
}
function productividadLeyenda(): string {
    return 'Productividad (desvío de tiempo) = (real computable - estimado comparable) / estimado comparable × 100. Negativo (-): ahorro, favorable; positivo (+): exceso, desfavorable; 0 %: cumple el estimado. Se comparan sumas de tiempos válidos de trabajos terminados, ponderadas por su estimado. Sin datos no es 0 %. Incluye retrabajos y pausas con flag=1; en corte ya están incluidas. Los resultados parciales y la atribución incompleta no son una liquidación de adicionales. No se infieren estimados por tarea.';
}
function productividadSql(string $alias = 'd'): string {
    return "CASE WHEN $alias.c_estado = 3 AND TIME_TO_SEC($alias.tiempo_estimado) > 0 AND TIME_TO_SEC($alias.tiempo_real) > 0 THEN ROUND((TIME_TO_SEC($alias.tiempo_real) - TIME_TO_SEC($alias.tiempo_estimado)) * 100.0 / TIME_TO_SEC($alias.tiempo_estimado), 2) END";
}
function productividadOrden(int $ordenId): ?float {
    $items = R::getAll('SELECT m.* FROM ('.productividadDetallesSql().') m WHERE m.orden_id=?', [$ordenId]);
    return productividadPromedio(array_map('productividadDetalleDatos', $items));
}
/** Tiempos guardados incluyen retrabajos. Armado excluye pausas; corte las incluye. */
function productividadDetallesSql(): string {
    $historial=productividadHistorialSql('d');
    $base="CASE WHEN pr.registros_con_tiempo>0 THEN COALESCE(pr.procesos_segundos,0)+($historial) ELSE TIME_TO_SEC(d.tiempo_real) END";
    return "SELECT d.*, $base base_segundos,
        COALESCE(ev.eventos_segundos,0) eventos_segundos,COALESCE(ev.eventos_incompletos,0) eventos_incompletos,
        COALESCE(ev.eventos_incompletos,0)+COALESCE(pr.sin_tiempo,0) medicion_incompleta,
        ($base)+COALESCE(ev.eventos_segundos,0) real_segundos,
        COALESCE(pr.fecha_fin,d.`update`) fecha_medicion,
        CASE WHEN pr.fecha_fin IS NULL THEN 'Última actualización (histórico sin cierre)' ELSE 'Finalización de procesos' END fecha_origen,
        CASE WHEN pr.operarios=1 AND pr.operador=d.operador_id AND pr.ayudantes=0
            AND pr.invalidos=0 AND pr.abiertos=0 AND pr.sin_fin=0 AND pr.sin_tiempo=0
            AND NOT EXISTS (SELECT 1 FROM detallehistorial h WHERE h.detalle_id=d.id AND h.tipo IN ('reversion_tipologia','reversion_tipologia_v2'))
            AND NOT EXISTS (SELECT 1 FROM eventos e JOIN procesos p ON p.id=e.proceso_id
                WHERE p.detalle_id=d.id AND e.operador_id IS NOT NULL AND e.operador_id<>d.operador_id)
            THEN 1 ELSE 0 END atribuible
        FROM detalles d
        LEFT JOIN (SELECT p.detalle_id,
            SUM(CASE WHEN e.tipo='pausa' AND EXISTS (SELECT 1 FROM pausas pa WHERE pa.descripcion=e.descripcion AND pa.flag=1)
                AND NOT EXISTS (SELECT 1 FROM cortes c WHERE c.proceso_id=p.id)
                THEN TIME_TO_SEC(e.tiempo_real) ELSE 0 END) eventos_segundos,
            SUM(CASE WHEN e.tipo='pausa' AND NOT EXISTS (SELECT 1 FROM cortes c WHERE c.proceso_id=p.id)
                AND (e.tiempo_real IS NULL OR NOT EXISTS (SELECT 1 FROM pausas pa WHERE pa.descripcion=e.descripcion))
                THEN 1 ELSE 0 END) eventos_incompletos
            FROM procesos p JOIN eventos e ON e.proceso_id=p.id GROUP BY p.detalle_id) ev ON ev.detalle_id=d.id
        LEFT JOIN (SELECT detalle_id,MAX(CASE WHEN estado=2 THEN fin END) fecha_fin,
            SUM(CASE WHEN estado=2 AND tiempo_real IS NOT NULL THEN 1 ELSE 0 END) registros_con_tiempo,
            SUM(CASE WHEN estado=2 THEN TIME_TO_SEC(tiempo_real) ELSE 0 END) procesos_segundos,
            COUNT(DISTINCT CASE WHEN estado=2 THEN operador_id END) operarios,
            MAX(CASE WHEN estado=2 THEN operador_id END) operador,
            SUM(CASE WHEN estado=2 AND operador_aux>0 THEN 1 ELSE 0 END) ayudantes,
            SUM(CASE WHEN estado=3 THEN 1 ELSE 0 END) invalidos,
            SUM(CASE WHEN estado IN (0,1) THEN 1 ELSE 0 END) abiertos,
            SUM(CASE WHEN estado=2 AND fin IS NULL THEN 1 ELSE 0 END) sin_fin,
            SUM(CASE WHEN estado=2 AND tiempo_real IS NULL THEN 1 ELSE 0 END) sin_tiempo
            FROM procesos GROUP BY detalle_id) pr ON pr.detalle_id=d.id";
}
function productividadDuracion($seconds): ?string {
    if ($seconds===null) return null;
    $s=(int)$seconds;
    return ($s<0?'-':'').sprintf('%02d:%02d:%02d',intdiv(abs($s),3600),intdiv(abs($s)%3600,60),abs($s)%60);
}
function productividadDetalleDatos(array $item): array {
    $item['tiempo_base']=productividadDuracion($item['base_segundos']);
    $item['tiempo_real']=productividadDuracion($item['real_segundos']);
    $item['estado']=$item['c_estado'];
    return $item;
}

function productividadTareasSql(): string {
            $taskValid = "p.estado=2 AND d.c_estado=3 AND d.etapas=1
                AND (SELECT COUNT(*) FROM procesos px WHERE px.detalle_id=d.id AND px.estado=2)=1
                AND NOT EXISTS (SELECT 1 FROM detallehistorial h WHERE h.detalle_id=d.id AND h.tipo IN ('reversion_tipologia','reversion_tipologia_v2'))
                AND NOT EXISTS (SELECT 1 FROM eventos e WHERE e.proceso_id=p.id AND e.tipo='pausa' AND (e.tiempo_real IS NULL OR NOT EXISTS (SELECT 1 FROM pausas pa WHERE pa.descripcion=e.descripcion)) AND NOT EXISTS (SELECT 1 FROM cortes c WHERE c.proceso_id=p.id))";
            $costIncomplete="EXISTS (SELECT 1 FROM eventos e WHERE e.proceso_id=p.id AND e.tipo='pausa' AND (e.tiempo_real IS NULL OR NOT EXISTS (SELECT 1 FROM pausas pa WHERE pa.descripcion=e.descripcion)) AND NOT EXISTS (SELECT 1 FROM cortes c WHERE c.proceso_id=p.id))";
            $taskReal = "TIME_TO_SEC(p.tiempo_real)+COALESCE((SELECT SUM(TIME_TO_SEC(e.tiempo_real)) FROM eventos e
                WHERE e.proceso_id=p.id AND e.tipo='pausa'
                AND EXISTS (SELECT 1 FROM pausas pa WHERE pa.descripcion=e.descripcion AND pa.flag=1)
                AND NOT EXISTS (SELECT 1 FROM cortes c WHERE c.proceso_id=p.id)),0)";
            return "SELECT p.id,p.detalle_id,d.orden_id,o.codigo orden,d.tipologia,t.descripcion tarea,op.nombre operario,
                aux.nombre ayudante,p.estado _estado,p.inicio inicio_registrado,COALESCE(p.fin,p.inicio) fecha,p.fin fin,
                $taskReal `real`,CASE WHEN $costIncomplete THEN 1 ELSE 0 END _cost_incomplete,CASE WHEN $taskValid THEN TIME_TO_SEC(d.tiempo_estimado) END estimado,
                CASE WHEN $taskValid THEN TIME_TO_SEC(d.tiempo_estimado) END _estimated,
                CASE WHEN $taskValid THEN $taskReal END _actual,
                CASE WHEN $taskValid THEN 'Estimado del detalle de una etapa' ELSE 'Sin estimado verificable por tarea' END medicion
                FROM procesos p LEFT JOIN detalles d ON d.id=p.detalle_id LEFT JOIN ordenes o ON o.id=d.orden_id
                LEFT JOIN tareas t ON t.id=p.tarea_id LEFT JOIN operadores op ON op.id=p.operador_id
                LEFT JOIN operadores aux ON aux.id=p.operador_aux ";
}

/** Las reversiones antiguas guardaban el acumulado y luego sumaban todos los acumulados.
 * Hn - SUM(H1..Hn-2) recupera el total anterior sin duplicaciones.
 * Desde v2 se guardan únicamente los segundos del ciclo revertido.
 */
function productividadHistorialSql(string $alias='d'): string {
    return "COALESCE((SELECT h.tiempo_real_anterior-COALESCE((
        SELECT SUM(h2.tiempo_real_anterior) FROM detallehistorial h2
        WHERE h2.detalle_id=h.detalle_id AND h2.tipo='reversion_tipologia'
        AND h2.id < COALESCE((SELECT MAX(h3.id) FROM detallehistorial h3
            WHERE h3.detalle_id=h.detalle_id AND h3.tipo='reversion_tipologia' AND h3.id<h.id),0)
        ),0) FROM detallehistorial h WHERE h.detalle_id=$alias.id AND h.tipo='reversion_tipologia'
        ORDER BY h.id DESC LIMIT 1),0)
        +COALESCE((SELECT SUM(h.tiempo_real_anterior) FROM detallehistorial h
            WHERE h.detalle_id=$alias.id AND h.tipo='reversion_tipologia_v2'),0)";
}
