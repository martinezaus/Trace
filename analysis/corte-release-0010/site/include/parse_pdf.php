<?php

define('PDFTOTEXT_BIN', '/usr/bin/pdftotext');


/**
 * Ejecuta "pdftotext -bbox" sobre el PDF y devuelve el XML crudo (string)
 * con la posición (x, y) de cada palabra. Reemplaza el uso de "-layout",
 * que solo devolvía texto plano sin coordenadas.
 */
function ejecutarPdftotextBbox(string $rutaPdf): ?string
{
    $rutaAbs = realpath($rutaPdf);
    if ($rutaAbs === false) {
        registrarLog('ERROR','FUNCION',"Archivo no existe o ruta inválida: $rutaPdf");
        return null;
    }

    $cmd = [PDFTOTEXT_BIN, '-bbox', $rutaAbs, '-'];

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    registrarLog('INFO','PDF', "disable_functions: ".ini_get('disable_functions'));
    registrarLog('INFO','PDF', "Binario existe: ".(file_exists(PDFTOTEXT_BIN) ? 'SI' : 'NO'));
    registrarLog('INFO','PDF', "Comando: ".print_r($cmd, true));

    $proc = proc_open($cmd, $descriptors, $pipes);

    if (!is_resource($proc)) {
        registrarLog('ERROR','FUNCION',"No se pudo ejecutar pdftotext -bbox (proc_open falló) para $rutaAbs");
        return null;
    }
    
    stream_set_chunk_size($pipes[1], 1024 * 1024); // 1MB chunks
    $salida  = stream_get_contents($pipes[1]);
    $errSal  = stream_get_contents($pipes[2]);

    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $codigo = proc_close($proc);

    registrarLog('INFO','PDF', "pdftotext -bbox código=$codigo, salida(200)=".substr($salida,0,200)." err=$errSal");

    if ($codigo !== 0 || trim($salida) === '') {
        registrarLog('ERROR','FUNCION',"pdftotext -bbox no devolvió datos para $rutaAbs (código $codigo, err: $errSal)");
        return null;
    }

    // pdftotext a veces mezcla warnings (stderr) al principio del string.
    // Nos quedamos solo desde el XML real en adelante.
    $idx = strpos($salida, '<!DOCTYPE');
    if ($idx === false) {
        $idx = strpos($salida, '<doc>');
    }
    if ($idx === false) {
        registrarLog('ERROR','FUNCION',"Salida de pdftotext -bbox no contiene XML válido");
        return null;
    }

    return substr($salida, $idx);
}


/**
 * Parsea el XML de pdftotext -bbox y devuelve un array de páginas,
 * cada una con su lista de "palabras" (text, xMin, yMin, xMax, yMax).
 */

function parsearXmlBbox(string $xml): array
{
    libxml_use_internal_errors(true);
    $root = simplexml_load_string($xml);

    if ($root === false) {
        registrarLog('ERROR','FUNCION',"No se pudo parsear el XML de pdftotext -bbox");
        return [];
    }

    // El XML declara xmlns="http://www.w3.org/1999/xhtml" como namespace
    // por defecto. SimpleXML normalmente lo resuelve solo vía $root->body->doc->page,
    // pero por las dudas dejamos un fallback explícito con el namespace.
    $pages = $root->body->doc->page ?? null;

    if (empty($pages)) {
        $ns    = $root->getNamespaces(true);
        $nsUri = $ns[''] ?? 'http://www.w3.org/1999/xhtml';
        $body  = $root->children($nsUri)->body ?? null;
        $doc   = $body->children($nsUri)->doc ?? null;
        $pages = $doc->children($nsUri)->page ?? null;
    }

    if (empty($pages)) {
        registrarLog('ERROR','FUNCION',"No se encontraron páginas en el XML de pdftotext -bbox");
        return [];
    }

    $paginas = [];
    $numPagina = 0;

    foreach ($pages as $page) {
        $numPagina++;
        $palabras = [];

        $words = $page->word ?? [];

        foreach ($words as $w) {
            $palabras[] = [
                'text' => (string)$w,
                'xMin' => (float)$w['xMin'],
                'yMin' => (float)$w['yMin'],
                'xMax' => (float)$w['xMax'],
                'yMax' => (float)$w['yMax'],
            ];
        }

        $paginas[$numPagina] = [
            'width'    => (float)$page['width'],
            'height'   => (float)$page['height'],
            'palabras' => $palabras,
        ];
    }

    return $paginas;
}


