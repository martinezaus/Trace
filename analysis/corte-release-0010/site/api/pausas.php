<?php
    header('Content-Type: application/json');

    require_once "../vendor/autoload.php";
    require_once "../include/config.php";
	require_once 'rb-mysql.php';

	// Verifica si RedBeanPHP está funcionando
	try{
		R::setup('mysql:host='.hostBD.';dbname='.nombreBD, usuarioBD, claveBD);
		R::freeze(false); // Permitir que RedBean actualice el esquema
	}catch(Exception $e){
		echo die(json_encode(["error" => "No se pudo conectar a la base de datos"]));
	}

	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$response = [];

		if($_POST['accion']==='listar'){
			$pausas = R::findAll('pausas','codigo_id = ?',[$_POST['idc']]);
		    foreach($pausas as $pausa) {
				$response[] = ['id' => (int)$pausa->id,
							   'codigo' => $pausa->codigo,
							   'descripcion' => $pausa->descripcion,
							   'flag' => (int)$pausa->flag,
							   'barcode' => base64_encode($pausa->barcode),
							   'barcode2d' => base64_encode($pausa->barcode2d)];
			}
			echo json_encode($response);
		}else if($_POST['accion']==='select'){
            $pausa = R::findOne('pausas','codigo = ?', [$_POST['codigo']]);
            $response[]=[ 'id' => $pausa ? (int)$pausa->id : 0,
                          'codigo' => $pausa ? $pausa->codigo : '',
                          'descripcion' => $pausa ? $pausa->descripcion : '',
                          'flag' => $pausa ? (int)$pausa->flag : 0];
            echo json_encode($response);
        }else if($_POST['accion']==='add'){
            //Terminar
			echo json_encode($response);
		}elseif($_POST['accion']==='update'){
            //Terminar
			echo json_encode($response);
		}
	}    
	R::close();
	// Run it!
	//$router->run();

?>