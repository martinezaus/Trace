<?php
require_once __DIR__ . '/includes/auth.php';
require_role(['admin', 'master']);
$role = get_role();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Panel de configuraciones</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/partials/header.php'; ?>
<main class="container">
  <div class="login-logo" style="text-align:center; margin-bottom:16px;">
    <img src="../../img/isologo.png" alt="Conetia" style="height:206px;">
  </div>
  <h2>Panel de configuraciones</h2>
  <p style="color:var(--text-dim); font-size:12.5px; margin-top:0;">
    Nivel de acceso: <span class="badge badge-<?= $role ?>"><?= strtoupper($role) ?></span>
  </p>

  <div class="cards">
    <a class="card" href="negocio.php">
      <h3>Datos del negocio</h3>
      <p>Relgas de Negocio: Logo, coeficiente de cálculo, escala y tamaño de letra en PDF, color.</p>
    </a>
    <a class="card" href="smtp.php">
      <h3>Correo (SMTP)</h3>
      <p>Correo: Servidor, puerto, cuenta y tipo de cifrado usados para enviar correo.</p>
    </a>
    <!-- <a class="card" href="remoto.php">
      <h3>Conexion Remoto (Cliente)</h3>
      <p>Control escritorio remoto.</p>
    </a> -->
    <a class="card" href="logs.php">
      <h3>Logs del sistema</h3>
      <p>Archivos de funcionalidad del sistema:Ver en pantalla los archivos de registro generados por el sistema.</p>
    </a>
  </div>
  <div class="cards">
  <?php if ($role === 'master'): ?>
    <a class="card" href="basededatos.php">
      <h3>Base de datos</h3>
      <p>Administracion base de datos.</p>
    </a>
    <a class="card" href="usage.php">
      <h3>Sistema y licencia</h3>
      <p>Usuario, claves de acceso, vencimiento de licencia y módulos habilitados.</p>
    </a>
  <?php endif; ?> 
    <a class="card" href="parametros.php">
      <h3>Parametros del Sistema</h3>
      <p>Parametros Tareas, Codigos, Pausas y Suspensiones.</p>
    </a>    
  </div>  

</main>
</body>
</html>
