<?php
    $headerType = 'admin';
    include_once("include/header.php");
    include_once("api/estadisticas.php");

  $asignadas=getCharData("SELECT DATE_FORMAT(d.created, '%m') AS mes, COUNT(d.id) AS cantidad FROM detalles d WHERE YEAR(d.created) = :year  GROUP BY mes ORDER BY mes ASC");
  $terminadas=getCharData("SELECT DATE_FORMAT(d.`update`, '%m') AS mes, COUNT(d.id) AS cantidad FROM detalles d WHERE YEAR(d.`update`) = :year AND d.c_estado = 3 GROUP BY mes ORDER BY mes ASC");
  $tareasOperarios=getCharDatatwo("SELECT o.id AS operario, o.nombre AS nombre, COUNT(d.id) AS cantidad FROM detalles d inner join operadores o on o.id = d.operador_id WHERE YEAR(d.`update`) = :year AND d.c_estado = 3 GROUP BY operario");

  $prodSusp = contarDetalles_producidasSuspendidas();

?>
    <div class="content">

        <div class="row">

            <div class="col-12">
                <div class="card shadow rounded-4" style="background: transparent;">
                    <div class="card-header bg-primary text-white rounded-top-4">
                        <h5 class="mb-0">Tipologías creadas / terminadas</h5>
                    </div>
                    <div class="card-body ">
                        <div class="chart-wrap-grey chart-wrap-grey--lg">
                            <canvas id="chartTareasAsigTerm"></canvas>
                        </div>

                    </div>

                    <div class="card-footer ">
                        <hr>
                        <div class="stats">
                            <i class="fa fa-history"></i> Actualiza cada 3 minutos
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col">
                <div class="card shadow rounded-4">
                    <div class="card-header bg-primary text-white rounded-top-4">
                        <h5 class="mb-0">Tipologías terminadas por operario </h5>
                    </div>

                    <div class="card-body ">
                        <div class="chart-wrap-grey chart-wrap-grey--sm">
                            <canvas id="chartOperariosTareas"></canvas>
                        </div>
                    </div>

                    <div class="card-footer ">
                        <hr>
                        <div class="stats">
                            <i class="fa fa-history"></i> Actualiza cada 3 minutos
                        </div>
                    </div>
                </div>
            </div>

            <div class="col">
                <div class="card shadow rounded-4">
                    <div class="card-header bg-primary text-white rounded-top-4">
                        <h5 class="mb-0">Tipologías terminadas / suspendidas</h5>
                    </div>

                    <div class="card-body ">
                        <div class="chart-wrap-grey chart-wrap-grey--sm">
                            <canvas id="chartTareasProdSusp"></canvas>
                        </div>
                    </div>

                    <div class="card-footer ">
                        <hr>
                        <div class="stats">
                            <i class="fa fa-history"></i> Actualiza cada 3 minutos
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <link rel="stylesheet" href="css/estadisticas.css">
    <script>
		$(document).ready(function(){
            const tareasasignadas = <?php echo $asignadas; ?>;
            const tareasterminadas = <?php echo $terminadas; ?>;
            const tareasroperarios = <?php echo $tareasOperarios; ?>;
            const prodSusp = <?php echo json_encode($prodSusp, JSON_NUMERIC_CHECK); ?>;

            grafico.initChartsTareas(tareasasignadas.datasets[0].data, tareasterminadas.datasets[0].data);
            grafico.initCharsTareasOperarios(tareasroperarios.datasets[0].data);
            grafico.initCharsTareasPS(prodSusp.producidas, prodSusp.suspendidas);

            setInterval(function() {
                location.reload();
            }, 180000);
		});
	</script>
<?php
   include_once("include/footer.php");
?>