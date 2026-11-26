<?php 

    require_once __DIR__ . '/include/auth.php'; 
    requerirOperadorPagina();
	$mostrarMenu=true;
    $headerType = 'produccion';
    include_once("include/header.php");
	if (!empty($mostrarMenu) && $mostrarMenu === true){
		//lista de tareas - bardcode
		//include_once("menu_barcode.php"); 
	}  
?>
<script>const OPERADOR_ID = <?php echo operadorId(); ?>;</script>
<div class="page-wrapper">
	
	<!-- Sidebar Titulo, Button(cambio de menu) e Img -->
	<div class="sidebar" id="sidebar">
		<div class="logo-container">
			<img src="img/logo_ferroalum_blco.png" alt="Logo Empresa">
		</div>
        </br>
		<!-- <h5 class="menu-title" id="menuTitle"></h5> -->
		<button class="btn btn-primary w-100 mb-3" id="switchMenu">Cambiar Menú</button>
		<h5 class="menu-title" id="menuTitle"></h5>
		<hr> 
		<!-- MENU de codigos QR Tareas -->
		<div id="qrGrid" class="qr-grid"></div>
	</div>

	<!-- Contenido principal -->
	<div class="content">
		<div class="row">
		<form class="col-lg-12" id="formProcesoProduccion">
			<div class="row g-3">
			    <div class="col-lg-12">
				    <div class="card card-stats">
					    <div class="card-body">
						    <div class="row">
							    <div class="col-12">
								    <div id="mensaje" style="font-weight: bold;"></div>    
							    </div>
						    </div>
					    </div>
				    </div>
			    </div>    
			</div>        
			<hr>
			<!-- Tarjetas superiores ORDEN -->
			<div class="row g-3">
				<div class="col-lg-12">
					<div class="card shadow">
						<div class="card-header">Orden</div>
						<div class="card-body">
							<div class="row">
								<div class="col-md-4">
									<div class="card card-stats card-description">
										<div class="card-header">Archivo</div>
										<div class="card-body" style="display: flex; justify-content: center;">
											<img class="form-control" id="barcode_Opdf" style="width:200px;"></br>
										</div>
										<div class="card-footer">
                                            escanee la imagen para ver la orden de trabajo.
                                        </div>    
									</div>
								</div>
								<div class="col-lg-4">
									<div class="card card-stats card-description">
										<div class="card-header">Descripcion</div>
										<div class="card-body">
											<label class="form-control" id="detalle"></label>
										</div>
										<div class="card-footer"></div>    
									</div>
								</div>
								<div class="col-lg-4">
									<div class="card card-stats card-description">
										<div class="card-header">Tipologias</div>
										<div class="card-body">
                                            <img id="barcode_orden_img" style="width:70px; height:70px; flex-shrink:0; margin-right:10px;">
											<strong id="topologia"></strong>
										</div>
										<div class="card-footer"></div>    
									</div>
								</div>                                                        

							</div>
						</div>
					</div>
				</div>
			</div>
			<hr>
			
			<!-- TAREA / OPERADOR -->
			<div class="row g-3">
				<!-- Tarea -->
				<div class="col-lg-6">
					<div class="card card-stats">
						<div class="card-body">
							<div class="row">
								<div class="col-2">
									<i class="fa fa-clipboard"></i>
								</div>
								<div class="col-10">
									Tarea a realizar:</br> 
									<strong id="tarea_nombre" style="font-size: 15px; color:#FF0000"></strong>
								</div>
							</div>
						</div>
					</div>
				</div>
				<!-- Operarios -->
				<div class="col-lg-6">
					<div class="card card-stats">
						<div class="card-body">
							<div class="row">
								<div class="col-2">
									<i class="fa fa-user"></i>
								</div>
								<div class="col-9">
									<label class="" id="label_operario"></label>
									<label class="" id="label_tpo_operario"></label>
                                    <label class="" id="label_operario_aux"></label>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<hr>

			<!-- temporizador Orden / Pausa -->
			<div class="row g-3">
				<!-- Orden -->
				<div class="col-lg-6">
					<div class="card card-stats">
						<div class="card-body">
							<div class="row">
								<div class="col-3">
									<i class="fa fa-regular fa fa-clock-o"></i>
								</div>
								<div class="col-9">
									Temporizador: <strong>[Orden]:</strong> 
									<div class="cronometro" id="timerO">00:00:00.00</div>
								</div>
							</div>
						</div>
						<div class="card-footer">
                            Pausa:   <label class="label-text text-danger" id="label_pausa_descripcion"></label>
							<div class="row card-footer">
								<div class="col-10">
									<div id="timerP" class="cronometro">00:00:00.00</div>
								</div>
								<div class="col-2" style="display:flex; align-items:center; justify-content:flex-end;">
									<img id="barcode_accion" style="width: 65px;">
								</div>                               
							</div>
						</div>
					</div>
				</div>

				<!-- Temporizador -->
				<div class="col-lg-6">
					<div class="card card-stats">
						<div class="card-body">
							<div class="row">
								<div class="col-3">
									<i class="fa fa-regular fa fa-clock-o"></i>
								</div>
								<div class="col-9">
									Temporizador: <strong>[Tarea]</strong> - Tiempo Ideal: [<strong class="text" id="label_tpo_ideal"></strong>]
									<div class="cronometro" id="timerT">00:00:00.00</div>
								</div>
							</div>
						</div>
						<div class="card-footer">
							<div class="row">
                                <!-- Atajos -->
                                <div class="d-flex flex-column gap-2">
                                    <small class="text-muted">Atajos de teclado</small>
                                    <div class="d-flex align-items-center">
                                        <kbd style="width:32px; text-align:center;">F2</kbd>
                                        <span class="ms-2">OBSERVACIONES</span>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <kbd style="width:32px; text-align:center;">F3</kbd>
                                        <span class="ms-2">REINICIAR [TAREA]</span>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <kbd style="width:32px; text-align:center;">F4</kbd>
                                        <span class="ms-2">SUSPENDER</span>
                                    </div>
                                </div>
							</div>
						</div>
					</div>

					<div class="card card-stats">
						<div class="card-body">
							<div class="row">
								<div class="col-10">
									<div style="color: blue;">
										[FINALIZA TAREA]</br>
										Escanee el código una vez terminada la tarea.
									</div>
								</div>
								<div class="col-2">
									<img id="barcode_close" style="width:70px;">
								</div>
							</div>
						</div>
					</div>


				</div>
			</div>
		</form>
		</div>
	</div>


	<!-- Botón flotante de IA (ícono visible siempre si habilitado) -->
	<div id="ia-button" style="display:none"><img src="img/icons/icons8-ia-32.png" alt="IA"></div>
	
	<!-- Contenedor del chat (inicia oculto) -->
	<div id="chat-container">
		<button class="btn btn-close" id="close-btn"></button>
		<div id="chat-messages"></div>
		<div id="chat-input-area">
			<textarea id="chat-input" rows="2" placeholder="Ej: ¿Qué tornillos usar para marco de 2 hojas?"></textarea>
			<button class="btn btn-success" id="send-btn">Enviar</button>
			
		</div>
	</div>

</div>

<?php
	include_once('./include/modal.php'); 
	include_once('./include/footer.php'); 
?>