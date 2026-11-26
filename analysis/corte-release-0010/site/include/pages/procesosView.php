<?php

require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../productividad.php';
require_once __DIR__ . '/../negocio_logo.php';

require_once __DIR__ . '/../../vendor/autoload.php';

use Mpdf\Mpdf;
$mpdf = new Mpdf([
    'mode'              => 'utf-8',
    'format'            => 'A4-P',
    'margin_left'       => 15,
    'margin_right'      => 15,
    'margin_top'        => 20,
    'margin_header'     => 8,
    'margin_bottom'     => 20,
    'margin_footer'     => 8,
    'default_font'      => 'arial',
    'default_font_size' => 10,
]);
$logoNegocio = negocio_logo_html($mpdf);
$fechaProceso = static function ($valor, string $sinFecha): string {
    if (!is_string($valor) || trim($valor) === '' || strpos($valor, '0000-00-00') === 0) return $sinFecha;
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $valor);
    if (!$fecha || $fecha->format('Y-m-d H:i:s') !== $valor) return 'Sin fecha';
    return $fecha->format('d/m/Y') . '<br>' . $fecha->format('H:i:s');
};

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($body)) {
    die(
        'Error: el body de la petición no contiene un JSON válido. ' . 'JSON error: ' . json_last_error_msg()
    );
}

if (!isset($body['detalles']) || !is_array($body['detalles'])) {
    die('Error: no se recibió el campo "detalles" ' . 'en el body de la petición.');
}

$orden_id = $body['ordenId'];
$detalles = $body['detalles'];
$procesos = $body['procesos'] ?? [];
$mpdf->SetHTMLFooter('<table width="100%" style="font-size:8pt; color:#777;">
                        <tr>
                            <td width="33%">Generado: ' . date('d/m/Y H:i') . '</td>
                            <td width="33%" align="center">{PAGENO} / {nbpg}</td>
                            <td width="33%" align="right">General Alvear, Mendoza</td>
                        </tr>
                    </table>');
$css = file_get_contents( __DIR__ . '/../../css/reportes.css');
$html = '<style>' . $css . '.subtabla {width:100%; margin-left:0;} .subtabla th, .subtabla td {font-size:8pt; padding:5px 4px;} </style>';
$html .= '<table width="100%" style="font-size:9pt; color:#666;" border="0">
            <tr>
                <td width="50%">
                    ' . $logoNegocio . '
                </td>
                <td width="50%">
                    <h1 class="text-center">Informe detallado</h1>
                    <span class="text-center">  Orden ID: '.htmlspecialchars($orden_id).'</span>
                </td>
            </tr>
        </table>';


$html .= '<p style="page-break-inside:avoid; font-size:9pt; color:#555;">' . htmlspecialchars(productividadLeyenda(), ENT_QUOTES, 'UTF-8') . '</p>';
$html .= '<div class="subtitulo">Detalle Tipologías/Procesos</div>';

$total_d = 0;
$total_e = 0;
$procesosPorDetalle = [];
if (!empty($procesos)) {
    foreach ($procesos as $itemp) {
        $detalleIdProceso = $itemp['detalle_id'] ?? 0;
        $detalleIdProceso = (int)$detalleIdProceso;
        if ($detalleIdProceso > 0) {
            if (!isset($procesosPorDetalle[$detalleIdProceso])) {
                $procesosPorDetalle[$detalleIdProceso] = [];
            }
            $procesosPorDetalle[$detalleIdProceso][] = $itemp;
        }
    }
}

foreach ($detalles as $itemd) {
    $id = $itemd['id'] ?? 0;
    $operador = $itemd['operador'] ?? '—';
    $tipologia = $itemd['tipologia'] ?? '';
    $tiempo_estimado = $itemd['tiempo_estimado'] ?? '';
    $tiempo_real = $itemd['tiempo_real'] ?? '';
    $productividad = productividadItem($itemd);
    
    $total_e += $productividad;
    $total_d += 1;
    $procesosDelDetalle = $procesosPorDetalle[(int)$id] ?? [];

    $html .= '<div class="bloque-detalle">';
    $html .= '
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Operador</th>
                    <th class="center">Tipología</th>
                    <th class="center">Tiempo (Estimado)</th>
                    <th class="center">Tiempo (Real)</th>
                    <th class="center">Productividad</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="center">' . htmlspecialchars($id) . '</td>
                    <td>' . htmlspecialchars($operador) . '</td>
                    <td class="left">' . htmlspecialchars($tipologia) . '</td>
                    <td class="center">' . htmlspecialchars($tiempo_estimado) . '</td>
                    <td class="center">' . htmlspecialchars($tiempo_real) . '</td>
                    <td class="right">' . productividadFormato($productividad) . '</td>
                </tr>
            </tbody>
        </table>
    ';
    if (!empty($procesosDelDetalle)) {

        $html .= '
            <div class="subtitulo-procesos">
                Procesos realizados
            </div>
        ';
        $html .= '
            <table class="subtabla">
                <thead>
                    <tr>
                        <th>#</th>
                        <th class="center">Tarea</th>
                        <th class="center" width="16%">Fecha de inicio</th>
                        <th class="center" width="16%">Fecha de fin</th>
                        <th class="center">Tiempo</th>
                        <th class="center">Operador</th>
                        <th class="center">Ayudante</th>
                    </tr>
                </thead>
                <tbody>
        ';
        foreach ($procesosDelDetalle as $itemp) {
            $procesoId = $itemp['id'] ?? '';
            $tarea = $itemp['tarea'] ?? '';
            $tiempo = $itemp['tiempo'] ?? '';
            $operadorProceso = $itemp['operador'] ?? '';
            $ayudante = $itemp['ayudante'] ?? '';
            $html .= '
                <tr>
                    <td class="center">
                        ' .
                        htmlspecialchars($procesoId) .
                    '
                    </td>
                    <td>
                        ' .
                        htmlspecialchars($tarea) .
                    '
                    </td>
                    <td class="center">' . $fechaProceso($itemp['inicio'] ?? null, 'Sin fecha') . '</td>
                    <td class="center">' . $fechaProceso($itemp['fin'] ?? null, 'Sin finalizar') . '</td>
                    <td class="center">
                        ' .
                        htmlspecialchars($tiempo) .
                    '
                    </td>
                    <td class="center">
                        ' .
                        htmlspecialchars($operadorProceso) .
                    '
                    </td>
                    <td class="center">
                        ' .
                        htmlspecialchars($ayudante) .
                    '
                    </td>

                </tr>

            ';
        }


        $html .= '

                </tbody>

            </table>

        ';

    } else {


        $html .= '
            <div class="sin-procesos">
                Sin procesos registrados
            </div>
        ';
    }



    $html .= '
    </div>
    ';
}


$mpdf->WriteHTML($html);
$filename = 'historial_procesos_' . date('Ymd_His') . '.pdf';
$mpdf->Output($filename, 'I');

exit;
