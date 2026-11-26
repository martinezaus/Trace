
	
	<form id="formTareas">
		
		<label class="form-label">Tarea</label>
		<select class="form-control form-select" multiple name="tareas" id="tareas" aria-label="multiple select example">
		</select>
		<br/>
		<div id="ayudante" style="display: none;">
			<label class="form-label">Operario Auxiliar</label>
			<select class="form-control form-select" name="operario_a" id="operario_a" aria-label="Operario ayudante">
				
			</select>
		</div>

	</form>


	<script>
		$(document).ready(function () {

			//lineas para obtener datos pasados por $_Get
			var params = new URLSearchParams(window.location.search);
			let operador = params.get("operario");

			//console.log(operador);

			//muestros todas las tareas en el select
			$.ajax({
				url: "api/tareas.php",
				type: "POST",
				data: { accion: "load"},
				dataType: "json",
				success: function (respuesta) {
					//console.log(respuesta);
					$.each(respuesta, function (index, tarea) {
						$("select[id=tareas]").append("<option value='" + tarea.codigo + "'>" + tarea.nombre + "</option>");                            
					});
				},
				error: function () {
					$("select[id=tareas]").append("<option value='0'>Error</option>");
				}
			});           

			
			$("select[id=tareas]").on("change",function(){
				var tarea_id=$(this).val();


				//console.log(id);
				$.ajax({
					url: "api/tareas.php",
					type: "POST",
					data: { 'accion': 'auxiliar', 'tarea': tarea_id },
					dataType: "json",
					success: function (respuesta) {
						$.each(respuesta, function (index, operario) {
							//console.log(operario.ayudante);
							//Si la tareas es c/auxiliar o ayudante 
							if(operario.ayudante == 1){  
								$("div[id=ayudante]").show(); 
								//lleno el select del operador auxiliar
								$.ajax({
									url: "api/operadores.php",
									type: "POST",
									data: { "accion": "load"},
									dataType: "json",
									success: function (respuesta) {
										$("select[id=operario_a]").empty();
										$("select[id=operario_a]").append("<option value='0' selected>Sin Operador Auxiliar</option>")
										$.each(respuesta, function (index, operario_aux) {
											//console.log(operario.ayudante);
											if(operario_aux.id != operador){
												$("select[id=operario_a]").append("<option value='" + operario_aux.id + "'>" + operario_aux.nombre + "</option>");
											}
										});
									},
									error: function () {
										$("select[id=operario_a]").append("<option value='0'>Error</option>");
									}
								}); 
								
								
							}else{ //Si la tareas s/auxiliar o ayudante solo sera seleccionada la tarea.
								$('#modal').modal('hide');
								$("#barcode").focus();
								
								//console.log('Operario: '+operador+' Tarea: '+$("select[id=tareas] option:selected").text());

								$('#tarea_descripcion').text($("select[id=tareas] option:selected").text());
								
								//console.log('Operario(token): '+operador+' Tarea: '+id);
								//tarea, operador

								$.ajax({
									url: "api/operadores.php",
									type: "POST",
									data: { 'accion': 'select_id', 'id': operador},
									dataType: "json",
									success: function (respuesta) {
										$.each(respuesta, function (index, operario) {
											//console.log('Entro: '+operario.nombre);
											//$('#operador').text(operario.id);
											$('#operador').text(operario.nombre + '('+operario.id+')');
										});
									},
									error: function () {
										$('#operador').text('Error');
									}
								});               
							}    
						});
					},
					error: function () {
						Swal.fire({ title: "Error al cargar la tareas. Proceso cancelado.",
									confirmButtonText: "OK"
								}).then((result) => {
									/* Read more about isConfirmed, isDenied below */
									if (result.isConfirmed) {
										location.href="index.php";
									} 
								});                        
					}
				});
				
			});  


			$("select[id=operario_a]").on("change",function(){

				$('#modal').modal('hide');
				$("#barcode").focus();
				//tarea, operador + operador auxiliar
				var id_a=$(this).val();
				$('#operador_a').text($(this).text());

				//Operador
				$.ajax({
					url: "api/operadores.php",
					type: "POST",
					data: { 'accion': 'select_id', 'id': operador},
					dataType: "json",
					success: function (respuesta) {
						$.each(respuesta, function (index, operario) {
							//console.log('Entro: '+operario.nombre);
							$('#operador').text(operario.nombre + '('+operario.id+')');
						});
					},
					error: function () {
						$('#operador').text('Error');
					}
				});               

			});
		});              
	  
	</script>