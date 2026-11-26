</div>
<?php if ($headerType === 'admin'): ?>
    </div>
<?php endif; ?>

    <footer class="footer">
        © 2025, realizado por 
        <a href="https://www.conetia.com.ar" target="_blank">
            <img src="img/isologo.png" alt="Isologo">
        </a>.
        <?php if ($headerType === 'admin'): ?>
            <a href="javascript:;" id="licencia" name="licencia">Términos y Condiciones</a>

        <?php endif; ?>
    </footer>

    <!-- Bootstrap -->
    <?php if ($headerType === 'admin'): ?>
        <script src="<?= $baseUrl ?>plugins/bootstrap/popper.min.js"></script>
        <script src="<?= $baseUrl ?>plugins/bootstrap/bootstrap.min.js"></script>
        <script src="<?= $baseUrl ?>plugins/charts-c3/js/chartjs.min.js"></script>
        <script src="<?= $baseUrl ?>plugins/bootstrap/bootstrap-notify.js"></script>
    <?php else: ?>
        <script src="<?= $baseUrl ?>vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <?php endif; ?>

    <!-- JS compartido -->
    <script src="<?= $baseUrl ?>plugins/js/jquery.window.js"></script>

    <!-- JS específico por contexto -->
    <?php switch ($headerType):
        case 'admin': ?>

            <script src="<?= $baseUrl ?>plugins/js/jquery.functions.js"></script>
            <script src="<?= $baseUrl ?>plugins/js/jquery.menu_interaccion.js"></script>
            <script src="<?= $baseUrl ?>plugins/js/jquery.soporte.js"></script>
            <script src="<?= $baseUrl ?>plugins/js/jquery.fecha.js"></script>
            <script src="<?= $baseUrl ?>plugins/js/admin_exports.js?v=0008-r2"></script>
            <script src="<?= $baseUrl ?>plugins/js/jquery.ordenes.js?v=0008-r2"></script>
            <script src="<?= $baseUrl ?>plugins/js/negocio_logo.js?v=20260923"></script>
            <script src="<?= $baseUrl ?>plugins/js/jquery.operarios.js?v=0008-r2"></script>
            <script src="<?= $baseUrl ?>plugins/js/jquery.grafico.js?v=0008-r2"></script>
            <script src="<?= $baseUrl ?>plugins/js/jquery.reportes.js?v=20260928-productividad"></script>
            <script src="<?= $baseUrl ?>plugins/js/jquery.archivo.js?v=0008-r2"></script>  
            <script>
                setInterval(() => {
                    $.ajax({
                        url: 'api/operadores.php',
                        data: { 'accion': 'productividad' },
                        type: 'POST'
                    });
                }, 3600000);
            </script>
            <?php break;

        case 'produccion': ?>

            <script src="<?= $baseUrl ?>plugins/js/jquery.functions.js"></script>
            <script src="<?= $baseUrl ?>plugins/js/jquery.tareas.js"></script>
            <script src="<?= $baseUrl ?>plugins/js/jquery.cronometro.js"></script>
            <script src="<?= $baseUrl ?>plugins/js/jquery.lecturabarcode.js"></script>
            <script src="<?= $baseUrl ?>plugins/js/jquery.fecha.js"></script>
            <script src="<?= $baseUrl ?>plugins/js/jquery.procesoV1.0.3.js?v=20261005-procesos-1"></script>
            <script src="<?= $baseUrl ?>plugins/js/jquery.procesopausaV1.0.3.js?v=20261005-procesos-1"></script>
            <script src="<?= $baseUrl ?>plugins/js/jquery.procesosuspenderV1.0.3.js?v=20261005-procesos-1"></script>
            <script src="<?= $baseUrl ?>plugins/js/jquery.documentos.js?v=20260928-1"></script>
            <script src="<?= $baseUrl ?>plugins/js/jquery.session.js"></script>
            <?php break;

        case 'corte': ?>

            <script src="<?= $baseUrl ?>plugins/js/jquery.functions.js"></script>
            <script src="<?= $baseUrl ?>plugins/js/jquery.lecturabarcode.js"></script>
            <script src="<?= $baseUrl ?>plugins/js/jquery.fecha.js"></script>
            <script src="<?= $baseUrl ?>plugins/js/jquery.corte_pdf.js?v=20261005-procesos-1"></script>
            <script src="<?= $baseUrl ?>plugins/js/jquery.corte_documentos.js?v=20261005-procesos-1"></script>
            <script src="<?= $baseUrl ?>plugins/js/jquery.archivo.js?v=0008-r2"></script>
            <script src="<?= $baseUrl ?>plugins/js/jquery.session.js"></script>
            <?php break;

        case 'index': ?>

            <script src="<?= $baseUrl ?>plugins/js/jquery.functions.js"></script>
            <script src="<?= $baseUrl ?>plugins/js/jquery.session.js"></script>
            <?php break;

        case 'soporte': ?>

            <script src="<?= $baseUrl ?>plugins/js/jquery.functions.js"></script>
            <script src="<?= $baseUrl ?>modules/soporte/js/jquery.dashboard.js"></script>  
            <script src="<?= $baseUrl ?>plugins/js/jquery.session.js"></script>          
            <?php break;

    endswitch; ?>

</body>
</html>