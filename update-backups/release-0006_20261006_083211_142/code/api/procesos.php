<?php
    header('Content-Type: application/json; charset=utf-8');

    require_once "../vendor/autoload.php";
    require_once "../include/config.php";
    require_once 'rb-mysql.php';
    require_once "../include/functions.php";


    require_once __DIR__ . '/../include/procesos_fecha.php';
    require_once __DIR__ . '/../include/proceso_service.php';

    // Verifica si RedBeanPHP está funcionando
    try{
        R::setup('mysql:host='.hostBD.';dbname='.nombreBD, usuarioBD, claveBD);
        R::freeze(false); // Permitir que RedBean actualice el esquema
        //R::nuke(); // OJO: Borra todas las tablas, solo en desarrollo
        //R::debug(true); // Habilitar debug en RedBeanPHP
    }catch(Exception $e){
        echo die(json_encode(["error" => "No se pudo conectar a la base de datos"]));
    }


    // Verifica si RedBeanPHP está funcionando
    if (!R::testConnection()) {
        die(json_encode(["error" => "No se pudo conectar a la base de datos"]));
    }


    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $response = [];
        if($_POST['accion']==='select'){
            $proceso = R::findOne('procesos','id = ? ', [$_POST['id']]);
            if($proceso){
                $tarea_codigo = R::getCell("SELECT codigo FROM tareas WHERE tareas.id = ?", [$proceso->tarea_id]);
                $response[]=['id' => $proceso->id, 'tarea_id' => $proceso->tarea_id, 'tarea_codigo' => $tarea_codigo, 'estado' => (int)$proceso->estado];
            }
            echo json_encode($response); // Enviar JSON
        }elseif($_POST['accion']==='view'){
			$procesos = R::findAll('procesos', 'detalle_id = ?', [$_POST['idDetalle']]);
			foreach ($procesos as $proceso) {
                $tarea_descripcion = R::getCell("SELECT descripcion FROM tareas WHERE tareas.id = ?", [$proceso->tarea_id]);
                if ((int)$proceso->estado === 4) {
                    $tarea_descripcion .= ' (lectura descartada: hasta 60 segundos)';
                }
                $operador = R::getCell("SELECT nombre FROM operadores WHERE operadores.id = ?", [$proceso->operador_id]);
                $ayudante = R::getCell("SELECT nombre FROM operadores WHERE operadores.id = ?", [$proceso->operador_aux]);            

				$tiempoMs = $proceso->tiempo_real;
				$response[] = ['id'        => $proceso->id, 
                               'tarea'     => $tarea_descripcion,
                               'inicio' => $proceso->inicio,
                               'fin' => $proceso->fin,
                               'tiempo'    => $tiempoMs,
                               'tiempo_ms' => milisegundosToTime((int) $tiempoMs),
                               'operador'  => $operador,
                               'ayudante'  => $ayudante ?? ''
                            ];
			}
			echo json_encode($response);
        }elseif($_POST['accion']==='add'){
            try {
                $service=new ProcesoService(R::getDatabaseAdapter()->getDatabase()->getPDO());
                echo json_encode($service->start((int)($_POST['tarea']??0),(int)($_POST['detalle']??0),(int)($_POST['operador']??0),(int)($_POST['operadorAux']??0),(int)($_POST['pendienteId']??0)));
            } catch(Throwable $e) {
                error_log('Inicio de proceso: '.$e->getMessage());
                echo json_encode(['id'=>0,'retomada'=>false,'error'=>'No se pudo iniciar la tarea.']);
            }
            exit;
        }elseif($_POST['accion']==='suspender'){
            try {
                $data=json_decode($_POST['datos']??'',true,512,JSON_THROW_ON_ERROR);
                $service=new ProcesoService(R::getDatabaseAdapter()->getDatabase()->getPDO());
                echo json_encode($service->savePending($data));
            }catch(Throwable $e){error_log('Suspender proceso: '.$e->getMessage());echo json_encode(['status'=>'error']);}
            exit;
        }elseif($_POST['accion']==='contarCompletadas'){
            // Usado al reanudar una orden: cuenta cuántas tareas ya se completaron
            // de esta tipología, consultando directo la fuente de verdad (no un contador
            // que viaja por el cliente y puede desincronizarse).
            $completadas = R::count('procesos', 'detalle_id = ? AND estado = 2', [$_POST['detalle']]);
            echo json_encode(['completadas' => (int)$completadas]);
            exit;
        }elseif($_POST['accion']==='update'){
            try {
                $service=new ProcesoService(R::getDatabaseAdapter()->getDatabase()->getPDO());
                $response[]=$service->close((int)($_POST['id']??0),$_POST['tiempo']??null,(int)($_POST['estado']??-1),(string)($_POST['circuito']??''));
            } catch(Throwable $e) {
                error_log('Cierre de proceso: '.$e->getMessage());
                $response[]=['status'=>'error','id'=>0];
            }
            echo json_encode($response);
            exit;
        }
    }
