<?php
	header('Content-Type: application/json');
	error_reporting(E_ALL & ~E_WARNING);
	ini_set('display_errors', 1);

	require_once '../../include/config.php';
	require_once '../../api/rb-mysql.php';
	require_once '../../include/functions.php';
    require_once "../../include/log.php";
	
	$documentos = [];

	// Verifica si RedBeanPHP está funcionando
	try{
		R::setup('mysql:host='.hostBD.';dbname='.nombreBD, usuarioBD, claveBD);
		R::freeze(true); // Permitir que RedBean actualice el esquema
		//R::nuke(); // OJO: Borra todas las tablas, solo en desarrollo
		//R::debug(true); // Habilitar debug en RedBeanPHP

	}catch(Exception $e){
        registrarLog('ERROR','CONECTION[Ordenes]',"No se pudo conectar a la base de datos. ".$e->getMessage());
		echo die(json_encode(["error" => "No se pudo conectar a la base de datos"]));
        exit;
	}

    // Validar que llegó el id de la orden
    $idOrden = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if (!$idOrden) {
        echo json_encode(['error' => 'No se recibió el id de la orden']);
        registrarLog('ERROR', 'FILE', 'Linea 30, filePdf.php: falta o es inválido el parámetro ido');
        exit;
    }

	
    // Mover planos opcionales (array)
    if (!empty($_FILES['planos']['name'])) {
        foreach ($_FILES['planos']['tmp_name'] as $i => $tmpName) {
            if ($_FILES['planos']['error'][$i] === UPLOAD_ERR_OK) {
                $nombre = $_FILES['planos']['name'][$i];
                $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
                if (!move_uploaded_file($tmpName, '../../tmp/' . $nombre)) {
                    echo json_encode(['error' => "No se pudo mover el plano: $nombre"]);
                    registrarLog('ERROR', 'FILE', "No se pudo mover plano: $nombre");
                    exit;
                }else{
                    if ($ext !== 'dat') {
                        $documentos[] = ['nombre' => $nombre,
                                         'descripcion' => $_POST['planos_descripciones'][$i] ?? '',  
                                         'tipo' =>  $_POST['planos_tipo'][$i] ?? '',
                                         'orden_id' => $idOrden,
                                        ];
                    }    
                }
            }
        }
    }

	echo json_encode(['documentos' => $documentos]);
	exit;

?>