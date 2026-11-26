<?php
require_once __DIR__.'/../auth.php';
ini_set('display_errors','0');
try {
    $raw=file_get_contents('php://input');
    if(strlen($raw)>3000000)throw new InvalidArgumentException('La tabla es demasiado grande para exportar.');
    $data=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
    if(($_SERVER['REQUEST_METHOD']??'')!=='POST' || !is_string($data['csrf']??null) || !hash_equals($_SESSION['admin_export_csrf']??'', $data['csrf']) || empty($_SESSION['admin_export_csrf'])){http_response_code(403);throw new RuntimeException('Recargue la página antes de exportar.');}
    $headers=$data['headers']??null;$rows=$data['rows']??null;
    if(!is_array($headers)||!$headers||count($headers)>12||!is_array($rows)||count($rows)>10000)throw new InvalidArgumentException('Tabla no válida o demasiado grande.');
    $escape=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $html='<h1>'.$escape($data['title']??'Listado').'</h1><table style="width:100%;border-collapse:collapse" border="1" cellpadding="5"><thead><tr>';
    foreach($headers as $v){if(!is_scalar($v))throw new InvalidArgumentException('Cabecera inválida.');$html.='<th>'.$escape($v).'</th>';}$html.='</tr></thead><tbody>';
    foreach($rows as $row){if(!is_array($row)||count($row)!==count($headers))throw new InvalidArgumentException('Fila inválida.');$html.='<tr>';foreach($row as $v){if(!is_scalar($v)&&$v!==null)throw new InvalidArgumentException('Celda inválida.');$html.='<td>'.$escape($v).'</td>';}$html.='</tr>';}
    $html.='</tbody></table>';session_write_close();
    require_once __DIR__.'/../../vendor/autoload.php';
    $mpdf=new Mpdf\Mpdf(['tempDir'=>sys_get_temp_dir(),'format'=>'A4-L','default_font'=>'dejavusans','default_font_size'=>9]);
    $mpdf->WriteHTML($html);$mpdf->Output('listado.pdf','D');
}catch(Throwable $e){if(http_response_code()<400)http_response_code($e instanceof InvalidArgumentException || $e instanceof JsonException?422:500);header('Content-Type:application/json; charset=utf-8');error_log('Exportación admin: '.$e->getMessage());echo json_encode(['mensaje'=>$e instanceof InvalidArgumentException || http_response_code()===403?$e->getMessage():'No se pudo generar el PDF.'],JSON_INVALID_UTF8_SUBSTITUTE);}
