<?php
require_once __DIR__ . '/../include/codigos_vidrio_pla.php';
function verificar($ok, $mensaje) { if (!$ok) throw new RuntimeException($mensaje); }
function falla(callable $fn) { try { $fn(); } catch (RuntimeException $e) { return; } throw new RuntimeException('Se esperaba rechazo'); }
$filas = [[5,439,2139,'VA9/PAT1'],[6,439,2139,'VA9/PAT1'],[1,339,2139,'VA6/VA6'],[2,339,2139,'VA6/VA6'],[3,439,939,'VA7/VA7'],[4,439,939,'VA7/VA7'],[7,638,1938,'VA11/VA11'],[8,638,1938,'VA11 */VA12']];
$texto = "FLOAT 5 mm\nCLIENTE (#14254)\n1 de 3600 x 2500 Placa Entera\nPiezas ubicadas\n";
foreach ($filas as $f) $texto .= "({$f[0]}) 1 {$f[1]} {$f[2]} PROYECTO({$f[3]})\n";
$texto .= 'Desperdicio = 0';
$esperados = ['1425400000044','1425400010050','1425400020004','1425400030010','1425400040026','1425400050032','1425400060062','1425400070078'];
$etiquetas = plaEtiquetasDesdeTexto($texto, '14254');
verificar(array_column($etiquetas,'codigo') === $esperados, 'No reproduce las ocho etiquetas originales');
foreach ($etiquetas as $e) verificar(etiquetaVidrioEanValido($e['codigo']), 'EAN invalido');
$d = ['tipologia'=>'V-DVH(VA6/VA6)', 'descripcion'=>'DVH VA6/VA6 FLOAT 5 CAMA9 - 339 x 2139'];
verificar(etiquetaCoincideDetalleVidrio($etiquetas[2],$d) && etiquetaCoincideDetalleVidrio($etiquetas[3],$d), 'Ambas etiquetas deben seleccionar VA6');
$d['descripcion']='DVH VA6/VA6 LAMIN3 CAMA9 - 339 x 2139';
verificar(!etiquetaCoincideDetalleVidrio($etiquetas[2],$d), 'No debe confundir material');
$tela = "TELAI\nCLIENTE (#14254)\n1 de 1000 x 1000 Placa Entera\nPiezas ubicadas\n(1) 1 100 200 PROYECTO(M1/M1)\nDesperdicio = 0";
$t = plaEtiquetasDesdeTexto($tela,'14254');
verificar(etiquetaCoincideDetalleVidrio($t[0],['tipologia'=>'TELA-M(M1/M1)','descripcion'=>'Tela Mosquitera M1/M1 - 100 x 200']), 'TELAI misma regla');
falla(fn()=>plaEtiquetasDesdeTexto($texto,'99999'));
falla(fn()=>plaCodigoVidrio('14254',10000,1));
falla(fn()=>plaCodigoVidrio('14254',0,1001));
falla(fn()=>plaEtiquetasDesdeTexto(str_replace('FLOAT 5 mm','DESCONOCIDO',$texto),'14254'));
$multi = str_replace('1 de 1000','2 de 1000',$tela)."\f".$tela;
$e = plaEtiquetasDesdeTexto($multi,'14254');
verificar(count($e)===3 && substr($e[2]['codigo'],5,4)==='0002', 'Recorrido multipagina adoptado');
$internos = [['00010001','V-X/X',10,3,'Vidrio Simple X/X FLOAT 5 - 100 x 200']];
$sinPla = plaAplicarCodigosImportacion('14254', [], $internos, [], __DIR__);
verificar($sinPla[0][0] === $internos[0][0] && $sinPla[0][5] === [], 'Sin PLA conservar interno');
echo "OK: ocho codigos originales, grupos, material, TELAI, limites, pedido y recorrido multipagina.\n";
