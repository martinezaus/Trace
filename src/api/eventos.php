<?php

    header('Content-Type: application/json; charset=utf-8');
    
    require_once "../vendor/autoload.php";
    require_once "../include/config.php"; 
    require_once "../include/functions.php";
	require_once 'rb-mysql.php';
    require_once "../include/log.php";

    // Verifica si RedBeanPHP está funcionando
    try{
        R::setup('mysql:host='.hostBD.';dbname='.nombreBD, usuarioBD, claveBD);
        R::freeze(true); // Permitir que RedBean actualice el esquema
        //R::debug(true);  // poné true si querés ver el SQL en pantalla
    }catch(Exception $e){
        registrarLog('ERROR','CONECTION[Eventos]',"No se pudo conectar a la base de datos. ".$e->getMessage());
        echo die(json_encode(["error" => "No se pudo conectar a la base de datos"]));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $response = [];
        if($_POST['accion']==='add'){
            try{
               $datos = json_decode($_POST['datos'], true);    

               $evento = R::dispense('eventos');
               $evento->proceso_id  = !empty($datos['procesoId']) ? (int)$datos['procesoId'] : null;
               $evento->tipo        = $datos['tipo'];     //pausa, suspension u otro
            //    $evento->codigo_id   = !empty($datos['codigo_id']) ? (int)$datos['codigo_id'] : null;
            //    $evento->codigo_sub  = $datos['codigo_sub'];
               $evento->descripcion = $datos['descripcion'] ?? '';
               if ($evento->tipo === 'pausa' && !empty($datos['codigo_sub'])) {
                   $pausa = R::findOne('pausas', 'codigo = ?', [$datos['codigo_sub']]);
                   if ($pausa) $evento->descripcion = $pausa->descripcion;
               }
               $evento->tiempo_real = milisegundosToTime($datos['tiempo'] ?? 0);
               $evento->operador_id = $datos['operadorId'] ?? 0;
               $id = R::store($evento);
               registrarLog('INFO','EVENTOS',"Linea 37, se agrego el evento con ID: ".$id." , datos: ".print_r($datos, true));
            }catch(Exception $e){
                registrarLog('ERROR','EVENTOS',"Linea 39, ".$e->getMessage());
                $id=0;
            }
            $response[]=['id'=>$id]; 
            echo json_encode($response);
            exit;
        }elseif($_POST['accion']==='update'){
            $evento = R::findOne('eventos', 'id = ?', [$_POST['evento']]); // ID
            if($evento){
                $evento->tiempo_real = milisegundosToTime($_POST['tiempo']);
                $evento->operador_id = $_POST['operador']; 
                R::store($evento);
                registrarLog('INFO','EVENTOS',"Linea 47, se actualizo el evento con ID: ".$_POST['evento']);  
                $band = 0;
            }else{
                registrarLog('ERROR','EVENTOS',"Linea 50, No se actualizo evento con ID: " .$_POST['evento'].". Verifique.");
                $band = 1;
            }
            echo json_encode($band);
            exit;
        }elseif($_POST['accion']==='view'){
            $descripcion="";
            // Los eventos históricos identifican la pausa por su descripción.
            $pausasConTiempo = R::getCol('SELECT descripcion FROM pausas WHERE flag = 1');
            $eventos = R::findAll('eventos', 'proceso_id = ? AND (tipo="pausa" OR tipo="suspension" OR tipo="lectura_descartada") ORDER BY eventos.id ASC', [$_POST['id']]);
            if($eventos){
                foreach ($eventos as $evento) {

                    // if($evento->codigo_id == 3){
                    //     $pausa = R::findOne('pausas','codigo = ?', $evento->codigo_sub);
                    //     $descripcion = $pausa->descripcion ?? '';
                    // }else if($evento->codigo_id == 4){
                    //     $suspension = R::findOne('suspensiones','codigo = ?', $evento->codigo_sub);
                    //     $descripcion = $suspension->descripcion ?? '';
                    // }

                    $response[]=['id' => $evento->id, 
                                 'proceso' => $evento->proceso_id,
                                 'suma_tiempo' => $evento->tipo === 'pausa' && in_array($evento->descripcion, $pausasConTiempo, true),
                                 'tipo' => $evento->tipo,         //Tipo de Codigo: Reanudar, Pausa, Suspension o Finalizacion
                                //  'codigo' => $evento->codigo_id, 
                                //  'tipoc' => $evento->codigo_sub,     
							     'descripcion' => $evento->descripcion, //."(".$descripcion.")", 
							     'fecha' => $evento->created,
							     'tiempo' => $evento->tiempo_real,
                                 'operador' => $evento->operador_id];
                }                                  
            }else{
                $response[]=['id' => 0];
            }
            echo json_encode($response); // Enviar JSON 
            exit;
        }    
    }    
    R::close();
	// Run it!
	//$router->run();

?>
