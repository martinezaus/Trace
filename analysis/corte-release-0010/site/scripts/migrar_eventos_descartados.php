<?php
// Migración aditiva. Sin --apply sólo muestra el diagnóstico. No borra registros.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../include/config.php';
function migrarEventosDescartados(PDO $db,bool $apply): array {
    $changes=[];
    $type=$db->query("SHOW COLUMNS FROM eventos LIKE 'tipo'")->fetch(PDO::FETCH_ASSOC);
    if(str_starts_with(strtolower($type['Type']),'enum(') && !str_contains($type['Type'],"'lectura_descartada'")) {
        $new=substr($type['Type'],0,-1).",'lectura_descartada')";
        $sql='ALTER TABLE eventos MODIFY tipo '.$new.($type['Null']==='YES'?' NULL':' NOT NULL');
        if($type['Default']!==null)$sql.=' DEFAULT '.$db->quote($type['Default']);
        $changes[]=$sql;if($apply)$db->exec($sql);
    }
    $desc=$db->query("SHOW COLUMNS FROM eventos LIKE 'descripcion'")->fetch(PDO::FETCH_ASSOC);
    if(preg_match('/^varchar\((\d+)\)$/i',$desc['Type'],$m) && (int)$m[1]<255) {
        $sql='ALTER TABLE eventos MODIFY descripcion VARCHAR(255)'.($desc['Null']==='YES'?' NULL':' NOT NULL');
        if($desc['Default']!==null)$sql.=' DEFAULT '.$db->quote($desc['Default']);
        $changes[]=$sql;if($apply)$db->exec($sql);
    }
    return $changes;
}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    $db=new PDO('mysql:host='.hostBD.';dbname='.nombreBD.';charset=utf8mb4',usuarioBD,claveBD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $apply=in_array('--apply',$argv,true);$changes=migrarEventosDescartados($db,$apply);
    echo json_encode(['aplicado'=>$apply,'cambios'=>$changes],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
}
