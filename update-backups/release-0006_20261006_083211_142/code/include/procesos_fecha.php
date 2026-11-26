<?php
/** Fechas civiles de procesos: siempre Mendoza, independientes del TZ de PHP/MySQL. */
function procesoFechaLocal(?int $epoch = null): string {
    $fecha = $epoch === null ? new DateTimeImmutable('now', new DateTimeZone('UTC')) : new DateTimeImmutable('@' . $epoch);
    return $fecha->setTimezone(new DateTimeZone('America/Argentina/Mendoza'))->format('Y-m-d H:i:s');
}
