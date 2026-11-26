<?php

    header('Content-Type: application/json; charset=utf-8');

    require_once "../vendor/autoload.php";
    require_once "../include/config.php";
    require_once 'rb-mysql.php';
    require_once '../include/log.php';

    // Verifica si RedBeanPHP está funcionando
    try {
        R::setup('mysql:host='.hostBD.';dbname=' . nombreBD, usuarioBD, claveBD);
        R::freeze(true); // Esquema congelado: no se crean/alteran columnas solas
        //R::debug(true);  // poné true si querés ver el SQL en pantalla

    } catch (Exception $e) {
        registrarLog('ERROR', 'PENDIENTES', 'Linea 12: No se pudo conectar a la base de datos - ' . $e->getMessage());
        echo json_encode([['status' => 'error', 'valor' => 'No se pudo conectar a la base de datos']]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $response = [];
        $accion   = $_POST['accion'] ?? null;

        if ($accion === 'select') {
            $ordenId   = $_POST['ordenId'] ?? null;
            $detalleId = $_POST['detalleId'] ?? null;

            if ($ordenId === null || $detalleId === null) {
                registrarLog('ERROR', 'PENDIENTES', 'Linea select: falta ordenId o detalleId en la peticion');
                echo json_encode([['status' => 'error', 'valor' => 'Faltan parametros']]);
                exit;
            }

            $pendientes = R::findOne("pendientes", "orden_id = ? AND detalle_id = ? AND valided = ?", [$ordenId, $detalleId, 0]);

            if ($pendientes) {
                $response[] = [
                    'id'                 => $pendientes->id,
                    'orden_id'           => $pendientes->orden_id,
                    'detalle_id'         => $pendientes->detalle_id,
                    'proceso_id'         => $pendientes->proceso_id,
                    'sesionId'           => $pendientes->sesion_id,
                    'tareaActualId'      => $pendientes->tarea_actual_id,
                    'tiempoOrdenMs'      => $pendientes->tiempo_orden_ms,
                    'tiempoTareaMs'      => $pendientes->tiempo_tarea_ms,
                    'tiempoTareaTotalMs' => $pendientes->tiempo_tarea_total_ms,
                    'tiempoOperadorMs'   => $pendientes->tiempo_operador_ms,     
                    'menu'               => $pendientes->menu,
                    'valided'            => $pendientes->valided,
                    'created'            => $pendientes->created
                ];
            } 
            echo json_encode($response);
            exit;

        } elseif ($accion === 'listarPorOrden') {
            $ordenId = $_POST['ordenId'] ?? null;

            if ($ordenId === null) {
                echo json_encode([]);
                exit;
            }

            $lista = R::findAll("pendientes", "orden_id = ? AND valided = ? ORDER BY created DESC", [$ordenId, 0]);
            foreach ($lista as $p) {
                $response[] = [
                    'id'                 => $p->id,
                    'orden_id'           => $p->orden_id,
                    'detalle_id'         => $p->detalle_id,
                    'proceso_id'         => $p->proceso_id,
                    'sesionId'           => $p->sesion_id,
                    'tareaActualId'      => $p->tarea_actual_id,
                    'tiempoOrdenMs'      => $p->tiempo_orden_ms,
                    'tiempoTareaMs'      => $p->tiempo_tarea_ms,
                    'tiempoTareaTotalMs' => $p->tiempo_tarea_total_ms,
                    'tiempoOperadorMs'   => $p->tiempo_operador_ms,
                    'menu'               => $p->menu,
                    'created'            => $p->created,
                ];
            }
            echo json_encode($response);
            exit;

        } elseif ($accion === 'add') {

            $datos = json_decode($_POST['datos'] ?? '', true);

            if ($datos !== null) {

                $pendientes = R::dispense('pendientes');

                $pendientes->orden_id              = $datos['ordenId'] ?? 0;
                $pendientes->detalle_id            = $datos['detalleId'] ?? 0;
                $pendientes->proceso_id            = $datos['procesoId'] ?? 0;
                $pendientes->sesion_id             = $datos['sesionId'] ?? 0;
                $pendientes->tarea_actual_id       = !empty($datos['tareaActualId']) ? (int)$datos['tareaActualId'] : 0;
                $pendientes->tiempo_orden_ms       = $datos['tiempoOrdenMs'] ?? 0;
                $pendientes->tiempo_tarea_ms       = $datos['tiempoTareaMs'] ?? 0;
                $pendientes->tiempo_tarea_total_ms = $datos['tiempoTareaTotalMs'] ?? 0;
                $pendientes->tiempo_operador_ms     = $datos['tiempoOperadorMs'] ?? 0;
                $pendientes->menu                  = $datos['menu'] ?? null;

                try {
                    $id = R::store($pendientes);
                    registrarLog('INFO', 'PENDIENTES', 'Linea 76, se agrego el pendiente con ID: ' . $id . ', datos: ' . json_encode($datos));
                    $response[] = ['status' => 'ok', 'valor' => $id];
                } catch (Exception $e) {
                    registrarLog('ERROR', 'PENDIENTES', 'Linea 79, ' . $e->getMessage() . ' - Archivo: ' . $e->getFile() . ' - Linea: ' . $e->getLine() . ' - Datos: ' . json_encode($datos));
                    $response[] = ['status' => 'error', 'valor' => $e->getMessage()];
                }

            } else {
                registrarLog('ERROR', 'PENDIENTES', 'Linea add: datos JSON invalidos o ausentes. Raw: ' . ($_POST['datos'] ?? '(vacio)'));
                $response[] = ['status' => 'error', 'valor' => 'Datos invalidos'];
            }

            echo json_encode($response);
            exit;

        } elseif ($accion === 'status') {
            $id = $_POST['id'] ?? null;
            if ($id === null) {
                registrarLog('ERROR', 'PENDIENTES', 'Linea status: falta detalleID en la peticion');
                echo json_encode([['status' => 'error', 'valor' => 'Falta detalleID']]);
                exit;
            }
            $pendientes = R::findOne('pendientes', 'pendientes.id = ? AND pendientes.valided = ?', [$id, 0]);
            if ($pendientes) {
                $pendientes->valided = 1;
                $pendientes->updated = date("Y-m-d H:i:s");
                try {
                    R::store($pendientes);
                    $response[]=["status" => "ok", "id" => $pendientes->id];
                } catch (Exception $e) {
                    $response[]=["status" => "error, ".$e->getMessage(), "id" => 0];
                }
            } else {
                $response[] = ['request' => 1];
            }
            echo json_encode($response);
            exit;
        } 
    }

    R::close();