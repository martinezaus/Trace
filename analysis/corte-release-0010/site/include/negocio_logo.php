<?php
/** Logo vigente del negocio, embebido para PDF y documentos imprimibles. */
function negocio_logo_data_uri(?PDO $conexion = null): string
{
    if ($conexion === null) {
        require_once __DIR__ . '/config.php';
        $conexion = new PDO('mysql:host=' . hostBD . ';dbname=' . nombreBD . ';charset=utf8mb4', usuarioBD, claveBD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }
    // Se consulta en cada generación: no reutilizar una imagen de un reporte anterior.
    $logo = $conexion->query('SELECT logo FROM systemnegocio ORDER BY id LIMIT 1')->fetchColumn();
    if ($logo === false || $logo === null || $logo === '') return '';
    $info = @getimagesizefromstring($logo);
    if (!$info || !in_array($info['mime'], ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)) {
        throw new RuntimeException('El logo del negocio no es una imagen válida.');
    }
    $mime = $info['mime'];
    // PDFMake admite PNG/JPEG. Convertir GIF/WEBP también permite usarlos en mPDF.
    if ($mime === 'image/gif' || $mime === 'image/webp') {
        $imagen = imagecreatefromstring($logo);
        if (!$imagen) throw new RuntimeException('No se pudo preparar el logo del negocio.');
        imagealphablending($imagen, false);
        imagesavealpha($imagen, true);
        ob_start();
        imagepng($imagen);
        $logo = ob_get_clean();
        imagedestroy($imagen);
        $mime = 'image/png';
    }
    return 'data:' . $mime . ';base64,' . base64_encode($logo);
}

function negocio_logo_html(?\Mpdf\Mpdf $pdf = null): string
{
    $logo = negocio_logo_data_uri();
    if ($logo === '') return '';
    $binario = base64_decode(explode(',', $logo, 2)[1]);
    $info = getimagesizefromstring($binario);
    if ($pdf !== null) {
        $pdf->imageVars['negocioLogo'] = $binario;
        $logo = 'var:negocioLogo';
    }
    $escala = min(150 / $info[0], 65 / $info[1], 1);
    $ancho = max(1, (int)round($info[0] * $escala));
    $alto = max(1, (int)round($info[1] * $escala));
    return '<img src="' . $logo . '" alt="Logo del negocio" width="' . $ancho . '" height="' . $alto . '">';
}
