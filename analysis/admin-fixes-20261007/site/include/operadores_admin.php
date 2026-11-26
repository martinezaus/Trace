<?php
/** Operaciones administrativas con la misma conexión de RedBean. */
function guardarOperadorAdmin(string $accion,array $input,array $files): array {
    $id=filter_var($input['id']??0,FILTER_VALIDATE_INT);
    if($accion!=='add' && (!$id || $id<1))throw new InvalidArgumentException('Identificador de operario inválido.');
    $nombre=trim((string)($input['nombre']??''));
    $sesion=filter_var($input['sesion']??null,FILTER_VALIDATE_INT);
    $corte=filter_var($input['corte']??null,FILTER_VALIDATE_INT);
    if(in_array($accion,['add','edit'],true)){
        if($nombre==='' || mb_strlen($nombre)>50)throw new InvalidArgumentException('Ingrese un nombre de hasta 50 caracteres.');
        if($sesion!==1)throw new InvalidArgumentException('Actualmente se admite una sesión por operario.');
        if(!in_array($corte,[0,1],true))throw new InvalidArgumentException('El permiso de corte debe ser 0 o 1.');
    }
    $foto=null;
    if(isset($files['foto']) && ($files['foto']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
        if($files['foto']['error']!==UPLOAD_ERR_OK)throw new InvalidArgumentException('No se pudo subir la foto.');
        $bytes=file_get_contents($files['foto']['tmp_name']);
        $size=@getimagesizefromstring($bytes);
        if(!$size || $size[0]*$size[1]>20000000)throw new InvalidArgumentException('Seleccione una imagen válida de hasta 20 megapíxeles.');
        $image=@imagecreatefromstring($bytes);
        if(!$image)throw new InvalidArgumentException('Formato de foto no válido.');
        if(imagesx($image)>300){$scaled=imagescale($image,300);imagedestroy($image);$image=$scaled;}
        ob_start();imagejpeg($image,null,80);$foto=ob_get_clean();imagedestroy($image);
    }
    R::begin();
    try {
        if($accion==='add'){$operario=R::dispense('operadores');$operario->estado=0;}
        else {
            if(!R::getRow('SELECT id FROM operadores WHERE id=? FOR UPDATE',[$id]))throw new InvalidArgumentException('El operario no existe. Actualice el listado.');
            $operario=R::load('operadores',$id);
        }
        if(in_array($accion,['add','edit'],true)){
            if(R::getCell('SELECT id FROM operadores WHERE UPPER(TRIM(nombre))=UPPER(?) AND id<>? LIMIT 1',[$nombre,$accion==='add'?0:$id]))throw new InvalidArgumentException('El operario ya existe con ese nombre.');
            $operario->nombre=mb_strtoupper($nombre,'UTF-8');$operario->session=$sesion;$operario->corte=$corte;
        }
        if(in_array($accion,['add','reset'],true)){
            $token=null;
            for($i=0;$i<100;$i++){$candidate='O'.str_pad((string)random_int(1,9999),4,'0',STR_PAD_LEFT);if(!R::getCell('SELECT id FROM operadores WHERE token=?',[$candidate])){$token=$candidate;break;}}
            if($token===null)throw new RuntimeException('No se pudo asignar una credencial libre.');
            $operario->token=$token;$operario->barcode=(new Picqer\Barcode\BarcodeGeneratorPNG())->getBarcode($token,Picqer\Barcode\BarcodeGeneratorPNG::TYPE_CODE_128);
        }
        if($accion==='del')$operario->estado=(int)$operario->estado===0?1:0;
        $saved=R::store($operario);
        if($foto!==null)R::exec('UPDATE operadores SET foto=? WHERE id=?',[$foto,$saved]);
        R::commit();return ['resultado'=>'OK','id'=>(int)$saved];
    }catch(Throwable $e){if(R::getPDO()->inTransaction())R::rollback();throw $e;}
}
