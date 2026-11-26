<?php
require_once __DIR__ . '/codigos_vidrio_pla.php';

function importacionTipoPdf(string $texto): ?int {
    $texto = mb_strtoupper($texto, 'UTF-8');
    if (strpos($texto, 'DIAGRAMAS DE CORTE') !== false) return 2;
    if (strpos($texto, 'PIEZAS UBICADAS') !== false && strpos($texto, 'DESPERDICIO') !== false) return 3;
    if (preg_match('/TIPOLOG[IÍ]A/u', $texto) && strpos($texto, 'CANTIDAD') !== false && strpos($texto, 'DETALLE') !== false) return 1;
    return null;
}

/** Valida cada documento y conserva solo las asignaciones del conjunto aceptado. */
function importacionDocumentosCompatibles(string $pedido, array $documentos, array $vidrios, array $aberturas, string $directorio, ?string $proyecto = null, ?callable $leer = null, ?callable $aplicar = null): array {
    $leer = $leer ?? 'plaLeerTexto';
    $aplicar = $aplicar ?? 'plaAplicarCodigosImportacion';
    $aceptados = [];
    $omitidos = [];
    $vistos = [];
    $resultadoVidrios = $aplicar($pedido, [], $vidrios, $aberturas, $directorio);
    $tipos = [1=>'Orden de Trabajo (OP)', 2=>'PLA de perfiles', 3=>'PLA de vidrio'];
    foreach ($documentos as $doc) {
        $nombre = (string)($doc['nombre'] ?? '');
        $tipo = (int)($doc['tipo'] ?? 0);
        try {
            if ($nombre === '' || basename(str_replace('\\', '/', $nombre)) !== $nombre || strtolower(pathinfo($nombre, PATHINFO_EXTENSION)) !== 'pdf') throw new RuntimeException('El archivo no es un PDF valido.');
            if (!isset($tipos[$tipo])) throw new RuntimeException('Tipo de documento no admitido para el alta.');
            if (isset($vistos[$nombre])) throw new RuntimeException('Documento repetido: ya fue incluido una vez.');
            $texto = $leer($directorio . '/' . $nombre);
            $primera = explode("\f", $texto)[0];
            $detectado = importacionTipoPdf($primera);
            if ($detectado === null) throw new RuntimeException('Formato no reconocido o PDF sin texto legible.');
            if ($detectado !== $tipo) throw new RuntimeException('Es '.$tipos[$detectado].', pero fue seleccionado como '.$tipos[$tipo].'.');
            if (preg_match('/\(#\s*:?\s*(\d+)\s*\)/u', $primera, $numero) && $numero[1] !== $pedido) throw new RuntimeException('El numero de pedido del PDF no coincide con el DAT.');
            $coincide = preg_match('/(?<!\d)'.preg_quote($pedido, '/').'(?!\d)/u', $primera) === 1;
            if (!$coincide && $proyecto) {
                $normalizar = static fn($v) => mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $v)), 'UTF-8');
                $coincide = preg_match('/(?:^|[\s(])'.preg_quote($normalizar($proyecto), '/').'(?=$|[\s:)])/u', $normalizar($primera)) === 1;
            }
            if (!$coincide) throw new RuntimeException('No se pudo comprobar que el PDF corresponda al pedido del DAT.');
            $candidatos = array_merge($aceptados, [$doc]);
            // No aplicar parcialmente los codigos de un documento rechazado.
            $candidatosVidrios = $aplicar($pedido, $candidatos, $vidrios, $aberturas, $directorio);
            $aceptados = $candidatos;
            $resultadoVidrios = $candidatosVidrios;
            $vistos[$nombre] = true;
        } catch (Throwable $e) {
            $omitidos[] = ['nombre'=>$nombre, 'tipo'=>$tipo, 'motivo'=>$e->getMessage()];
        }
    }
    return ['documentos'=>$aceptados, 'vidrios'=>$resultadoVidrios, 'omitidos'=>$omitidos];
}
