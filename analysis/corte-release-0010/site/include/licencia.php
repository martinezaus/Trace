<?php
function datosLicencia(): array
{
    $system=R::findOne('systemusage','id = ?',[1]);
    if(!$system)return ['vigente'=>false,'fecha'=>null,'motivo'=>'configuracion'];
    $enabled=(int)$system->enabled===1;
    $fecha=trim((string)($system->licencia??''));
    if(!$enabled||$fecha==='')return ['vigente'=>false,'fecha'=>$fecha?:null,'motivo'=>$enabled?'sin_fecha':'inhabilitada'];
    $vence=DateTimeImmutable::createFromFormat('!Y-m-d',$fecha);
    $hoy=new DateTimeImmutable('today',new DateTimeZone(ZONA));
    $vigente=$vence instanceof DateTimeImmutable&&$vence>=$hoy;
    return ['vigente'=>$vigente,'fecha'=>$fecha,'motivo'=>$vigente?null:'vencida'];
}
function licenciaVigente(): bool{return datosLicencia()['vigente'];}