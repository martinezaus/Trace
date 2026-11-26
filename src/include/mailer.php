<?php
/**
 * mailer.php
 * ---------------------------------------------------------
 * Script GENÉRICO para enviar correos, reutilizable desde:
 *   - Cualquier script PHP:  require_once "mailer.php"; enviarCorreo(...);
 *   - jQuery / AJAX: llamando a este mismo archivo por POST (ver abajo).
 *
 * Usa PHPMailer (instalado vía Composer) + tu tabla de configuración
 * de correo (host SMTP, usuario, clave, destinatarios).
 *
 * ANTES DE USAR: ajustá el bloque "CONFIGURACIÓN - EDITAR" con los
 * nombres reales de tu tabla y columnas.
 * ---------------------------------------------------------
 * Envía un correo usando PHPMailer + los datos SMTP de la tabla.
 *
 * @param string|array|null $destinatarios  Un correo, array de correos, o null
 *                                          para usar los de la tabla (COL_DESTINATARIOS).
 * @param string $asunto
 * @param string $mensaje   Cuerpo del mensaje (se admite HTML simple).
 * @param bool   $esHTML
 * @return array ['ok' => bool, 'error' => string|null]

 */

require_once __DIR__ . '/../vendor/autoload.php'; // Composer: phpmailer/phpmailer
require_once __DIR__ . '/config.php';             // hostBD, usuarioBD, claveBD, nombreBD
require_once __DIR__ . '/log.php';                // registrarLog()

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ======================================================================
// ENDPOINT AJAX (opcional) — permite llamar a este mismo archivo desde jQuery
// Solo actúa como endpoint si viene una petición POST con 'accion=enviar'.
// Si lo estás incluyendo con require_once desde PHP, este bloque no hace nada.
// ======================================================================
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['accion'] ?? '') === 'enviar') {
    header('Content-Type: application/json; charset=utf-8');

    $asunto  = trim($_POST['asunto'] ?? '');
    $mensaje = trim($_POST['mensaje'] ?? '');
    $destino = trim($_POST['destinatario'] ?? ''); // opcional, vacío = usa los de la tabla

    if ($asunto === '' || $mensaje === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Faltan asunto o mensaje']);
        exit;
    }

    $param = json_decode($_POST['parametros'], true);
    $mensaje = $_POST['mensaje'];
    $asunto = $_POST['asunto'];
    
    registrarLog('INFO', 'MAILER', 'Correo solicitado. Parámetros: ' . json_encode($param, JSON_UNESCAPED_UNICODE) . ' | Asunto: ' . $asunto);
    $resultado = enviarCorreo($param, $asunto, $mensaje);
    echo json_encode($resultado);
    exit;
}


function enviarCorreo($parametros, string $asunto, string $mensaje, bool $esHTML = true): array {
 
    // Resolver destinatarios
    // if ($destinatarios === null) {
    //     $raw = $config[COL_DESTINATARIOS] ?? '';
    //     $destinatarios = array_filter(array_map('trim', explode(',', $raw)));
    // } elseif (is_string($destinatarios)) {
    //     $destinatarios = [$destinatarios];
    // }

    if (empty($parametros['destinos'])) {
        registrarLog('ERROR', 'MAILER', 'No hay destinatarios para enviar el correo.');
        return ['ok' => false, 'error' => 'No hay destinatarios configurados'];
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->SMTPDebug  =  2; // agregá esta línea

        $mail->Host       =  $parametros['host'];
        $mail->SMTPAuth   =  true;
        $mail->Username   =  $parametros['user'];
        $mail->Password   =  $parametros['pass']; 
        $mail->SMTPSecure =  $parametros['cifrado'] ?: PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       =  (int)($parametros['puerto'] ?? 587);
        $mail->CharSet    =  'UTF-8';

        $nombreFrom = 'Trace Alertas';

        $mail->setFrom('jpmartinez@mendoza.gov.ar', $nombreFrom);

        $mail->addAddress($parametros['destinos']);
        // foreach ($destinatarios as $correo) {
        //     $mail->addAddress($correo);
        // }
        $mail->isHTML($esHTML);
        $mail->Subject = $asunto;
        $mail->Body    = $mensaje;

        $mail->send();
        $destinatariosTexto = $parametros['destinos'];
        registrarLog('INFO', 'MAILER', 'Correo enviado a: ' . $destinatariosTexto . ' | Asunto: ' . $asunto);
        return ['ok' => true, 'error' => null];

    } catch (Exception $e) {
        $err = $mail->ErrorInfo ?: $e->getMessage();
        registrarLog('ERROR', 'MAILER', 'Fallo al enviar correo: ' . $err);
        return ['ok' => false, 'error' => $err];
    }
}


