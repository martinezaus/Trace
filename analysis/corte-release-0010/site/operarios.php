<?php
    require_once __DIR__ . '/include/auth.php';
    // El panel administrativo no usa el login de producción del operario.
    if (empty($_SESSION['credencial_impresion'])) {
        $_SESSION['credencial_impresion'] = bin2hex(random_bytes(32));
    }
    $headerType = 'admin';
    include_once("include/header.php");
?>
    <div class="content" id="panelOperarios" data-impresion="<?= htmlspecialchars($_SESSION['credencial_impresion'], ENT_QUOTES, 'UTF-8') ?>">
        

        <br>
        <div class="card shadow rounded-4">
            <div class="card-header bg-primary text-white rounded-top-4 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Operarios</h5>
                <div class="d-flex align-items-center ">
                    <a href="javascript:;" id="operario_add" class="me-3">
                        <img src="img/icons/icons8-añadir-32.png" title="Agregar" />
                    </a>
                    <!--
                    <a href="javascript:;" id="asistencia">
                        <img src="img/icons/icons8-asistencia-32.png" title="asistencia" />
                    </a>
                    -->
                </div>    
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table" id="tabla_operarios">
                        <thead class="text-primary">
                            <th class="text-center">#</th>
                            <th class="text-center">Apellido, Nombre</th>
                            <th class="text-center">Estado</th>
                            <th class="text-center">Token</th>
                            <th class="text-center">Acciones</th>
                        </thead>
                        <tbody class="text-center">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>    

<?php
   include_once("include/modal.php");
   include_once("include/footer.php");
?>