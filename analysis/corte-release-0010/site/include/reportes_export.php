<?php
declare(strict_types=1);
require_once __DIR__ . '/reportes_service.php';

function reportEscape($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function reportExportSelection(array $snapshot, string $selection): array {
    if (($snapshot['metricVersion'] ?? '') !== 'ahorro-v1') {
        throw new InvalidArgumentException('El criterio de productividad cambió. Genere nuevamente el reporte antes de exportar.');
    }
    $indices = json_decode($selection, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($indices) || !array_is_list($indices) || count($indices) > 10000) {
        throw new InvalidArgumentException('Selección de filas inválida.');
    }
    $rows = []; $seen = [];
    foreach ($indices as $index) {
        if (!is_int($index) || !array_key_exists($index, $snapshot['rows']) || isset($seen[$index])) {
            throw new InvalidArgumentException('Selección de filas inválida. Repita la búsqueda.');
        }
        $seen[$index] = true; $rows[] = $snapshot['rows'][$index];
    }
    if (!$rows) throw new InvalidArgumentException('No hay filas para exportar.');
    return $rows;
}
function reportPdf(array $snapshot, array $rows, string $search = '', bool $withLogo = true): string {
    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/negocio_logo.php';
    $config = reportCatalog()[$snapshot['filters']['type']];
    $pdf = new \Mpdf\Mpdf(['mode'=>'utf-8', 'format'=>'A4', 'orientation'=>count($config['columns']) > 7 ? 'L' : 'P',
        'margin_left'=>10, 'margin_right'=>10, 'margin_top'=>12, 'margin_bottom'=>16,
        'default_font'=>'dejavusans', 'default_font_size'=>8,
        'tempDir'=>sys_get_temp_dir() . '/trace-reportes-mpdf']);
    $pdf->SetTitle('Reporte - ' . $config['title']);
    $pdf->SetHTMLFooter('<div style="text-align:right;font-size:8pt;color:#555">Página {PAGENO} de {nbpg}</div>');
    $html = '<style>body{color:#243247}h1{font-size:18pt;margin:8px 0}.meta{font-size:8pt;color:#465366;margin:5px 0}
        table{border-collapse:collapse;width:100%;font-size:8pt;table-layout:fixed}
        th{background:#e8edf4;text-align:left;font-weight:bold}td,th{border:0.2mm solid #ccd3dd;padding:5px;overflow-wrap:break-word}
        thead{display:table-header-group}tr{page-break-inside:avoid}.number{text-align:right}</style>';
    if ($withLogo) $html .= negocio_logo_html($pdf);
    $html .= '<h1>' . reportEscape($config['title']) . '</h1><p class="meta">' . reportEscape(reportDescription($snapshot['filters'])) . '</p>';
    $html .= '<p class="meta">Consulta: ' . reportEscape($snapshot['created']) . ' | Exportación: ' . date('d/m/Y H:i') .
        ' | Registros: ' . count($rows) . ' de ' . count($snapshot['rows']) . '</p>';
    if ($search !== '') $html .= '<p class="meta">Búsqueda en tabla: ' . reportEscape($search) . '</p>';
    $html .= '<p class="meta">' . reportEscape($config['note']) . '</p><table><thead><tr>';
    foreach ($config['columns'] as $column) $html .= '<th>' . reportEscape($column['title']) . '</th>';
    $html .= '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach ($config['columns'] as $column) {
            $numeric = in_array($column['type'], ['duration','integer','percent'], true);
            $html .= '<td' . ($numeric ? ' class="number"' : '') . '>' .
                reportEscape(reportCell($row[$column['field']] ?? null, $column['type'])) . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';
    $pdf->WriteHTML($html);
    return $pdf->Output('', 'S');
}
function reportCsvValue(string $value): string {
    // Evitar que los textos ingresados por usuarios se ejecuten como fórmulas en Excel.
    return preg_match('/^[\s\x{FEFF}]*[=+@-]/u', $value) || preg_match('/^[\t\r\n]/', $value) ? "'" . $value : $value;
}
function reportCsv(array $snapshot, array $rows, string $search = ''): string {
    $config = reportCatalog()[$snapshot['filters']['type']];
    $stream = fopen('php://temp', 'w+');
    fwrite($stream, "\xEF\xBB\xBF");
    $write = static function(array $values) use ($stream): void {
        fputcsv($stream, array_map('reportCsvValue', $values), ';', '"', '');
    };
    $write([$config['title']]);
    $write([reportDescription($snapshot['filters'])]);
    $write(['Consulta: ' . $snapshot['created'], 'Registros: ' . count($rows) . ' de ' . count($snapshot['rows'])]);
    $write([$config['note']]);
    if ($search !== '') $write(['Búsqueda en tabla: ' . $search]);
    $write(array_column($config['columns'], 'title'));
    foreach ($rows as $row) {
        $values = [];
        foreach ($config['columns'] as $column) {
            $v = $row[$column['field']] ?? null;
            $values[] = reportCell($v, $column['type']);
        }
        $write($values);
    }
    rewind($stream); $csv = stream_get_contents($stream); fclose($stream);
    return $csv;
}