/**
 * Agrupa palabras de una página en líneas (mismo yMin aproximado),
 * ordenadas de arriba hacia abajo, cada palabra de izquierda a derecha.
 */

function agruparEnLineas(array $palabras, float $tolerancia = 2.0): array
{
    // Los índices de PHP sólo pueden ser enteros o strings: conservar Y como dato.
    $lineas = [];
    foreach ($palabras as $w) {
        $indice = null;
        foreach ($lineas as $i => $linea) {
            if (abs($linea['y'] - $w['yMin']) <= $tolerancia) { $indice = $i; break; }
        }
        if ($indice === null) {
            $indice = count($lineas);
            $lineas[] = ['y' => (float)$w['yMin'], 'palabras' => []];
        }
        $lineas[$indice]['palabras'][] = $w;
    }
    usort($lineas, static fn($a, $b) => $a['y'] <=> $b['y']);
    $resultado = [];
    foreach ($lineas as $linea) {
        $palabrasLinea = $linea['palabras'];
        usort($palabrasLinea, static fn($a, $b) => $a['xMin'] <=> $b['xMin']);
        $resultado[] = ['y'=>$linea['y'], 'texto'=>implode(' ', array_column($palabrasLinea,'text')), 'palabras'=>$palabrasLinea];
    }

    return $resultado;
}


/**
 * Función principal: parsea el PDF completo y devuelve los items
 * (perfil, descripcion, barra_num, detalle, pagina, pos_x, pos_y).
 *
 * pos_x / pos_y (en puntos PDF) corresponden al inicio de la línea
 * "N / M)", para poder dibujar el tilde justo al lado.
 */

function parsearPdf(string $rutaPdf): array
{
    $xml = ejecutarPdftotextBbox($rutaPdf);
    if ($xml === null) {
        return [];
    }

    $paginas = parsearXmlBbox($xml);
    if (empty($paginas)) {
        return [];
    }

    $items = [];
    $perfilActual = null;
    $descripcionActual = null;

    foreach ($paginas as $numPagina => $pagina) {
        $lineas = agruparEnLineas($pagina['palabras']);

        foreach ($lineas as $linea) {
            $texto = trim($linea['texto']);

            // --- Detectar "Perfil XXXX ..." ---
            if (preg_match('/^Perfil\s+([A-Z0-9]+)\s+(.+)$/', $texto, $m)) {
                $perfilActual = trim($m[1]);
                $descripcionActual = null;
                continue;
            }

            // --- Detectar línea de descripción (la que sigue al "Perfil ...") ---
            if ($perfilActual !== null && $descripcionActual === null) {
                $candidata = preg_replace('/\s+Pen\s*$/', '', $texto);
                if ($candidata !== '' && !preg_match('/^\d+\s*\/\s*\d+\)/', $candidata)) {
                    $candidata = preg_replace('/^Perfil\s+/i', '', $candidata);
                    $descripcionActual = $candidata;
                    continue;
                }
            }

            // --- Detectar línea de barra "N / M) ..." ---
            if (preg_match('/^(\d+)\s*\/\s*(\d+)\)\s*(.*)$/', $texto, $m)) {
                $barraNum = trim($m[1]) . '/' . trim($m[2]);
                $detalle  = trim($m[3]);

                $primeraPalabra = $linea['palabras'][0];
                $posX = $primeraPalabra['xMin'];
                $posY = $primeraPalabra['yMin'];

                $items[] = [
                    'perfil'      => $perfilActual,
                    'descripcion' => $descripcionActual,
                    'barra_num'   => $barraNum,
                    'detalle'     => $detalle,
                    'pagina'      => $numPagina,
                    'pos_x'       => $posX,
                    'pos_y'       => $posY,
                    'tilde'       => 0,
                    'observacion' => '',
                ];
                continue;
            }

            if (preg_match('/^Total Barras/i', $texto)) {
                continue;
            }
        }
    }

    validarOrdenItems($items, $rutaPdf);

    return $items;
}


