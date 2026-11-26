<?php
require_once __DIR__ . '/includes/auth.php';
require_role(['admin', 'master']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Logs del sistema</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/partials/header.php'; ?>
<main class="container">
  <h2>Logs del sistema</h2>
  <p class="field-hint">Solo lectura. Se muestran las últimas líneas de cada archivo.</p>

  <div class="log-layout">
    <div class="log-list">
      <ul id="logFileList"><li class="field-hint" style="border:none;">Cargando…</li></ul>
    </div>
    <div class="log-view">
      <div class="toolbar">
        <span class="name" id="logName">Seleccione un archivo</span>
        <button type="button" class="secondary" id="btnRefresh" style="display:none;">Actualizar</button>
      </div>
      <pre id="logContent"><span class="empty">Elija un archivo de la lista para ver su contenido.</span></pre>
    </div>
  </div>
</main>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="assets/js/app.js"></script>
<script>
$(function () { initLogViewer(); });
</script>
</body>
</html>
