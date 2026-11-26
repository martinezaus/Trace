<?php
require_once __DIR__ . '/includes/auth.php';
require_role(['admin', 'master']);
use RedBeanPHP\R;

$row = R::findOne('systemsmtp');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Configuración SMTP</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/partials/header.php'; ?>
<main class="container">
  <h2>Correo saliente (SMTP)</h2>
  <div id="alertBox"></div>

  <form id="formSmtp" class="panel">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

    <label for="host">Host</label>
    <input type="text" id="host" name="host" required
           value="<?= htmlspecialchars($row->host ?? '') ?>" placeholder="smtp.miservidor.com">

    <div class="row">
      <div>
        <label for="port">Puerto</label>
        <input type="number" id="port" name="port" min="1" max="65535" required
               value="<?= htmlspecialchars($row->port ?? '587') ?>">
      </div>
      <div>
        <label for="encryption">Cifrado</label>
        <select id="encryption" name="encryption">
          <?php $enc = $row->encryption ?? 'tls'; foreach (['none','ssl','tls','starttls'] as $opt): ?>
            <option value="<?= $opt ?>" <?= $enc === $opt ? 'selected' : '' ?>><?= strtoupper($opt) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <label for="email">Cuenta de correo - DESDE</label>
    <input type="email" id="email" name="email" required
           value="<?= htmlspecialchars($row->email ?? '') ?>" placeholder="notificaciones@miempresa.com">
    
    <!-- <label for="server">server</label>
    <input type="password" id="server" name="server" 
           value="" placeholder="Dejar vacío para no cambiar" autocomplete="new-password">
    <p class="field-hint">Por seguridad, la clave actual no se muestra. Completar solo si desea cambiarla.</p> -->

    <label for="email">Cuenta de correo - A</label>
    <input type="email" id="email_a" name="email_a" required
           value="<?= htmlspecialchars($row->to ?? '') ?>" placeholder="">  
    <span>Si agrega mas de un destinatario separar por [ , ] </span>
    
    <div class="actions">
      <button type="submit">Guardar cambios</button>
      <span id="savingMsg" class="field-hint"></span>
    </div>
  </form>
</main>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="assets/js/app.js"></script>
<script>
$(function () {
  bindSimpleForm('#formSmtp', 'api/save_smtp.php');
});
</script>
</body>
</html>
