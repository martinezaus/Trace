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

        }elseif($_POST['accion']==='load'){

        }elseif($_POST['accion']==='add'){

        }elseif($_POST['accion']==='update'){

        }    

    }

?>