<?php
ignore_user_abort(true);
set_time_limit(0);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/log.php';

$jobId = $argv[1] ?? '';
if (PHP_SAPI !== 'cli' || !preg_match('/^[a-zA-Z0-9_-]{6,}$/', $jobId)) exit(1);
$statusPath = __DIR__ . '/backup_status_' . $jobId . '.json';
$writeStatus = function(array $data) use ($statusPath) {
    $data['updatedAt'] = date('c');
    if (file_put_contents($statusPath, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
        throw new RuntimeException('No se pudo actualizar el estado del respaldo.');
    }
};
$finished = false;
register_shutdown_function(function() use (&$finished, $writeStatus) {
    if (!$finished) {
        $writeStatus(['stage' => 'error', 'progress' => 0, 'done' => true, 'error' => 'El proceso de respaldo se interrumpió. Revise el registro del servidor.']);
    }
});
$base = rtrim(carpetaBackup, '/\\') . DIRECTORY_SEPARATOR;
$fecha = date('Y-m-d_H-i-s') . '_' . $jobId;
$archivoSQL = $base . 'backup_bd_' . $fecha . '.sql';
$archivoZIP = $base . 'backup_completo_' . $fecha . '.zip';
try {
    $writeStatus(['stage' => 'dump_db', 'progress' => 10, 'done' => false, 'error' => null]);
    if (!class_exists('ZipArchive')) throw new RuntimeException('PHP no tiene habilitada la extensión ZIP.');
    if (!is_file(cmysqldump)) throw new RuntimeException('No se encontró mysqldump en la ruta configurada.');
    // Separar stderr del SQL y usar argumentos sin shell evita corromper el dump.
    $command = [cmysqldump, '--opt', '--single-transaction', '--host=' . hostBD,
        '--user=' . usuarioBD, '--password=' . claveBD, '--result-file=' . $archivoSQL, nombreBD];
    // El servicio db de Compose usa el certificado autofirmado de MySQL.
    // MariaDB 11 verifica certificados por defecto; conservar TLS en esta red local.
    if (hostBD === 'db') $command[] = '--skip-ssl-verify-server-cert';
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('No se pudo ejecutar mysqldump.');
    fclose($pipes[0]);
    stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $diagnostic = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0 || !is_file($archivoSQL) || filesize($archivoSQL) === 0) {
        registrarLog('ERROR', 'BACKUPS', 'mysqldump: ' . substr($diagnostic, 0, 500));
        throw new RuntimeException('Error al exportar la base de datos. Revise el registro del servidor.');
    }
    $writeStatus(['stage' => 'zip', 'progress' => 20, 'done' => false, 'error' => null]);
    $zip = new ZipArchive();
    if ($zip->open($archivoZIP, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
        throw new RuntimeException('No se pudo crear el archivo ZIP.');
    }
    if (!$zip->addFile($archivoSQL, basename($archivoSQL))) throw new RuntimeException('No se pudo agregar el SQL al ZIP.');
    $raiz = realpath(__DIR__ . '/..');
    $excluded = array_filter(array_map('realpath', [carpetaBackup, $raiz . '/database', $raiz . '/node_modules', $raiz . '/.git']));
    $filter = new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS),
        function($entry) use ($excluded) {
            if ($entry->isLink()) return false;
            foreach ($excluded as $path) {
                if (strcasecmp($entry->getRealPath(), $path) === 0) return false;
            }
            return !preg_match('/^backup_status_.*\.json$/', $entry->getFilename());
        }
    );
    $archivos = [];
    foreach (new RecursiveIteratorIterator($filter) as $entry) {
        if ($entry->isFile()) $archivos[] = $entry->getPathname();
    }
    $lastPercent = 20;
    foreach ($archivos as $index => $abs) {
        $interno = str_replace('\\', '/', substr($abs, strlen($raiz) + 1));
        if (!$zip->addFile($abs, $interno)) throw new RuntimeException('No se pudo agregar un archivo al respaldo.');
        $percent = 20 + (int)(($index + 1) / max(count($archivos), 1) * 70);
        if ($percent !== $lastPercent) {
            $writeStatus(['stage' => 'zip', 'progress' => $percent, 'done' => false, 'error' => null]);
            $lastPercent = $percent;
        }
    }
    $writeStatus(['stage' => 'comprimiendo', 'progress' => 90, 'done' => false, 'error' => null]);
    if (!$zip->close()) throw new RuntimeException('No se pudo finalizar el ZIP. Compruebe el espacio disponible.');
    @unlink($archivoSQL);
    $writeStatus(['stage' => 'ok', 'progress' => 100, 'done' => true, 'error' => null, 'zipNombre' => $archivoZIP]);
    $finished = true;
    registrarLog('INFO', 'BACKUPS', 'Finalizado. jobId=' . $jobId);
} catch (Throwable $e) {
    if (isset($zip)) unset($zip);
    @unlink($archivoSQL);
    @unlink($archivoZIP);
    $writeStatus(['stage' => 'error', 'progress' => 0, 'done' => true, 'error' => $e->getMessage()]);
    $finished = true;
    registrarLog('ERROR', 'BACKUPS', $e->getMessage());
    exit(1);
}
