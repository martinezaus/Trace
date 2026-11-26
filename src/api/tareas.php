<?php
    header('Content-Type: application/json; charset=utf-8');
    // Require composer autoloader
	//require_once 'C:\xampp\htdocs\desarrollos\trace\vendor\autoload.php';
    
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
        
        if($_POST['accion']==='load'){
            $opcion = (string) ($_POST['opcion'] ?? '0');
            if($opcion === '0'){ //Mecanizado
                $tareas = R::findAll('tareas','(tipo=0 OR tipo=9) AND enable=0');
                foreach ($tareas as $tarea) {
                    $response[] = ['codigo' => $tarea->id,'nombre' => $tarea->descripcion,'barcode' => base64_encode($tarea->barcode),'barcode2d' => base64_encode($tarea->barcode2d) ];
                }
            }elseif($opcion === '1'){ //Vidrio
                $tareas = R::findAll('tareas','(tipo=1 OR tipo=9) AND enable=0');
                foreach ($tareas as $tarea) {
                    $response[] = ['codigo' => $tarea->id,'nombre' => $tarea->descripcion,'barcode' => base64_encode($tarea->barcode),'barcode2d' => base64_encode($tarea->barcode2d)];
                }
            }elseif(($opcion === '2' || $opcion === '3')){ //mosquitero
                $tareas = R::findAll('tareas','(tipo=3 OR tipo=9) AND enable=0');
                foreach ($tareas as $tarea) {
                    $response[] = ['codigo' => $tarea->id,'nombre' => $tarea->descripcion,'barcode' => base64_encode($tarea->barcode),'barcode2d' => base64_encode($tarea->barcode2d)];
                }
            }
            echo json_encode($response);
            //echo $response;
        }elseif($_POST['accion']==='auxiliar'){
            $tarea = R::findOne('tareas','codigo = ? ', [$_POST['tarea']]);
            if($tarea){
                $response[]=['id' => $tarea->id, 'nombre' => $tarea->descripcion, 'ayudante' => $tarea->ayudante, 'barcode' => base64_encode($tarea->barcode),'barcode2d' => base64_encode($tarea->barcode2d)];
            }else{
                $response[]=['id' => 0];
            }
            echo json_encode($response); // Enviar JSON
        }elseif($_POST['accion']==='select'){
            $tarea = R::findOne('tareas','codigo = ? ', [$_POST['codigo']]);
            if($tarea){
                $response[]=['id' => $tarea->id, 'nombre' => $tarea->descripcion];
            }else{
                $response[]=['id' => 0];
            }
            echo json_encode($response); // Enviar JSON
        }
    }    


	// Run it!
	//$router->run();

?>