<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/log.php';

try {
    if (!is_dir(carpetaBackup) && !mkdir(carpetaBackup, 0777, true)) {
        throw new RuntimeException('No se pudo crear la carpeta de respaldos.');
    }
    $php = PHP_BINDIR . '/php';
    if (!is_file($php) || !is_executable($php)) {
        throw new RuntimeException('No se encontró PHP CLI para generar el respaldo.');
    }
    $jobId = bin2hex(random_bytes(10));
    $statusPath = __DIR__ . '/backup_status_' . $jobId . '.json';
    $initial = ['stage' => 'iniciando', 'progress' => 0, 'done' => false, 'error' => null, 'updatedAt' => date('c')];
    if (file_put_contents($statusPath, json_encode($initial), LOCK_EX) === false) {
        throw new RuntimeException('No se pudo guardar el estado del respaldo.');
    }
    // PHP-FPM ejecuta el trabajador CLI en Linux sin retener la petición HTTP.
    $cmd = escapeshellarg($php) . ' ' . escapeshellarg(__DIR__ . '/backup_run.php')
        . ' ' . escapeshellarg($jobId) . ' </dev/null >/dev/null 2>&1 &';
    exec($cmd, $output, $exitCode);
    if ($exitCode !== 0) {
        throw new RuntimeException('No se pudo iniciar el proceso de respaldo.');
    }
    registrarLog('INFO', 'BACKUPS', 'Respaldo solicitado. jobId=' . $jobId);
    echo json_encode(['status' => 'ok', 'jobId' => $jobId]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'mensaje' => $e->getMessage()]);
}
