<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL & ~E_WARNING);
ini_set('display_errors', '0');

require_once '../../include/config.php';
require_once '../../api/rb-mysql.php';
require_once '../../include/functions.php';
require_once '../../include/log.php';

/*
 * DAT:
 * ITEMS  [0]=número (desde 1), [1]=grupo (desde 0), [4]=tipología de armado.
 * PIEZAS [1]=índice de item (desde 0), [2]=P/V/A, [3]=material,
 *        [4]=cantidad, [5]-[6]=medida y [12]=tipología de pieza.
 */
function datSegundosConfiguracion($valor): int {
    $valor = trim((string) $valor);
    if ($valor === '') return 0;
    return preg_match('/^\d{1,3}:\d{2}:\d{2}$/', $valor)
        ? timeToSeconds($valor)
        : max(0, (int) $valor);
}

function datCantidadLegible(float $cantidad): string {
    return floor($cantidad) == $cantidad
        ? (string) (int) $cantidad
        : rtrim(rtrim(number_format($cantidad, 3, '.', ''), '0'), '.');
}

function datMaterialLegible(string $material): string {
    $material = trim($material);
    return preg_match('/^FLOAT\s*(\d+)$/i', $material, $m)
        ? 'FLOAT ' . $m[1]
        : $material;
}

try {
    R::setup('mysql:host='.hostBD.';dbname=' . nombreBD, usuarioBD, claveBD);
    R::freeze(true);
} catch (Exception $e) {
    registrarLog('ERROR', 'CONECTION[Ordenes]', 'No se pudo conectar a la base de datos. ' . $e->getMessage());
    echo json_encode(['error' => 'No se pudo conectar a la base de datos']);
    exit;
}

if (!isset($_FILES['archivo_dat']) || $_FILES['archivo_dat']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'Error al subir archivos. Asegúrate de enviar archivo [.dat].']);
    exit;
}

function guardarArchivoImportado(string $origen,string $destino):bool {
    if(is_file($destino))return hash_file('sha256',$origen)===hash_file('sha256',$destino);
    return move_uploaded_file($origen,$destino);
}

$nombreDat = basename($_FILES['archivo_dat']['name']);
$rutaDat = '../../tmp/' . $nombreDat;
if (!guardarArchivoImportado($_FILES['archivo_dat']['tmp_name'], $rutaDat)) {
    registrarLog('ERROR', 'FILE', 'No se pudo guardar el DAT: revise permisos o un archivo distinto con el mismo nombre.');
    echo json_encode(['error' => 'No se pudo guardar el DAT: revise permisos o un archivo distinto con el mismo nombre.']);
    exit;
}

$documentos = [];
$omitidos = [];
if (!empty($_FILES['planos']['name'])) {
    foreach ($_FILES['planos']['tmp_name'] as $indice => $tmpName) {
        if ($_FILES['planos']['error'][$indice] !== UPLOAD_ERR_OK) {
            $omitidos[] = ['nombre'=>basename($_FILES['planos']['name'][$indice]), 'motivo'=>'Fallo de subida del PDF (codigo '.(int)$_FILES['planos']['error'][$indice].').'];
            continue;
        }

        $nombre = basename($_FILES['planos']['name'][$indice]);
        $extension = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        if (!guardarArchivoImportado($tmpName, '../../tmp/' . $nombre)) {
            registrarLog('ERROR', 'FILE', 'No se pudo mover plano: ' . $nombre);
            $omitidos[] = ['nombre'=>$nombre,'motivo'=>'No se pudo guardar el PDF: revise permisos o un archivo distinto con el mismo nombre.'];
            continue;
        }
        if ($extension !== 'dat') {
            $documentos[] = [
                'nombre' => $nombre,
                'descripcion' => $_POST['planos_descripciones'][$indice] ?? '',
                'tipo' => $_POST['planos_tipo'][$indice] ?? ''
            ];
        }
    }
}

if (!file_exists($rutaDat)) {
    registrarLog('ERROR', 'FILE', 'No se encuentra el archivo cargado.');
    echo json_encode(['error' => 'No se encuentra el archivo cargado.']);
    exit;
}

