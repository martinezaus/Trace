<?php
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../../../include/productividad_costos.php';
require_role(['admin','master']);
require_post();
use RedBeanPHP\R;
if (!is_string($_POST['csrf']??null) || !verify_csrf($_POST['csrf'])) json_response(['ok'=>false,'error'=>'Token de seguridad inválido. Recargue la página.'],400);
$version=filter_var($_POST['version']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
if($version===false || $version===null) json_response(['ok'=>false,'error'=>'Versión inválida. Recargue la página.'],422);
try {
    if (($_POST['accion']??'guardar')==='simular') {
        $c=productividadConfigValidar($_POST);$c['id']='vista previa';
        $est=productividadTiempo($_POST['sim_estimado']??null);$real=productividadTiempo($_POST['sim_real']??null);
        if($est===null || $real===null || $est<=0 || $real<=0) throw new InvalidArgumentException('Complete los tiempos del ejemplo en HHH:MM:SS, mayores que cero.');
        json_response(['ok'=>true,'productividad'=>productividadSegundos($est,$real),'economia'=>productividadEconomia($est,$real,$c,true)]);
    }
    if (($_POST['accion']??'guardar')!=='guardar') throw new InvalidArgumentException('Acción inválida.');
    $id=productividadConfigGuardar(R::getPDO(),$_POST,(string)get_role(),$version);
    json_response(['ok'=>true,'version'=>$id]);
} catch(InvalidArgumentException $e) {json_response(['ok'=>false,'error'=>$e->getMessage()],422);}
catch(Throwable $e) {error_log('Configuración de productividad: '.$e->getMessage());json_response(['ok'=>false,'error'=>'No se pudo guardar la configuración. Intente nuevamente.'],500);}
