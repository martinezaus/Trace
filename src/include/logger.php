<?php

    require_once 'log.php';

    $nivel   = $_POST['nivel']   ?? 'INFO';
    $mensaje = $_POST['mensaje'] ?? '';
    $modulo  = $_POST['modulo']  ?? '';

    registrarLog($nivel, $modulo, $mensaje);

    echo json_encode([
        'ok' => true
    ]);

?>