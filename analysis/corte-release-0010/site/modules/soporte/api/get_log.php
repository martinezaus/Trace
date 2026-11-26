<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role(['admin', 'master']);

header('Content-Type: application/json; charset=utf-8');

if (LOG_DIR === false || !is_dir(LOG_DIR)) {
    json_response(['ok' => false, 'error' => 'Directorio de logs no encontrado. Revise LOG_DIR_RAW en config.php.'], 500);
}

$action = $_GET['action'] ?? 'list';

if ($action === 'list') {
    $files = [];
    foreach (scandir(LOG_DIR) as $f) {
        if ($f === '.' || $f === '..') continue;
        $full = LOG_DIR . DIRECTORY_SEPARATOR . $f;
        if (is_file($full) && preg_match('/\.(log|txt)$/i', $f)) {
            $files[] = [
                'name'     => $f,
                'size'     => filesize($full),
                'modified' => date('Y-m-d H:i:s', filemtime($full)),
            ];
        }
    }
    usort($files, fn($a, $b) => strcmp($b['modified'], $a['modified']));
    json_response(['ok' => true, 'files' => $files]);
}

if ($action === 'view') {
    $name = basename((string) ($_GET['file'] ?? ''));
    $full = realpath(LOG_DIR . DIRECTORY_SEPARATOR . $name);

    // Evita path traversal: el archivo resuelto debe seguir dentro de LOG_DIR
    // y tener una extensión permitida.
    if ($full === false || strpos($full, LOG_DIR) !== 0 || !preg_match('/\.(log|txt)$/i', $name)) {
        json_response(['ok' => false, 'error' => 'Archivo inválido.'], 400);
    }

    $lines = (int) ($_GET['lines'] ?? 500);
    $lines = max(50, min(2000, $lines));

    json_response(['ok' => true, 'name' => $name, 'content' => tail_file($full, $lines)]);
}

json_response(['ok' => false, 'error' => 'Acción no soportada.'], 400);

/** Lee las últimas $lines líneas de un archivo sin cargarlo completo en memoria */
function tail_file(string $path, int $lines): string
{
    $f = fopen($path, 'r');
    if (!$f) return '';

    $buffer = 8192;
    fseek($f, 0, SEEK_END);
    $pos = ftell($f);
    $chunk = '';
    $lineCount = 0;

    while ($pos > 0 && $lineCount <= $lines) {
        $seek = max(0, $pos - $buffer);
        $readSize = $pos - $seek;
        fseek($f, $seek);
        $data = fread($f, $readSize);
        $chunk = $data . $chunk;
        $lineCount = substr_count($chunk, "\n");
        $pos = $seek;
        if ($seek === 0) break;
    }
    fclose($f);

    $allLines = explode("\n", $chunk);
    $tail = array_slice($allLines, -$lines);
    return implode("\n", $tail);
}
