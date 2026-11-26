<?php

// Opción A: Usando date() con timestamp (más común)
function fechaDDMMAA($fecha = null) {
    // Si no pasas nada → usa fecha actual
    $timestamp = $fecha ? strtotime($fecha) : time();
    return $timestamp ? date('d-m-y', $timestamp) : 'Fecha inválida';
}

// Opción B: Usando DateTime (más segura y moderna)
function fechaDDMMAA_DT($fecha = null) {
    try {
        $dt = $fecha ? new DateTime($fecha) : new DateTime();
        return $dt->format('d-m-Y H:i:s');
    } catch (Exception $e) {
        return 'Fecha inválida';
    }
}

// Función auxiliar para convertir TIME(hora) a segundos
function timeToSeconds(string $time): int {
    if (!$time || $time === '00:00:00') return 0;
    $parts = explode(':', $time);
    if (count($parts) !== 3) return 0;
    return (int)$parts[0] * 3600 + (int)$parts[1] * 60 + (int)$parts[2];
}

// Funcion se pasa los segundos y te devuelve en formato Hora.
function segundosToTime(int|float $segundosTotales): string{
    $segundosTotales=(int)$segundosTotales; // Fracciones descartadas explícitamente, como antes.
    $horas    = intdiv($segundosTotales, 3600);
    $minutos  = intdiv($segundosTotales % 3600, 60);
    $segundos = $segundosTotales % 60;

    return sprintf('%02d:%02d:%02d', $horas, $minutos, $segundos);
}

function msToTime(int $milliseconds): string {
    return segundosToTime(intdiv($milliseconds, 1000));
}

function milisegundosToTime(int $milliseconds): string{
    $segundos = intdiv($milliseconds, 1000);
    $horas = floor($segundos / 3600);
    $minutos = floor(($segundos % 3600) / 60);
    $secs = $segundos % 60;
    return sprintf('%02d:%02d:%02d', $horas, $minutos, $secs);
}

function customMinutesToSeconds(float $valor) { //horasADuracion()
    $horas = $valor;
    $minutos = round(($valor - $horas) * 100);
    return (($horas * 60) + $minutos) * 60;
}


?>