<?php

	header('Content-Type: application/json; charset=utf-8');

    require_once '../include/auth.php';
	require_once '../include/licencia.php';
	require_once "../vendor/autoload.php";
	require_once '../include/config.php';
	require_once 'rb-mysql.php';
	
	
	use Picqer\Barcode\BarcodeGeneratorPNG;
	$generator= new BarcodeGeneratorPNG();
	$tipo=$generator::TYPE_CODE_128;

	// Verifica si RedBeanPHP está funcionando

	try{
		R::setup('mysql:host='.hostBD.';dbname='.nombreBD, usuarioBD, claveBD);
		if (!R::testConnection()) {
			throw new Exception('No se pudo conectar a la base de datos');
		}        
       
		R::freeze(true); // Permitir que RedBean actualice el esquema
		//R::nuke(); // OJO: Borra todas las tablas, solo en desarrollo
	}catch(Throwable $e){
		echo json_encode(['resultado' => 'ERROR', 'mensaje' => $e->getMessage()]);
	}


	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$response = [];
		if($_POST['accion']==='load'){ //Todos los operadores activos/inactivos
			$operarios = R::getAll('SELECT id,nombre,token,CAST(estado AS UNSIGNED) estado,CAST(session AS UNSIGNED) sesion,CAST(corte AS UNSIGNED) corte,CAST(revisador AS UNSIGNED) revisador FROM operadores');
			foreach ($operarios as $operario) {
				$response[] = ['id'     => $operario['id'],
                               'nombre' => $operario['nombre'],
                               'token'  => $operario['token'],
                               'estado' => (int)$operario['estado'],
                               'sesion' => (int)$operario['sesion'],
                               'corte' => (int)$operario['corte'],
                               'revisador' => (int)$operario['revisador']];
			}
			echo json_encode($response);
		}elseif($_POST['accion']==='login'){ 
            $token = trim((string)($_POST['token'] ?? $_POST['codigo'] ?? ''));
			if ($token === '') {
				echo json_encode(['ok' => false, 'motivo' => 'credencial']);
				exit;
			}

			$operador = R::findOne('operadores', 'token = ? AND estado = 0', [$token]);
			if (!$operador) {
				echo json_encode(['ok' => false, 'motivo' => 'credencial']);
				exit;
			}
			if (!licenciaVigente()) {
				echo json_encode(['ok' => false, 'motivo' => 'licencia']);
				exit;
			}
			$permisos = R::getRow('SELECT CAST(corte AS UNSIGNED) corte,CAST(revisador AS UNSIGNED) revisador FROM operadores WHERE id=?', [(int)$operador->id]);
			if (!$permisos) {
				http_response_code(409);
				echo json_encode(['ok' => false, 'motivo' => 'credencial']);
				exit;
			}
			$corte = (int)$permisos['corte'];
			$revisador = (int)$permisos['revisador'];
			session_regenerate_id(true);
			$_SESSION['operador_id'] = (int)$operador->id;
			$_SESSION['operador_nombre'] = $operador->nombre;
			$_SESSION['operador_corte'] = $corte;
			$_SESSION['operador_revisador'] = $revisador;
			$operador->session_token = session_id();

			try {
				R::store($operador);
			} catch (Throwable $e) {
				// La columna puede faltar durante un despliegue; el login sigue sin control de sesión única.
				error_log('No se pudo guardar session_token del operador: ' . $e->getMessage());
			}
            echo json_encode([
                'ok'       => true,
                'id'       => (int)$operador->id,
                'nombre'   => $operador->nombre,
                'corte'    => $corte,
                'revisador'=> $revisador
            ]);
		
		}elseif($_POST['accion']==='select'){ 
            $id = (int)($_POST['id'] ?? 0);
			$operario = R::load('operadores', $id);
			$sessionToken = null;
			try {
				$propiedades = $operario->getProperties();
				if (array_key_exists('session_token', $propiedades)) {
					$sessionToken = $operario->session_token;
				}
			} catch (Throwable $e) {
				// Si no se puede leer la columna, se omite el control de sesión única.
				error_log('No se pudo leer session_token del operador: ' . $e->getMessage());
			}
			if (operadorId() > 0 && $id === operadorId() && $sessionToken !== null && $sessionToken !== '' && $sessionToken !== session_id()) {
				echo json_encode(['ok' => false, 'motivo' => 'sesion']);
				exit;
			}
			$valores = R::getRow('SELECT CAST(estado AS UNSIGNED) estado,CAST(session AS UNSIGNED) sesion,CAST(corte AS UNSIGNED) corte,CAST(revisador AS UNSIGNED) revisador FROM operadores WHERE id=?', [$id]);
			if (!$operario->id || !$valores) {
				http_response_code(404);
				echo json_encode(['ok' => false, 'mensaje' => 'El operario no existe.']);
				exit;
			}
			$response[] = ['id' => $operario->id,
                           'nombre' => $operario->nombre, 
                           'token' => $operario->token, 
                           'estado' => (int)$valores['estado'],
                           'sesion' => (int)$valores['sesion'],
                           'corte' => (int)$valores['corte'],
                           'revisador' => (int)$valores['revisador'],
                           'foto' => base64_encode((string) ($operario->foto ?? ''))];
			echo json_encode($response); // Enviar JSON

        }elseif(in_array($_POST['accion'],['add','edit','del','reset'],true)){
            require_once __DIR__.'/../include/operadores_admin.php';
            ini_set('display_errors','0');
            try { echo json_encode(guardarOperadorAdmin($_POST['accion'],$_POST,$_FILES),JSON_THROW_ON_ERROR); }
            catch(Throwable $e){
                http_response_code($e instanceof InvalidArgumentException?422:500);
                error_log('Operarios: '.$e->getMessage());
                echo json_encode(['resultado'=>'ERROR','mensaje'=>$e instanceof InvalidArgumentException?$e->getMessage():'No se pudo guardar el operario. Revise el registro con soporte.'],JSON_INVALID_UTF8_SUBSTITUTE);
            }
		}elseif($_POST['accion']==='print'){
			$operador = R::load('operadores', $_POST['id']);
			$response[] = ['barcode' => base64_encode($operador->barcode), 'detalle' => $operador->nombre];
			echo json_encode($response);   
		}elseif($_POST['accion']==='productividad'){
            try {
            $operadores = R::findAll('operadores', 'estado = 0');
            foreach ($operadores as $operador) {
                // Cantidad producida por el operador en el mes actual
                $cantidadMes = R::getCell("SELECT COUNT(id)
                                    FROM detalles
                                    WHERE operador_id = ?
                                      AND YEAR(`update`) = ?
                                      AND MONTH(`update`) = ?
                                      AND c_estado = 3",
                                    [$operador->id, date('Y'), date('m')]);
            
                // Verifico si existe registro de productividad para el mes
                $productividad = R::findOne('productividad', 'operador_id = ? AND anio = ? AND mes = ?',
                                    [$operador->id, date('Y'), date('m')]);
            
                if (!$productividad) {
                    // Crear registro
                    $productividad = R::dispense('productividad');
                    $productividad->operador_id = $operador->id;
                    $productividad->anio = date('Y');
                    $productividad->mes = date('m');
                    $productividad->cantidad = $cantidadMes;
            
                } else {
            
                    // Actualizar registro existente
                    $productividad->cantidad = $cantidadMes;
            
                }
            
                R::store($productividad);
            }
            echo json_encode(['resultado'=>'OK']);
            }catch(Throwable $e){http_response_code(500);error_log('Productividad: '.$e->getMessage());echo json_encode(['resultado'=>'ERROR','mensaje'=>'No se pudo actualizar la productividad.']);}
        }elseif($_POST['accion']==='validarAyudante'){
            // Valida el código de un operador ayudante SIN tocar la sesión activa
            // del operador principal (no regenera session id, no pisa session_token).
            $token = trim((string)($_POST['token'] ?? $_POST['codigo'] ?? ''));
            if ($token === '') {
                echo json_encode(['ok' => false, 'motivo' => 'credencial']);
                exit;
            }
        
            $operador = R::findOne('operadores', 'token = ? AND estado = 0', [$token]);
            if (!$operador) {
                echo json_encode(['ok' => false, 'motivo' => 'credencial']);
                exit;
            }
        
            echo json_encode([
                'ok'     => true,
                'id'     => (int)$operador->id,
                'nombre' => $operador->nombre
            ]);
        }        
	}    
?>