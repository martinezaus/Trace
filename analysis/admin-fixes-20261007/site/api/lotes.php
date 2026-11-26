<?php

    header('Content-Type: application/json; charset=utf-8');

    require_once "../vendor/autoload.php";    
    require_once "../include/config.php";    
	require_once 'rb-mysql.php';

    // Verifica si RedBeanPHP está funcionando
    try{
        R::setup('mysql:host='.hostBD.';dbname='.nombreBD, usuarioBD, claveBD);
        R::freeze(false); // Permitir que RedBean actualice el esquema
        //R::nuke(); // OJO: Borra todas las tablas, solo en desarrollo
    }catch(Exception $e){
        echo die(json_encode(["error" => "No se pudo conectar a la base de datos"]));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $response = [];
        if($_POST['accion']==='select'){
            //$data=json_decode(file_get_contents('php://input'),true);
            //$val= array_values($data);
            $lote = R::find('lotes','orden_id = ?', [$_POST['orden']]);
            if(count($lote)){
                foreach($lote as $row){
                    $response[]=['codigo' => $row->id, 'producto' => $row->producto, 'fecha' => $row->f_inicio];
                }
                //print_r(json_encode((object)$response));
                echo json_encode($response); // Enviar JSON            
            }else{
                $response[]=['codigo' => 0, 'producto' => 'No existe', 'fecha' => ''];
                echo json_encode($response);
            }
        }elseif($_POST['accion']==='view'){
            $lote = R::findOne('lotes','orden_id = ?', [$_POST['id']]);
            if($lote){
                $response[]=['id' => $lote->id,
                             'items' => $lote->items, 
                             'piezas' => $lote->piezas, 
                             'tipologias' => $lote->tipologias,
                             'vidrios' => $lote->vidrios];

            }else{
                $response[]=['id' => 0];

            }
            echo json_encode($response);
        }    
        
    }    
    R::close();
	// Run it!
	//$router->run();

?>