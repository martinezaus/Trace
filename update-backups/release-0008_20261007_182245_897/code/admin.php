
    <?php
      $headerType = 'admin';
      include_once("include/header.php");
      include_once("api/estadisticas.php");
    ?>   

    <div class="content">
        <div class="row">
          <div class="col-md-12">
            <div class="row">
                <div class="col-lg-12 col-md-6 col-sm-6">
                    <div class="card card-stats">
                        <div class="card-body ">
                            <div class="row">
                                <div class="col-5 col-md-4">
                                    <div class="icon-big text-center icon-warning">
                                        <i class="fa fa-solid fa-upload text-warning"></i>
                                    </div>
                                </div>
                                <div class="col-7 col-md-8">
                                    <div class="numbers">
                                        <p class="card-category">Cargar</p>
                                        <p class="card-title">
                                            <button class="btn btn-primary" id="archivo">Archivo</button>
                                        <p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer ">
                            <hr>
                            <div class="stats">
                                <i class="fa fa-refresh"></i>
                                <a href="ordenes.php">ver</a>
                            </div>
                        </div>
                    </div> 
                </div>
            </div>    
            <div class="row">
                <div class="col-lg-3 col-md-6 col-sm-6">
                    <div class="card card-stats">
                        <div class="card-body ">
                            <div class="row">
                                <div class="col-5 col-md-4">
                                    <div class="icon-big text-center icon-warning">
                                        <i class="fa fa-solid fa-user text-warning"></i>
                                    </div>
                                </div>
                                <div class="col-7 col-md-8">
                                    <div class="numbers">
                                        <p class="card-category">Operadores</p>
                                        <p class="card-title"><?php echo contarOperadores(); ?><p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer ">
                            <hr>
                            <div class="stats">
                                <i class="fa fa-refresh"></i>
                                <a href="operarios.php">ver</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 col-sm-6">
                    <div class="card card-stats">
                        <div class="card-body ">
                            <div class="row">
                                <div class="col-5 col-md-4">
                                    <div class="icon-big text-center icon-warning">
                                        <i class="fa fa-solid fa-list-ol text-success"></i>
                                    </div>
                                </div>
                                <div class="col-7 col-md-8">
                                    <div class="numbers">
                                        <p class="card-category">Tipologias</p>
                                        <p class="card-title">
                                            <?php echo contarLotes_tipologias(); ?>
                                        <p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer ">
                            <hr>
                            <div class="stats">
                                <i class="fa fa-calendar-o"></i>
                                <?php echo date("d-m-Y"); ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 col-sm-6">
                    <div class="card card-stats">
                        <div class="card-body ">
                            <div class="row">
                                <div class="col-5 col-md-4">
                                    <div class="icon-big text-center icon-warning">
                                        <i class="fa fa-regular fa-clipboard text-error"></i>
                                    </div>
                                </div>
                                <div class="col-7 col-md-8">
                                    <div class="numbers">
                                        <p class="card-category">Cargadas</p>
                                        <p class="card-title"><?php echo contarOrdenes_cargadas(); ?><p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer ">
                            <hr>
                            <div class="stats">
                                <!-- <i class="fa fa-refresh"></i> -->
                                ordenes
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 col-sm-6">
                    <div class="card card-stats">
                        <div class="card-body ">
                            <div class="row">
                                <div class="col-5 col-md-4">
                                    <div class="icon-big text-center icon-warning">
                                        <i class="fa fa-regular fa-clipboard text-success"></i>
                                        
                                    </div>
                                </div>
                                <div class="col-7 col-md-8">
                                    <div class="numbers">
                                        <p class="card-category">Terminadas</p>
                                        <p class="card-title"><?php echo contarOrdenes_terminadas(); ?><p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer ">
                            <hr>
                            <div class="stats">
                                <!-- <i class="fa fa-clock-o"></i> -->
                                <?php //echo date('h:m:s'); ?>
                                ordenes
                            </div>
                        </div>
                    </div>
                </div>
            </div>
			<div class="row">
                <div class="col-lg-12 col-md-6 col-sm-6">
                    <div class="card border-0 shadow-sm mb-4 rounded-4">
                        <div class="card-header bg-primary text-white rounded-top-4">
                            <h5 class="card-title mb-0">
                                <i class="fa fa-solid fa-filter"></i>Ordenes
                            </h5>
                            <small class="text-muted">Evolucion Ordenes Cargadas/Procesadas por mes</small>
                        </div>
                        <div class="card-body">
                            <canvas id=graficoOrdenes height="55"></canvas>
                        </div>
                    </div>
                </div>
            </div>
          </div>
        </div>
    </div>

	<?php include_once('include/modal.php'); ?>

	<script>
        let chart, actualizandoGrafico=false;
        async function obtenerDatos(statusFilter, additionalCondition) {
            const response = await fetch('./api/ordenes.php',{
                                         method: 'POST',
                                         headers: {"Content-Type": "application/x-www-form-urlencoded"},
                                         body: new URLSearchParams({accion: 'chartO', filter: statusFilter, condition: additionalCondition})
            });
            return await response.json();
        }

        async function inicializarGrafico() {
            // Estados segun el sistema actual:
            // 0 = Cargadas, 1 = Procesadas, 2 = Suspendidas, 3 = Terminadas, 4 = Traslado, 5 = Instaladas 
            const ordenes_cargadas = await obtenerDatos(0,'');
            const ordenes_procesando = await obtenerDatos(1,'');
            const ordenes_terminadas = await obtenerDatos(2,'');
            const ordenes_intaladas = await obtenerDatos(3,'');
            const ctx = document.getElementById('graficoOrdenes').getContext('2d');

            if(chart)chart.destroy();
            chart = new Chart(ctx, {
              type: 'line',
              data: {
                labels: ordenes_cargadas.labels,
                datasets: [
                  {
                    label: 'Ordenes Cargadas',
                    data: ordenes_cargadas.data,
                    borderColor: 'rgba(255, 99, 132, 1)',
                    backgroundColor: 'rgba(255, 99, 132, 0.25)',
                    borderWidth: 2,
                    tension: 0.25
                  },
                  {
                    label: 'Ordenes Procesando',
                    data: ordenes_procesando.data,
                    borderColor: 'rgba(54, 162, 235, 1)',
                    backgroundColor: 'rgba(54, 162, 235, 0.25)',
                    borderWidth: 2,
                    tension: 0.25
                  },
                  {
                    label: 'Ordenes Terminadas',
                    data: ordenes_terminadas.data,
                    borderColor: 'rgba(25, 135, 84, 1)',
                    backgroundColor: 'rgba(25, 135, 84, 0.25)',
                    borderWidth: 2,
                    tension: 0.25
                  },
                  {
                    label: 'Ordenes Instaladas',
                    data: ordenes_intaladas.data,
                    borderColor: 'rgba(255, 193, 7, 1)',
                    backgroundColor: 'rgba(255, 193, 7, 0.25)',
                    borderWidth: 2,
                    tension: 0.25
                  }
                ]
              },
              options: {
                responsive: true,
                animation: {
                  // Animación con delay para que se vea el avance
                  duration: 900,
                  easing: 'easeOutQuart',
                  delay: (context) => {
                    const index = context.dataIndex || 0;
                    return index * 80; // delay por punto
                  }
                },
                title: { display: true, text: 'Órdenes por mes/año: estados actuales' },
                scales: {
                  xAxes: [{ ticks: { autoSkip: true, maxRotation: 0, minRotation: 0 } }],
                  yAxes: [{ ticks: { beginAtZero: true, precision: 0 } }]
                }
              }
            });
        }

        async function actualizarGrafico() {
            if(!chart || actualizandoGrafico)return;actualizandoGrafico=true;
            try {
            const ordenes_cargadas_nvas = await obtenerDatos(0,'');
            const ordenes_procesando_nvas = await obtenerDatos(1,'');
            const ordenes_terminadas_nvas = await obtenerDatos(2,'');
            const ordenes_instaladas_nvas = await obtenerDatos(3,'');

            chart.data.labels = ordenes_cargadas_nvas.labels;
            chart.data.datasets[0].data = ordenes_cargadas_nvas.data;
            chart.data.datasets[1].data = ordenes_procesando_nvas.data;
            chart.data.datasets[2].data = ordenes_terminadas_nvas.data;
            chart.data.datasets[3].data = ordenes_instaladas_nvas.data;

            chart.update();
            }catch(e){console.error(e);}finally{actualizandoGrafico=false;}
        }

        // La instancia se crea una sola vez al estar lista la página.

        // Actualiza cada 5 segundos 
        setInterval(actualizarGrafico, 5000);
        
        /** Cargo los archivos .dat, .pdf */
        $("#archivo").on('click',function(){
            //Verifico que antes de comenzar a cargar exista por lo menos un operdaor cargado
            $.post("api/operadores.php",{'accion':'load'}, function(response){
                if (response.length > 0) {
                    $(".modal-title").html('Archivos');
                    $(".modal-body").load("./include/pages/upload.php",function(){  //Funcion que carga los archivos
                        $('#modal').modal('toggle');				   
                    });
                }else{
                    mostrarMensaje("NO existen operadores cargados, cargue los Operarios.","#BD1A00",20000);
                }
            });
        });


	    $(document).ready(function(){
            // Inicializa el gráfico
            inicializarGrafico().catch(e=>mostrarMensaje('No se pudo cargar el gráfico. Actualice la página.','red',10000));

            //verifico la Fecha de la licencia.
            $.ajax({
                  url: "api/system.php",
                  type: "POST",
                  data: {'accion': 'enabled'},
                  success: function(response){
                     //console.log(response);
                     if(response==1){
                        $("body").append('<div id="overlay" style="position:fixed; top:0; left:0; width:100%; height:100%; background:#000000aa; color:white; display:flex; align-items:center; justify-content:center; font-size:24px; z-index:9999;">Acceso denegado. Licencia vencida. Comuniquese con soporte</div>');
                        $("body *:not(#overlay)").css("pointer-events", "none"); // bloquea interacciones                        
                     } 
                  }                
            }); 
	    });  
	</script>

    <?php
        include_once("include/footer.php");
    ?>   
