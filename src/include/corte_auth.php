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
    $q=$db->prepare('SELECT id,nombre,token,CAST(estado AS UNSIGNED) estado,session_token,CAST(corte AS UNSIGNED) corte FROM operadores WHERE id=?'.$suffix);$q->execute([$op]);$operator=$q->fetch(PDO::FETCH_ASSOC);
    if(!$operator || (int)$operator['estado']!==0 || !is_string($operator['session_token']) || !hash_equals($operator['session_token'],session_id())){
        http_response_code(401);throw new RuntimeException('El acceso cambió o fue deshabilitado. Ingrese nuevamente.');
    }
    if((int)$operator['corte']!==0){http_response_code(403);throw new RuntimeException('No tiene permiso para ingresar al sector Corte.');}
    return $operator;
}
function corteCerrarAcceso(): void {
    operadorDestruirSesion();
}
