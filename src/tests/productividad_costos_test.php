<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../include/productividad_costos.php';
$n=0;
function ok($v,$label){global $n;$n++;if(!$v)throw new RuntimeException('FAIL '.$label);}
function rejects($fn,$label){try{$fn();}catch(InvalidArgumentException $e){ok(true,$label);return;}ok(false,$label);}
function settings(array $extra=[]):array{return array_replace(['vigente_desde'=>'2026-09-01','moneda'=>'ARS','costos_activos'=>'1','costo_hora'=>'1000,00','adicionales_activos'=>'1','base_adicional'=>'ahorro','ahorro_desde'=>['10','20'],'adicional_porcentaje'=>['25','50']],$extra);}
ok(productividadCentavos('12,34')===1234,'Decimal con coma exacto');
ok(productividadCentavos('0')===0,'Cero no equivale a sin configurar');
foreach(['-1','NaN','1.001',[],10000001,'1e2'] as $bad) rejects(fn()=>productividadCentavos($bad),'Rechaza importe inválido');
$config=productividadConfigValidar(settings());$config['id']=1;
$e=productividadEconomia(36000,28800,$config,true);
ok($e['costo_estimado']===10000 && $e['costo_real']===8000 && $e['ahorro_costo']===2000,'Costos comparables y ahorro');
ok($e['adicional']===1000 && $e['adicional_tasa']===50,'Tramo inclusivo y base de ahorro');
ok(productividadEconomia(36000,28800,$config,false)['adicional']===null,'No proponer adicional sin atribución completa');
ok(productividadEconomia(36000,40000,$config,true)['adicional']===0,'Exceso no paga adicional');
ok(productividadEconomia(36000,36000,$config,true)['adicional']===0,'Sin ahorro no paga adicional');
ok(productividadEconomia(null,3600,$config,true)['costo_real']===1000,'Proceso sin estimado tiene costo real, no ahorro');
ok(productividadEconomia(null,3600,$config,true)['ahorro_costo']===null,'No inventa ahorro sin estimado');
ok(productividadEconomia(3600,0,$config,true)['adicional']===null,'Sin medición válida no hay propuesta');
ok(productividadEconomia(3600,1800,null,true)['costo_real']===null,'No hay importe sin configuración');
$c=$config;$c['costos_activos']=0;ok(productividadEconomia(3600,1800,$c,true)['costo_real']===null,'Costos desactivados');
$c=$config;$c['tarifa_centavos']=0;ok(productividadEconomia(3600,1800,$c,true)['costo_real']===0,'Tarifa cero explícita');
$c=$config;$c['base_adicional']='costo_real';ok(productividadEconomia(36000,28800,$c,true)['adicional']===4000,'Base sobre costo real');
$c=$config;$c['base_adicional']='costo_estimado';ok(productividadEconomia(36000,28800,$c,true)['adicional']===5000,'Base sobre costo estimado');
$c=$config;$c['escala']=[['ahorro_bp'=>1000,'adicional_bp'=>2500]];
ok(productividadSegundos(200000,180001)===-10.0,'Caso en borde redondeado');
ok(productividadEconomia(200000,180001,$c,true)['adicional_tasa']===0,'Umbral usa porcentaje sin redondear');
ok(productividadEconomia(200000,180000,$c,true)['adicional_tasa']===25,'Umbral exacto incluido');
ok(productividadCostoTiempo(1,1800)===1,'Redondeo a centavo');
ok(productividadCostoTiempo(-1,1800)===-1,'Ahorro con signo simétrico');
foreach([
 ['vigente_desde'=>'2026-02-30'],['moneda'=>'ARS<script>'],['costos_activos'=>[]],
 ['costo_hora'=>''],['base_adicional'=>'sueldo inventado'],['costos_activos'=>'0'],
 ['ahorro_desde'=>['10','10']],['ahorro_desde'=>['101'],'adicional_porcentaje'=>['10']],
 ['ahorro_desde'=>['10'],'adicional_porcentaje'=>['101']],['ahorro_desde'=>[],'adicional_porcentaje'=>[]]
] as $bad)rejects(fn()=>productividadConfigValidar(settings($bad)),'Validación de configuración');
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
ok(productividadConfigLeer($db)===null,'Esquema anterior sigue funcionando');
$db->exec('CREATE TABLE systemproductividad(id INTEGER PRIMARY KEY AUTOINCREMENT,vigente_desde TEXT,moneda TEXT,costos_activos INTEGER,tarifa_centavos INTEGER,adicionales_activos INTEGER,base_adicional TEXT,escala_json TEXT,creado_por TEXT,created TEXT DEFAULT CURRENT_TIMESTAMP)');
$id=productividadConfigGuardar($db,settings(),'admin',0);ok($id===1,'Guardar primera versión');
rejects(fn()=>productividadConfigGuardar($db,settings(),'admin',0),'Evita sobreescritura concurrente');
rejects(fn()=>productividadConfigGuardar($db,settings(),'operario',1),'Rol sin permiso');
$id=productividadConfigGuardar($db,settings(['vigente_desde'=>'2026-10-01','costo_hora'=>'1500']),'master',1);ok($id===2,'Nueva versión conserva anterior');
ok((int)$db->query('SELECT COUNT(*) FROM systemproductividad')->fetchColumn()===2,'Historial sin actualizaciones destructivas');
ok((int)productividadConfigLeer($db,'2026-09-30')['id']===1,'Elegir tarifa por vigencia');
ok((int)productividadConfigLeer($db,'2026-10-01')['id']===2,'Vigencia inclusiva');
ok(productividadConfigLeer($db,'2026-08-31')===null,'No aplicar tarifa a período anterior');
ok((int)productividadConfigLeer($db)['id']===2,'Editor abre última versión guardada');
echo "OK: $n comprobaciones económicas, sin datos del negocio.\n";
