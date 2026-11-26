<?php
// Valor por defecto si no se definió $headerType
if (!isset($headerType)) $headerType = 'admin';
// --- NUEVA LÓGICA PARA RUTA DINÁMICA ---
// Detecta si es HTTP o HTTPS
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
// Obtiene el dominio (ej: conetia.sistemas.com)
$domain = $_SERVER['HTTP_HOST'];
// Detecta la carpeta del proyecto dinámicamente eliminando el archivo actual de la ruta
$projectFolder = str_replace('/include/header.php', '', $_SERVER['SCRIPT_NAME']);

// Si por alguna razón no se incluye desde /include/, limpiamos rutas comunes o usamos la carpeta base
$projectFolder = preg_replace('#/modules/soporte(/page)?$#', '', dirname($_SERVER['SCRIPT_NAME'])); 

// En tu entorno local/desarrollo esto dará: "/desarrollos/trace"
// En producción (si va en la raíz) dará: ""
$baseUrl = $projectFolder;

if ($headerType === 'admin') {
    require_once __DIR__.'/auth.php';
    if(empty($_SESSION['admin_export_csrf']))$_SESSION['admin_export_csrf']=bin2hex(random_bytes(32));
}

// Título según contexto
$titles = [
    'admin'      => 'Tablero',
    'index'      => 'Proceso',
    'produccion' => 'Proceso',
    'corte'      => 'Proceso Corte',
];
$title = $titles[$headerType] ?? 'Tablero';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0, shrink-to-fit=no' name='viewport'>
    <title><?= $title ?></title>
    <link rel="icon" type="image/png" href="<?= $baseUrl ?>img/tracer.png">
    <?php if ($headerType === 'admin'): ?>
        <link rel="apple-touch-icon" sizes="76x76" href="<?= $baseUrl ?>img/apple-icon.png">
    <?php endif; ?>

    <!-- Fuentes e iconos (compartidos) -->
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700,200" rel="stylesheet">
    <link href="https://maxcdn.bootstrapcdn.com/font-awesome/latest/css/font-awesome.min.css" rel="stylesheet">

    <!-- CSS compartidos -->
    <link href="<?= $baseUrl ?>css/footer.css?v=0008-r2" rel="stylesheet">
    <link href="<?= $baseUrl ?>css/modal.css?v=1.0.0" rel="stylesheet">
    <link href="<?= $baseUrl ?>plugins/datatables/datatables.min.css?v=0008-r2" rel="stylesheet">

    <!-- CSS específico por contexto -->
    <?php if ($headerType === 'index'): ?>
        <link href="<?= $baseUrl ?>vendor/twbs/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="<?= $baseUrl ?>vendor/twbs/bootstrap/dist/css/bootstrap-grid.min.css" rel="stylesheet">
        <link href="<?= $baseUrl ?>css/login.css?v=1.0.0" rel="stylesheet">
        <link href="<?= $baseUrl ?>css/timer.css?v=1.0.0" rel="stylesheet">
        <link href="<?= $baseUrl ?>modules/ia/css/mia.css?v=1.0.0" rel="stylesheet">
    <?php elseif ($headerType === 'admin'): ?>
        <link href="<?= $baseUrl ?>css/bootstrap.min.css" rel="stylesheet">
        <link href="<?= $baseUrl ?>css/paper-dashboard.css?v=2.0.1" rel="stylesheet">
        <link href="<?= $baseUrl ?>css/popper.css?v=1.0.0" rel="stylesheet">
    <?php elseif ($headerType === 'produccion'): ?>
        <link href="<?= $baseUrl ?>vendor/twbs/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="<?= $baseUrl ?>vendor/twbs/bootstrap/dist/css/bootstrap-grid.min.css" rel="stylesheet">
        <link href="<?= $baseUrl ?>css/style.css?v=2.0.1" rel="stylesheet">
        <link href="<?= $baseUrl ?>css/timer.css?v=1.0.0" rel="stylesheet">
        <link href="<?= $baseUrl ?>modules/ia/css/mia.css?v=1.0.0" rel="stylesheet">
    <?php elseif ($headerType === 'corte'): ?>
        <link href="<?= $baseUrl ?>vendor/twbs/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="<?= $baseUrl ?>vendor/twbs/bootstrap/dist/css/bootstrap-grid.min.css" rel="stylesheet">
        <link href="<?= $baseUrl ?>css/corte.css?v=2.0.2" rel="stylesheet">
        <link href="<?= $baseUrl ?>css/timer.css" rel="stylesheet">
     <?php elseif ($headerType === 'soporte'): ?>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

        <style>
            body { overflow-x: hidden; }
            .sidebar a { color: #fff; text-decoration: none; display: block; padding: .6rem .75rem; border-radius: .5rem; }
            .sidebar a:hover { background: rgba(255,255,255,.08); }
            .sidebar a.active { background: rgba(255,255,255,.15); }
            .section { display: none; }
            .active-section { display: block; }
            .table td, .table th { vertical-align: middle; }
            .value-cell { max-width: 420px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

            /* Login overlay */
            .login-overlay {
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,.55);
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 1rem;
                z-index: 2000;
            }
        </style>
    <?php endif; ?>

    <!-- JS compartidos -->
    <script src="<?= $baseUrl ?>plugins/js/jquery.min_3.6.0.js"></script>
    <script src="<?= $baseUrl ?>plugins/datatables/datatables.min.js"></script>

    <!-- JS específico por contexto -->
    <?php if ($headerType === 'admin'): ?>
        <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.0/dist/JsBarcode.all.min.js"></script>
    <?php elseif ($headerType === 'produccion' || $headerType === 'corte'): ?>
        <script type="module" src="<?= $baseUrl ?>/plugins/js/app.js"></script>
    <?php endif; ?>
</head>

<body class="<?= $headerType === 'admin' ? 'admin-page' : '' ?>">
<?php if($headerType === 'admin'): ?><input type="hidden" id="adminExportCsrf" value="<?= htmlspecialchars($_SESSION['admin_export_csrf'],ENT_QUOTES,'UTF-8') ?>"><?php endif; ?>
<?php if ($headerType === 'admin'): ?>
    <div class="wrapper">
        <div class="sidebar" data-color="white" data-active-color="danger">
            <div class="logo">
                <div class="logo-image-big">
                    <img src="<?= $baseUrl ?>img/logo_ferroalum_blco.png" width="100%" height="100%">
                </div>
            </div>
            <?php include_once("menu_lateral.php"); ?>
        </div>
        <div class="main-panel" style="height: 110vh;">
            <nav class="navbar navbar-expand-lg navbar-absolute fixed-top navbar-transparent">
                <div class="container-fluid">
                    <div class="collapse navbar-collapse justify-content-end" id="navigation">
                        <form>
                            <div class="input-group no-border">
                                <div class="input-group-append">
                                    <div class="input-group-text">
                                        <div id="mensaje" style="margin-top: 10px; font-weight: bold;"></div>
                                    </div>
                                </div>
                            </div>
                        </form>
                        <ul class="navbar-nav">
                            <li class="nav-item btn-rotate dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownMenuLink"
                                   data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="fa fa-solid fa-gear"></i>
                                    <p><span class="d-lg-none d-md-block"></span></p>
                                </a>
                                <?php include_once("menu_settings.php"); ?>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>
        <!-- fin navbar admin -->
<?php endif; ?>