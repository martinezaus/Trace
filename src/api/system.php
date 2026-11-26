<?php
    header('Content-Type: application/json; charset=utf-8');

    require_once "../include/auth.php";
    require_once "../vendor/autoload.php";
    require_once "../include/config.php";
    require_once 'rb-mysql.php';
    require_once '../include/log.php';
    require_once "../include/licencia.php";
    require_once "../include/negocio.php";

    use Picqer\Barcode\BarcodeGeneratorPNG;
    $generator= new BarcodeGeneratorPNG;
	$tipo=$generator::TYPE_CODE_128;

    // Verifica si RedBeanPHP está funcionando
    try{
        R::setup('mysql:host='.hostBD.';dbname='.nombreBD, usuarioBD, claveBD);
        R::freeze(false);
    }catch(Exception $e){
        echo die(json_encode(["error" => "No se pudo conectar a la base de datos"]));
    }

    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $response = [];
        $band=null;
        if($_POST['accion']==='enabled'){ 
            if(licenciaVigente()){
                $band=0;
            }else{
                $band=1;
            }
        }elseif($_POST['accion']==='sesion'){
            requerirOperadorApi();
            $id = operadorId();
            R::getDatabaseAdapter()->exec("SET @usuario_id = ".(int)$id);
            echo json_encode(['ok' => true, 'operador_id' => $id]);

        }elseif($_POST['accion']==='logueo'){
            $system = R::getCell('SELECT * FROM systemusage WHERE password = ?',[$_POST['pw']]);
            if($system){
               $band = 0;
            }else{
                $master = R::getRow('SELECT * FROM systemusage WHERE pwmaster = ?', [$_POST['pw']]);
                $band = $master ? 0 : 1;
            }
            echo json_encode($band);
        }elseif($_POST['accion']==='operacion'){
            $codigo = R::findOne('codigos', 'id = ?', [$_POST['id']]);
            if($codigo){
                $response=['barcode' => base64_encode($codigo->barcode),'barcode2d' => base64_encode($codigo->barcode2d)]; 
            }else{
                $response = ['error' => 'Código no encontrado'];
            }
            echo json_encode($response);
        }elseif($_POST['accion']==='master'){ //cierra la pagina completa con un codigo master
            $system = R::findOne('systemusage', 'id = 1 AND master = ?', [$_POST['codigo']]);
            $response[] = ['clave' => $system?->password ?? 0];
            echo json_encode($response);            
        }elseif($_POST['accion']==='select'){
            $system = R::findOne('systemusage', 'id = 1');
            $response[]=['pwm' => $system->pwmaster,'master' => base64_encode($system->barcode_master), 'leyenda' => 'TOKEN DE ACCESO'];
            echo json_encode($response); // Enviar JSON

        }elseif($_POST['accion']==='modulo'){
            $system = R::findOne('systemusage','id = 1');
            switch($_POST['modulo']){
              case 'mia': //Modulo IA
                $data = $system->ia; 
                break;
              case 'ml': //instalacion y logistica
                $data = $system->instalacion;
                break;
               case 'mp':
                $data = $system->personal;
                break;
              default:
                break;     
            }
            echo $data; // Enviar JSON
        }elseif($_POST['accion']==='negocio'){   // <--Tabla: systemnegocio
            // El logo es binario y no forma parte de los parámetros de marcado.
            $negocio = reglas_negocio();
            if (is_array($negocio) && isset($negocio['error'])) {
                http_response_code(500);
                echo json_encode($negocio);
            } else {
                echo json_encode([
                    'escala' => (float) ($negocio->escala ?? 1.5),
                    'tamanio' => (int) ($negocio->tamanio ?? 12)
                ]);
            }
        }elseif($_POST['accion']==='system'){
            $system = R::findOne('systemusage','id = 1');
            echo json_encode($system);
        }elseif ($_POST['accion']==='smtp') {
            $smtp = R::findOne('systemsmtp', 'id = ?', [1]);
            if (!$smtp) {
                echo json_encode(['ok' => false]);
                exit;
            }
            echo json_encode([
                'ok'   => true,
                'host' => $smtp->host,
                'port' => $smtp->port,
                'user' => $smtp->email,
                'pass' => $smtp->server,
                'encryption' => $smtp->encryption,
                'to'   => $smtp->to,
                // smtp_pass NUNCA se manda al cliente
            ]);
        }
    }
    R::close();
?>