// getRow es imprescindible: getCell solo devuelve coeficientev y anulaba tv/corte.
$reglaNegocio = R::getRow(
    'SELECT coeficientev, tiempo_cortep, tiempo_cortev FROM systemnegocio WHERE id = 1'
);
$coeficienteVidrio = (float) ($reglaNegocio['coeficientev'] ?? 0);
$segundosCortePerfil = datSegundosConfiguracion($reglaNegocio['tiempo_cortep'] ?? 0);
$segundosCorteVidrio = datSegundosConfiguracion($reglaNegocio['tiempo_cortev'] ?? 0);

$lineas = file($rutaDat, FILE_IGNORE_NEW_LINES);
if ($lineas === false) { echo json_encode(['error'=>'No se pudo leer el DAT.']); exit; }
$lineas = array_map(static function ($linea) {
    return mb_check_encoding($linea, 'UTF-8') ? $linea : mb_convert_encoding($linea, 'UTF-8', 'Windows-1252');
}, $lineas);
$referenciaProyecto = null;
$cabecera = ['dat' => $nombreDat];
$itemsPorNumero = [];
$aberturas = [];
$piezasPorItem = [];
$enItems = false;
$enPiezas = false;
$cortesPerfiles = 0.0;
$cortesVidrios = 0.0;

foreach ($lineas as $lineaOriginal) {
    $linea = trim($lineaOriginal);
    if ($linea === '') continue;

    if (strpos($linea, '=') !== false) {
        [$clave, $valor] = explode('=', $linea, 2);
        $clave = trim($clave);
        $valor = trim($valor);

        switch ($clave) {
            case '[ARCHIVO]': $referenciaProyecto = basename(str_replace('\\', '/', $valor)); break;
            case '[NRO_PTO]': $cabecera['numero'] = $valor; break;
            case '[CLIENTE]': $cabecera['cliente'] = $valor; break;
            case '[ITEMS]':
                $cabecera['items'] = $valor;
                $enItems = true;
                $enPiezas = false;
                break;
            case '[PIEZAS]':
                $cabecera['piezas'] = $valor;
                $enItems = false;
                $enPiezas = true;
                break;
            case '[OPT]':
                $enItems = false;
                $enPiezas = false;
                break;
        }
        continue;
    }

    if ($linea === '[FIN ITEMS]') {
        $enItems = false;
        continue;
    }
    if ($linea === '[FIN PIEZAS]') {
        $enPiezas = false;
        continue;
    }

    $columnas = str_getcsv($linea);

    if ($enItems) {
        if (count($columnas) < 27) continue;

        $numeroItem = (int) trim($columnas[0]);
        $grupoTipologia = trim($columnas[1]);
        $tipologiaArmado = trim($columnas[4]);
        $tipologiaClave = $tipologiaArmado !== '' ? $tipologiaArmado : 'SIN-TIPOLOGIA';
        $tiempoItem = customMinutesToSeconds((float) trim($columnas[26]));
        $descripcionItem = trim(
            trim($columnas[7]) . ', ' . trim($columnas[8]) . ', ' .
            trim($columnas[10])
        );

        // Una pieza con piezas[1]=N pertenece a items[0]=N+1.
        $itemsPorNumero[$numeroItem] = [
            'numero' => $numeroItem,
            'grupo_tipologia' => $grupoTipologia,
            'tipologia' => $tipologiaClave
        ];

        // Varios items con el mismo [4] forman una única tipología de armado.
        if (!isset($aberturas[$tipologiaClave])) {
            $aberturas[$tipologiaClave] = [
                'codigo' => '',
                'grupo_tipologia' => $grupoTipologia,
                'tipologia' => $tipologiaClave,
                'tiempo' => 0,
                'etapas' => 8,
                'descripcion' => ''
            ];
        }
        $aberturas[$tipologiaClave]['tiempo'] += $tiempoItem;
        $aberturas[$tipologiaClave]['descripcion'] .=
            ($aberturas[$tipologiaClave]['descripcion'] === '' ? '' : ' ') . $descripcionItem;
        continue;
    }

    if ($enPiezas) {
        if (count($columnas) < 13) continue;

        $numeroItem = (int) trim($columnas[1]) + 1;
        $tipoPieza = strtoupper(trim($columnas[2]));
        $cantidad = (float) str_replace(',', '.', trim($columnas[4]));

        $piezasPorItem[$numeroItem][] = [
            'tipo' => $tipoPieza,
            'material' => trim($columnas[3]),
            'cantidad' => $cantidad > 0 ? $cantidad : 1,
            'ancho' => trim($columnas[5] ?? ''),
            'alto' => trim($columnas[6] ?? ''),
            'tipologia_pieza' => trim($columnas[12] ?? '')
        ];

        // El parámetro de configuración es por pieza a cortar, no por renglón DAT.
        if ($tipoPieza === 'P') $cortesPerfiles += $cantidad > 0 ? $cantidad : 1;
        if ($tipoPieza === 'V') $cortesVidrios += $cantidad > 0 ? $cantidad : 1;
    }
}

