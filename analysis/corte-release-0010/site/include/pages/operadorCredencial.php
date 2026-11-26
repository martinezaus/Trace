<?php
require_once __DIR__ . '/../auth.php';
$impresion = $_GET['impresion'] ?? '';
$permitida = is_string($impresion) && !empty($_SESSION['credencial_impresion'])
    && hash_equals($_SESSION['credencial_impresion'], $impresion);
if (operadorId() <= 0 && !$permitida) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    exit('<!doctype html><html lang="es"><meta charset="utf-8"><title>Identificación de operario</title><p>Abra nuevamente la identificación desde el panel de Operarios.</p><a href="../../operarios.php">Volver a Operarios</a></html>');
}
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../api/rb-mysql.php';
$id=(int)($_GET['id']??0);
if($id<=0){http_response_code(400);exit('Operador inválido.');}
R::setup('mysql:host='.hostBD.';dbname='.nombreBD,usuarioBD,claveBD);
R::freeze(true);
$o=R::load('operadores',$id);
if(!$o->id){http_response_code(404);exit('Operador no encontrado.');}
require_once __DIR__ . '/../negocio_logo.php';
$logo=negocio_logo_data_uri();
$barcode='data:image/png;base64,'.base64_encode((string)$o->barcode);
$foto=!empty($o->foto)?'data:image/jpeg;base64,'.base64_encode((string)$o->foto):'';
$nombre=htmlspecialchars((string)$o->nombre,ENT_QUOTES,'UTF-8');
$token=htmlspecialchars((string)$o->token,ENT_QUOTES,'UTF-8');
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Identificación <?= $nombre ?></title><style>
*{box-sizing:border-box}body{margin:0;background:#eef3f7;color:#15354d;font-family:Arial,sans-serif}.toolbar{display:flex;justify-content:center;gap:10px;padding:18px}.toolbar button{padding:10px 18px;border:0;border-radius:6px;color:#fff;background:#0f7ec2;font-weight:700;cursor:pointer}.toolbar .close{background:#6c757d}.sheet{display:flex;justify-content:center;padding:10px}.credential{width:86mm;height:54mm;display:grid;grid-template-columns:25mm 1fr;overflow:hidden;background:#fff;border:1px solid #9fb6c8;border-radius:4mm;box-shadow:0 8px 24px rgba(15,76,117,.18)}.brand{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4mm;padding:4mm;background:#0f4c75}.logo{width:20mm;max-height:13mm;object-fit:contain}.photo{width:18mm;height:22mm;object-fit:cover;background:#fff;border:1px solid #fff;border-radius:2mm}.content{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:4mm}.title{font-size:9pt;font-weight:700;text-transform:uppercase}.name{margin:2mm 0 1mm;text-align:center;font-size:11pt;font-weight:700}.barcode{width:46mm;height:14mm;object-fit:contain}.token{margin-top:1mm;font-size:9pt;font-weight:700;letter-spacing:.12em}@media print{body{background:#fff}.toolbar{display:none}.sheet{padding:0;justify-content:flex-start}.credential{box-shadow:none}@page{size:86mm 54mm;margin:0}}
</style></head><body><div class="toolbar"><button onclick="window.print()">Imprimir identificación</button><button class="close" onclick="window.close()">Cerrar</button></div><div class="sheet"><article class="credential"><div class="brand"><?php if($logo): ?><img class="logo" src="<?= $logo ?>" alt="Logo del negocio"><?php endif; ?><?php if($foto): ?><img class="photo" src="<?= $foto ?>" alt="Foto"><?php endif; ?></div><div class="content"><div class="title">Identificación de operador</div><div class="name"><?= $nombre ?></div><img class="barcode" src="<?= $barcode ?>" alt="Código"><div class="token"><?= $token ?></div></div></article></div></body></html>