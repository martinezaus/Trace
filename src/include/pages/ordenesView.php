<?php
	
	require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../productividad.php';
require_once __DIR__ . '/../reportes_service.php';
require_once __DIR__ . '/../negocio_logo.php';

	require_once __DIR__ . '/../../vendor/autoload.php';
	
	use Mpdf\Mpdf;

	//Configuracion
	$mpdf = new Mpdf([
		'mode'            => 'utf-8',
		'format'          => 'A4-P',           // Landscape (vertical)
		'margin_left'     => 15,
		'margin_right'    => 15,
		'margin_top'      => 20,
		'margin_header'   => 8,
		'margin_bottom'   => 20,
		'margin_footer'   => 8,
		'default_font'    => 'arial',
		'default_font_size' => 10,
	]);
$logoNegocio = negocio_logo_html($mpdf);

	// Opcional: Watermark sutil (descomenta si quieres)
	// $mpdf->SetWatermarkImage('ruta/a/tu/logo_o_fondo.png', 0.08, 'P', 'F');
	// $mpdf->showWatermarkImage = true;

	$orden = json_decode($_POST['orden'] ?? '{}', true); 
	$detalles = json_decode($_POST['detalles'] ?? '[]', true); 
	$eventos = json_decode($_POST['eventos'] ?? '[]', true); 
	$lote = json_decode($_POST['lote'] ?? '{}', true);

	$ordenData = $orden[0] ?? [];
    // Los porcentajes y tiempos se leen de la base, nunca del resultado enviado por el navegador.
    $dbProductividad = reportDatabase();
    $consulta = $dbProductividad->prepare('SELECT m.*,op.nombre operador FROM ('.productividadDetallesSql().') m LEFT JOIN operadores op ON op.id=m.operador_id WHERE m.orden_id=? ORDER BY m.id');
    $consulta->execute([(int)($ordenData['id'] ?? 0)]);
    $detalles = array_map('productividadDetalleDatos',$consulta->fetchAll());
    $consulta = $dbProductividad->prepare('SELECT o.*,o.c_estado estado,o.f_alta fecha,o.timestamp_inicio tiempo_inicio FROM ordenes o WHERE id=?');
    $consulta->execute([(int)($ordenData['id'] ?? 0)]);
    $ordenActual=$consulta->fetch();
    if (!$ordenActual) { http_response_code(404); exit('Orden inexistente.'); }
    $ordenData=$ordenActual;
    $estadoReal=$ordenActual['estado'];
    $consulta=$dbProductividad->prepare("SELECT e.id,e.tipo,e.descripcion,e.created fecha,e.tiempo_real tiempo,
        CASE WHEN e.tipo='pausa' AND EXISTS (SELECT 1 FROM pausas pa WHERE pa.descripcion=e.descripcion AND pa.flag=1) THEN 1 ELSE 0 END suma_tiempo
        FROM eventos e JOIN procesos p ON p.id=e.proceso_id JOIN detalles d ON d.id=p.detalle_id
        WHERE d.orden_id=? ORDER BY e.created,e.id");
    $consulta->execute([(int)$ordenData['id']]);
    $eventos=$consulta->fetchAll();
    $consulta=$dbProductividad->prepare('SELECT SUM(piezas) piezas,SUM(items) items,SUM(tipologias) tipologias FROM lotes WHERE orden_id=?');
    $consulta->execute([(int)$ordenData['id']]);
    $lote=[$consulta->fetch()];
    $resumenProductividad = productividadResumen($detalles);
    $promedioItems = $resumenProductividad['porcentaje'];
    $medicionFinal = $resumenProductividad['completo'] && in_array((int)$estadoReal,[2,3],true);
    $textoMedicion = $medicionFinal ? 'Final' : 'Provisional: orden abierta o mediciones incompletas';
	$loteData = $lote[0] ?? [];
	$total_e = 0;
	$total_toe=0;

	//Footer
	$mpdf->SetHTMLFooter('
						<table width="100%" style="font-size:8pt; color:#777;">
							<tr>
								<td width="33%">Generado: ' . date('d/m/Y H:i') . '</td>
								<td width="33%" align="center">{PAGENO} / {nbpg}</td>
								<td width="33%" align="right">General Alvear, Mendoza</td>
							</tr>
						</table>
					');
					
	$html='<style>
				body { font-family: Arial, sans-serif; font-size: 11pt; }
				h1 { text-align:center; color:#1a3c6d; margin:0 0 6px 0; font-size:18pt; }
				.subtitulo { text-align:center; color:#555; margin-bottom:20px; font-size:11pt; }
				table { width:100%; border-collapse:collapse; margin:20px 0; }
				th, td { border:1px solid #bbb; padding:8px 10px; }
				th { background:#1a3c6d; color:white; text-align:center; font-weight:bold; }
				.right { text-align:right; }
				.center { text-align:center; }
				.total { font-weight:bold; background:#f8f9fa; font-size:12pt; }
				tr:nth-child(even) { background:#f9f9f9; }
			</style>';

	$html .= '<table width="100%" style="font-size:9pt; color:#666;" border="0">
			<tr>
				<td width="50%">' . $logoNegocio . '</td>
				<td width="50%">
					<h1 class="text-center">Historial de Orden. '.$ordenData['codigo'].'</h1>
				</td>
			</tr>
		</table>'; 

	$estado = ($ordenData['estado'] == 0) ? "CARGADA" : (($ordenData['estado'] == 1) ? "PROCESANDO" : "FINALIZADA");
	
	$html .= '<table style="width:100%; font-family:Arial, sans-serif; font-size:13px;">
				<tr>
	                <td style="width:55%; padding:15px; vertical-align:top;">
		                <div style="margin-bottom:6px;">
			                <span style="font-weight:bold; color:#555;">Cliente:</span>
			                <span style="font-weight:bold; color:#222;">'.$ordenData['cliente'].'</span>
		                </div>

		                <div style="margin-bottom:6px;">
			                <span style="font-weight:bold; color:#555;">ID:</span>
			                <span>'.htmlspecialchars((string) ($ordenData['id'] ?? '')).'</span>
		                </div>

		                <div style="margin-bottom:6px;">
			                <span style="font-weight:bold; color:#555;">Estado:</span>
			                <span style="font-weight:bold;">'.$estado.'</span>
		                </div>

		                <div style="margin-bottom:6px;">
			                <span style="font-weight:bold; color:#555;">Orden:</span>
			                <span>'.htmlspecialchars($ordenData['codigo']).'</span>
		                </div>

		                <div style="margin-bottom:6px;">
			                <span style="font-weight:bold; color:#555;">Fecha/Hora de carga:</span>
			                <span>'.fechaDDMMAA_DT($ordenData['fecha']).'</span>
		                </div>

		                <div style="margin-bottom:6px;">
			                <span style="font-weight:bold; color:#555;">Inicio del proceso:</span>
			                <span>'.fechaDDMMAA_DT($ordenData['tiempo_inicio']).'</span>
		                </div>

		                <div style="margin-top:10px; padding:8px; background:#f5f6f7; ">
			                <span style="font-weight:bold; color:#555;">Productividad de la orden (desvío):</span>
			                <span style="font-size:16px; font-weight:bold;">'.productividadFormato($promedioItems).' </span><br><span>'.htmlspecialchars($textoMedicion,ENT_QUOTES,'UTF-8').'</span>
		                </div>

	                </td>

					<td style="width:45%; border-left:none; padding:8px;">
						<table border="0" cellpadding="5" cellspacing="0" style="width:100%;">
							<tr style="background:#e8e8e8;">
								<td colspan="2" style="text-align:center; font-size:18px; font-weight:bold;">
									DATOS DE LA ORDEN - TIEMPOS ESTIMADOS
								</td>
							</tr>
							<tr>
								<td style="font-weight:bold; width:48%;">Piezas:</td>
								<td>'.$loteData['piezas'].'</td>
							</tr>
							<tr>
								<td style="font-weight:bold;">Items:</td>
								<td>'.$loteData['items'].'</td>
							</tr>
							<tr>
								<td style="font-weight:bold;">Tipologias:</td>
								<td>'.$loteData['tipologias'].'</td>
							</tr>
							<tr>
								<td style="font-weight:bold;">Tiempo Orden:</td>
								<td>'.$ordenData['tiempo_orden'].'</td>
							</tr>
							<tr>
								<td style="font-weight:bold;">Tiempo Armado:</td>
								<td>'.$ordenData['tiempo_armado'].'</td>
							</tr>                   
							<tr>
								<td style="font-weight:bold;">Tiempo Vidrio:</td>
								<td>'.$ordenData['tiempo_vidrio'].'</td>
							</tr>   
			                <tr>
				                <td style="padding:5px 8px;">
					                <strong>Corte perfil / vidrio</strong>
				                </td>
				                <td style="padding:5px 8px; text-align:right; ">'.($ordenData['tiempo_corte'] ?? '00:00:00').'</td>
							</tr>							                                                     
						</table>
					</td>
				</tr>
			</table>';
	$html .= '<p style="page-break-inside:avoid; font-size:9pt; color:#555;">' . htmlspecialchars(productividadLeyenda(), ENT_QUOTES, 'UTF-8') . '</p>';
$html .= '<div class="subtitulo">Items </div>';        
	$total_d = 0;
	$total_estimado = 0;
	$total_real = 0;
	$html .= '<table>
			<thead>
				<tr>
					<th>#</th>
					<th>Operador</th>
					<th class="center">Tipologia</th>
					<th class="center">Tiempo(Estimado)</th>
					<th class="center">Tiempo(Real)</th>
					<th class="center">Productividad</th>
				</tr>
			</thead>
			<tbody>';

			foreach ($detalles as $itemd) {
				$id              = $itemd['id'] ?? 0;
				$operador        = $itemd['operador'] ?? '—';
				$tipologia       = $itemd['tipologia'] ?? '';
				//$descripcion   = $itemd['descripcion'] ?? '';
				$tiempo_estimado = $itemd['tiempo_estimado'] ?? '';
				$tiempo_real     = $itemd['tiempo_real'] ?? '';
				$productividad   = productividadItem($itemd);
                $productividadTexto = productividadFormato($productividad);
				
				$total_e      += $productividad;
				$total_estimado += productividadItem($itemd)!==null ? timeToSeconds($tiempo_estimado) : 0;
				$total_real += productividadItem($itemd)!==null ? timeToSeconds($tiempo_real) : 0;
				$total_d      += 1;

				$html .= '
					<tr>
						<td class="center">' . $id . '</td>
						<td>' . htmlspecialchars($operador) . '</td>
						<td class="left">' . htmlspecialchars($tipologia) . '</td>
						<td class="center">' . htmlspecialchars($tiempo_estimado) . '</td>
						<td class="center">' . htmlspecialchars($tiempo_real) . '</td>
						<td class="right">' . $productividadTexto . '</td>
					</tr>';
			}
	$pdio = $promedioItems;   //Promedio general  
	$html .= '
				</tbody>
				<tfoot>
					<tr>
						<td colspan="2" class="right total">Total comparable:</td>
						<td class="right total">' . number_format($total_d, 0, ',', '.') . '</td>
						<td class="right total">' . segundosToTime($total_estimado) . '</td>
						<td class="right total">' . segundosToTime($total_real) . '</td>

						<td class="right total">' . productividadFormato($pdio).'</td>

					</tr>
				</tfoot>
			</table>';

	// El título forma parte del encabezado: acompaña y se repite con la tabla.   
	$total_e = 0;
	$total_toe=0;
	$html .= '<table>
				<thead>
					<tr><th colspan="5" style="background:#fff; color:#555; font-size:11pt; padding:10px;">Eventos producidos durante la producción</th></tr>
					<tr>
						<th>#</th>
						<th class="center">Tipo</th>
						<th class="center">Descripcion</th>
						<th class="center">Fecha</th>
						<th class="center">Tiempo</th>
					</tr>
				</thead>
				<tbody>';
				    if (empty($eventos)) {
						$html .= '<tr>
									<td colspan="5" class="center">No hay eventos registrados</td>
								</tr>';
					} else {
						foreach ($eventos as $iteme) {   
							$total_toe += timeToSeconds($iteme['tiempo'] ?? '');                
							$total_e      += 1;
                            $tipo = trim((string) ($iteme['tipo'] ?? ''));
                            $resaltarPausa = $tipo === 'pausa' && in_array($iteme['suma_tiempo'] ?? false, [true, 1, '1'], true);
                            $estiloEvento = $resaltarPausa ? ' style="background-color:#FFE0B2; color:#7A3500;"' : '';
                            $tipo = ['pausa' => 'Pausa', 'suspension' => 'Suspensión', 'cierre_jornada' => 'Cierre de jornada', 'reanudacion' => 'Reanudación', 'finalizacion' => 'Finalización'][$tipo] ?? ($tipo !== '' ? ucfirst(str_replace('_', ' ', $tipo)) : 'Sin especificar');
							$html .= '<tr' . $estiloEvento . '>
										<td class="center"' . $estiloEvento . '>' . ( $iteme['id'] ?? 0) . '</td>
										<td class="center"' . $estiloEvento . '>' . htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8') . '</td>
										<td class="left"' . $estiloEvento . '>' . htmlspecialchars($iteme['descripcion'] ?? '—') . '</td>
										<td class="center"' . $estiloEvento . '>' . (fechaDDMMAA_DT($iteme['fecha']) ?? '') . '</td>
										<td class="center"' . $estiloEvento . '>' . ($iteme['tiempo'] ?? '') . '</td>
									</tr>';
						}
					}      
	$html .= '
				</tbody>
				<tfoot>
					<tr>
						<td colspan="2" class="">Total(Registros):</td>
						<td class="right total">'. number_format($total_e, 0, ',', '.') . '</td>
						<td class="">Horas:</td>
						<td class="right total">'.segundosToTime($total_toe).'</td>

					</tr>
				</tfoot>
			</table>';

	
$eventosComparables=0;
foreach($detalles as $detalleResumen) {
    if(productividadItem($detalleResumen)!==null) $eventosComparables+=(int)$detalleResumen['eventos_segundos'];
}
$resultadoProductividad=$promedioItems===null ? 'Sin datos suficientes' : ($promedioItems<0 ? 'Ahorro de tiempo' : ($promedioItems>0 ? 'Exceso de tiempo' : 'Cumple el estimado'));
$html.='<div style="page-break-inside:avoid"><h2>Resultado de productividad de la orden</h2><table>';
foreach([
    'Medición'=>$textoMedicion,
    'Cobertura'=>$resumenProductividad['medibles'].' de '.$resumenProductividad['total'].' ítems',
    'Registros sin regla o duración (impiden medición completa)'=>array_sum(array_column($detalles,'medicion_incompleta')),
    'Estimado comparable'=>productividadDuracion($resumenProductividad['estimado']),
    'Tiempo registrado comparable (incluye retrabajos y pausas de corte)'=>productividadDuracion($resumenProductividad['real']-$eventosComparables),
    'Pausas computables agregadas (flag=1, fuera de corte)'=>productividadDuracion($eventosComparables),
    'Real computable comparable'=>productividadDuracion($resumenProductividad['real']),
    'Ahorro de tiempo (negativo indica exceso)'=>productividadDuracion($resumenProductividad['ahorro']),
    'Productividad (desvío)'=>productividadFormato($promedioItems),
    'Resultado'=>$resultadoProductividad,
] as $etiqueta=>$valor) $html.='<tr><td>'.htmlspecialchars($etiqueta,ENT_QUOTES,'UTF-8').'</td><td>'.htmlspecialchars($resumenProductividad['medibles']===0 && in_array($etiqueta,['Estimado comparable','Tiempo registrado comparable (incluye retrabajos y pausas de corte)','Pausas computables agregadas (flag=1, fuera de corte)','Real computable comparable','Ahorro de tiempo (negativo indica exceso)'],true) ? 'Sin datos' : (string)$valor,ENT_QUOTES,'UTF-8').'</td></tr>';
$html.='</table>';
$fechasEconomicas=array_filter(array_column($detalles,'fecha_medicion'));
$fechaEconomica=$medicionFinal && $fechasEconomicas ? substr(max($fechasEconomicas),0,10) : date('Y-m-d');
$configEconomica=productividadConfigLeer($dbProductividad,$fechaEconomica);
$economia=productividadEconomia($resumenProductividad['estimado'],$resumenProductividad['real'],$configEconomica);
$html.='<h3>Estimación del costo de producción</h3><p>'.htmlspecialchars($economia['config_costos'],ENT_QUOTES,'UTF-8').'. Tarifa considerada al '.htmlspecialchars($fechaEconomica,ENT_QUOTES,'UTF-8').'.</p>';
if($economia['costo_real']!==null) {
    $html.='<table>';
    foreach(['Costo por hora'=>((int)$configEconomica['tarifa_centavos'])/100,'Costo estimado comparable'=>$economia['costo_estimado'],'Costo real comparable'=>$economia['costo_real'],'Ahorro de costo estimado'=>$economia['ahorro_costo']] as $label=>$money) {
        $html.='<tr><td>'.htmlspecialchars($label,ENT_QUOTES,'UTF-8').'</td><td>'.htmlspecialchars($economia['moneda'].' '.number_format((float)$money,2,',','.'),ENT_QUOTES,'UTF-8').'</td></tr>';
    }
    $html.='</table>';
} else $html.='<p>Sin estimación económica: falta una tarifa activa o una medición comparable.</p>';
$html.='<p>El ahorro económico valora el tiempo comparable con la misma tarifa por hora. No representa el beneficio total de venta ni descuenta adicionales pagados. El total de eventos de la tabla anterior es informativo: no se suma nuevamente.</p></div>';

$mpdf->WriteHTML($html);
	// Descarga directa (o guarda en servidor si prefieres)
	$filename = 'historial_Orden_' . ($ordenData['codigo'] ?? 'sin_codigo') . '_' . date('Ymd_His') . '.pdf';
	$mpdf->Output($filename, 'I');  // 'D' = descarga   |  'I' = ver en browser   |  'F' = guardar en disco
	exit;
?>