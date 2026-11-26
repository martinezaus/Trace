<?php 
    $mostrarMenu = false;
    $headerType = 'index';
    include_once("include/header.php");
?>
	
	
    <div class="login-wrapper">
    <div class="login-panel">
 
        <!-- Logo de la empresa -->
        <div class="login-brand">
            <img src="img/logo_ferroalum_transp.png" alt="Ferroalum">
            <span class="brand-tagline">Sistema de Producción</span>
        </div>
 
        <!-- Formulario + logo del sistema -->
        <div class="login-form-side">
            <div class="system-header">
                <img src="img/tracer.png" alt="Tracer">
                <h2>Trace - Login</h2>
            </div>
            <p class="system-subtitle">Ingrese sus datos para acceder al sistema.</p>
 
            <div class="login-icon-lock">
                <i class="fa fa-lock"></i>
            </div>
 
            <form id="validarOperario" class="form-horizontal">
                <input type="password" class="form-control" name="operario" id="operario" placeholder="Operario" autofocus>
                <small class="sub-title">Ingrese datos para acceder</small>
                <button type="submit" class="btn signin btn-secondary">Ingresar</button>
            </form>
 
            <div id="mensaje"></div>
        </div>
 
    </div>
</div>
<?php 
    
    include_once("include/footer.php"); 
?>