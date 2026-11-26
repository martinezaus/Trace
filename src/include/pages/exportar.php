<?php
declare(strict_types=1);
require_once __DIR__ . '/../reportes_access.php';
require_once __DIR__ . '/../reportes_export.php';
header('Cache-Control: no-store');
try {
    reportCheckRequest();
    $id = reportScalar($_POST, 'snapshot');
    $snapshot = $_SESSION['reportes_snapshot'][$id] ?? null;
    if (!$snapshot) throw new InvalidArgumentException('La consulta venció. Vuelva a buscar antes de exportar.');
    $format = reportScalar($_POST, 'export_format');
    if (!in_array($format, ['pdf','csv'], true)) throw new InvalidArgumentException('Formato no válido.');
    $rows = reportExportSelection($snapshot, reportScalar($_POST, 'rows', '[]'));
    $search = reportScalar($_POST, 'tableSearch');
    if (mb_strlen($search) > 500) throw new InvalidArgumentException('La búsqueda de tabla es demasiado larga.');
    session_write_close();
    $content = $format === 'pdf' ? reportPdf($snapshot, $rows, $search) : reportCsv($snapshot, $rows, $search);
    header('Content-Type: ' . ($format === 'pdf' ? 'application/pdf' : 'text/csv; charset=utf-8'));
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: attachment; filename="reporte_' . $snapshot['filters']['type'] . '_' . date('Y-m-d_H-i-s') . '.' . $format . '"');
    echo $content;
} catch (Throwable $e) {
    if (http_response_code() < 400) http_response_code($e instanceof InvalidArgumentException || $e instanceof JsonException ? 422 : 500);
    header('Content-Type: application/json; charset=utf-8');
    $known = $e instanceof InvalidArgumentException || (http_response_code() >= 400 && http_response_code() < 500);
    if (!$known) error_log('Exportación Reportes: ' . $e->getMessage());
    echo json_encode(['success'=>false,'message'=>$known ? $e->getMessage() : 'No se pudo generar el archivo. Revise el registro del servidor.'], JSON_INVALID_UTF8_SUBSTITUTE);
}
