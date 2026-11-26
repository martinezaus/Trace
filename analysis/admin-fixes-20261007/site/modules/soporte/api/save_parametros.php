<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role(['admin', 'master']);
require_post();
use RedBeanPHP\R;

if (!is_string($_POST['csrf'] ?? null) || !verify_csrf($_POST['csrf'])) {
    json_response(['ok' => false, 'error' => 'Token de seguridad inválido, recargue la página.'], 400);
}
$tablas = ['tareas' => 'enable', 'pausas' => 'flag', 'suspensiones' => 'flag'];
$accion = $_POST['accion'] ?? '';
$lock = null;
$transaccion = false;
try {
    if ($accion === 'crear') {
        $categoria = $_POST['categoria'] ?? '';
        $descripcion = $_POST['descripcion'] ?? '';
        $prefijo = $_POST['prefijo'] ?? '';
        if (!is_string($categoria) || !isset($tablas[$categoria])) {
            json_response(['ok' => false, 'error' => 'Categoría inválida.'], 422);
        }
        if (!is_string($descripcion) || trim($descripcion) === '' || mb_strlen(trim($descripcion), 'UTF-8') > 100) {
            json_response(['ok' => false, 'error' => 'Ingrese una descripción de hasta 100 caracteres.'], 422);
        }
        $descripcion = trim($descripcion);
        if (!is_string($prefijo)) {
            json_response(['ok' => false, 'error' => 'Prefijo inválido.'], 422);
        }
        $prefijo = strtoupper(trim($prefijo));
        if ($categoria === 'tareas') {
            if (!preg_match('/\AT[A-Z]{0,9}\z/', $prefijo)) {
                json_response(['ok' => false, 'error' => 'El prefijo debe comenzar con T y contener hasta 10 letras.'], 422);
            }
            $tipo = $_POST['tipo'] ?? null;
            if (!is_string($tipo) || !in_array($tipo, ['0', '1', '2', '3', '9'], true)) {
                json_response(['ok' => false, 'error' => 'Seleccione la aplicación de la tarea.'], 422);
            }
        } else {
            $prefijo = $categoria === 'pausas' ? 'CP' : 'CS';
        }
        $opcion = ($_POST['opcion'] ?? '') === '1' ? 1 : 0;
        // Serializa las altas de cada categoría, incluso entre sesiones distintas.
        $lock = 'soporte_param_' . $categoria;
        if ((int) R::getCell('SELECT GET_LOCK(?, 10)', [$lock]) !== 1) {
            $lock = null;
            json_response(['ok' => false, 'error' => 'Hay otra alta en curso. Intente nuevamente.'], 409);
        }
        R::begin();
        $transaccion = true;
        $maximo = 0;
        foreach (R::getCol("SELECT codigo FROM `$categoria`") as $existente) {
            if (preg_match('/\A[A-Z]+([0-9]+)\z/i', $existente, $partes)) {
                // Evita desbordamientos si hay códigos ajenos al formato habitual.
                if (strlen(ltrim($partes[1], '0')) > 9) {
                    throw new RuntimeException('La numeración existente excede el rango admitido.');
                }
                $maximo = max($maximo, (int)$partes[1]);
            }
        }
        $codigo = $prefijo . str_pad((string)($maximo + 1), 4, '0', STR_PAD_LEFT);
        if (R::getCell("SELECT id FROM `$categoria` WHERE codigo = ? LIMIT 1", [$codigo])) {
            throw new RuntimeException('Código duplicado.');
        }
        require_once __DIR__ . '/../../../vendor/autoload.php';
        $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
        $barcode = $generator->getBarcode($codigo, $generator::TYPE_CODE_128);
        $qr = new \Endroid\QrCode\QrCode($codigo);
        $qr->setSize(300);
        $qr->setMargin(10);
        $barcode2d = (new \Endroid\QrCode\Writer\PngWriter())->write($qr)->getString();
        if ($categoria === 'tareas') {
            R::exec('INSERT INTO tareas (codigo, descripcion, barcode, barcode2d, tipo, enable) VALUES (?, ?, ?, ?, ?, ?)',
                [$codigo, $descripcion, $barcode, $barcode2d, (int)$tipo, $opcion]);
        } else {
            R::exec("INSERT INTO `$categoria` (codigo, descripcion, barcode, barcode2d, flag, codigo_id) VALUES (?, ?, ?, ?, ?, ?)",
                [$codigo, $descripcion, $barcode, $barcode2d, $opcion, $categoria === 'pausas' ? 3 : 4]);
        }
        $id = (int) R::getCell('SELECT LAST_INSERT_ID()');
        $respuesta = ['ok' => true, 'item' => ['id' => $id, 'categoria' => $categoria, 'codigo' => $codigo,
            'descripcion' => $descripcion, 'opcion' => $opcion]];
    } elseif ($accion === 'guardar') {
        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids)) {
            json_response(['ok' => false, 'error' => 'Parámetros inválidos.'], 422);
        }
        foreach ($tablas as $tabla => $campo) {
            foreach ([$ids[$tabla] ?? [], $_POST[$tabla] ?? []] as $lista) {
                if (!is_array($lista)) {
                    json_response(['ok' => false, 'error' => 'Listado inválido.'], 422);
                }
                foreach ($lista as $id) {
                    if (!is_string($id) || !ctype_digit($id) || (int)$id < 1) {
                        json_response(['ok' => false, 'error' => 'Identificador inválido.'], 422);
                    }
                }
            }
        }
        $descripciones = $_POST['descripciones'] ?? null;
        if (!is_array($descripciones)) {
            json_response(['ok' => false, 'error' => 'Faltan las descripciones. Recargue la página.'], 422);
        }
        // Validar todo antes de comenzar la transaccion.
        foreach ($tablas as $tabla => $campo) {
            if (!is_array($descripciones[$tabla] ?? [])) {
                json_response(['ok' => false, 'error' => 'Descripciones inválidas.'], 422);
            }
            foreach (array_unique($ids[$tabla] ?? []) as $id) {
                $texto = $descripciones[$tabla][$id] ?? null;
                if (!is_string($texto) || trim($texto) === '' || mb_strlen(trim($texto), 'UTF-8') > 100) {
                    json_response(['ok' => false, 'error' => 'Cada descripción debe tener entre 1 y 100 caracteres.'], 422);
                }
                $descripciones[$tabla][$id] = trim($texto);
            }
        }
        R::begin();
        $transaccion = true;
        foreach ($tablas as $tabla => $campo) {
            $marcados = array_flip($_POST[$tabla] ?? []);
            // Solo modifica filas presentes en el formulario; respeta altas de otras sesiones.
            foreach (array_unique($ids[$tabla] ?? []) as $id) {
                R::exec("UPDATE `$tabla` SET `$campo` = ?, descripcion = ? WHERE id = ?", [isset($marcados[$id]) ? 1 : 0, $descripciones[$tabla][$id], (int)$id]);
            }
        }
        $respuesta = ['ok' => true];
    } else {
        json_response(['ok' => false, 'error' => 'Acción inválida.'], 422);
    }
    R::commit();
    $transaccion = false;
} catch (Throwable $e) {
    if ($transaccion) R::rollback();
    error_log('Parámetros de soporte: ' . $e->getMessage());
    $respuesta = ['ok' => false, 'error' => 'No se pudieron guardar los parámetros. Intente nuevamente.'];
} finally {
    if ($lock !== null) R::getCell('SELECT RELEASE_LOCK(?)', [$lock]);
}
json_response($respuesta, $respuesta['ok'] ? 200 : 500);
