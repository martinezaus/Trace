<?php
   // Require composer autoloader
	//require_once 'C:\xampp\htdocs\desarrollos\trace\vendor\autoload.php';

    require_once "../include/config.php";    
	require_once 'rb-mysql.php';

    // Verifica si RedBeanPHP está funcionando
    try{
        R::setup('mysql:host='.hostBD.';dbname='.nombreBD, usuarioBD, claveBD);
        R::freeze(false); // Permitir que RedBean actualice el esquema
    }catch(Exception $e){
        echo die(json_encode(["error" => "No se pudo conectar a la base de datos"]));
    }

    // if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    //     $response = [];
    //     if($_POST['accion']==='select'){
    //         // $codigo = R::findOne('codigos','codigo = ?', [$_POST['codigo']]);
    //         // if($codigo){
    //         //     $response[] = ['id' => $codigo->id];
    //         // }else{
    //         //     $response[] = ['id' => 0];
    //         // }
    //         // echo json_encode($response);
    //     }else if($_POST['accion']==='add'){
    //         requerirOperadorApi();
    //         $corte = R::findOne('cortes', 'orden_id = ? AND estado = ?', [$_POST['ordenId'], 0]);
	// 		if ($corte) {
	// 			echo json_encode(['id' => (int)$corte->id]);
	// 			exit;
	// 		}

    //         $corte = R::dispense('cortes');

    //         $corte->orden_id        = $_POST['ordenId'];
    //         $corte->tipo            = $_POST['tipo'];
    //         $corte->timestamp_inicio = 
    //         $corte->
    //         $corte->operador_id     = operadorId();
    //         $corte->codigo          = "";
    //         $corte->barcode         = null; 
    //         $detalle->barcode2d       = null; 
    //         $detalle->tipologia       = "CORTE";
    //         $detalle->etapas          = 1;
    //         $detalle->descripcion     = "CORTE DE PERFILES/VIDRIO";
    //         $detalle->tiempo_estimado = $tiempo_corte;

    //         try {
    //             R::store($detalle);
    //             $id=$detalle->id;
    //             //$detalles_guardados++;
    //         } catch (Exception $e) {
    //             registrarLog('ERROR','DETALLES',"linea 223, Add. Aluminio.".$e->getMessage());
    //             echo json_encode(['error' => 'Error al guardar detalle: ' . $e->getMessage()]);
    //             exit;
    //         }			 
    //         echo json_encode(['id' => $id]);
    //         exit;



    //     }elseif($_POST['accion']==='update'){
    //        //Terminar

    //     }elseif($_POST['accion']==='listar'){
        
    //     }
    // }    
    R::close();
	// Run it!
	//$router->run();

?>