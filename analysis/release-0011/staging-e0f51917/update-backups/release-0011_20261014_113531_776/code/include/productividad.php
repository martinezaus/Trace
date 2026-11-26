<?php
/** Criterio único: ahorro de tiempo respecto al estimado, promedio simple de ítems medibles. */
function productividadSegundos($estimado, $real): ?float {
    if (!is_numeric($estimado) || !is_numeric($real) || $estimado <= 0 || $real <= 0) return null;
    return round(((float)$estimado - (float)$real) / (float)$estimado * 100, 2);
}
function productividadTiempo($tiempo): ?int {
    if (!is_string($tiempo) || !preg_match('/^(\d+):([0-5]\d):([0-5]\d)$/D', $tiempo, $m)) return null;
    return (int)$m[1] * 3600 + (int)$m[2] * 60 + (int)$m[3];
}
function productividadItem(array $item): ?float {
    if ((int)($item['estado'] ?? $item['c_estado'] ?? -1) !== 3) return null;
    return productividadSegundos(productividadTiempo($item['tiempo_estimado'] ?? null), productividadTiempo($item['tiempo_real'] ?? null));
}
function productividadPromedio(array $items): ?float {
    $valores = array_values(array_filter(array_map('productividadItem', $items), static fn($v) => $v !== null));
    return $valores ? round(array_sum($valores) / count($valores), 2) : null;
}
function productividadFormato($valor): string {
    if ($valor === null || $valor === '') return 'Sin datos';
    $valor = round((float)$valor, 2);
    return ($valor > 0 ? '+' : '') . number_format($valor == 0 ? 0 : $valor, 2, ',', '.') . ' %';
}
function productividadLeyenda(): string {
    return 'Productividad = (tiempo estimado - tiempo real) / tiempo estimado × 100. Positivo (+): menos tiempo y mejor rendimiento; negativo (-): más tiempo y menor rendimiento; 0 %: igual al estimado. Órdenes y operarios: promedio simple de los porcentajes de ítems terminados con ambos tiempos mayores que cero. Sin datos: no hay medición válida; se excluye del promedio. Los tiempos reales incluyen los retrabajos registrados.';
}
function productividadSql(string $alias = 'd'): string {
    return "CASE WHEN $alias.c_estado = 3 AND TIME_TO_SEC($alias.tiempo_estimado) > 0 AND TIME_TO_SEC($alias.tiempo_real) > 0 THEN ROUND((TIME_TO_SEC($alias.tiempo_estimado) - TIME_TO_SEC($alias.tiempo_real)) * 100.0 / TIME_TO_SEC($alias.tiempo_estimado), 2) END";
}
function productividadOrden(int $ordenId): ?float {
    $valor = R::getCell('SELECT AVG(' . productividadSql() . ') FROM detalles d WHERE d.orden_id = ?', [$ordenId]);
    return $valor === null ? null : round((float)$valor, 2);
}
