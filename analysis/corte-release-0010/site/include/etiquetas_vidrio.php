<?php
// Etiquetas externas: la relacion se obtiene del PDF, nunca de un correlativo supuesto.
function etiquetaVidrioEanValido(string $codigo): bool {
    if (!preg_match('/^\d{13}$/D', $codigo)) return false;
    $suma = 0;
    for ($i = 0; $i < 12; $i++) $suma += (int)$codigo[$i] * ($i % 2 ? 3 : 1);
    return (10 - $suma % 10) % 10 === (int)$codigo[12];
}

function extraerEtiquetasVidrioTexto(string $texto): array {
    $lineas = preg_split('/\R/u', $texto);
    $etiquetas = [];
    foreach ($lineas as $i => $linea) {
        $codigo = preg_replace('/\s+/u', '', $linea);
        if (!etiquetaVidrioEanValido($codigo)) continue;
        // Formato impreso: referencia, ancho, alto, cantidad/medida, (N), EAN, fecha, material, pedido.
        $referencia = trim($lineas[$i - 5] ?? '');
        $medidas = trim($lineas[$i - 2] ?? '');
        $material = trim($lineas[$i + 2] ?? '');
        $pedido = trim($lineas[$i + 3] ?? '');
        if (!preg_match('/\(([^()\r\n]+\/[^()\r\n]+)\)\s*$/u', $referencia, $ref)) continue;
        if (!preg_match('/^\d+\s+de\s+(\d+(?:[.,]\d+)?)\s*x\s*(\d+(?:[.,]\d+)?)$/iu', $medidas, $med)) continue;
        if (!preg_match('/^\d+$/D', $pedido) || substr($codigo, 0, 5) !== $pedido || $material === '') continue;
        $etiquetas[] = [
            'codigo' => $codigo, 'pedido' => $pedido, 'referencia' => trim($ref[1]),
            'material' => $material, 'ancho' => (float)str_replace(',', '.', $med[1]),
            'alto' => (float)str_replace(',', '.', $med[2])
        ];
    }
    return $etiquetas;
}

function leerEtiquetasVidrioPdf(string $ruta): array {
    if (!is_file($ruta)) throw new RuntimeException('No se encontro el PDF de etiquetas cargado.');
    $bin = PHP_OS_FAMILY === 'Windows'
        ? dirname(__DIR__) . '/plugins/poppler/Library/bin/pdftotext.exe'
        : '/usr/bin/pdftotext';
    $pipes = [];
    $proc = proc_open([$bin, '-raw', '-enc', 'UTF-8', $ruta, '-'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'a']], $pipes);
    if (!is_resource($proc)) throw new RuntimeException('No se pudo leer el PDF de etiquetas.');
    fclose($pipes[0]);
    $texto = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $salida = proc_close($proc);
    if ($salida !== 0 || !is_string($texto) || trim($texto) === '') {
        throw new RuntimeException('No se pudo extraer texto del PDF de etiquetas.');
    }
    $etiquetas = extraerEtiquetasVidrioTexto($texto);
    if (!$etiquetas) throw new RuntimeException('No se reconocio el formato del PDF de etiquetas.');
    return $etiquetas;
}

function etiquetaCoincideDetalleVidrio(array $etiqueta, array $detalle): bool {
    $tipo = $detalle['tipologia'] ?? '';
    if (strpos($tipo, 'V-DVH(') === 0 || strpos($tipo, 'TELA-M(') === 0) {
        $ref = substr($tipo, strpos($tipo, '(') + 1, -1);
    } elseif (strpos($tipo, 'V-') === 0) {
        $ref = substr($tipo, 2);
    } else return false;
    $normalizar = function ($valor) { return mb_strtoupper(preg_replace('/\s+/u', '', $valor), 'UTF-8'); };
    if ($normalizar($ref) !== $normalizar($etiqueta['referencia'])) return false;
    $descripcion = $detalle['descripcion'] ?? '';
    if (!preg_match('/-\s*(\d+(?:[.,]\d+)?)\s*x\s*(\d+(?:[.,]\d+)?)\s*$/iu', $descripcion, $med)) return false;
    $ancho = (float)str_replace(',', '.', $med[1]);
    $alto = (float)str_replace(',', '.', $med[2]);
    if (!(($ancho === $etiqueta['ancho'] && $alto === $etiqueta['alto']) ||
          ($alto === $etiqueta['ancho'] && $ancho === $etiqueta['alto']))) return false;
    if (strpos($tipo, 'TELA-M(') === 0) return $normalizar($etiqueta['material']) === 'TELAI';
    // El material sigue a la referencia en las descripciones generadas por el DAT.
    $refRegex = implode('\s*', array_map(function ($p) { return preg_quote($p, '/'); }, preg_split('//u', $normalizar($ref), -1, PREG_SPLIT_NO_EMPTY)));
    if (!preg_match('/' . $refRegex . '\s+([A-Z]+\s*\d*)\b/iu', $descripcion, $mat)) return false;
    return $normalizar($mat[1]) === $normalizar($etiqueta['material']);
}
