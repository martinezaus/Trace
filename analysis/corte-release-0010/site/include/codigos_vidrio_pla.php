<?php
require_once __DIR__ . '/etiquetas_vidrio.php';

function plaCodigoVidrio(string $pedido, int $indice, int $referencia): string {
    if (!preg_match('/^\d{5}$/D', $pedido) || $indice < 0 || $indice > 9999 || $referencia < 1 || $referencia > 1000) {
        throw new RuntimeException('El pedido o los correlativos del PLA exceden el formato de etiqueta 5+4+3+1.');
    }
    $base = $pedido . sprintf('%04d%03d', $indice, $referencia - 1);
    $suma = 0;
    for ($i = 0; $i < 12; $i++) $suma += (int)$base[$i] * ($i % 2 ? 3 : 1);
    return $base . ((10 - $suma % 10) % 10);
}

function plaLeerTexto(string $ruta): string {
    if (!is_file($ruta)) throw new RuntimeException('No se encuentra el PLA: ' . basename($ruta));
    $bin = PHP_OS_FAMILY === 'Windows' ? __DIR__ . '/../plugins/poppler/Library/bin/pdftotext.exe' : '/usr/bin/pdftotext';
    $proc = proc_open([$bin, '-layout', '-enc', 'UTF-8', $ruta, '-'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'a']], $pipes);
    if (!is_resource($proc)) throw new RuntimeException('No se pudo ejecutar el lector de PLA.');
    fclose($pipes[0]);
    $texto = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $estado = proc_close($proc);
    if ($estado !== 0 || trim((string)$texto) === '') throw new RuntimeException('No se pudo leer el PLA: ' . basename($ruta));
    return $texto;
}

function plaMaterial(string $encabezado): string {
    if (preg_match('/\bFLOAT\s*(\d+)\s*mm\b/iu', $encabezado, $m)) return 'FLOAT' . $m[1];
    if (preg_match('/\bLAMINADO\s*(\d+)\s*\+\s*\1\b/iu', $encabezado, $m)) return 'LAMIN' . $m[1];
    if (preg_match('/\bTELAI\b|\bTELA\s+MOSQUITERA\b/iu', $encabezado)) return 'TELAI';
    throw new RuntimeException('Material del PLA no reconocido. Revise el encabezado del plano.');
}

// Indice de impresion desde cero por PLA, recorrido por pagina/fila/unidad.
// La regla esta contrastada con las ocho etiquetas FLOAT5 de la muestra.
function plaEtiquetasDesdeTexto(string $texto, string $pedido): array {
    $resultado = [];
    $indice = 0;
    foreach (explode("\f", $texto) as $pagina) {
        $pos = stripos($pagina, 'Piezas ubicadas');
        if ($pos === false) continue;
        $encabezado = substr($pagina, 0, $pos);
        if (!preg_match('/\(#\s*:?\s*' . preg_quote($pedido, '/') . '\s*\)/u', $encabezado)) {
            throw new RuntimeException('El presupuesto del PLA no coincide con el DAT.');
        }
        $material = plaMaterial($encabezado);
        if (!preg_match('/^\s*(\d+)\s+de\s+\d+\s*x\s*\d+\s+Placa\s+Entera/im', $encabezado, $placa)) {
            throw new RuntimeException('No se reconoce la cantidad de placas del PLA.');
        }
        $placas = (int)$placa[1];
        if ($placas < 1) throw new RuntimeException('Cantidad de placas invalida.');
        $tabla = substr($pagina, $pos);
        $fin = stripos($tabla, 'Desperdicio');
        if ($fin !== false) $tabla = substr($tabla, 0, $fin);
        $filas = 0;
        foreach (preg_split('/\R/u', $tabla) as $linea) {
            if (!preg_match('/^\s*\((\d+)\)\s+(\d+)\s+(\d+)\s+(\d+)\s+(.+)$/u', $linea, $m)) continue;
            if (!preg_match('/\(([^()\r\n]+\/[^()\r\n]+)\)\s*$/u', trim($m[5]), $ref)) {
                throw new RuntimeException('Referencia de tipologia no reconocida en el PLA.');
            }
            $cantidad = (int)$m[2] * $placas;
            if ($cantidad < 1 || $cantidad > 10000) throw new RuntimeException('Cantidad de piezas invalida en el PLA.');
            $filas++;
            for ($unidad = 0; $unidad < $cantidad; $unidad++) {
                $resultado[] = ['codigo' => plaCodigoVidrio($pedido, $indice++, (int)$m[1]),
                    'pedido' => $pedido, 'referencia' => trim($ref[1]), 'material' => $material,
                    'ancho' => (float)$m[3], 'alto' => (float)$m[4]];
            }
        }
        if (!$filas) throw new RuntimeException('No se reconocieron las filas de Piezas ubicadas del PLA.');
    }
    if (!$resultado) throw new RuntimeException('El PDF no contiene tablas de Piezas ubicadas.');
    return $resultado;
}

