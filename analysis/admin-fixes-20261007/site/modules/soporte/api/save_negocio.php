<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role(['admin', 'master']);
require_post();
use RedBeanPHP\R;

if (!verify_csrf($_POST['csrf'] ?? null)) {
    json_response(['ok' => false, 'error' => 'Token de seguridad inválido, recargue la página.'], 400);
}

$coeficientev = filter_input(INPUT_POST, 'coeficientev', FILTER_VALIDATE_FLOAT);
$escala       = filter_input(INPUT_POST, 'escala', FILTER_VALIDATE_FLOAT);
$tamanio      = filter_input(INPUT_POST, 'tamanio', FILTER_VALIDATE_INT);
$color        = s('color', '');
$tiempo_cortep = filter_input(INPUT_POST, 'tiempo_cortep');
$tiempo_cortev = filter_input(INPUT_POST, 'tiempo_cortev');


if ($coeficientev === false || $coeficientev === null || $coeficientev < 0 || $coeficientev > 999.99) {
    json_response(['ok' => false, 'error' => 'Coeficiente V inválido.'], 422);
}
if ($escala === false || $escala === null || $escala < 0 || $escala > 999.99) {
    json_response(['ok' => false, 'error' => 'Escala inválida.'], 422);
}
if ($tamanio === false || $tamanio === null || $tamanio < 1 || $tamanio > 99) {
    json_response(['ok' => false, 'error' => 'Tamaño de letra inválido.'], 422);
}

// if ($color !== '' && !preg_match('/^#?[0-9A-Fa-f]{3,8}$/', $color)) {
//     json_response(['ok' => false, 'error' => 'Color inválido, use formato #RRGGBB.'], 422);
// }

$row = R::findOne('systemnegocio', ' ORDER BY id') ?: R::dispense('systemnegocio');
$row->coeficientev = $coeficientev;
$row->escala       = $escala;
$row->tamanio      = $tamanio;
// $row->color     = $color;

$logoActualizado = false;
if (isset($_FILES['logo'])) {
    $archivo = $_FILES['logo'];
    $error = $archivo['error'] ?? null;
    if (!is_int($error)) {
        json_response(['ok' => false, 'error' => 'La carga del logo es inválida.'], 422);
    }
    if ($error !== UPLOAD_ERR_NO_FILE) {
        if ($error !== UPLOAD_ERR_OK) {
            $mensaje = in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'El logo supera el tamaño permitido. Seleccione una imagen de hasta 3 MB.'
                : 'No se pudo recibir el logo completo. Vuelva a seleccionar la imagen e intente nuevamente.';
            json_response(['ok' => false, 'error' => $mensaje], 422);
        }
        $tmp = $archivo['tmp_name'] ?? '';
        if (!is_string($tmp) || !is_uploaded_file($tmp)) {
            json_response(['ok' => false, 'error' => 'No se recibió un archivo de imagen válido.'], 422);
        }
        $size = filesize($tmp);
        if (!$size || $size > 3 * 1024 * 1024) {
            json_response(['ok' => false, 'error' => 'El logo debe ser una imagen de hasta 3 MB y no puede estar vacío.'], 422);
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!in_array($mime, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)
            || @getimagesize($tmp) === false) {
            json_response(['ok' => false, 'error' => 'Formato de logo no permitido. Use PNG, JPG, GIF o WEBP.'], 422);
        }
        $contenido = file_get_contents($tmp);
        if ($contenido === false) {
            json_response(['ok' => false, 'error' => 'No se pudo leer la imagen. Intente nuevamente.'], 500);
        }
        $row->logo = $contenido;
        $logoActualizado = true;
    }
}
$row->tiempo_cortep =  $tiempo_cortep;
$row->tiempo_cortev =  $tiempo_cortev;

try {
    R::store($row);
} catch (Throwable $e) {
    error_log('Guardar negocio: ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'No se pudieron guardar los datos del negocio. Revise los valores e intente nuevamente.'], 500);
}
json_response(['ok' => true, 'logo_actualizado' => $logoActualizado]);
