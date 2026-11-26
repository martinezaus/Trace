<?php
declare(strict_types=1);
require_once __DIR__ . '/../include/reportes_access.php';
require_once __DIR__ . '/../include/reportes_service.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    reportCheckRequest();
    $filters = reportFilters($_POST);
    // Liberar la sesión durante la consulta. Las búsquedas llevan su propio identificador.
    session_write_close();
    $rows = reportData(reportDatabase(), $filters);
    $snapshot = ['metricVersion'=>PRODUCTIVIDAD_VERSION, 'id'=>bin2hex(random_bytes(16)), 'filters'=>$filters, 'rows'=>$rows, 'created'=>date('Y-m-d H:i:s')];
    session_start();
    // Conservar varias búsquedas para que una respuesta tardía no invalide la visible.
    $_SESSION['reportes_snapshot'][$snapshot['id']] = $snapshot;
    while (count($_SESSION['reportes_snapshot']) > 3) array_shift($_SESSION['reportes_snapshot']);
    session_write_close();
    $config = reportCatalog()[$filters['type']];
    echo json_encode(['success'=>true,'data'=>$rows,'columns'=>$config['columns'],'total'=>count($rows),
        'snapshot'=>$snapshot['id'],'created'=>$snapshot['created'],'description'=>reportDescription($filters)], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    if (http_response_code() < 400) http_response_code($e instanceof InvalidArgumentException ? 422 : 500);
    if ($e instanceof PDOException || !($e instanceof InvalidArgumentException || $e instanceof RuntimeException)) {
        error_log('Reportes: ' . $e->getMessage());
        $message = 'No se pudo generar el reporte. Revise el registro del servidor.';
    } else $message = $e->getMessage();
    echo json_encode(['success'=>false,'message'=>$message], JSON_INVALID_UTF8_SUBSTITUTE);
}