// $detalles contiene los grupos del DAT o los registros ya guardados.
function plaAsignarCodigos(string $pedido, array $documentos, array $detalles, string $directorio): array {
    $asignaciones = array_fill_keys(array_keys($detalles), []);
    $propietarios = [];
    $vistos = [];
    foreach ($documentos as $doc) {
        if ((int)($doc['tipo'] ?? 0) !== 3) continue;
        $nombre = basename(str_replace('\\', '/', $doc['nombre']));
        if (isset($vistos[$nombre])) continue;
        $vistos[$nombre] = true;
        foreach (plaEtiquetasDesdeTexto(plaLeerTexto($directorio . '/' . $nombre), $pedido) as $etiqueta) {
            $matches = [];
            foreach ($detalles as $id => $detalle) {
                if (etiquetaCoincideDetalleVidrio($etiqueta, $detalle)) $matches[] = $id;
            }
            if (count($matches) !== 1) throw new RuntimeException('El PLA no identifica un unico grupo del DAT: ' . $etiqueta['referencia'] . ' ' . $etiqueta['material']);
            $id = $matches[0];
            $codigo = $etiqueta['codigo'];
            if (isset($propietarios[$codigo]) && $propietarios[$codigo] !== $id) {
                throw new RuntimeException('Dos PLA generan el mismo codigo para distintos vidrios: ' . $codigo . '. Debe verificarse la numeracion entre materiales.');
            }
            $propietarios[$codigo] = $id;
            $asignaciones[$id][$codigo] = $codigo;
        }
    }
    return array_map('array_values', $asignaciones);
}

function plaAplicarCodigosImportacion(string $pedido, array $documentos, array $vidrios, array $aberturas, string $directorio): array {
    $detalles = [];
    foreach ($vidrios as $id => $v) $detalles[$id] = ['tipologia' => $v[1], 'descripcion' => $v[4]];
    $codigos = plaAsignarCodigos($pedido, $documentos, $detalles, $directorio);
    $ocupados = [];
    foreach ($aberturas as $a) $ocupados[$pedido . $a['codigo']] = true;
    // Reservar tambien los codigos internos de grupos sin PLA.
    foreach ($vidrios as $id => $v) if (!$codigos[$id]) $ocupados[$pedido . $v[0]] = true;
    foreach ($vidrios as $id => &$v) {
        $v[5] = $codigos[$id]; // codigos completos, incluido EAN; el alta guarda todos.
        if (!$codigos[$id]) continue;
        $principal = null;
        foreach ($codigos[$id] as $codigo) if (!isset($ocupados[$codigo])) { $principal = $codigo; break; }
        if ($principal === null) throw new RuntimeException('Los codigos del vidrio coinciden con otros detalles de la orden.');
        $v[0] = substr($principal, 5); // api/ordenes antepone el pedido una sola vez.
        $ocupados[$principal] = true;
    }
    unset($v);
    return $vidrios;
}
