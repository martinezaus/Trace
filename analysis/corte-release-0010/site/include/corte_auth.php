<?php
/** Controles exclusivos de corte: no cambia la autenticacion de armado. */
function corteValidarAcceso(PDO $db, array $input, bool $lock = false): array {
    $op=(int)($_SESSION['operador_id']??0);
    if(!$op){http_response_code(401);throw new RuntimeException('La sesión venció. Ingrese nuevamente.');}
    $context=$input['corte_context']??null;
    if(!is_string($context) || empty($_SESSION['corte_context']) || (int)($_SESSION['corte_context_op']??0)!==$op || !hash_equals($_SESSION['corte_context'],$context)){
        http_response_code(403);throw new RuntimeException('Sólo Proceso de Corte puede realizar esta operación. Abra nuevamente la pantalla de corte.');
    }
    $suffix=$lock && $db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
    $q=$db->prepare('SELECT id,nombre,token,estado,session_token FROM operadores WHERE id=?'.$suffix);$q->execute([$op]);$operator=$q->fetch(PDO::FETCH_ASSOC);
    if(!$operator || (int)$operator['estado']!==0 || !is_string($operator['session_token']) || !hash_equals($operator['session_token'],session_id())){
        http_response_code(401);throw new RuntimeException('El acceso cambió o fue deshabilitado. Ingrese nuevamente.');
    }
    return $operator;
}
function corteCerrarAcceso(): void {
    $_SESSION=[];
    if(ini_get('session.use_cookies')){$p=session_get_cookie_params();setcookie(session_name(),'', ['expires'=>time()-42000,'path'=>$p['path'],'domain'=>$p['domain'],'secure'=>$p['secure'],'httponly'=>$p['httponly'],'samesite'=>$p['samesite']??'Lax']);}
    session_destroy();
}
