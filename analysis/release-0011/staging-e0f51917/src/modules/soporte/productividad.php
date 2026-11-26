<?php
require_once __DIR__.'/includes/auth.php';
require_role(['admin','master']);
require_once __DIR__.'/../../include/productividad_costos.php';
use RedBeanPHP\R;
$db=R::getPDO();
$disponible=productividadCostosDisponibles($db);
$config=$disponible ? productividadConfigLeer($db) : null;
$history=$disponible ? $db->query('SELECT id,vigente_desde,moneda,costos_activos,tarifa_centavos,adicionales_activos,creado_por,created FROM systemproductividad ORDER BY id DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC) : [];
$e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$decimal=static fn($v)=>$v===null ? '' : number_format((int)$v/100,2,'.','');
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Productividad y costos</title><link rel="stylesheet" href="assets/css/style.css">
<style>.scale-scroll{overflow-x:auto} .scale-table{width:100%;border-collapse:collapse;margin:12px 0} .scale-table th,.scale-table td{text-align:left;padding:8px;border-bottom:1px solid var(--border,#ddd)} .scale-table input{min-width:100px} .checkline{display:flex;align-items:center;gap:10px;margin:15px 0} .checkline input{width:auto;margin:0} .field-hint{line-height:1.5} select{width:100%;padding:10px} .economia-badge{font-weight:bold} .scale-table button{white-space:nowrap}</style>
</head><body><?php include __DIR__.'/partials/header.php'; ?>
<main class="container"><h2>Productividad y costos</h2>
<p>Productividad = (real computable - estimado comparable) / estimado comparable × 100. <strong>Negativo: ahorro. Positivo: exceso.</strong></p>
<div id="alertBox" role="status" aria-live="polite"></div>
<?php if(!$disponible): ?><div class="alert alert-error">La configuración todavía no está disponible. Contacte a soporte.</div><?php endif; ?>
<form id="formProductividad" class="panel" method="post" action="api/save_productividad.php">
<input type="hidden" name="csrf" value="<?= $e(csrf_token()) ?>"><input type="hidden" name="version" value="<?= (int)($config['id']??0) ?>">
<fieldset <?= !$disponible?'disabled':'' ?> style="border:0;padding:0;margin:0">
<h3>Costo de producción</h3>
<p class="field-hint">Costo por hora de trabajo registrado. Estimado y real se valoran con la misma tarifa. No equivale al precio de venta ni al beneficio total de la orden.</p>
<label class="checkline"><input type="checkbox" name="costos_activos" value="1" <?= !empty($config['costos_activos'])?'checked':'' ?>> Habilitar estimación de costos</label>
<div class="row"><div><label for="costo_hora">Costo por hora</label><input type="number" id="costo_hora" name="costo_hora" min="0" max="10000000" step="0.01" value="<?= $e($decimal($config['tarifa_centavos']??null)) ?>" placeholder="Sin definir"></div>
<div><label for="moneda">Moneda (código de tres letras)</label><input type="text" id="moneda" name="moneda" minlength="3" maxlength="3" pattern="[A-Za-z]{3}" value="<?= $e($config['moneda']??'ARS') ?>" required></div></div>
<label for="vigente_desde">Vigente desde</label><input type="date" id="vigente_desde" name="vigente_desde" min="2000-01-01" max="2099-12-31" value="<?= $e($config['vigente_desde']??date('Y-m-d')) ?>" required>
<p class="field-hint">Cada guardado crea una versión y conserva las anteriores. Los reportes usan la configuración vigente al cierre del período seleccionado y muestran su versión. Si el período cruza un cambio de tarifa, divídalo para comparar cada tramo con su configuración.</p>
<h3>Adicional por ahorro</h3>
<label class="checkline"><input type="checkbox" name="adicionales_activos" value="1" <?= !empty($config['adicionales_activos'])?'checked':'' ?>> Habilitar propuesta de adicional</label>
<label for="base_adicional">Aplicar el porcentaje de adicional sobre</label>
<select id="base_adicional" name="base_adicional">
<?php foreach(['ahorro'=>'Costo ahorrado (repartir una parte del ahorro)','costo_real'=>'Costo del tiempo real comparable','costo_estimado'=>'Costo del tiempo estimado comparable'] as $key=>$label): ?><option value="<?= $key ?>" <?= ($config['base_adicional']??'ahorro')===$key?'selected':'' ?>><?= $e($label) ?></option><?php endforeach; ?>
</select>
<p class="field-hint">Ahorro mínimo de 20 % corresponde a productividad de -20 %. Se aplica el tramo de mayor umbral alcanzado, sin acumular tramos. Si no hay ahorro, el adicional es cero. Si hay trabajos excluidos o falta una medición confiable, el adicional queda sin determinar.</p>
<div class="scale-scroll"><table class="scale-table"><thead><tr><th>Ahorro mínimo (%)</th><th>Adicional sobre la base (%)</th><th>Acción</th></tr></thead><tbody id="scaleRows">
<?php foreach($config['escala']??[] as $tramo): ?><tr><td><input type="number" name="ahorro_desde[]" min="0" max="100" step="0.01" value="<?= $e($decimal($tramo['ahorro_bp'])) ?>" required aria-label="Ahorro mínimo"></td><td><input type="number" name="adicional_porcentaje[]" min="0" max="100" step="0.01" value="<?= $e($decimal($tramo['adicional_bp'])) ?>" required aria-label="Porcentaje de adicional"></td><td><button type="button" class="removeTier">Quitar</button></td></tr><?php endforeach; ?>
</tbody></table></div>
<button type="button" id="addTier">Agregar tramo</button>
<p class="field-hint">La propuesta no liquida sueldos ni modifica pagos. Requiere revisión del supervisor y datos completos. La tarifa y la escala quedan sin activar hasta que se guarden valores válidos.</p>
<h3>Vista previa sin guardar</h3><p class="field-hint">Pruebe la tarifa y los tramos con un ejemplo. Esto no registra pagos ni cambia la configuración.</p>
<div class="row"><div><label for="sim_estimado">Tiempo estimado del ejemplo</label><input type="text" id="sim_estimado" name="sim_estimado" placeholder="10:00:00"></div><div><label for="sim_real">Tiempo real computable del ejemplo</label><input type="text" id="sim_real" name="sim_real" placeholder="08:00:00"></div></div>
<button type="button" id="simulate">Calcular vista previa</button><p id="simResult" role="status" aria-live="polite"></p>
<div class="actions"><button type="submit">Guardar configuración</button><span id="savingMsg" class="field-hint"></span></div>
</fieldset></form>
<section class="panel"><h3>Últimas versiones</h3><p>Configuración cargada: <span class="economia-badge"><?= $config ? '#'.(int)$config['id'] : 'Sin configurar' ?></span></p>
<div class="scale-scroll"><table class="scale-table"><thead><tr><th>Versión</th><th>Vigente desde</th><th>Tarifa por hora</th><th>Costos / adicionales</th><th>Guardado</th></tr></thead><tbody>
<?php foreach($history as $h): ?><tr><td>#<?= (int)$h['id'] ?></td><td><?= $e($h['vigente_desde']) ?></td><td><?= $h['tarifa_centavos']===null?'Sin definir':$e($h['moneda'].' '.$decimal($h['tarifa_centavos'])) ?></td><td><?= $h['costos_activos']?'Activos':'Inactivos' ?> / <?= $h['adicionales_activos']?'Activos':'Inactivos' ?></td><td><?= $e($h['created'].' · '.$h['creado_por']) ?></td></tr><?php endforeach; ?>
<?php if(!$history): ?><tr><td colspan="5">No hay versiones guardadas.</td></tr><?php endif; ?></tbody></table></div></section>
</main><script src="assets/js/productividad.js"></script></body></html>
