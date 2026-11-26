<?php
    header('Content-Type: application/json; charset=utf-8');

    require_once "../vendor/autoload.php";
    require_once "../include/config.php";
    require_once 'rb-mysql.php';
    require_once "../include/functions.php";


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
                $response[]=['id' => $proceso->id, 'tarea_id' => $proceso->tarea_id, 'tarea_codigo' => $tarea_codigo];
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
            $proceso_existente = R::findOne('procesos', 'tarea_id = ? AND detalle_id = ?', [$_POST['tarea'], $_POST['detalle']]);

            if ($proceso_existente) {
                if ((int)$proceso_existente->estado === 2) {
                    // Ya terminada de verdad: bloquear
                    echo json_encode(['id' => 0, 'retomada' => false]);
                    exit;
                } else {
                    if ((int)$proceso_existente->estado === 4) {
                        $proceso_existente->tiempo_real = '00:00:00';
                        $proceso_existente->fin = null;
                        $proceso_existente->operador_aux = 0;
                    }
                    // Estaba en 0 (pendiente) o 1 (en curso, colgada): retomarla
                    $proceso_existente->estado      = 1;
                    $proceso_existente->operador_id = $_POST['operador'];
                    if (!empty($_POST['operadorAux']) && $_POST['operadorAux'] != 0 && $_POST['operadorAux'] !== '') {
                        $proceso_existente->operador_aux = (int) $_POST['operadorAux'];
                    }
                    $id = R::store($proceso_existente);
                    echo json_encode(['id' => $id, 'retomada' => true]);
                    exit;
                }
            } else {
                try {
                    $proceso = R::dispense('procesos');
                    $proceso->tarea_id     = $_POST['tarea'];
                    $proceso->estado       = $_POST['estado'];
                    $proceso->detalle_id   = $_POST['detalle'];
                    $proceso->operador_id  = $_POST['operador'];
                    if (!empty($_POST['operadorAux']) && $_POST['operadorAux'] != 0 && $_POST['operadorAux'] !== '') {
                        $proceso->operador_aux = (int) $_POST['operadorAux'];
                    } else {
                        $proceso->operador_aux = 0;
                    }
                    $id = R::store($proceso);
                    echo json_encode(['id' => $id, 'retomada' => false]);
                } catch (Throwable $e) {
                    echo json_encode(['id' => 0, 'retomada' => false]);
                }
                exit;
            }
        }elseif($_POST['accion']==='contarCompletadas'){
            // Usado al reanudar una orden: cuenta cuántas tareas ya se completaron
            // de esta tipología, consultando directo la fuente de verdad (no un contador
            // que viaja por el cliente y puede desincronizarse).
            $completadas = R::count('procesos', 'detalle_id = ? AND estado = 2', [$_POST['detalle']]);
            echo json_encode(['completadas' => (int)$completadas]);
            exit;
        }elseif($_POST['accion']==='update'){
			$proceso = R::load('procesos', $_POST['id']);
			if($proceso->id) {
                $duracionMs = filter_var($_POST['tiempo'] ?? null, FILTER_VALIDATE_INT);
                if ($duracionMs === false || $duracionMs < 0) {
                    echo json_encode([['status' => 'error', 'id' => 0]]);
                    exit;
                }
                // La regla se aplica al cierre de armado, no al de corte.
                $descartada = ($_POST['circuito'] ?? '') === 'armado'
                    && (int)$_POST['estado'] === 2 && $duracionMs <= 60000;
                try {
                    // Tablas existentes: evita DDL y habilita la transaccion de RedBean.
                    R::freeze(true);
                    R::begin();
                    if ($descartada && (int)$proceso->estado !== 4) {
                        $evento = R::dispense('eventos');
                        $evento->proceso_id = $proceso->id;
                        $evento->tipo = 'lectura_descartada';
                        $evento->descripcion = 'Descartada por duracion de hasta 60 segundos; tarea='
                            . $proceso->tarea_id . '; detalle=' . $proceso->detalle_id
                            . '; ayudante=' . $proceso->operador_aux . '; duracion_ms=' . $duracionMs;
                        $evento->tiempo_real = msToTime($duracionMs);
                        $evento->operador_id = $proceso->operador_id;
                        $evento->created = date('Y-m-d H:i:s');
                        R::store($evento);
                    }
                    $proceso->estado = $descartada ? 4 : $_POST['estado'];
                    $proceso->tiempo_real = msToTime($duracionMs);
                    $proceso->fin = date("Y-m-d H:i:s");
                    R::store($proceso);
                    R::commit();
                    $response[]=["status" => "ok", "id" => $proceso->id, 'descartada' => $descartada];
                } catch (Throwable $e) {
                    R::rollback();
                    $response[]=['status' => 'error', 'id' => 0];
                }
                
			}else{
                $response[]=["status" => "error", "id" => 0];
            }
            echo json_encode($response);
            exit;
        }
    }

?>
