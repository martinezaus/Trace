<?php

function parsearPdfVidrio(string $rutaPdf): array{
    $xml = ejecutarPdftotextBbox($rutaPdf);
    if ($xml === null) {
        registrarLog('ERROR', 'PDF_VIDRIO', "No se pudo obtener bbox para $rutaPdf");
        return [];
    }

    $paginas = parsearXmlBbox($xml);
    if (empty($paginas)) {
        registrarLog('ERROR', 'PDF_VIDRIO', "Sin páginas en el bbox de $rutaPdf");
        return [];
    }

    $items = [];

    foreach ($paginas as $numPagina => $pagina) {
        $lineas = agruparEnLineas($pagina['palabras']);

        // 1. Ubicar el límite entre diagrama (arriba) y tabla (abajo)
        //    usando la línea "Piezas ubicadas" como corte en Y.
        $yLimiteTabla = null;
        foreach ($lineas as $linea) {
            if (stripos($linea['texto'], 'Piezas ubicadas') !== false) {
                $yLimiteTabla = $linea['y'];
                break;
            }
        }

        if ($yLimiteTabla === null) {
            // Página sin tabla de piezas (portada, resumen, etc.) -> se ignora
            continue;
        }

        // 2. Recorrer el DIAGRAMA (todo lo que está por encima del límite)
        //    y registrar la posición real de cada referencia "(N)".
        //    Si "(N)" se repite (cant > 1), se guardan todas las
        //    posiciones en una cola FIFO para poder repartirlas.
        $posicionesPorRef = [];
        foreach ($lineas as $linea) {
            if ($linea['y'] >= $yLimiteTabla) continue; // ya es zona de tabla

            foreach ($linea['palabras'] as $palabra) {
                if (preg_match('/^\((\d+)\)$/', trim($palabra['text']), $m)) {
                    $posicionesPorRef[$m[1]][] = [
                        'x' => $palabra['xMin'],
                        'y' => $palabra['yMin'],
                    ];
                }
            }
        }

        // 3. Recorrer la TABLA (todo lo que está por debajo del límite)
        //    y por cada fila, tomar su cant/base/altura/detalle de ahí,
        //    pero su pos_x/pos_y de la posición real hallada en el diagrama.
        foreach ($lineas as $linea) {
            if ($linea['y'] < $yLimiteTabla) continue; // todavía zona de diagrama

            $texto = trim($linea['texto']);

            // Formato: (REF) CANT BASE ALTURA DETALLE...
            // Ej: (19) 1 580 2229 ARIATI BERNARDO 14716 OPC 4(PR85/PE3)
            if (preg_match('/^\((\d+)\)\s+(\d+)\s+(\d+)\s+(\d+)\s+(.+)$/', $texto, $m)) {
                $ref = $m[1];

                $pos = null;
                if (!empty($posicionesPorRef[$ref])) {
                    $pos = array_shift($posicionesPorRef[$ref]); // próxima posición disponible
                } else {
                    registrarLog(
                        'WARNING',
                        'PDF_VIDRIO',
                        "No se encontró posición en el diagrama para la ref ($ref) en pág $numPagina de $rutaPdf. " .
                        "Se guarda sin pos_x/pos_y; el tilde no podrá dibujarse en el lugar correcto."
                    );
                }

                $items[] = [
                    'perfil'      => '(' . $ref . ')',      // ref como "(19)"
                    'descripcion' => trim($m[5]),            // detalle completo
                    'barra_num'   => $ref,                   // número de ref
                    'cant'        => (int)$m[2],
                    'base'        => (int)$m[3],
                    'altura'      => (int)$m[4],
                    'observacion' => '',
                    'pagina'      => $numPagina,
                    'pos_x'       => $pos['x'] ?? '',
                    'pos_y'       => $pos['y'] ?? '',
                    'tilde'       => 0,
                ];
            }
        }
    }

    registrarLog('INFO', 'PDF_VIDRIO', "Items extraídos: " . count($items));
    return $items;
}