/**
 * ✅ Chequeo de sanidad post-parseo.
 *
 * El dibujo del tilde en documentos.php usa directamente pos_x/pos_y
 * guardados en la base, sin volver a leer el PDF. Si el parser alguna
 * vez desalinea la barra "N/M)" con las coordenadas de otra barra
 * (por ejemplo, por un layout con columnas que agrupamos mal en
 * agruparEnLineas), el error queda "guardado para siempre" en la
 * base y el tilde se dibuja en el lugar equivocado sin que nadie lo
 * note hasta revisar el PDF a ojo.
 *
 * Esta función no corrige nada: solo verifica que, dentro de cada
 * página, los items conserven el mismo orden vertical (pos_y
 * creciente) en el que fueron detectados los números de barra
 * "N/M)". Si encuentra un salto hacia atrás, lo deja registrado en
 * el log para poder detectarlo apenas se procesa el documento, en
 * vez de descubrirlo semanas después con un tilde mal puesto.
 */
function validarOrdenItems(array $items, string $rutaPdf): void
{
    if (!function_exists('registrarLog')) {
        return; // modo standalone / CLI: no hay logger disponible
    }

    $anterior = null; // último item visto, para comparar con el actual

    foreach ($items as $it) {
        if ($anterior !== null && $anterior['pagina'] === $it['pagina']) {
            // Dentro de la misma página, cada barra debe quedar más
            // abajo (pos_y mayor) que la anterior. Un pos_y menor o
            // igual indica que el emparejamiento barra <-> coordenada
            // se rompió en algún punto del agrupado de líneas.
            if ($it['pos_y'] <= $anterior['pos_y']) {
                registrarLog(
                    'WARNING',
                    'PARSE_PDF_SANITY',
                    "Posible desalineación de coordenadas en '$rutaPdf': " .
                    "barra {$anterior['perfil']} {$anterior['barra_num']} (pág {$anterior['pagina']}, y={$anterior['pos_y']}) " .
                    "seguida de barra {$it['perfil']} {$it['barra_num']} (pág {$it['pagina']}, y={$it['pos_y']}). " .
                    "El tilde de estas barras podría dibujarse en la posición equivocada."
                );
            }
        }
        $anterior = $it;
    }
}


// ============================================================
// Modo CLI: si se ejecuta directamente, parsea y muestra/inserta
// ============================================================
if (php_sapi_name() === 'cli' && isset($argv[1])) {
    $ruta = $argv[1];

    if (!file_exists($ruta)) {
        fwrite(STDERR, "Archivo no encontrado: $ruta\n");
        exit(1);
    }

    // Stub de registrarLog si se ejecuta standalone (fuera del sistema)
    if (!function_exists('registrarLog')) {
        function registrarLog($nivel, $modulo, $msg) {
            fwrite(STDERR, "[$nivel][$modulo] $msg\n");
        }
    }

    $items = parsearPdf($ruta);

    fwrite(STDERR, "Items encontrados: " . count($items) . "\n\n");

    foreach ($items as $i) {
        printf(
            "Pag:%d Perfil: %-8s | %-35s | Barra: %-6s | x=%.1f y=%.1f | %s\n",
            $i['pagina'],
            $i['perfil'],
            $i['descripcion'],
            $i['barra_num'],
            $i['pos_x'],
            $i['pos_y'],
            $i['detalle']
        );
    }
}