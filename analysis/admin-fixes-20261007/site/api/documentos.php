<?php
	header('Content-Type: application/json; charset=utf-8');

	require_once "../vendor/autoload.php";
    require_once "../include/auth.php";
	require_once "../include/config.php";    
	require_once 'rb-mysql.php';
	require_once "../include/functions.php";
	require_once "../include/log.php";
	require_once "../include/parse_pdf.php";
    require_once "../include/parse_pdfvidrio.php";
	
	use setasign\Fpdi\Fpdi;

	// Verifica si RedBeanPHP está funcionando
	try{
		R::setup('mysql:host='.hostBD.';dbname='.nombreBD, usuarioBD, claveBD);
		R::freeze(false); // Permitir que RedBean actualice el esquema
		//R::nuke(); // OJO: Borra todas las tablas, solo en desarrollo
	}catch(Exception $e){
		registrarLog('ERROR','CONECTION[Detalles]',"No se pudo conectar a la base de datos. ".$e->getMessage());
		echo die(json_encode(["error" => "No se pudo conectar a la base de datos"]));
	}

	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$response = [];
		if($_POST['accion']==='lista'){
			$documento = R::getRow("SELECT id, nombre, archivo_final, tipo FROM documentos WHERE id = ?", [(int)($_POST['documento_id'] ?? 0)]);
			$items = R::getAll("SELECT id, 
									   perfil,
									   descripcion,
									   barra,
									   tilde,
									   observacion
								FROM documentositems 
								WHERE documento_id = ? 
								ORDER BY documentositems.id", [$documento['id']]);
			$response[] = ['id'         => $documento['id'],
						   'titulo'     => $documento['nombre'] ?? null,
						   'titulo_mod' => $documento['archivo_final'] ?? null,
                           'mod_disponible' => !empty($documento['archivo_final']) && is_file(dirname(__DIR__).'/tmp/modificados/'.basename($documento['archivo_final'])),
                           'tipo'       => (int)($documento['tipo'] ?? 0),
						   'items'      => $items,           
						];            
			echo json_encode($response);
		}elseif($_POST['accion']==='add'){
			// ✅ 1. Desempaquetar $_POST['datos']
            $datos = json_decode($_POST['datos'] ?? '[]', true);

            if (!is_array($datos) || empty($datos)) {
                echo json_encode(['error' => 'No se recibieron documentos para guardar']);
                exit;
            }
            $guardados = [];
            try {
                foreach ($datos as $doc) {
                    $documento = R::dispense('documentos');
                    $documento->orden_id    = (int)($doc['orden_id'] ?? 0);
                    $documento->nombre      = $doc['nombre'] ?? '';
                    $documento->descripcion = $doc['descripcion'] ?? '';
                    $documento->tipo        = $doc['tipo'] ?? '';
        
                    $id = R::store($documento);
                    $guardados[] = ['id'          => $id,
                                    'nombre'      => $documento->nombre,
                                    'tipo'        => $documento->tipo,
                                ];
                }
        
                registrarLog('INFO', 'DOCUMENTOS', 'Linea 62, Guardados ' . count($guardados) . ' documentos: ' . json_encode($guardados));
                echo json_encode(['ok' => true, 'documentos' => $guardados]);
        
            } catch (Throwable $e) {
                registrarLog('ERROR', 'DOCUMENTOS', 'Linea 66, '.$e->getMessage());
                echo json_encode(['ok' => false, 'error' => 'No se pudieron guardar los documentos']);
            }
     
		}elseif($_POST['accion']==='update'){
			// ✅ 1. Desempaquetar $_POST['datos']
			$datos  = $_POST['datos'] ?? [];
            
			$itemId = (int)($datos['item_id'] ?? 0);
			$docId  = (int)($datos['doc_id']  ?? 0);
			$escala = (float)($datos['escala'] ?? 1.5);
			$tamanio = (int)($datos['tamanio'] ?? 12);

			// registrarLog('INFO', 'FONT_DEBUG', "linea 48, tamaño =" . $tamanio . " tipo=" . gettype($tamanio)." | escala: ". $escala);


			if (!$itemId) {
				http_response_code(400);
				echo json_encode(['ok' => false, 'error' => 'item_id requerido']);
				exit;
			}

            requerirOperadorApi();
            R::freeze(true);
            R::begin();
            register_shutdown_function(static function () { if (R::getDatabaseAdapter()->getDatabase()->getPDO()->inTransaction()) R::rollback(); });
            // Bloquear tarea antes del documento, igual que la API de corte.
            $cortePdf = R::getRow('SELECT * FROM cortepdf WHERE documento_id = ? FOR UPDATE', [$docId]);
            $documentoBloqueado = R::getRow('SELECT id FROM documentos WHERE id = ? FOR UPDATE', [$docId]);
            if (!$documentoBloqueado || !R::getCell('SELECT id FROM documentositems WHERE id = ? AND documento_id = ?', [$itemId, $docId])) {
                R::rollback(); echo json_encode(['ok'=>false,'error'=>'El ítem no pertenece al documento.']); exit;
            }
            if ($cortePdf && ($cortePdf['estado'] !== 'activa' || (int)$cortePdf['operador_id'] !== operadorId() || !hash_equals($cortePdf['token'], (string)($datos['corte_token'] ?? '')))) {
                R::rollback(); echo json_encode(['ok'=>false,'error'=>'El PDF no tiene una tarea activa en esta sesión.']); exit;
            }
            if (!$cortePdf && isset($datos['corte_token'])) {
                R::rollback(); echo json_encode(['ok'=>false,'error'=>'Inicie la tarea del PDF antes de marcar.']); exit;
            }
            $completo = R::getCell('SELECT COUNT(*)>0 AND SUM(CASE WHEN COALESCE(tilde,0)<>1 THEN 1 ELSE 0 END)=0 FROM documentositems WHERE documento_id=?',[$docId]);
            if ($completo) { R::rollback(); echo json_encode(['ok'=>false,'error'=>'PDF completo: solo consulta. No se permiten cambios.']); exit; }
			$campos  = [];
			$valores = [];

			if (isset($datos['tilde'])) {
				$campos[]  = 'tilde = ?';
				$valores[] = (int)$datos['tilde'] ? 1 : 0;
			}
			if (isset($datos['observacion'])) {
				$campos[]  = 'observacion = ?';
				$valores[] = trim($datos['observacion']);
			}
			if (empty($campos)) {
				echo json_encode(['ok' => false, 'error' => 'Nada para actualizar']);
				exit;
			}

			$campos[]  = 'usuario = ?';
			$valores[] = operadorId();
			$campos[]  = '`update` = NOW()';  // ✅ 3. Escapar palabra reservada
			$valores[] = $itemId;
            $valores[] = $docId;

			$sql = "UPDATE documentositems SET " . implode(', ', $campos) . " WHERE id = ? AND documento_id = ?";
			$ok  = R::exec($sql, $valores);

			registrarLog('INFO', 'DOCUMENTOS', 'Actualizar item '.$itemId.' del PDF '.$docId);
			// ✅ 2. Buscar items por documento_id correcto
			$documento = R::load('documentos', $docId);
			$rutaOriginal = __DIR__ . '/../tmp/' . $documento->nombre;
			$rutaOriginal = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . $documento->nombre;
			registrarLog('INFO', 'DOC_DEBUG', "linea 80, $rutaOriginal - docId=$docId | nombre=" . ($documento->nombre ?? 'NULL') . " | id=" . ($documento->id ?? 'NULL'));
			
			if ($rutaOriginal === false || !file_exists($rutaOriginal)) {
				registrarLog('ERROR', 'PDF_FINAL', "linea 91, PDF original no encontrado: " . $documento->nombre);
				echo json_encode(['ok' => false, 'error' => 'PDF original no encontrado']);  // ✅ 4.
				exit;
			}

			$items = R::find('documentositems', 'documento_id = ? ORDER BY id', [$docId]); // ✅ 2.
			if (count($items) === 0) {
				registrarLog('ERROR', 'PDF_FINAL', "linea 99,Documento " . $docId . " no tiene items");
				echo json_encode(['ok' => false, 'error' => 'El documento no tiene items']);  // ✅ 4.
				exit;
			}

			// 4. Agrupar items por página, para procesar el PDF página por página
			$itemsPorPagina = [];
			foreach ($items as $it) {
				$pag = (int)$it->pagina ?: 1;
				$itemsPorPagina[$pag][] = $it;
			}

            $rutaCompatible = tempnam(sys_get_temp_dir(), 'trace-corte-');
            register_shutdown_function(static function () use ($rutaCompatible) { if (is_file($rutaCompatible)) @unlink($rutaCompatible); });
            $cmd = escapeshellarg(GS_PATH) . ' -dBATCH -dNOPAUSE -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 '
                . escapeshellarg('-sOutputFile='.$rutaCompatible) . ' ' . escapeshellarg($rutaOriginal) . ' 2>&1';
            exec($cmd, $output, $retCode);
            if ($retCode !== 0 || !is_file($rutaCompatible) || filesize($rutaCompatible) === 0) {
                R::rollback();
                registrarLog('ERROR','PDF_FINAL','Conversión fallida: '.implode(' ', $output));
                echo json_encode(['ok'=>false,'error'=>'No se pudo convertir el PDF. El tilde no se guardó.']); exit;
            }

			// Usar el PDF convertido
			$pdfContent = file_get_contents($rutaCompatible);

			// 5. Generar el PDF con FPDI
			try {
				if ($pdfContent === false) {
					registrarLog('ERROR', 'PDF_FINAL', "linea 124, No se pudo leer el archivo: " . $rutaOriginal);
					echo json_encode(['ok' => false, 'error' => 'No se pudo leer el PDF original']);
					exit;
				}
			
				$stream = fopen('php://memory', 'r+');
				fwrite($stream, $pdfContent);
				rewind($stream);
			
				$pdf          = new Fpdi();
				$totalPaginas = $pdf->setSourceFile($stream);
                foreach ($items as $it) {
                    if ((int)$it->id === $itemId && (int)$it->tilde === 1 && (int)$it->pagina > $totalPaginas) throw new RuntimeException('La pagina del item no existe en el PDF. El tilde no se guardo.');
                } // ✅ usa el stream, no la ruta
			
				for ($pagNum = 1; $pagNum <= $totalPaginas; $pagNum++) {
					$tplId = $pdf->importPage($pagNum);
					$size  = $pdf->getTemplateSize($tplId);
			
					$anchoPagina = $size['width']  ?? $size['w'];
					$altoPagina  = $size['height'] ?? $size['h'];
					$orientacion = ($anchoPagina > $altoPagina) ? 'L' : 'P';
			
					$pdf->AddPage($orientacion, [$anchoPagina, $altoPagina]);
					$pdf->useTemplate($tplId);
			
					if (isset($itemsPorPagina[$pagNum])) {
						foreach ($itemsPorPagina[$pagNum] as $it) {
							if ((int)$it->tilde !== 1) continue;

                            // Base y altura son dimensiones de la pieza, nunca coordenadas.
                            $validPosition = $it->pos_x !== null && $it->pos_y !== null && (float)$it->pos_x > 0 && (float)$it->pos_y > 0;
                            $xMm = (float)$it->pos_x * 25.4 / 72;
                            $yMm = (float)$it->pos_y * 25.4 / 72;
                            if (!$validPosition || $xMm >= $anchoPagina || $yMm >= $altoPagina) {
                                if ((int)$it->id === $itemId) throw new RuntimeException('No se pudo ubicar este item en el PDF. Revise o vuelva a importar el documento; el tilde no se guardo.');
                                continue;
                            }

							$xTilde = max(0, $xMm - 5);
			
							$w = 4.0 * $escala;   // ancho total del tilde
							$h = 4.0 * $escala;   // alto total del tilde							

							$pdf->SetDrawColor(0, 140, 0);
							$pdf->SetLineWidth(0.6 * $escala);
							$pdf->Line($xTilde, $yMm + ($h * 0.5), $xTilde + ($w * 0.35), $yMm + $h);
							$pdf->Line($xTilde + ($w * 0.35), $yMm + $h, $xTilde + $w, $yMm);

							// Observacion
							if (!empty(trim($it->observacion))) {
								$pdf->SetFont('Helvetica', '', $tamanio);
								$pdf->SetTextColor(0, 100, 0);  // verde oscuro
								$xObs = $xTilde + $w + 2;      // 2mm a la derecha del tilde
								$yObs = $yMm;                   // misma altura
								$pdf->SetXY($xObs, $yObs);
								$pdf->Cell(50, 4, $it->observacion, 0, 0, 'L');
							}
						}
					}
				}
			
				$dirDestino = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'modificados';
				if (!is_dir($dirDestino)) mkdir($dirDestino, 0775, true);
			
				$info        = pathinfo($documento->nombre);
				$nombreFinal = $info['filename'] . '_1.' . $info['extension'];
				$rutaFinal   = $dirDestino . DIRECTORY_SEPARATOR . $nombreFinal; // ✅ separador Windows
				$rutaTemporal = tempnam($dirDestino, 'corte-');
                register_shutdown_function(static function () use ($rutaTemporal) { if (is_file($rutaTemporal)) @unlink($rutaTemporal); });
                $pdf->Output('F', $rutaTemporal);
				fclose($stream); // ✅ cerrar el stream al terminar

				
				// Contar pendientes DESPUÉS de generar el PDF
				$pendientes = 0;
				foreach ($items as $it) {
					if ((int)$it->tilde !== 1) $pendientes++;
				}


				$documento->archivo_final = $nombreFinal;
				$documento->update        = date('Y-m-d H:i:s');
				R::store($documento);
                if (!rename($rutaTemporal, $rutaFinal)) throw new RuntimeException('No se pudo publicar el PDF.');
                require_once '../include/corte_pdf_service.php';
                $servicioCorte = new CortePdfService(R::getDatabaseAdapter()->getDatabase()->getPDO());
                $tareaFinal = $servicioCorte->completeMarked($docId);
                R::commit();
			
				registrarLog('INFO', 'PDF_FINAL', "linea 206, PDF generado: " . $rutaFinal . " | pendientes: $pendientes");
				echo json_encode(['ok' => true, 'completo' => ($pendientes === 0), 'archivo' => $nombreFinal, 'tarea' => $tareaFinal]);
			
			} catch (Throwable $e) {
                R::rollback();
				registrarLog('ERROR', 'PDF_FINAL', "linea " . $e->getLine() . ": " . $e->getMessage());
				echo json_encode(['ok' => false, 'error' => $e->getMessage()]); // ✅ echo, no return
				exit;
			}
		}elseif($_POST['accion']==='archive'){
			$documentos = R::find('documentos','orden_id = ? ', [$_POST['id']]);
			foreach ($documentos as $documento) {
				$nombreModificado = basename((string)($documento->archivo_final ?? ''));
				$rutaModificada = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'modificados' . DIRECTORY_SEPARATOR . $nombreModificado;
				$response[] = ['id'             => $documento->id,
							   'titulo'         => $documento->nombre ?? null,
							   'titulo_mod'     => $nombreModificado !== '' ? $nombreModificado : null,
							   'mod_disponible' => $nombreModificado !== '' && is_file($rutaModificada),
							   'descripcion'    => $documento->descripcion,
							   'tipo'           => $documento->tipo
							];

			}
			echo json_encode($response);
		}elseif($_POST['accion']==='items_add'){
            $documentos= json_decode($_POST['datos'] ?? '[]', true);

            registrarLog('INFO','DOCUMENTOS',"linea 270, Archivo encontrado. [" . $documentos);


            if (is_array($documentos) && count($documentos) > 0) {
			    foreach ($documentos as $doc) {
                    if(!in_array((int)$doc['tipo'], [2, 3], true)) continue; //solo pueden entrar pla.
				    $ruta = "../tmp/".$doc['nombre'];
				    registrarLog('INFO','DOCUMENTOS',"linea 273, Archivo encontrado. [" . $doc['nombre'] . ", ID: ".$doc['id'].", ruta: ".$ruta."]. Se procede a cargar documentos.");
				    if (!file_exists($ruta)) {
					    registrarLog('ERROR','DOCUMENTOS',"linea 275,Archivo no encontrado. [" . $doc['nombre'] . ", ID: ".$doc['id'].", ruta: ".$ruta."]");
                        continue; 
                    }

                    // Elegir parser según tipo de documento
                    if ((int)$doc['tipo'] === 3) {
                        $items = parsearPdfVidrio($ruta);
                    } else {
                        $items = parsearPdf($ruta);  // tipo 2: PLA aluminio
                   }
				   
                   registrarLog('INFO','DOCUMENTOS',"linea 286, Items parseados: ".count($items));
				   foreach ($items as $row => $item) {

					    //registrarLog('INFO','DOCUMENTOS', $documento_id." Item: [".$item['perfil']." ".$item['descripcion']." ".$item['barra_num']."]");

					    $documento_item = R::dispense('documentositems');
					    $documento_item->documento_id = $doc['id'];
					    $documento_item->perfil       = $item['perfil'];
					    $documento_item->descripcion  = $item['descripcion'];
					    $documento_item->barra        = $item['barra_num'];
					    $documento_item->observacion  = $item['observacion'] ?? '';
					    $documento_item->pagina       = $item['pagina']      ?? '';
					    $documento_item->pos_x        = $item['pos_x']       ?? '';
					    $documento_item->pos_y        = $item['pos_y']       ?? '';
                        $documento_item->cantidad     = $item['cant']        ?? 0;
                        $documento_item->base         = $item['base']        ?? 0;
                        $documento_item->altura       = $item['altura']      ?? 0;
					    $documento_item->usuario      = 0;

					    try {
					        R::store($documento_item);
						    registrarLog('INFO','DOCUMENTOS_ITEMS',"linea 307, GUARDADO. Try/catch. Item ".$row." perfil ".$item['perfil']." barra ".$item['barra_num']);
					    } catch (Throwable $e) {
						    registrarLog('ERROR','DOCUMENTOS_ITEMS',"linea 309, Error item ".$row.": ".$e->getMessage());
						    echo json_encode(['error' => 'Error al guardar item de documento: ' . $e->getMessage()]);
						    exit;
					    }
				    }
			    }
            }
            echo json_encode(['resultado' => 'OK']);


        }elseif($_POST['accion']==='replace'){
            $docId = (int)($_POST['id'] ?? 0);
            $nuevoNombre = $_POST['nombre'] ?? '';
            $tipo = (int)($_POST['tipo'] ?? 0);
        
            if (!$docId || !$nuevoNombre) {
                echo json_encode(['error' => 'Faltan datos para reemplazar el documento']);
                exit;
            }
        
            R::freeze(true);
            R::begin();
            register_shutdown_function(static function () { if (R::getDatabaseAdapter()->getDatabase()->getPDO()->inTransaction()) R::rollback(); });
            $tareaPdf = R::getRow('SELECT documento_id FROM cortepdf WHERE documento_id = ? FOR UPDATE', [$docId]);
            R::getRow('SELECT id FROM documentos WHERE id = ? FOR UPDATE', [$docId]);
            $pdfCompleto = R::getCell('SELECT COUNT(*)>0 AND SUM(CASE WHEN COALESCE(tilde,0)<>1 THEN 1 ELSE 0 END)=0 FROM documentositems WHERE documento_id=?',[$docId]);
            if ($tareaPdf || $pdfCompleto) {
                R::rollback();
                echo json_encode(['ok'=>false,'error'=>'Este PDF tiene trabajo registrado. Cargue la nueva version como otro documento para conservar su historial.']); exit;
            }
            $documento = R::load('documentos', $docId);
            if (!$documento->id) {
                echo json_encode(['error' => 'Documento no encontrado']);
                exit;
            }
        
            $nombreAnterior = $documento->nombre; // se guarda antes de pisarlo
            if ($nombreAnterior && $nombreAnterior !== $nuevoNombre) {
                $rutaAnterior = dirname(__DIR__) . '/tmp/' . $nombreAnterior;
                if (file_exists($rutaAnterior)) {
                    @unlink($rutaAnterior);
                }
            }
            // (opcional) borrar también el "modificado" viejo si existía
            if (!empty($documento->archivo_final)) {
                $rutaMod = dirname(__DIR__) . '/tmp/modificados/' . $documento->archivo_final;
                if (file_exists($rutaMod)) @unlink($rutaMod);
            }
        
            $documento->nombre        = $nuevoNombre;
            $documento->archivo_final = null; // ya no es válido el "modificado" anterior
            R::store($documento);
        
            $itemsRegenerados = false;
        
            // Solo tipo 2 (PLA aluminio) o 3 (vidrio) generan items
            if ($tipo === 2 || $tipo === 3) {
                $ruta = dirname(__DIR__) . '/tmp/' . $nuevoNombre;
                if (!file_exists($ruta)) {
                    registrarLog('ERROR','DOCUMENTOS',"replace: archivo no encontrado tras subir: $ruta");
                } else {
                    $items = ($tipo === 3) ? parsearPdfVidrio($ruta) : parsearPdf($ruta);

                    if (empty($items)) {
                        registrarLog('ERROR','DOCUMENTOS',"replace: el parseo de $ruta no devolvió items, se conservan los items anteriores");
                    } else {
                        // Recién acá, con los items nuevos ya listos, se borran los viejos.
                        R::exec('DELETE FROM documentositems WHERE documento_id = ?', [$docId]);

                        foreach ($items as $item) {
                            $documento_item = R::dispense('documentositems');
                            $documento_item->documento_id = $docId;
                            $documento_item->perfil       = $item['perfil'];
                            $documento_item->descripcion  = $item['descripcion'];
                            $documento_item->barra        = $item['barra_num'];
                            $documento_item->observacion  = $item['observacion'] ?? '';
                            $documento_item->pagina       = $item['pagina']      ?? '';
                            $documento_item->pos_x        = $item['pos_x']       ?? '';
                            $documento_item->pos_y        = $item['pos_y']       ?? '';
                            $documento_item->cantidad     = $item['cant']        ?? 0;
                            $documento_item->base         = $item['base']        ?? 0;
                            $documento_item->altura       = $item['altura']      ?? 0;
                            $documento_item->usuario      = 0;
                            R::store($documento_item);
                        }
                        $itemsRegenerados = true;
                    }
                }
            }
        
            registrarLog('INFO', 'DOCUMENTOS', "Documento $docId reemplazado por $nuevoNombre (tipo $tipo)");
            R::commit();
            echo json_encode(['ok' => true, 'items_regenerados' => $itemsRegenerados]);
        
        }
	}