<?php

    header('Content-Type: application/json; charset=utf-8');

    require_once "../vendor/autoload.php";
    require_once "../include/config.php";
    require_once 'rb-mysql.php';
    require_once '../include/log.php';
    require_once '../include/functions.php';
    require_once '../include/procesos_fecha.php';
    require_once '../include/proceso_service.php';

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
            $sesion = R::findOne('sesiones','sesiones.id = ?',[$_POST['id']]);
            if($sesion){
                $response[] = [
                    'id' => $sesion->id,
                    'operador_id' => $sesion->operador_id,
                ];
            }
            echo json_encode($response);
        }else if ($accion === 'add') {
            $datos = json_decode($_POST['datos'] ?? '', true);
            if ($datos !== null) {

                $sesion = R::dispense('sesiones');
                $sesion->operador_id = $datos['operadorId'] ?? null;
                $sesion->estado = $datos['estado'];

                try{
                    $id=R::store($sesion);
                    $response[] = ['status' => 'ok', 'id' => $id];
                } catch (Exception $e) {
                    registrarLog('ERROR', 'SESION', 'Linea 48, ' . $e->getMessage() . ' - Archivo: ' . $e->getFile() . ' - Linea: ' . $e->getLine() . ' - Datos: ' . json_encode($datos));
                    $response[] = ['status' => 'error', 'id' => 0];    
                }
            }
            echo json_encode($response);
        }else if ($accion === 'cerrarConPendiente') {
            $sesionId = (int)($_POST['sesionId'] ?? 0);
            $datosSesion = json_decode($_POST['datosSesion'] ?? '', true);
            $datosCierre = json_decode($_POST['datosCierre'] ?? '', true);
            $datosEvento = json_decode($_POST['datosEvento'] ?? '', true);

            if (!$sesionId || !is_array($datosSesion) || !is_array($datosCierre) || !is_array($datosEvento)) {
                echo json_encode([['status' => 'error', 'valor' => 'Datos de cierre incompletos']]);
                exit;
            }

            try {
                $datosCierre['sesionId']=$sesionId;
                $service=new ProcesoService(R::getDatabaseAdapter()->getDatabase()->getPDO());
                echo json_encode([$service->savePending($datosCierre,true)]);
            } catch(Throwable $e) {
                registrarLog('ERROR','SESION','Cierre transaccional fallido: '.$e->getMessage());
                echo json_encode([['status'=>'error','valor'=>'No se pudo guardar el cierre.']]);
            }
        }else if ($accion === 'update') {
            $sesion = R::findOne('sesiones', 'sesiones.id = ?', [$_POST['id'] ?? null]);

            if ($sesion) {
                try {
                    $datos = json_decode($_POST['datos'] ?? '', true);
 
                    $sesion->fecha_fin = date('Y-m-d H:i:s');
                    $sesion->estado    = $datos['estado'] ?? $sesion->estado;
        
                    R::store($sesion);
                    $response[] = ['status' => 'ok'];
                } catch (\Throwable $e) {
                    registrarLog('ERROR', 'SESION', 'Linea 67, update, id=' . ($_POST['id'] ?? 'null') . ' - ' . $e->getMessage() . ' - Archivo: ' . $e->getFile() . ' - Linea: ' . $e->getLine() . ' - Datos: ' . ($_POST['datos'] ?? '(vacio)'));
                    $response[] = ['status' => 'error', 'valor' => 'Error al guardar la sesión'];
                }
            } else {
                $response[] = ['status' => 'error', 'valor' => 'Sesion no encontrada, id: ' . ($_POST['id'] ?? 'null')];
            }
            echo json_encode($response);
        }
    }
