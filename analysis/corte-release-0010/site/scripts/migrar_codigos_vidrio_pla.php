<?php
// CLI: php scripts/migrar_codigos_vidrio_pla.php [--pedido=14254] [--aplicar]
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../api/rb-mysql.php';
require_once __DIR__ . '/../include/codigos_vidrio_pla.php';
require_once __DIR__ . '/../vendor/autoload.php';
R::setup('mysql:host=' . (defined('hostBD') ? hostBD : 'localhost') . ';dbname=' . nombreBD, usuarioBD, claveBD);
R::freeze(true);
$opciones = getopt('', ['pedido:', 'aplicar']);
$aplicar = array_key_exists('aplicar', $opciones);
if (!isset(R::inspect('detalles')['codigos_vidrio'])) {
    if ($aplicar) {
        R::exec('ALTER TABLE detalles ADD COLUMN codigos_vidrio MEDIUMTEXT NULL');
        echo "Columna codigos_vidrio creada.\n";
    } else echo "Pendiente: agregar columna codigos_vidrio (usar --aplicar).\n";
}
if (!isset($opciones['pedido'])) exit;
$orden = R::findOne('ordenes', 'codigo = ?', [(string)$opciones['pedido']]);
if (!$orden) throw new RuntimeException('No existe la orden indicada.');
$documentos = R::getAll('SELECT nombre,tipo FROM documentos WHERE orden_id=? AND tipo=3 ORDER BY id', [$orden->id]);
if (!$documentos) throw new RuntimeException('La orden no tiene PLA de vidrio.');
$todos = R::getAll('SELECT id,codigo,tipologia,descripcion FROM detalles WHERE orden_id=? ORDER BY id', [$orden->id]);
$vidrios = []; $aberturas = []; $ids = []; $anteriores = [];
foreach ($todos as $d) {
    if (strpos($d['tipologia'], 'V-') === 0 || strpos($d['tipologia'], 'TELA-M(') === 0) {
        $ids[] = $d['id']; $anteriores[] = (string)$d['codigo'];
        $vidrios[] = [substr((string)$d['codigo'], 5), $d['tipologia'], 0, 3, $d['descripcion']];
    } else $aberturas[] = ['codigo' => substr((string)$d['codigo'], 5)];
}
$nuevos = plaAplicarCodigosImportacion((string)$orden->codigo, $documentos, $vidrios, $aberturas, __DIR__ . '/../tmp');
$propietarios = [];
foreach ($nuevos as $i => $v) foreach ($v[5] as $codigo) $propietarios[$codigo] = $i;
if ($aplicar) R::begin();
try {
    $total = 0;
    foreach ($nuevos as $i => $v) {
        if (!$v[5]) continue;
        $codigo = (string)$orden->codigo . $v[0];
        $aliases = $v[5];
        // Conservar QR anteriores siempre que no identifiquen otro grupo.
        if (!isset($propietarios[$anteriores[$i]]) || $propietarios[$anteriores[$i]] === $i) $aliases[] = $anteriores[$i];
        $aliases = array_values(array_unique($aliases));
        echo $v[1] . ': ' . $codigo . ' (' . count($v[5]) . " codigos PLA)\n";
        if ($aplicar) {
            $qr = new Endroid\QrCode\QrCode($codigo);
            $qr->setSize(300); $qr->setMargin(10);
            $pngQr = (new Endroid\QrCode\Writer\PngWriter())->write($qr)->getString();
            $png = (new Picqer\Barcode\BarcodeGeneratorPNG())->getBarcode($codigo, Picqer\Barcode\BarcodeGeneratorPNG::TYPE_CODE_128);
            R::exec('UPDATE detalles SET codigo=?, codigos_vidrio=?, barcode=?, barcode2d=? WHERE id=? AND orden_id=?',
                [$codigo, json_encode($aliases), $png, $pngQr, $ids[$i], $orden->id]);
        }
        $total++;
    }
    if ($aplicar) R::commit();
    echo ($aplicar ? 'Actualizados: ' : 'Vista previa: ') . $total . " grupos. Cantidades, tareas y estados conservados.\n";
} catch (Throwable $e) {
    if ($aplicar) R::rollback();
    throw $e;
}
