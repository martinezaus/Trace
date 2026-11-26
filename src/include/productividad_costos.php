<?php
require_once __DIR__.'/productividad.php';

function productividadCentavos($value, int $max=1000000000): ?int {
    if ($value===null || $value==='') return null;
    if (!is_scalar($value)) throw new InvalidArgumentException('Importe inválido.');
    $text=str_replace(',','.',trim((string)$value));
    if (!preg_match('/^(\d{1,8})(?:\.(\d{1,2}))?$/D',$text,$m)) throw new InvalidArgumentException('Use un importe no negativo con hasta dos decimales.');
    $c=(int)$m[1]*100+(int)str_pad($m[2]??'',2,'0');
    if ($c>$max) throw new InvalidArgumentException('El importe supera el máximo permitido.');
    return $c;
}
function productividadConfigValidar(array $input): array {
    foreach(['vigente_desde','moneda','base_adicional'] as $key) if (!isset($input[$key]) || !is_string($input[$key])) throw new InvalidArgumentException('Configuración incompleta.');
    $date=DateTimeImmutable::createFromFormat('!Y-m-d',$input['vigente_desde']);
    if (!$date || $date->format('Y-m-d')!==$input['vigente_desde'] || $input['vigente_desde']<'2000-01-01' || $input['vigente_desde']>'2099-12-31') throw new InvalidArgumentException('Fecha de vigencia inválida.');
    $moneda=strtoupper(trim($input['moneda']));
    if (!preg_match('/^[A-Z]{3}$/D',$moneda)) throw new InvalidArgumentException('Ingrese el código de moneda de tres letras, por ejemplo ARS.');
    $base=$input['base_adicional'];
    if (!in_array($base,['ahorro','costo_real','costo_estimado'],true)) throw new InvalidArgumentException('Base de adicional inválida.');
    foreach(['costos_activos','adicionales_activos'] as $key) {
        if (!in_array($input[$key]??'0',['0','1',0,1],true)) throw new InvalidArgumentException('Opción de activación inválida.');
    }
    $costos=(int)($input['costos_activos']??0);$adicionales=(int)($input['adicionales_activos']??0);
    $tarifa=productividadCentavos($input['costo_hora']??null);
    if ($costos && $tarifa===null) throw new InvalidArgumentException('Complete el costo por hora para activar los costos.');
    if ($adicionales && !$costos) throw new InvalidArgumentException('Active los costos antes de activar los adicionales.');
    $thresholds=$input['ahorro_desde']??[];$rates=$input['adicional_porcentaje']??[];
    if (!is_array($thresholds) || !is_array($rates) || count($thresholds)!==count($rates) || count($thresholds)>30 || !array_is_list($thresholds) || !array_is_list($rates)) throw new InvalidArgumentException('Escala inválida; máximo 30 tramos.');
    $escala=[];$seen=[];
    foreach($thresholds as $i=>$threshold) {
        if ($threshold==='' && $rates[$i]==='') continue;
        $desde=productividadCentavos($threshold,10000);$pct=productividadCentavos($rates[$i],10000);
        if ($desde===null || $pct===null || isset($seen[$desde])) throw new InvalidArgumentException('Complete ambos porcentajes y no repita umbrales.');
        $seen[$desde]=true;$escala[]=['ahorro_bp'=>$desde,'adicional_bp'=>$pct];
    }
    usort($escala,static fn($a,$b)=>$a['ahorro_bp']<=>$b['ahorro_bp']);
    if ($adicionales && !$escala) throw new InvalidArgumentException('Agregue al menos un tramo para activar los adicionales.');
    return ['vigente_desde'=>$input['vigente_desde'],'moneda'=>$moneda,'costos_activos'=>$costos,
        'tarifa_centavos'=>$tarifa,'adicionales_activos'=>$adicionales,'base_adicional'=>$base,'escala'=>$escala];
}
function productividadCostosDisponibles(PDO $db): bool {
    if ($db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite') return (bool)$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='systemproductividad'")->fetchColumn();
    return (bool)$db->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='systemproductividad'")->fetchColumn();
}
function productividadConfigLeer(PDO $db, ?string $fecha=null): ?array {
    if (!productividadCostosDisponibles($db)) return null;
    $q=$db->prepare($fecha===null ? 'SELECT * FROM systemproductividad ORDER BY id DESC LIMIT 1' : 'SELECT * FROM systemproductividad WHERE vigente_desde<=? ORDER BY vigente_desde DESC,id DESC LIMIT 1');
    $q->execute($fecha===null?[]:[$fecha]);$row=$q->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    $row['escala']=json_decode($row['escala_json'],true,512,JSON_THROW_ON_ERROR);
    return $row;
}
function productividadConfigGuardar(PDO $db, array $input, string $role, int $expected): int {
    if (!in_array($role,['admin','master'],true)) throw new InvalidArgumentException('Acceso denegado.');
    $config=productividadConfigValidar($input);
    if (!productividadCostosDisponibles($db)) throw new RuntimeException('Falta instalar la tabla de configuración de productividad.');
    $db->beginTransaction();
    try {
        $lock=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
        $latest=(int)$db->query('SELECT id FROM systemproductividad ORDER BY id DESC LIMIT 1'.$lock)->fetchColumn();
        if ($latest!==$expected) throw new InvalidArgumentException('La configuración cambió en otra sesión. Recargue antes de guardar.');
        $q=$db->prepare('INSERT INTO systemproductividad(vigente_desde,moneda,costos_activos,tarifa_centavos,adicionales_activos,base_adicional,escala_json,creado_por) VALUES(?,?,?,?,?,?,?,?)');
        $q->execute([$config['vigente_desde'],$config['moneda'],$config['costos_activos'],$config['tarifa_centavos'],$config['adicionales_activos'],$config['base_adicional'],json_encode($config['escala'],JSON_THROW_ON_ERROR),$role]);
        $id=(int)$db->lastInsertId();$db->commit();return $id;
    } catch(Throwable $e) { if($db->inTransaction())$db->rollBack();throw $e; }
}
/** Importes enteros en centavos; se redondea a centavo después de convertir horas. */
function productividadCostoTiempo(int $seconds,int $tarifa): int {
    $sign=$seconds<0?-1:1;$s=abs($seconds);
    return $sign*(intdiv($s,3600)*$tarifa+(int)round(($s%3600)*$tarifa/3600));
}
function productividadEconomia($estimated,$actual,?array $config,bool $adicionalPermitido=false): array {
    $out=['costo_estimado'=>null,'costo_real'=>null,'ahorro_costo'=>null,'adicional'=>null,'adicional_tasa'=>null,'moneda'=>$config['moneda']??'',
        'config_costos'=>$config ? '#'.$config['id'].' desde '.$config['vigente_desde'] : 'Sin configuración vigente'];
    if (!$config || !(int)$config['costos_activos'] || $config['tarifa_centavos']===null) return $out;
    if (is_numeric($actual) && $actual>0) $out['costo_real']=productividadCostoTiempo((int)$actual,(int)$config['tarifa_centavos'])/100;
    if (productividadSegundos($estimated,$actual)===null) return $out;
    $est=(int)$estimated;$real=(int)$actual;$tarifa=(int)$config['tarifa_centavos'];
    $ce=productividadCostoTiempo($est,$tarifa);$cr=productividadCostoTiempo($real,$tarifa);$ahorro=$ce-$cr;
    $out['costo_estimado']=$ce/100;$out['costo_real']=$cr/100;$out['ahorro_costo']=$ahorro/100;
    if (!$adicionalPermitido || !(int)$config['adicionales_activos']) return $out;
    $bp=0;
    if ($est>$real) foreach($config['escala'] as $tramo) {
        // Comparar antes del redondeo del porcentaje mostrado: un 9,995 % no alcanza un umbral de 10 %.
        if (($est-$real)*10000 >= (int)$tramo['ahorro_bp']*$est) $bp=(int)$tramo['adicional_bp'];
    }
    $base=['ahorro'=>max(0,$ahorro),'costo_real'=>$cr,'costo_estimado'=>$ce][$config['base_adicional']];
    $out['adicional_tasa']=$bp/100;
    $out['adicional']=(intdiv($base,10000)*$bp+(int)round(($base%10000)*$bp/10000))/100;
    return $out;
}
