<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$jobId = $_GET['jobId'] ?? '';
$error = null;
if (!preg_match('/^[a-zA-Z0-9_-]{6,}$/', $jobId)) {
    $error = 'Identificador de respaldo inválido.';
} else {
    $path = __DIR__ . '/backup_status_' . $jobId . '.json';
    $handle = @fopen($path, 'r');
    if (!$handle) {
        $error = 'No se encontró el proceso de respaldo. Vuelva a iniciarlo.';
    } else {
        flock($handle, LOCK_SH);
        $data = json_decode(stream_get_contents($handle), true);
        flock($handle, LOCK_UN);
        fclose($handle);
        if (!is_array($data)) $error = 'No se pudo leer el estado del respaldo.';
        elseif (($data['stage'] ?? '') === 'iniciando' && time() - strtotime($data['updatedAt']) > 30) {
            $error = 'El proceso de respaldo no pudo iniciar. Compruebe PHP CLI y los permisos del servidor.';
        }
    }
}
if ($error !== null) $data = ['done' => true, 'error' => $error, 'progress' => 0, 'stage' => 'error'];
$data['status'] = !empty($data['error']) ? 'error' : (!empty($data['done']) ? 'ok' : 'running');
echo json_encode($data);
