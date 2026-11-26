from pathlib import Path
p=Path('src/include/parse_pdf.php');s=p.read_text(encoding='utf-8');a=s.index('    $lineas = [];',s.index('function agruparEnLineas'));b=s.index('\n    return $resultado;',a)
s=s[:a]+'''    // Los índices de PHP sólo pueden ser enteros o strings: conservar Y como dato.
    $lineas = [];
    foreach ($palabras as $w) {
        $indice = null;
        foreach ($lineas as $i => $linea) {
            if (abs($linea['y'] - $w['yMin']) <= $tolerancia) { $indice = $i; break; }
        }
        if ($indice === null) {
            $indice = count($lineas);
            $lineas[] = ['y' => (float)$w['yMin'], 'palabras' => []];
        }
        $lineas[$indice]['palabras'][] = $w;
    }
    usort($lineas, static fn($a, $b) => $a['y'] <=> $b['y']);
    $resultado = [];
    foreach ($lineas as $linea) {
        $palabrasLinea = $linea['palabras'];
        usort($palabrasLinea, static fn($a, $b) => $a['xMin'] <=> $b['xMin']);
        $resultado[] = ['y'=>$linea['y'], 'texto'=>implode(' ', array_column($palabrasLinea,'text')), 'palabras'=>$palabrasLinea];
    }
''' + s[b:];p.write_text(s,encoding='utf-8')
# Alta atómica: reutilizar la lógica existente dentro de un único try/transaction.
p=Path('src/api/ordenes.php');s=p.read_text(encoding='utf-8');a=s.index("\t\t\t// registrarLog",s.index("$_POST['accion']==='add'"));b=s.index("\t\t}elseif($_POST['accion']==='archive')",a);body=s[a:b]
body=body[body.index("\t\t\t$orden = json_decode"):]
body=body.replace("$orden = json_decode($_POST['orden'], true);", "$orden = json_decode($_POST['orden'] ?? '', true, 512, JSON_THROW_ON_ERROR);\n            if (!is_array($orden) || count($orden)<3 || !is_array($orden[0]) || !is_array($orden[1]) || !is_array($orden[2]) || empty($orden[0]['numero'])) throw new InvalidArgumentException('Datos de orden incompletos.');")
import re
body=re.sub(r"echo json_encode\(\['error' => (.*?)\]\);\s*exit;",r"throw new RuntimeException(\1);",body,flags=re.S)
body=body.replace("'codigo = ?', [$cabecera['numero']]", "'codigo = ? FOR UPDATE', [$cabecera['numero']]")
body=body.replace("                            continue;", "                            throw new RuntimeException('No se encuentra un PDF adjunto. No se creó la orden.');")
body=body.replace("$ruta = \"../tmp/\".$doc['nombre'];", "$nombreDoc = (string)$doc['nombre'];\n                        if (basename($nombreDoc)!==$nombreDoc || strpbrk($nombreDoc, '/\\\\:')!==false) throw new RuntimeException('Nombre de PDF inválido.');\n                        $ruta = __DIR__.\"/../tmp/\".$nombreDoc;")
body=body.replace("$items = ((int) $doc['tipo'] === 3) ? parsearPdfVidrio($ruta) : parsearPdf($ruta);", "$items = ((int) $doc['tipo'] === 3) ? parsearPdfVidrio($ruta) : parsearPdf($ruta);\n                        if (!$items) throw new RuntimeException('No se pudieron identificar ítems en '.$nombreDoc.'. No se creó la orden.');")
body=body.replace("echo json_encode(['resultado' => 'OK']);", "R::commit();\n            echo json_encode(['resultado'=>'OK','id'=>(int)$id_o], JSON_THROW_ON_ERROR);")
new='''            // Los diagnósticos van al log, nunca mezclados con la respuesta JSON.
            ini_set('display_errors','0');
            R::begin();
            register_shutdown_function(static function () { $pdo=R::getPDO(); if ($pdo && $pdo->inTransaction()) $pdo->rollBack(); });
            try {
'''+body+'''            } catch (Throwable $e) {
                if (R::getPDO()->inTransaction()) R::rollback();
                registrarLog('ERROR','IMPORTACION', $e->getMessage());
                http_response_code($e instanceof InvalidArgumentException || $e instanceof JsonException ? 422 : 409);
                echo json_encode(['resultado'=>'ERROR','error'=>$e->getMessage()], JSON_INVALID_UTF8_SUBSTITUTE);
            }
'''
s=s[:a]+new+s[b:];p.write_text(s,encoding='utf-8')
# JSON endpoints de subida: avisos al log, no al body.
for f in ['src/include/pages/fileV1.0.1.php','src/include/pages/filePdf.php']:
 p=Path(f);s=p.read_text(encoding='utf-8');s=s.replace("ini_set('display_errors', '1');","ini_set('display_errors', '0');");p.write_text(s,encoding='utf-8')
# Controladores y catálogo.
p=Path('src/api/estadisticas.php');s=p.read_text(encoding='utf-8').replace("'c_estado = ?', [4]","'c_estado = ?', [2]");p.write_text(s,encoding='utf-8')
p=Path('src/estadisticas.php');s=p.read_text(encoding='utf-8').replace('AND d.c_estado = 0','').replace('AND d.tiempo_real is not null','AND d.c_estado = 3').replace('Tareas Asignadas / Terminadas','Tipologías creadas / terminadas').replace('Tareas p/Operarios','Tipologías terminadas por operario').replace('Tareas p/Producidas-Suspendidas','Tipologías terminadas / suspendidas');p.write_text(s,encoding='utf-8')
p=Path('src/include/reportes_service.php');s=p.read_text(encoding='utf-8').replace("'2' => 'Finalizado']", "'2' => 'Finalizado', '3' => 'Invalidado por reversión', '4' => 'Lectura descartada (hasta 60 s)']");s=s.replace("'cierre_jornada' => 'Cierre de jornada'", "'cierre_jornada' => 'Cierre de jornada', 'lectura_descartada' => 'Lectura descartada (hasta 60 s)'");p.write_text(s,encoding='utf-8')
p=Path('src/css/footer.css');s=p.read_text(encoding='utf-8');s+='\n/* Administración: el pie ocupa espacio y no tapa gráficos ni controles. */\n.wrapper > .footer { position: static; margin: 20px 20px 12px 280px; clear: both; }\n@media (max-width: 991px) { .wrapper > .footer { margin: 16px; } }\n';p.write_text(s,encoding='utf-8')