// Código de abertura: 0000 + grupo de tipología (items[1]) + 0.
// api/ordenes.php antepone el número de orden al guardar el detalle.
foreach ($aberturas as &$abertura) {
    $abertura['codigo'] =
        '0000' . str_pad((string) $abertura['grupo_tipologia'], 3, '0', STR_PAD_LEFT) . '0';
}
unset($abertura);

$tiempoArmado = array_sum(array_column($aberturas, 'tiempo'));
$tiempoVidrios = (int) round($tiempoArmado * ($coeficienteVidrio / 100));

// La cámara pertenece al item: solo A,CAMAxx convierte sus V asociados en DVH.
$camarasPorItem = [];
foreach ($piezasPorItem as $numeroItem => $piezas) {
    foreach ($piezas as $pieza) {
        if ($pieza['tipo'] === 'A' && stripos($pieza['material'], 'CAMA') === 0) {
            $camarasPorItem[$numeroItem][$pieza['material']] = true;
        }
    }
}

/*
 * Un grupo de vidrio es único por armado/pieza/material/cámara/medida.
 * Por eso 4 V iguales producen 1 DVH; 2+2 con distinta medida producen 2.
 */
$gruposVidrio = [];
$cantidadTotalVidrios = 0.0;
foreach ($piezasPorItem as $numeroItem => $piezas) {
    $item = $itemsPorNumero[$numeroItem] ?? null;
    if (!$item) {
        registrarLog('WARNING', 'FILE', "Piezas asociadas al item {$numeroItem}, inexistente en ITEMS.");
        continue;
    }

    $camaras = array_keys($camarasPorItem[$numeroItem] ?? []);
    sort($camaras, SORT_NATURAL | SORT_FLAG_CASE);
    $camara = implode('/', $camaras);

    foreach ($piezas as $pieza) {
        if ($pieza['tipo'] !== 'V') continue;

        $esTela = strcasecmp($pieza['material'], 'TELAI') === 0;
        $tipoGrupo = $esTela ? 'TELA' : ($camara !== '' ? 'DVH' : 'SIMPLE');
        $clave = implode('|', [
            $tipoGrupo,
            $item['grupo_tipologia'],
            $item['tipologia'],
            $pieza['tipologia_pieza'],
            $pieza['material'],
            $camara,
            $pieza['ancho'],
            $pieza['alto']
        ]);

        if (!isset($gruposVidrio[$clave])) {
            $gruposVidrio[$clave] = [
                'tipo_grupo' => $tipoGrupo,
                'grupo_tipologia' => $item['grupo_tipologia'],
                'tipologia_armado' => $item['tipologia'],
                'tipologia_pieza' => $pieza['tipologia_pieza'],
                'material' => $pieza['material'],
                'camara' => $camara,
                'ancho' => $pieza['ancho'],
                'alto' => $pieza['alto'],
                'cantidad' => 0.0
            ];
        }
        $gruposVidrio[$clave]['cantidad'] += $pieza['cantidad'];
        $cantidadTotalVidrios += $pieza['cantidad'];
    }
}

$vidrios = [];
$indiceVidrio = 1;
$tiempoAsignado = 0;
$cantidadGrupos = count($gruposVidrio);
$indiceGrupo = 0;

