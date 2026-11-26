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

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $response = [];
        if($_POST['accion']==='select'){
            $codigo = R::findOne('codigos','codigo = ?', [$_POST['codigo']]);
            if($codigo){
                $response[] = ['id' => $codigo->id];
            }else{
                $response[] = ['id' => 0];
            }
            echo json_encode($response);
        }else if($_POST['accion']==='add'){
           //Terminar

        }elseif($_POST['accion']==='update'){
           //Terminar

        }elseif($_POST['accion']==='listar'){
        
        }
    }    
    R::close();
	// Run it!
	//$router->run();

?>