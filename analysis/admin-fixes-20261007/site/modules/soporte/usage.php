<?php
require_once __DIR__ . '/includes/auth.php';
require_role(['master']);
use RedBeanPHP\R;

$row = R::findOne('systemusage');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Sistema y licencia</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/partials/header.php'; ?>
<main class="container">
  <h2>Sistema y licencia</h2>
  <p class="field-hint">Sección visible solo con clave master.</p>
  <div id="alertBox"></div>

  <form id="formUsage" class="panel">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

    <div class="row">
      <div>
        <label for="nombre">Nombre / titular</label>
        <input type="text" id="nombre" name="nombre" maxlength="100" required
               value="<?= htmlspecialchars($row->nombre ?? '') ?>">
      </div>
      <div>
        <label for="username">Usuario</label>
        <input type="text" id="username" name="username" maxlength="10" required
               value="<?= htmlspecialchars($row->username ?? '') ?>">
      </div>
    </div>

    <div class="row">
      <div>
        <label for="version">Versión</label>
        <input type="text" id="version" name="version" maxlength="7"
               value="<?= htmlspecialchars($row->version ?? '') ?>">
      </div>
      <div>
        <label for="licencia">Vencimiento de licencia</label>
        <input type="date" id="licencia" name="licencia"
               value="<?= htmlspecialchars($row->licencia ?? '') ?>">
      </div>
    </div>

    <div class="checkline">
      <input type="checkbox" id="enabled" name="enabled" <?= !empty($row->enabled) ? 'checked' : '' ?>>
      <label for="enabled">Sistema habilitado</label>
    </div>

    <hr style="border-color:var(--line); margin:18px 0;">

    <div class="row">
      <div>
        <label for="password">Nueva clave ADMIN</label>
        <input type="password" id="password" name="password" autocomplete="new-password"
               placeholder="Dejar vacío para no cambiar">
      </div>
      <div>
        <label for="pwmaster">Nueva clave MASTER</label>
        <input type="password" id="pwmaster" name="pwmaster" autocomplete="new-password"
               placeholder="Dejar vacío para no cambiar">
      </div>
    </div>
    <p class="field-hint">
      Ambas claves se guardan con hash. Si desea usar bcrypt en lugar del
      formato MD5 heredado, amplíe las columnas <code>password</code> y
      <code>pwmaster</code> a <code>varchar(255)</code> (ver
      <code>sql/migration_security.sql</code>); el sistema detecta el ancho
      automáticamente.
    </p>

    <hr style="border-color:var(--line); margin:18px 0;">

    <label>Módulos habilitados</label>
    <div class="checkline">
      <input type="checkbox" id="soporte" name="soporte" <?= !empty($row->soporte) ? 'checked' : '' ?>>
      <label for="soporte">Soporte</label>
    </div>
    <div class="checkline">
      <input type="checkbox" id="ia" name="ia" <?= !empty($row->ia) ? 'checked' : '' ?>>
      <label for="ia">Inteligencia artificial</label>
    </div>
    <div class="checkline">
      <input type="checkbox" id="personal" name="personal" <?= !empty($row->personal) ? 'checked' : '' ?>>
      <label for="personal">Personal</label>
    </div>
    <div class="checkline">
      <input type="checkbox" id="instalacion" name="instalacion" <?= !empty($row->instalacion) ? 'checked' : '' ?>>
      <label for="instalacion">Instalación y logística</label>
    </div>

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
  bindSimpleForm('#formUsage', 'api/save_usage.php');
});
</script>
</body>
</html>
