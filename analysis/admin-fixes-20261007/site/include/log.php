<?php

    include_once("config.php");


    function registrarLog($nivel, $modulo, $mensaje){
   
    date_default_timezone_set(ZONA);
    $directorio = LOG;

    if (!is_dir($directorio)) {
        mkdir($directorio, 0777, true);
    }

    $archivo = $directorio . '/' . date('Y-m-d') . '.log';

    $fecha = date('Y-m-d H:i:s');
    $ip    = $_SERVER['REMOTE_ADDR'] ?? 'DESCONOCIDA';

    $texto = "[$fecha] [$nivel]";

    // if ($usuario != '') {
    //     $texto .= " [USR:$usuario]";
    // }

    if ($modulo != '') {
        $texto .= " [MOD:$modulo]";
    }

    $texto .= " [IP:$ip] $mensaje" . PHP_EOL;

    file_put_contents($archivo, $texto, FILE_APPEND | LOCK_EX);

}
