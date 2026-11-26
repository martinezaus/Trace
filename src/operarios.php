<?php
    require_once __DIR__ . '/include/auth.php';
    // El panel administrativo no usa el login de producción del operario.
    if (empty($_SESSION['credencial_impresion'])) {
        $_SESSION['credencial_impresion'] = bin2hex(random_bytes(32));
    }
    $headerType = 'admin';
    include_once("include/header.php");
?>
    <style>
        body.operarios-page .main-panel { height:auto !important; min-height:100vh; }
        #panelOperarios .card-header { background:#4fc6c9; }
        #panelOperarios .card-header h5 { color:#fff; }
        #panelOperarios #operariosExports { display:flex; gap:3px; }
        #panelOperarios #operariosExports .btn {
            background:#fff;
            border:1px solid #35c3c7;
            border-radius:3px;
            color:#32bfc3 !important;
            font-size:10px;
            line-height:1.4;
            padding:4px 11px;
            text-transform:uppercase;
        }
        #panelOperarios #operariosExports .btn:hover,
        #panelOperarios #operariosExports .btn:focus { background:#e9ffff; }
        #panelOperarios #operario_add {
            align-items:center;
            background:#fff;
            border:1px solid #35c3c7;
            border-radius:3px;
            color:#32bfc3;
            display:flex;
            font-size:20px;
            height:40px;
            justify-content:center;
            margin:0 0 0 10px;
            min-width:44px;
            padding:0 10px;
        }
        #panelOperarios #operario_add:hover,
        #panelOperarios #operario_add:focus { background:#e9ffff; }
        #tabla_operarios thead th {
            color:#35bfc3;
            font-size:11px;
            font-weight:600;
            text-transform:uppercase;
        }
        #tabla_operarios tbody td { vertical-align:middle; }
        #tabla_operarios tbody .btn-outline-secondary {
            background:#fff;
            border-color:#c8c8c8;
            border-radius:3px;
            line-height:1;
            padding:3px 7px;
        }
        #tabla_operarios .badge { padding:8px 17px; }
        @media (max-width:575px) {
            #panelOperarios .card-header { flex-wrap:wrap; gap:8px; }
            #panelOperarios #operario_add { margin:0; min-height:40px; }
        }
    </style>
    <script>document.body.classList.add('operarios-page');</script>
    <div class="content" id="panelOperarios" data-impresion="<?= htmlspecialchars($_SESSION['credencial_impresion'], ENT_QUOTES, 'UTF-8') ?>">
        

        <br>
        <div class="card shadow rounded-4">
            <div class="card-header rounded-top-4 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Operarios</h5>
                <div class="d-flex align-items-center">
                    <div id="operariosExports" aria-label="Exportar tabla"></div>
                    <button type="button" id="operario_add" aria-label="Agregar operario" title="Agregar operario">
                        <i class="fa fa-user-plus" aria-hidden="true"></i>
                    </button>
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
                        <thead>
                            <tr>
                                <th class="text-center">#</th>
                                <th class="text-center">Apellido, Nombre</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Token</th>
                                <th class="text-center">Acciones</th>
                            </tr>
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