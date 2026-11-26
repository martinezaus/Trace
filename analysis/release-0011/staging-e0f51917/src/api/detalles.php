<?php
require_once __DIR__ . "/../include/productividad.php";

    use BcMath\Number;

	header('Content-Type: application/json; charset=utf-8');

	require_once "../vendor/autoload.php";
    require_once "../include/auth.php";
	require_once "../include/config.php";    
	require_once 'rb-mysql.php';
	require_once "../include/functions.php";
	require_once "../include/log.php";
    require_once "../include/negocio.php";
	
	// Verifica si RedBeanPHP está funcionando
	try{
		R::setup('mysql:host='.hostBD.';dbname='.nombreBD, usuarioBD, claveBD);
		R::freeze(false); // Permitir que RedBean actualice el esquema
		//R::nuke(); // OJO: Borra todas las tablas, solo en desarrollo
	}catch(Exception $e){
		registrarLog('ERROR','CONECTION[Detalles]',"No se pudo conectar a la base de datos. ".$e->getMessage());
		echo die(json_encode(["error" => "No se pudo conectar a la base de datos"]));
	}

	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$response = [];
		

        if ($_POST['accion'] === 'resolverVidrio') {
            require_once '../include/etiquetas_vidrio.php';
            try {
                $ordenId = (int)($_POST['orden'] ?? 0);
                $codigo = trim((string)($_POST['barcode'] ?? ''));
                $orden = R::load('ordenes', $ordenId);
                if (!$orden->id || $codigo === '') throw new RuntimeException('Orden o codigo invalido.');
                $columnaCodigos = isset(R::inspect('detalles')['codigos_vidrio']) ? ', codigos_vidrio' : '';
                $detalles = R::getAll('SELECT id, codigo, tipologia, descripcion' . $columnaCodigos . ' FROM detalles WHERE orden_id = ? AND c_estado <> 3', [$ordenId]);
                $coincidencias = [];
                foreach ($detalles as $detalle) {
                    if (strpos($detalle['tipologia'], 'V-') !== 0 && strpos($detalle['tipologia'], 'TELA-M(') !== 0) continue;
                    $alternativos = json_decode($detalle['codigos_vidrio'] ?? '[]', true);
                    if ((string)$detalle['codigo'] === $codigo || (is_array($alternativos) && in_array($codigo, $alternativos, true))) {
                        $coincidencias[$detalle['id']] = $detalle;
                    }
                }
                if (count($coincidencias) > 1) throw new RuntimeException('El codigo coincide con varios vidrios. Revise los PLA de la orden.');
                if (!$coincidencias) throw new RuntimeException('Codigo no encontrado entre los vidrios pendientes. Verifique que se generaron los codigos desde el PLA de vidrio.');
                echo json_encode(['detalle' => array_values($coincidencias)[0]]);
            } catch (Throwable $e) {
                registrarLog('ERROR', 'ETIQUETAS_VIDRIO', $e->getMessage());
                echo json_encode(['error' => $e instanceof RuntimeException ? $e->getMessage() : 'No se pudo consultar la etiqueta.']);
            }
            exit;
        }

		if($_POST['accion']==='select'){
			$detalle = R::findOne('detalles','codigo = ? ', [$_POST['barcode']]);
			if($detalle){
				$response[]=['id' => $detalle->id, 
							 'codigo' => $detalle->codigo, 
							 'tipologia' => $detalle->tipologia, 
							 'descripcion' => $detalle->descripcion, 
							 'tiempo_estimado' => $detalle->tiempo_estimado, 
							 'etapas' => $detalle->etapas, 
							 'estado' => $detalle->c_estado,
							 'ayudante' => (int) $detalle->ayudante]; 
			}
			echo json_encode($response); // Enviar JSON            
		}elseif($_POST['accion']==='selectId'){
			$detalle = R::findOne('detalles', 'id = ?', [$_POST['id']]);
			if ($detalle) {
				$response[] = [
					'id' => $detalle->id,
					'codigo' => $detalle->codigo,
					'tipologia' => $detalle->tipologia,
					'estado' => $detalle->c_estado,
					'etapas' => $detalle->etapas
				];
			}
			echo json_encode($response);
		}elseif($_POST['accion']==='listar'){
			$detalles = R::findAll('detalles','orden_id = ? AND c_estado <> ? ORDER BY detalles.id ASC', [$_POST['orden'], 3]);
			if($detalles){
				foreach ($detalles as $detalle) {
					$response[] = ['id' => $detalle->id, 
								   'codigo' => $detalle->codigo,
								   'tipologia' => $detalle->tipologia, 
								   'descripcion' => $detalle->descripcion, 
								   'barcode' => base64_encode($detalle->barcode ?? ''),
								   'barcode2d' => base64_encode($detalle->barcode2d ?? '')];
				}
			}else{
				$response[] = [];
			} 
			echo json_encode($response);
		}elseif($_POST['accion']==='view'){
			$detalles = array_map('productividadDetalleDatos', R::getAll('SELECT m.* FROM ('.productividadDetallesSql().') m WHERE m.orden_id=? ORDER BY m.id', [$_POST['id']]));
			if($detalles){
				foreach ($detalles as $detalle) {
					$operador  = R::getCell('SELECT nombre FROM operadores WHERE id = ?', [$detalle['operador_id']]);

					$response[] = ['id' => $detalle['id'], 
								   'codigo' => $detalle['codigo'],
								   'tipologia' => $detalle['tipologia'], 
								   'descripcion' => $detalle['descripcion'], 
								   'operador' => $operador,
								   'tiempo_estimado' => $detalle['tiempo_estimado'],
								   'tiempo_real' => $detalle['tiempo_real'],
                                   'tiempo_base' => $detalle['tiempo_base'],
                                   'eventos_segundos' => $detalle['eventos_segundos'],
								   'eficiencia' => productividadItem($detalle),
								   'estado' => $detalle['c_estado'],
								   'detalles' => $detalle['details'],
								   'ayudante' => $detalle['ayudante']];
				}
			}else{
				$response[] = [];
			}
			echo json_encode($response);
		}elseif($_POST['accion']==='last'){
			$pendientes = R::count('detalles', 'orden_id = ? AND (c_estado = 0 OR c_estado = 1 OR c_estado = 2)', [$_POST['id_o']]);
			echo json_encode((int)$pendientes);
			exit;
		}elseif($_POST['accion']==='update'){
			$eficiencia=0; $segEstimado=0; $segReal=0;
			$detalle = R::findOne('detalles','id = ?', [$_POST['id']]);
			if($detalle){
			
				$tiempo_estimado = $detalle->tiempo_estimado;
				$segEstimado = timeToSeconds($tiempo_estimado);
				$segRealActual = (int) R::getCell('SELECT COALESCE(SUM(TIME_TO_SEC(tiempo_real)), 0) FROM procesos WHERE detalle_id = ? AND estado = 2', [$_POST['id']]);
				$segAnteriores = (int) R::getCell(
					'SELECT '.productividadHistorialSql('d').' FROM detalles d WHERE d.id=?', [$_POST['id']]
				);
				$segReal = $segRealActual + $segAnteriores;
				// Guardar el tiempo base; calcular el indicador con eventos sin volver a sumarlos al guardar.
                $medicion=R::getRow('SELECT m.eventos_segundos,m.medicion_incompleta FROM ('.productividadDetallesSql().') m WHERE m.id=?',[$_POST['id']]);
                $eficiencia = (int)$_POST['estado'] === 3 && (int)($medicion['medicion_incompleta']??0)===0 ? productividadSegundos($segEstimado, $segReal+(int)($medicion['eventos_segundos']??0)) : null;

				$detalle->operador_id = $_POST["operador"];
				$detalle->c_estado    = $_POST["estado"];
				$detalle->tiempo_real = segundosToTime($segReal);
				$detalle->eficiencia  = $eficiencia !== null && abs($eficiencia)>999.99 ? null : $eficiencia; // DECIMAL(5,2): la lectura recalcula sin recortes.
				R::store($detalle);      
			
				registrarLog('INFO','DETALLES',"Update: ".$_POST['id']." Tiempos (procesos+historial): ".$segRealActual."+".$segAnteriores."=".$segReal." Estimado: ".$segEstimado." Eficiencia: ".$eficiencia);

				echo json_encode(['status' => 'ok', 'id' => $detalle->id, 'eficiencia' => $eficiencia]);
			} else {
				registrarLog('ERROR','DETALLES', "Linea 128,Update fallido: detalle no encontrado id={$_POST['id']}");
				echo json_encode(['status' => 'error', 'id' => 0]);
			}
			exit;

		}elseif($_POST['accion']==='revertir'){
            require_once __DIR__.'/../include/detalle_admin_service.php';
            ini_set('display_errors','0');
            try{
                $service=new DetalleAdminService(R::getPDO());
                echo json_encode($service->revertir((int)($_POST['id']??0),operadorId()?:null,(string)($_POST['motivo']??'')),JSON_THROW_ON_ERROR);
            }catch(Throwable $e){
                http_response_code($e instanceof InvalidArgumentException?409:500);
                registrarLog('ERROR','DETALLES','Reversión: '.$e->getMessage());
                echo json_encode(['status'=>'error','valor'=>$e instanceof InvalidArgumentException?$e->getMessage():'No se pudo revertir. No se guardaron cambios.'],JSON_INVALID_UTF8_SUBSTITUTE);
            }
            exit;
		}elseif($_POST['accion']==='status'){
			$detalle = R::load('detalles', $_POST['id']);
			if($detalle->id) {
				$detalle->c_estado = $_POST['estado']; 
				R::store($detalle); 
				registrarLog('INFO','DETALLES',"Linea 190, Update[status]: ".$_POST['id']); 
				$band = 0;
			}else{
				registrarLog('ERROR','DETALLES',"Linea 193, Update[status].".$_POST['id'].".NO ACTUALIZADO.");
				$band = 1;
			}
			echo json_encode($band);
			exit;
		}elseif($_POST['accion']==='details'){
			$detalle = R::load('detalles', $_POST['id']);
			$response = [];
		
			if ($detalle->id) {
				if ((int)$_POST['opc'] === 0) {
					$detalle->details = $_POST['descripcion'] ?? '';
					registrarLog('INFO', 'DETALLES', "Linea 200, Update[descripcion]: Detalle -> ID:" . $detalle->id);
				}else {
					$detalle->ayudante = $_POST['ayudante'];
					registrarLog('INFO', 'DETALLES', "Linea 203, Update[ayudante]: Detalle -> ID:" . $detalle->id);
				}
				R::store($detalle);
				$response[] = ['resultado' => 'ok'];
			} else {
				$accion = ((int)$_POST['opc'] === 0) ? 'descripcion' : 'ayudante';
				registrarLog('ERROR', 'DETALLES', "Update[$accion]. Detalle -> ID:" . $_POST['id'] . ".NO ACTUALIZADO.");
				$response[] = ['resultado' => 'error'];
			}
		
			echo json_encode($response);
			exit;
		}elseif($_POST['accion'] === 'add_corte'){
            requerirOperadorApi();
            $detalle = R::findOne('detalles', 'orden_id = ? AND tipologia = ?', [$_POST['ordenId'], 'CORTE']);
			if ($detalle) {
				echo json_encode(['id' => (int)$detalle->id]);
				exit;
			}

            $negocio = reglas_negocio();
            $tareaCorte = (int) ($_POST['tareaId'] ?? 0);
            $tiempo_corte = ($tareaCorte === 20 ? $negocio->tiempo_cortep : $negocio->tiempo_cortev);  

            $detalle = R::dispense('detalles');
            $detalle->orden_id        = $_POST['ordenId'];
            $detalle->operador_id     = operadorId();
            $detalle->codigo          = "";
            $detalle->barcode         = null; 
            $detalle->barcode2d       = null; 
            $detalle->tipologia       = "CORTE";
            $detalle->etapas          = 1;
            $detalle->descripcion     = "CORTE DE PERFILES/VIDRIO";
            $detalle->tiempo_estimado = $tiempo_corte;

            try {
                R::store($detalle);
                $id=$detalle->id;
                //$detalles_guardados++;
            } catch (Exception $e) {
                registrarLog('ERROR','DETALLES',"linea 223, Add. Aluminio.".$e->getMessage());
                echo json_encode(['error' => 'Error al guardar detalle: ' . $e->getMessage()]);
                exit;
            }			 
            echo json_encode(['id' => $id]);
            exit;

		} 
	}    

	// Run it!
	//$router->run();

?>
