<?php
	/*
		script para validar que los PDF subidos (OP / PLA perfil / PLA vidrio)
		correspondan al tipo declarado y al pedido correcto, antes de dar
		de alta la orden.
		Se llama DESPUES de fileV1.0.1.php (que ya movió los archivos a /tmp)
		y ANTES de api/ordenes.php (accion=add).

		fecha
		Pablo Martinez
	*/
	header('Content-Type: application/json');
	error_reporting(E_ALL & ~E_WARNING);
	ini_set('display_errors', 1);

	require_once "../../include/log.php";

	// -------------------------------------------------------
	// Helpers
	// -------------------------------------------------------

	// Extrae el texto de la primera página de un PDF usando pdftotext (poppler-utils).
	// Devuelve null si la herramienta no está disponible o no se pudo leer el archivo,
	// para no romper la carga en un servidor que no la tenga instalada.
	function extraerTextoPrimeraPaginaPDF($ruta){
		$texto = @shell_exec('pdftotext -f 1 -l 1 ' . escapeshellarg($ruta) . ' - 2>/dev/null');
		return is_string($texto) ? $texto : null;
	}

	// Determina qué tipo de documento es un PDF a partir de frases que el software
	// de FerroAlum siempre imprime en la primera página de cada reporte.
	// Devuelve 1=Orden de Trabajo (OP), 2=Optimización de Perfil (PLA),
	// 3=Optimización de Vidrios (PLA), o null si no se pudo determinar.
	function detectarTipoPDF($textoPlano){
		$texto = mb_strtoupper($textoPlano, 'UTF-8');
		if (strpos($texto, 'DIAGRAMAS DE CORTE') !== false) {
			return 2;
		}
		if (strpos($texto, 'PIEZAS UBICADAS') !== false && strpos($texto, 'DESPERDICIO') !== false) {
			return 3;
		}
		if (strpos($texto, 'TIPOLOGIA') !== false && strpos($texto, 'CANTIDAD') !== false && strpos($texto, 'DETALLE') !== false) {
			return 1;
		}
		return null;
	}


    // La OP puede imprimir el nombre del proyecto en lugar de NRO_PTO.
    // Leer la referencia del DAT subido, comprobando primero su presupuesto.
    function referenciaProyectoDat($nombreDat, $numeroPedido) {
        if (!is_string($nombreDat) || $numeroPedido === '') return null;
        $nombreDat = basename(str_replace('\\', '/', $nombreDat));
        if (strtolower(pathinfo($nombreDat, PATHINFO_EXTENSION)) !== 'dat') return null;
        $lineas = @file(__DIR__ . '/../../tmp/' . $nombreDat, FILE_IGNORE_NEW_LINES);
        if ($lineas === false) return null;
        $numeroDat = null;
        $referencia = null;
        foreach ($lineas as $linea) {
            if (preg_match('/^\s*\[NRO_PTO\]\s*=\s*(.*?)\s*$/', $linea, $m)) $numeroDat = $m[1];
            if (preg_match('/^\s*\[ARCHIVO\]\s*=\s*(.*?)\s*$/', $linea, $m)) {
                $referencia = basename(str_replace('\\', '/', $m[1]));
            }
        }
        if ($numeroDat !== $numeroPedido || !$referencia) return null;
        if (!mb_check_encoding($referencia, 'UTF-8')) {
            $referencia = mb_convert_encoding($referencia, 'UTF-8', 'Windows-1252');
        }
        return $referencia;
    }

    function pdfCorrespondePedido($texto, $numeroPedido, $referenciaProyecto = null) {
        if ($numeroPedido === '') return true;
        if (preg_match('/(?<!\d)' . preg_quote($numeroPedido, '/') . '(?!\d)/u', $texto)) return true;
        if (!$referenciaProyecto) return false;
        // Referencia completa de proyecto, no solamente cliente o nombre del PDF.
        $normalizar = function ($valor) {
            return mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $valor)), 'UTF-8');
        };
        $referencia = preg_quote($normalizar($referenciaProyecto), '/');
        return preg_match('/(?:^|[\s(])' . $referencia . '(?=$|[\s:)])/u', $normalizar($texto)) === 1;
    }

	$etiquetasTipoPlano = [
		1 => 'Orden de Trabajo (OP)',
		2 => 'Optimización de Perfil (PLA)',
		3 => 'Optimización de Vidrios (PLA)',
		4 => 'Etiquetas de vidrio'
	];

	// -------------------------------------------------------
	// Entrada
	// -------------------------------------------------------
	// numero: Nº de pedido (cabecera.numero devuelto por fileV1.0.1.php)
	// documentos: JSON con [{nombre, descripcion, tipo}, ...] (el mismo array
	//             que ya devolvió fileV1.0.1.php en orden[3])

	$numeroPedido   = trim($_POST['numero'] ?? '');
	$referenciaProyecto = referenciaProyectoDat($_POST['dat'] ?? '', $numeroPedido);
	$documentosJson = $_POST['documentos'] ?? '[]';
	$documentos     = json_decode($documentosJson, true);

	if (!is_array($documentos)) {
		registrarLog('ERROR', 'FILE[validarPlanos]', "JSON de documentos inválido: " . $documentosJson);
		echo json_encode(['error' => 'No se recibieron documentos válidos para validar.']);
		exit;
	}

	// -------------------------------------------------------
	// Validación de cada plano
	// -------------------------------------------------------
	foreach ($documentos as $doc) {
		$nombre        = $doc['nombre'] ?? '';
		$tipoDeclarado = (int)($doc['tipo'] ?? 0);
		$ext           = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));

		if ($nombre === '' || $ext !== 'pdf') {
			continue; // solo validamos PDFs
		}

		$ruta = '../../tmp/' . $nombre;
		if (!file_exists($ruta)) {
			registrarLog('ERROR', 'FILE[validarPlanos]', "No se encontró el plano para validar: $nombre");
			echo json_encode(['error' => "No se encontró el archivo \"$nombre\" para validar."]);
			exit;
		}

		$textoPdf = extraerTextoPrimeraPaginaPDF($ruta);
		if ($textoPdf === null || trim($textoPdf) === '') {
			// pdftotext no disponible o PDF escaneado/imagen: no bloqueamos, seguimos con el resto.
			continue;
		}

		$tipoDetectado = detectarTipoPDF($textoPdf);

		if ($tipoDetectado !== null && $tipoDeclarado !== 0 && $tipoDetectado !== $tipoDeclarado) {
			$mensaje = "El archivo \"$nombre\" parece ser de tipo \"{$etiquetasTipoPlano[$tipoDetectado]}\", "
					 . "pero se subió en el campo de \"{$etiquetasTipoPlano[$tipoDeclarado]}\".";
			registrarLog('ERROR', 'FILE[validarPlanos]', "Tipo de PDF no coincide: $mensaje");
			echo json_encode(['error' => $mensaje]);
			exit;
		}

		if (!pdfCorrespondePedido($textoPdf, $numeroPedido, $referenciaProyecto)) {
			$mensaje = "El archivo \"$nombre\" no parece corresponder al pedido Nº $numeroPedido. "
					 . "Revisá que sea el PDF correcto antes de continuar.";
			registrarLog('ERROR', 'FILE[validarPlanos]', "Pedido no coincide en PDF: $mensaje");
			echo json_encode(['error' => $mensaje]);
			exit;
		}
	}

	echo json_encode(['resultado' => 'OK']);
	exit;
?>