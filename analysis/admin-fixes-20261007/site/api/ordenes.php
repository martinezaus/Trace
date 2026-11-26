<?php
require_once __DIR__ . "/../include/productividad.php";
	

	header('Content-Type: application/json; charset=utf-8');

	require_once "../vendor/autoload.php";
	require_once "../include/config.php";
	require_once 'rb-mysql.php';
	require_once "../include/functions.php";
	require_once "../include/log.php";
	require_once "../include/parse_pdf.php";
    require_once "../include/parse_pdfvidrio.php";

	//Genero barcode 1d (codigos de barra)
	use Picqer\Barcode\BarcodeGeneratorPNG;
	$generator= new BarcodeGeneratorPNG;
	$tipo=$generator::TYPE_CODE_128;


   //Genero barcode 2d (codigos QR) 
   use Endroid\QrCode\QrCode;
   use Endroid\QrCode\Writer\PngWriter;
   use Endroid\QrCode\Encoding\Encoding;
   use Endroid\QrCode\ErrorCorrectionLevel;
   use Endroid\QrCode\Color\Color;
   use Endroid\QrCode\RoundBlockSizeMode;

   use Endroid\QrCode\LabelAlignment;
   use Endroid\QrCode\Response\QrCodeResponse;

	// Verifica si RedBeanPHP está funcionando
	try{
		R::setup('mysql:host='.hostBD.';dbname='.nombreBD, usuarioBD, claveBD);
		R::freeze(true); // Permitir que RedBean actualice el esquema
		//R::nuke(); // OJO: Borra todas las tablas, solo en desarrollo
		//R::debug(true); // Habilitar debug en RedBeanPHP
	}catch(Exception $e){
		registrarLog('ERROR','CONECTION[Ordenes]',"No se pudo conectar a la base de datos. ".$e->getMessage());
		echo die(json_encode(["error" => "No se pudo conectar a la base de datos"]));
	}
	
	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$response = [];
		
		if($_POST['accion']==='select'){
			//codigo -> nro de presupuesto
			$orden = R::findOne('ordenes','codigo = ?', [$_POST['barcode']]);
			if($orden){
				$code='V'.strval($orden->codigo);
				$barcode_pdf=$generator->getBarcode($code,$tipo);
				$qrTipologias = new QrCode('O'.strval($orden->codigo));
				$qrTipologias->setSize(300);
				$qrTipologias->setMargin(10);
				$qrTipologiasResult = (new PngWriter())->write($qrTipologias);
				switch($orden->c_estado){
					case 0:
					case 1:    
						$response[]=['id' => $orden->id, 
									 'codigo' => $orden->codigo, 
									 'cliente' => $orden->cliente, 
									 'fecha' => $orden->f_alta, 
									 'barcode' => base64_encode($orden->barcode), 
									 'bpdf' => base64_encode($barcode_pdf), 
									 'barcode2d' => base64_encode($orden->barcode2d),
									 'btipologias' => base64_encode($qrTipologiasResult->getString()),
									 'timestamp_inicio' => $orden->timestamp_inicio]; 
						break;        
					default:
						$response[]=['id' => $orden->id, 'codigo' => $orden->codigo, 'estado' => $orden->c_estado]; 
						break; 
				}
			}else{
				$response[]=['id' => 0, 'codigo' => 'No existe Orden.'];
			}
			echo json_encode($response); // Enviar JSON            
		}elseif($_POST['accion']==='load'){
			$ordenes = R::findAll('ordenes');
			foreach ($ordenes as $orden) {
				$response[] = ['id' => $orden->id,'codigo' => $orden->codigo,'cliente' => $orden->cliente,'estado' => $orden->c_estado, 'obs' => $orden->observaciones];
			}
			echo json_encode($response);
		}elseif($_POST['accion']==='add'){
            // Los diagnósticos van al log, nunca mezclados con la respuesta JSON.
            ini_set('display_errors','0');
            R::begin();
            register_shutdown_function(static function () { $pdo=R::getPDO(); if ($pdo && $pdo->inTransaction()) $pdo->rollBack(); });
            try {
			$orden = json_decode($_POST['orden'] ?? '', true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($orden) || count($orden)<3 || !is_array($orden[0]) || !is_array($orden[1]) || !is_array($orden[2]) || empty($orden[0]['numero'])) throw new InvalidArgumentException('Datos de orden incompletos.');

			$cabecera  = $orden[0];
			$aberturas = $orden[1];
			$vidrios   = $orden[2];
			$documentos= $orden[3] ?? [];

            // Evitar un alta parcial si aun no se aplico la migracion aditiva.
            if ($vidrios && !isset(R::inspect('detalles')['codigos_vidrio'])) {
                throw new RuntimeException('Falta aplicar scripts/migrar_codigos_vidrio_pla.php --aplicar antes de importar vidrios.');
            }

			//verifico que no exista una orden ya cargada
			$orden_existente = R::getCell('SELECT id FROM ordenes WHERE codigo = ? FOR UPDATE', [$cabecera['numero']]);
			if($orden_existente){ //si existe no lo creo
				throw new RuntimeException('La orden ya existe en el sistema.');
			}

			$qrCode = new QrCode('O'.strval($cabecera['numero']));
			$qrCode->setSize(300);
			$qrCode->setMargin(10);
			$writer = new PngWriter();
			$result = $writer->write($qrCode);   

			$qrBCode = $generator->getBarcode(('B'.strval($cabecera['numero'])),$tipo); //codigo que se utilizara para abrir los archivos

			//ordenes
			$ordenes=R::dispense('ordenes');
			$ordenes->codigo=$cabecera['numero'];
			$ordenes->barcode=$qrBCode;
			$ordenes->barcode2d=$result->getString();
			$ordenes->cliente=$cabecera['cliente'];
			$ordenes->archivo=$cabecera['dat'];
			$ordenes->tiempo_armado=$cabecera['ta'];    
            $ordenes->tiempo_vidrio=$cabecera['tv'];
			$ordenes->tiempo_orden=$cabecera['tt'];
			$ordenes->tiempo_corte=$cabecera['corte'];
            
			try {
				$id_o = R::store($ordenes);
				registrarLog('INFO','ORDENES',"linea 116, [Add] ID:".$id_o);
			} catch (Exception $e) {
				registrarLog('ERROR','ORDENES',"linea 118, [Add]. Error al guardar la orden.".$e->getMessage());
				throw new RuntimeException('Error al guardar la orden: ' . $e->getMessage());
			}           

			/** LOTES */
			$lote = R::dispense('lotes');
			$lote->orden_id=$id_o;
			$lote->items=$cabecera['items'];
			$lote->piezas=$cabecera['piezas'];
			$lote->tipologias=$cabecera['tipologias']; //cantidad de aberturas
			$lote->vidrios=$cabecera['vidrios']; //cantidad de vidrios
			R::store($lote);

			
			/** DOCUMENTOS */
            if (is_array($documentos) && count($documentos) > 0) {
			    foreach ($documentos as $doc) {
                    $archivoAdjunto=(string)($doc['nombre']??'');
                    if($archivoAdjunto==='' || basename($archivoAdjunto)!==$archivoAdjunto || strpbrk($archivoAdjunto,'/\\:')!==false || !is_file(__DIR__.'/../tmp/'.$archivoAdjunto))throw new InvalidArgumentException('Archivo adjunto ausente o inválido: '.$archivoAdjunto);

				
				    $documento = R::dispense('documentos');
				    $documento->orden_id    = $id_o;
				    $documento->nombre      = $doc['nombre'];
				    $documento->descripcion = $doc['descripcion'];
				    $documento->tipo        = (int)$doc['tipo'];
				    $documento_id = R::store($documento);                        
	
                    registrarLog('INFO','DOCUMENTOS',"linea 144, Documento: [".$doc['nombre']."] tipo: ".(int)$doc['tipo']);
                    if(!in_array((int)$doc['tipo'], [2, 3], true)) { continue; //solo pueden entrar pla. 
                    }else{
				        $nombreDoc = (string)$doc['nombre'];
                        if (basename($nombreDoc)!==$nombreDoc || strpbrk($nombreDoc, '/\\:')!==false) throw new RuntimeException('Nombre de PDF inválido.');
                        $ruta = __DIR__."/../tmp/".$nombreDoc;
				        registrarLog('INFO','DOCUMENTOS',"linea 147, Archivo encontrado. [" . $doc['nombre'] . ", ID: ".$documento_id.", ruta: ".$ruta."]. Se procede a cargar documentos.");
				        if (!file_exists($ruta)) {
					        registrarLog('ERROR','DOCUMENTOS',"linea 149, Archivo no encontrado. [" . $doc['nombre'] . ", ID: ".$documento_id.", ruta: ".$ruta."]");
                            throw new RuntimeException('No se encuentra un PDF adjunto. No se creó la orden.'); 
                        }

                        // Elegir parser según tipo de documento
                        $items = ((int) $doc['tipo'] === 3) ? parsearPdfVidrio($ruta) : parsearPdf($ruta);
                        if (!$items) throw new RuntimeException('No se pudieron identificar ítems en '.$nombreDoc.'. No se creó la orden.');
				   
                        registrarLog('INFO','DOCUMENTOS',"linea 156, Items parseados: ".count($items));
				        foreach ($items as $row => $item) {

					        //registrarLog('INFO','DOCUMENTOS', $documento_id." Item: [".$item['perfil']." ".$item['descripcion']." ".$item['barra_num']."]");

					        $documento_item = R::dispense('documentositems');
					        $documento_item->documento_id = $documento_id;
					        $documento_item->perfil       = $item['perfil'];
					        $documento_item->descripcion  = $item['descripcion'];
					        $documento_item->barra        = $item['barra_num'];
					        $documento_item->observacion  = $item['observacion'] ?? '';
					        $documento_item->pagina       = isset($item['pagina']) && is_numeric($item['pagina']) ? (int) $item['pagina'] : 1;
					        $documento_item->pos_x        = isset($item['pos_x']) && is_numeric($item['pos_x']) ? (float) $item['pos_x'] : null;
					        $documento_item->pos_y        = isset($item['pos_y']) && is_numeric($item['pos_y']) ? (float) $item['pos_y'] : null;
                            $documento_item->cantidad     = $item['cant']        ?? 0;
                            $documento_item->base         = $item['base']        ?? 0;
                            $documento_item->altura       = $item['altura']      ?? 0;
					        $documento_item->usuario      = 0;

					        try {
					            R::store($documento_item);
						        registrarLog('INFO','DOCUMENTOS_ITEMS',"linea 178, GUARDADO. Try/catch. Item ".$row." perfil ".$item['perfil']." barra ".$item['barra_num']);
					        } catch (Throwable $e) {
						        registrarLog('ERROR','DOCUMENTOS_ITEMS',"linea 180,Error item ".$row.": ".$e->getMessage());
						        throw new RuntimeException('Error al guardar item de documento: ' . $e->getMessage());
					        }
				        }
                    }    
			    }
            }

			/** ABERTURAS */
			foreach ($aberturas as $da) {

				if (!is_array($da) || count($da) < 3) {
					registrarLog('INFO','ORDENES', "linea 193, Detalle ignorado por datos incompletos: " . print_r($da, true));
					continue;
				}
				
				//AGREGADO NUEVO 23/03 - 
				//$topologia = R::findOne('topologias','descripcion = ? ', [$da['tipologia']]);
				
				$qrCodeD = new QrCode($cabecera['numero'].$da['codigo']); //QrCode('A'.strval($cabecera['numero']));
				$qrCodeD->setSize(300);
				$qrCodeD->setMargin(10);
				$writerD = new PngWriter();
				$resultD = $writerD->write($qrCodeD);   

				$qrBCodeD = $generator->getBarcode(($cabecera['numero'].$da['codigo']), $tipo); //('A'.strval($detalle))

				$detalle = R::dispense('detalles');
				$detalle->orden_id        = $id_o;
				$detalle->operador_id     = 1;
				$detalle->codigo          = $cabecera['numero'].$da['codigo'];
				$detalle->barcode         = $qrBCodeD; 
				$detalle->barcode2d       = $resultD->getString(); 
				$detalle->tipologia       = $da['tipologia'];
				$detalle->etapas          = $da['etapas'];
				$detalle->descripcion     = $da['descripcion'];
				$detalle->tiempo_estimado = segundosToTime($da['tiempo']);

				try {
					R::store($detalle);
					//$detalles_guardados++;
				} catch (Exception $e) {
					registrarLog('ERROR','DETALLES',"linea 223, Add. Aluminio.".$e->getMessage());
					throw new RuntimeException('Error al guardar detalle: ' . $e->getMessage());
				}
			   
			}


            /** VIDRIOS */
			foreach ($vidrios as $dv) {
				if (!is_array($dv) || count($dv) < 3) {
					registrarLog('ERROR','DETALLES',"linea 234, Detalle ignorado por datos incompletos: " . print_r($dv, true));
					continue;
				}

				$qrCodeV = new QrCode($cabecera['numero'].$dv[0]); //'V'.strval($detalle[0])
				$qrCodeV->setSize(300);
				$qrCodeV->setMargin(10);
				$writerV = new PngWriter();
				$resultV = $writerV->write($qrCodeV);   

				$qrBCodeV = $generator->getBarcode(($cabecera['numero'].$dv[0]), $tipo);

				$v = R::dispense('detalles');
				$v->orden_id        = $id_o;
				$v->operador_id     = 1;
				$v->codigo          = $cabecera['numero'].$dv[0];
				$v->barcode         = $qrBCodeV; 
				$v->barcode2d       = $resultV->getString(); 
				$v->tipologia       = $dv[1];
				$v->etapas          = $dv[3];
				$v->tiempo_estimado = segundosToTime($dv[2]);
				$v->descripcion     = $dv[4];
                $v->codigos_vidrio  = json_encode(array_values($dv[5] ?? []));

				try {
					R::store($v);
					//$detalles_guardados++;
				} catch (Exception $e) {
					registrarLog('ERROR','linea 261, DETALLES[Vidrios]',"Add. ".$e->getMessage());
					throw new RuntimeException('Error al guardar detalle: ' . $e->getMessage());
				}
			}
			R::commit();
            echo json_encode(['resultado'=>'OK','id'=>(int)$id_o], JSON_THROW_ON_ERROR);
            } catch (Throwable $e) {
                if (R::getPDO()->inTransaction()) R::rollback();
                registrarLog('ERROR','IMPORTACION', $e->getMessage());
                http_response_code($e instanceof InvalidArgumentException || $e instanceof JsonException ? 422 : 409);
                echo json_encode(['resultado'=>'ERROR','error'=>$e->getMessage()], JSON_INVALID_UTF8_SUBSTITUTE);
            }
		}elseif($_POST['accion']==='archive'){
			$orden = R::findOne('ordenes','id = ? ', [$_POST['id']]);
			$response = ['titulo' => $orden->archivo ];
			echo json_encode($response);
		}elseif($_POST['accion']==='update'){
			$orden = R::load('ordenes', $_POST['ido']);
			if($orden->id) {
				$orden->c_estado=$_POST['estado']; 
				R::store($orden);

				echo json_encode(['status' => 'ok', 'id' => $orden->id]);
			} else {
				echo json_encode(['status' => 'error', 'id' => 0]);
			}
			exit;
		}elseif($_POST['accion']==='view'){
			$orden = R::load('ordenes', $_POST['id']);
			if($orden){
				$response[]=['id' => $orden->id, 
							 'codigo' => $orden->codigo, 
							 'cliente' => $orden->cliente, 
							 'fecha' => $orden->f_alta,
							 'tiempo_inicio' => $orden->timestamp_inicio,
                             'tiempo_orden' => $orden->tiempo_orden,
							 'tiempo_armado' => $orden->tiempo_armado, 
                             'tiempo_vidrio' => $orden->tiempo_vidrio,
                             'tiempo_corte' => $orden->tiempo_corte,
							 'estado' => $orden->c_estado,
                             'productividad'=>productividadOrden((int)$orden->id)]; 
			}else{
				$response[]=['id' => 0];
		   }
		   echo json_encode($response); // Enviar JSON  
		}elseif($_POST['accion']==='delete'){
            require_once __DIR__.'/../include/orden_delete_service.php';
            try {
                $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT);
                if($id===false || $id<=0)throw new OrdenDeleteDenied('Identificador de orden inválido.');
                $pdo=R::getPDO();
                if(!($pdo instanceof PDO))throw new RuntimeException('RedBean no tiene una conexion PDO disponible.');
                $service=new OrdenDeleteService($pdo,dirname(__DIR__).'/tmp');
                $result=$service->delete($id);
                registrarLog('INFO','ORDENES','DELETE confirmado: '.$id);
                echo json_encode($result,JSON_INVALID_UTF8_SUBSTITUTE);
            }catch(OrdenDeleteDenied $e){
                http_response_code(409);
                echo json_encode(['resultado'=>'error','mensaje'=>$e->getMessage()],JSON_INVALID_UTF8_SUBSTITUTE);
            }catch(Throwable $e){
                registrarLog('ERROR','ORDENES','DELETE fallido: '.($_POST['id']??'').'; '.$e->getMessage());
                http_response_code(500);
                echo json_encode(['resultado'=>'error','mensaje'=>'No se pudo completar la eliminación. Revise el registro con soporte.'],JSON_INVALID_UTF8_SUBSTITUTE);
            }
            exit;
		}elseif($_POST['accion']==='end'){

			$orden = R::load('ordenes', $_POST['ido']);
			if($orden->id) {
				$tiempo_actual = $orden->tiempo_armado ?? '00:00:00';
				$tiempo_nuevo  = $_POST["tiempo"] ?? '00:00:00';
				
				// Separar en horas, minutos, segundos
				list($h_actual, $m_actual, $s_actual) = array_map('intval', explode(':', $tiempo_actual));
				list($h_nuevo,  $m_nuevo,  $s_nuevo)  = array_map('intval', explode(':', $tiempo_nuevo));
				
				// Calcular segundos totales
				$segundos_total = ($h_actual * 3600 + $m_actual * 60 + $s_actual) + ($h_nuevo  * 3600 + $m_nuevo  * 60 + $s_nuevo);

				$orden->c_estado = 2; 
				$orden->duracion = gmdate('H:i:s', $segundos_total);

                $orden->productividad = productividadOrden((int)$orden->id);

				R::store($orden);
                registrarLog('INFO','ORDENES',"linea 347, Finaliza Orden ".$_POST['ido']);

                echo json_encode(['status' => 'ok', 'id' => $orden->id]);
			} else {
				registrarLog('ERROR','ORDENES',"Finalizar: orden no encontrada id=".$_POST['ido']);
				echo json_encode(['status' => 'error', 'id' => 0]);
			}
			exit;
		}elseif($_POST['accion']==='chartO'){
			// Devuelve series agrupadas por (año, mes) para que el gráfico muestre TODO el rango.
			// IMPORTANTE: no agrupamos solo por mes, porque eso mezcla distintos años.
			// Rango: año en curso (para que SIEMPRE aparezcan meses 01..12 del año actual, aunque alguno esté en 0)
			$anioActual = (int)date('Y');

			$data = R::getAll("SELECT MONTH(f_alta) AS mes, COUNT(id) AS cantidad " .
				              "FROM ordenes " .
				              "WHERE c_estado = ? AND YEAR(f_alta) = ? " .
				              "GROUP BY mes " .
				              "ORDER BY mes ASC", [$_POST['filter'], $anioActual]
			                );

			// Mapa mes => cantidad
			$map = array_fill(1, 12, 0);
			foreach ($data as $fila) {
				$mes = (int)$fila['mes'];
				if ($mes >= 1 && $mes <= 12) {
					$map[$mes] = (int)$fila['cantidad'];
				}
			}

			// Labels solo meses del año en curso
			$labels = [];
			$datos = [];
			for ($mes = 1; $mes <= 12; $mes++) {
				$labels[] = $mes; // 1..12 (Chart mostrará como X)
				$datos[] = $map[$mes];
			}

			echo json_encode(['labels' => $labels, 'data' => $datos]);

		}elseif($_POST["accion"]==="time"){
			$id    = intval($_POST['id']);
			$estado = intval($_POST['estado']);
			// Solo guardar timestamp_inicio si todavía no tiene uno
			if ($estado === 1) {
                registrarLog('INFO','ORDENES',"linea 421, Cambia el estado ".$id);
				$sql = "UPDATE ordenes SET c_estado = ?, timestamp_inicio = COALESCE(timestamp_inicio, NOW()) WHERE id = ?";
				$orden = R::exec($sql,[$estado, $id]);
			}
			// Devolver el timestamp guardado para que el JS lo use
			$row = R::getRow("SELECT timestamp_inicio, UNIX_TIMESTAMP(timestamp_inicio) * 1000 AS timestamp_ms FROM ordenes WHERE id = ?", [$id]);
			echo json_encode([
				'timestamp_inicio' => $row['timestamp_inicio'],
				'timestamp_ms' => isset($row['timestamp_ms']) ? (int)$row['timestamp_ms'] : null
			]);

		}    
	}    

	// Run it!
	//$router->run();

?>