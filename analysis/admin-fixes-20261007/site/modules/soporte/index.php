<?php
require_once __DIR__ . '/includes/auth.php';
use RedBeanPHP\R;

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_lockout();
    if (!verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'Sesión inválida. Recargue la página e intente de nuevo.';
    } else {
        $usuario = $_POST['usuario'];
        $clave = (string) ($_POST['clave'] ?? '');
        if ($usuario !== SYSTEM_USER || $clave === '') {
            register_failed_attempt();
            $error = 'Usuario o clave incorrectos.';
        } else {
            // Se asume una única fila de configuración del sistema (id = 1)
            $sys = R::findOne('systemusage');
            $role = null;

            if ($sys) {
                if (verify_password($clave, (string) $sys->pwmaster)) {
                    $role = 'master';
                } elseif (verify_password($clave, (string) $sys->password)) {
                    $role = 'admin';
                }
            }

            if ($role !== null) {
                session_regenerate_id(true);
                $_SESSION['auth_role'] = $role;
                $_SESSION['auth_user'] = SYSTEM_USER;
                reset_failed_attempts();
                header('Location: dashboard.php');
                exit;
            }

            register_failed_attempt();
            $error = 'Usuario o clave incorrectos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Configuraciones · Acceso</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-body">
  <div class="login-box">
    <div class="login-logo" style="text-align:center; margin-bottom:16px;">
      <img src="../../img/isologo.png" alt="Conetia" style="height:206px;">
    </div>
    <h1>Configuraciones</h1>
    <p class="subtitle">Acceso restringido del sistema</p>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php elseif (isset($_GET['timeout'])): ?>
      <div class="alert alert-info">La sesión expiró por inactividad.</div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <label for="usuario">Usuario</label>
      <input type="text" id="usuario" name="usuario" value="root" readonly>
      <label for="clave">Clave</label>
      <input type="password" id="clave" name="clave" required autofocus>
      <button type="submit">Ingresar</button>
    </form>
  </div>
</body>
</html>
