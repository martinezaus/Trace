<?php
require_once __DIR__.'/../include/auth.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
requerirOperadorApi();
require_once __DIR__.'/../include/config.php';
require_once __DIR__.'/../include/corte_pdf_service.php';
try {
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new RuntimeException('Método no permitido.');
    $db=new PDO('mysql:host='.hostBD.';dbname='.nombreBD.';charset=utf8mb4',usuarioBD,claveBD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $service=new CortePdfService($db);$op=operadorId();$action=$_POST['accion']??'';
    if($action==='inicio'){
        $id=(int)($_SESSION['corte_pdf_sesion']??0);
        $q=$db->prepare("SELECT id FROM sesiones WHERE id=? AND operador_id=? AND estado='activa'");$q->execute([$id,$op]);
        if(!$q->fetchColumn()){
            $q=$db->prepare("INSERT INTO sesiones (operador_id,estado) VALUES (?,'activa')");$q->execute([$op]);$id=(int)$db->lastInsertId();$_SESSION['corte_pdf_sesion']=$id;
        }
        $q=$db->prepare('SELECT nombre,token FROM operadores WHERE id=?');$q->execute([$op]);
        $result=['ok'=>true,'sesion'=>$id,'operador'=>$q->fetch(),'pendientes'=>$service->list($op)];
    }elseif($action==='documentos'){
        $q=$db->prepare('SELECT id,codigo,c_estado FROM ordenes WHERE codigo=?');$q->execute([trim((string)($_POST['codigo']??''))]);$order=$q->fetch();
        if(!$order)throw new RuntimeException('Orden no encontrada.');
        $result=['ok'=>true,'orden'=>$order,'documentos'=>$service->documents((int)$order['id'])];
    }elseif($action==='pendientes'){
        $result=['ok'=>true,'pendientes'=>$service->list($op)];
    }else{
        $session=(int)($_SESSION['corte_pdf_sesion']??0);
        if(!$session)throw new RuntimeException('Recargue la página para iniciar la jornada.');
        $result=$service->action($action,$op,$session,(int)($_POST['documento']??0),(string)($_POST['token']??''),(int)($_POST['pausa']??0),($_POST['parcial']??'')==='1');
    }
    echo json_encode($result,JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);
}catch(Throwable $e){
    http_response_code($e instanceof PDOException?500:409);
    error_log('Corte PDF: '.$e->getMessage());
    echo json_encode(['ok'=>false,'error'=>$e instanceof PDOException?'No se pudo guardar el corte. Intente nuevamente.':$e->getMessage()],JSON_INVALID_UTF8_SUBSTITUTE);
}
