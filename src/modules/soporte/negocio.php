<?php
require_once __DIR__ . '/includes/auth.php';
require_role(['admin', 'master']);
use RedBeanPHP\R;

$row = R::findOne('systemnegocio', ' ORDER BY id');

$hasLogo = $row && !empty($row->logo);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Datos del negocio</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/partials/header.php'; ?>
<main class="container">
  <h2>Datos del negocio</h2>
  <div id="alertBox" role="status" aria-live="polite"></div>

  <form id="formNegocio" class="panel" method="post" action="api/save_negocio.php" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

    <label for="logo">Logo del negocio</label>
    <img id="logoPreview" class="logo-preview" <?= $hasLogo ? 'src="api/get_logo.php"' : 'hidden' ?> alt="Logo del negocio">
    <p id="logoEstado" class="field-hint"><?= $hasLogo ? 'Logo guardado actualmente.' : 'Sin logo cargado.' ?></p>
    <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/gif,image/webp">
    <p class="field-hint">PNG, JPG, GIF o WEBP. Máximo 3 MB. Seleccione la imagen y pulse Guardar cambios. Dejar vacío mantiene el logo actual.</p>
    <div class="row">
      <div>
        <label for="coeficientev">Coeficiente V/DVH (corte / armado vidrio)</label>
        <input type="number" step="0.01" min="0" max="999.99" id="coeficientev" name="coeficientev"
               value="<?= htmlspecialchars($row->coeficientev ?? '17.50') ?>" required>
        <label>Para calculo de tiempo proceso de Vidrios/DVH</label>       
      </div>
      <div>
        <label for="escala">Escala del tilde (PDF / leyendas)</label>
        <input type="number" step="0.01" min="0" max="999.99" id="escala" name="escala"
               value="<?= htmlspecialchars($row->escala ?? '1.50') ?>" required>
      </div>
    </div>

    <div class="row">
      <div>
        <label for="tamanio">Tamaño de letra en PDF</label>
        <input type="number" step="1" min="1" max="99" id="tamanio" name="tamanio"
               value="<?= htmlspecialchars($row->tamanio ?? '7') ?>" required>
      </div>
      <div>
        <label for="color">Color</label>
        <input type="text" id="color" name="color" placeholder="#RRGGBB"
               value="<?= htmlspecialchars($row->color ?? '') ?>">
        <label>Cambio de color del sistema Administracion Oscuro/Claro.</label>       
      </div>
    </div>
    <div class="row">  
      <div>
        <label for="color">Tiempo Corte Perfil</label>
        <input type="text" id="tiempo_cortep" name="tiempo_cortep" placeholder="#"
               value="<?= htmlspecialchars($row->tiempo_cortep ?? 0) ?>">
        <label>tiempo estimado tarea corte perfil(aluminio)</label>       
      </div>
      <div>
        <label for="color">Tiempo Corte Vidrio</label>
        <input type="text" id="tiempo_cortev" name="tiempo_cortev" placeholder="#"
               value="<?= htmlspecialchars($row->tiempo_cortev ?? 0) ?>">
        <label>tiempo estimado tarea corte de vidrio.</label>       
      </div>
    </div>

    <div class="actions">
      <button type="submit">Guardar cambios</button>
      <span id="savingMsg" class="field-hint"></span>
    </div>
  </form>
</main>
<script src="assets/js/negocio.js"></script>
</body>
</html>