foreach ($gruposVidrio as $grupo) {
    $indiceGrupo++;
    $cantidad = $grupo['cantidad'];
    $esUltimoGrupo = $indiceGrupo === $cantidadGrupos;

    // Se distribuye el 25% por cantidad y el último absorbe el redondeo.
    $tiempoGrupo = $esUltimoGrupo ? max(0, $tiempoVidrios - $tiempoAsignado) : (int) round($tiempoVidrios * ($cantidad / max(1, $cantidadTotalVidrios)));
    $tiempoAsignado += $tiempoGrupo;
    $referencia = trim($grupo['tipologia_pieza'] . '/' . $grupo['tipologia_armado'], '/');
    $material = datMaterialLegible($grupo['material']);
    $medida = trim($grupo['ancho'] . ' x ' . $grupo['alto']);
    // $cantidadTexto = datCantidadLegible($cantidad);
    $cantidadTexto = '';

    if ($grupo['tipo_grupo'] === 'DVH') {
        $tipologia = 'V-DVH(' . $referencia . ')';
        $descripcion = 'DVH ' . $referencia . ' ' . $material . ' ' . $grupo['camara'] . ' ' . $cantidadTexto . ' - ' . $medida;
    } elseif ($grupo['tipo_grupo'] === 'TELA') {
        $tipologia = 'TELA-M(' . $referencia . ')';
        $descripcion = 'Tela Mosquitera ' . $referencia . ' ' . $cantidadTexto . ' - ' . $medida;
    } else {
        $tipologia = 'V-' . $referencia;
        $descripcion = 'Vidrio Simple ' . $referencia . ' ' . $material .
            ' ' . $cantidadTexto . ' - ' . $medida;
    }

    // Código: correlativo de DVH/tela + grupo de tipología + tipo.
    // Ej.: orden 14553 + 0001 + 000 + 1 = 1455300010001.
    // El último dígito identifica 1=vidrio y 2=tela mosquitera.
    $tipoCodigo = $grupo['tipo_grupo'] === 'TELA' ? '2' : '1';
    $codigoVidrio =
        str_pad((string) $indiceVidrio, 4, '0', STR_PAD_LEFT) .
        str_pad((string) $grupo['grupo_tipologia'], 3, '0', STR_PAD_LEFT) .
        $tipoCodigo;

    $vidrios[] = [
        $codigoVidrio,
        $tipologia,
        $tiempoGrupo,
        3,
        $descripcion
    ];
    $indiceVidrio++;
}

// El DAT debe ser utilizable aun cuando ninguno de los PDF sea compatible.
if (!preg_match('/^\d+$/D', (string)($cabecera['numero'] ?? '')) || !$aberturas ||
    (int)($cabecera['items'] ?? -1) !== count($itemsPorNumero) ||
    (int)($cabecera['piezas'] ?? -1) !== array_sum(array_map('count', $piezasPorItem))) {
    echo json_encode(['error'=>'El DAT esta incompleto o tiene un formato no reconocido. No se creo la orden.']);
    exit;
}
require_once __DIR__ . '/../importacion_documentos.php';
$compatibles = importacionDocumentosCompatibles((string)$cabecera['numero'], $documentos, $vidrios, $aberturas, __DIR__.'/../../tmp', $referenciaProyecto);
$documentos = $compatibles['documentos'];
$vidrios = $compatibles['vidrios'];
$omitidos = array_merge($omitidos, $compatibles['omitidos']);
foreach ($omitidos as $omitido) registrarLog('WARNING', 'IMPORTACION[PDF_OMITIDO]', json_encode($omitido, JSON_INVALID_UTF8_SUBSTITUTE));

$tiempoCorte = ($cortesPerfiles * $segundosCortePerfil) + ($cortesVidrios * $segundosCorteVidrio);

$cabecera['ta'] = segundosToTime((int) $tiempoArmado);
$cabecera['tv'] = segundosToTime($tiempoVidrios);
$cabecera['tt'] = segundosToTime((int) $tiempoArmado + $tiempoVidrios);
$cabecera['corte'] = segundosToTime($tiempoCorte);
$cabecera['tipologias'] = count($aberturas);
$cabecera['vidrios'] = count($vidrios);

echo json_encode([
    'orden' => [$cabecera, array_values($aberturas), $vidrios, $documentos],
    'omitidos' => $omitidos
], JSON_INVALID_UTF8_SUBSTITUTE);
exit;
?>
