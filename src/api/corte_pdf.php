<?php
require_once __DIR__.'/../include/auth.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
ini_set('display_errors','0');
require_once __DIR__.'/../include/config.php';
require_once __DIR__.'/../include/corte_pdf_service.php';
require_once __DIR__.'/../include/corte_auth.php';
$action=(string)($_POST['accion']??'');
try {
    if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);throw new RuntimeException('Método no permitido.');}
    // Permite salir del acceso incluso cuando falta una migracion; no altera tareas.
    if($action==='salir'){
        $context=$_POST['corte_context']??'';
        if(!is_string($context)||empty($_SESSION['corte_context'])||!hash_equals($_SESSION['corte_context'],$context)){http_response_code(403);throw new RuntimeException('Acceso no válido.');}
        corteCerrarAcceso();echo json_encode(['ok'=>true,'acceso_cerrado'=>true]);exit;
    }
    $db=new PDO('mysql:host='.hostBD.';dbname='.nombreBD.';charset=utf8mb4',usuarioBD,claveBD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $operator=corteValidarAcceso($db,$_POST);$op=(int)$operator['id'];
    $service=new CortePdfService($db,null,static fn()=>corteValidarAcceso($db,$_POST,true));
    if($action==='inicio'){
        // Verificar dependencias antes de crear una jornada evita sesiones huerfanas al fallar.
        $pending=$service->list($op);
        $db->beginTransaction();corteValidarAcceso($db,$_POST,true);
        $id=(int)($_SESSION['corte_pdf_sesion']??0);
        $q=$db->prepare("SELECT id FROM sesiones WHERE id=? AND operador_id=? AND estado='activa' FOR UPDATE");$q->execute([$id,$op]);
        if(!$q->fetchColumn()){
            $q=$db->prepare("INSERT INTO sesiones (operador_id,estado) VALUES (?,'activa')");$q->execute([$op]);$id=(int)$db->lastInsertId();
        }
        $db->commit();$_SESSION['corte_pdf_sesion']=$id;
        $result=['ok'=>true,'sesion'=>$id,'operador'=>['nombre'=>$operator['nombre'],'token'=>$operator['token']],'pendientes'=>$pending];
    }elseif($action==='documentos'){
        $code=trim((string)($_POST['codigo']??''));
        if(!preg_match('/^\d+$/D',$code))throw new RuntimeException('Número de orden inválido.');
        $q=$db->prepare('SELECT id,codigo,c_estado FROM ordenes WHERE codigo=?');$q->execute([$code]);$order=$q->fetch();
        if(!$order)throw new RuntimeException('Orden no encontrada.');
        $result=['ok'=>true,'orden'=>$order,'documentos'=>$service->documents((int)$order['id'])];
    }elseif($action==='pendientes'){
        $result=['ok'=>true,'pendientes'=>$service->list($op)];
    }else{
        if(!in_array($action,['iniciar','guardar','pausa','reanudar','pendiente','finalizar','jornada'],true))throw new RuntimeException('Acción no válida.');
        $session=(int)($_SESSION['corte_pdf_sesion']??0);
        if(!$session)throw new RuntimeException('Reintente la conexión para iniciar la jornada.');
        $result=$service->action($action,$op,$session,(int)($_POST['documento']??0),(string)($_POST['token']??''),(int)($_POST['pausa']??0),($_POST['parcial']??'')==='1',($_POST['recuperar']??'')==='1');
        if($action==='jornada' && !empty($result['cerrada']))corteCerrarAcceso();
    }
    echo json_encode($result,JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);
}catch(Throwable $e){
    if(isset($db)&&$db->inTransaction())$db->rollBack();
    if(http_response_code()<400)http_response_code($e instanceof PDOException?500:409);
    error_log('Corte PDF ['.$action.']: '.$e->getMessage());
    $message=$e instanceof PDOException?'No se pudo consultar o guardar corte. Verifique la conexión y que el release 0010 esté aplicado; luego reintente.':$e->getMessage();
    echo json_encode(['ok'=>false,'error'=>$message],JSON_INVALID_UTF8_SUBSTITUTE);
}
