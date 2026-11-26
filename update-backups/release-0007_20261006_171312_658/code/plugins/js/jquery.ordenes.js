
let tablaOrdenes = "";

$(document).ready(() => {
	initializeDataOrdenes();
	cargarOrdenes();

	// Cambia '#modal' por el id real si es diferente (en tu código es $('#modal'))
	$('#modal').on('shown.bs.modal', function () {
		setTimeout(function() {
			// Esta es la línea más efectiva según el foro oficial de DataTables
			$.fn.dataTable.tables({ visible: true, api: true }).columns.adjust().draw(false);
			// Si con la anterior no alcanza, descomenta la siguiente línea también:
			// $('#detallesTable').DataTable().columns.adjust().draw(false);
		}, 50);   // 300ms suele ser el valor más estable con scrollY + modal
	});

});

function initializeDataOrdenes(){
	tablaOrdenes = $('#tabla_ordenes').DataTable({ pageLength: 10,
												   searching: true,
												   lengthMenu: [ [10, 25, 50], [5, 10, 25, 50] ],
												   language: {
														url: 'plugins/js/es-ES.json'
												   },
												   responsive: true,
												   autoWidth: true,        // Ancho automático
												   dom: 'Bfrtip', // Posición de botones, filtro, tabla, info, paginación
												   buttons: ['copy', 'excel', 'pdf', 'print'], // Botones de exportación (necesita extensión Buttons)
												   order: [[0, 'desc']]   // Orden inicial: columna 0 descendente
												});
												
}

function cargarOrdenes(){
	var estado=""; var botones=""; var del=""; var act=""; var inst=""; var obs="";
	$.post("api/ordenes.php", { 'accion': 'load'}, function(respuesta){
		tablaOrdenes.clear(); 
		if (respuesta && Array.isArray(respuesta)) {
		  
			respuesta.forEach(function(orden) {
				switch(String(orden.estado)){
					case '0': //cargada
						estado="<span class='btn btn-fill rounded-pill'>cargada</span>"; 
						del="<a href='javascript:;' class='btn btn-simple btn-sm ms-1' onclick='deleteOrden("+orden.id+");'><img src='img/icons/icons8-eliminar-16.png' title='eliminar' ></a>";
						break;
					case '1': //Procesando
						estado="<span class='btn btn-info rounded-pill'>procesando</span>"; 
						break;
					case '2': //Terminada
						estado=`<span class="btn btn-success rounded-pill">terminada</span>`;
						inst=`<a href='javascript:;' class='btn btn-simple btn-sm ms-1' onclick='logistica("+orden.id+");'>
									<img src='img/icons/icons8-carca_colocacion-16.png' title='Transporte y colocacion'>
								</a>`;		                               
						break;
					case '3': //Para Entregar o Instalar
						//estado="<span class='btn btn-warning rounded-pill' onclick='mostrarobservacion("+orden.obs+");'>"+obs+"</span>";
						estado="<span class='btn btn-warning rounded-pill'>Instalada</span>"; 
						break;
				}

				var view="<a href='javascript:;' class='btn btn-simple btn-sm ms-1' onclick='viewOrdenes("+orden.id+");'><img src='img/icons/icons8-ver-16.png' title='ver orden' ></a>";
				var view_dat=`<a href='javascript:;' class='btn btn-simple btn-sm ms-1' onclick='viewArchivo(${orden.id}, "dat");'><img src='img/icons/icons8-dat-16.png' title='ver .dat'></a>`;
				var view_pdf=`<a href='javascript:;' class='btn btn-simple btn-sm ms-1' onclick='viewArchivo(${orden.id}, "pdf");'><img src='img/icons/icons8-pdf-16.png' title='ver .pdf'></a>`;
				//var print_b="<a href='javascript:;' class='btn btn-simple btn-sm ms-1' onclick='printBarcode("+orden.id+");'><img src='img/icons/icons8-imprimir-16.png' title='ver orden' ></a>";
				var tipologias="<a href='javascript:;' class='btn btn-simple btn-sm ms-1' onclick='tipologias("+orden.id+");'><img src='img/icons/icons8-tipologias-16.png' title='Tipologias' ></a>";

				botones=view+view_dat+view_pdf+del+act+inst;
				tablaOrdenes.row.add([orden.id,orden.codigo,orden.cliente,estado,tipologias,botones]);

				estado="";
				act="";
				del="";
				inst="";
				obs="";
			});

			tablaOrdenes.draw(); // Dibuja la tabla
		   
		}
	},"json").fail(function(xhr, status, error) {
		logger("ERROR", "ORDENES", xhr.responseText + ', ' + status + ', ' + error);
		mostrarMensaje("⚠️ Error!. "+ xhr.responseText, 'red', 10000);
	});
	setTimeout(() => $('#mensaje').html(''), 10000);
}                                                    

async function viewOrdenes(id) {
    resetModalCompleto();

	mostrarCarga("Generando PDF...");

    try {
        // 1. Datos principales de la orden.
        const [orden, detallesRespuesta, lote] = await Promise.all([
            $.post("api/ordenes.php", { accion: "view", id }, null, "json"),
            $.post("api/detalles.php", { accion: "view", id }, null, "json"),
            $.post("api/lotes.php", { accion: "view", id }, null, "json")
        ]);

        if (!Array.isArray(orden) || orden.length === 0) {
            throw new Error("No se encontró la orden solicitada.");
        }

        const detalles = Array.isArray(detallesRespuesta)
            ? detallesRespuesta
            : [];

        // 2. Procesos pertenecientes a cada tipología/detalle.
        const procesosPorDetalle = await Promise.all(
            detalles.map(async (detalle) => {
                const procesosRespuesta = await $.post(
                    "api/procesos.php",
                    {
                        accion: "view",
                        idDetalle: detalle.id
                    },
                    null,
                    "json"
                );

                return {
                    detalle,
                    procesos: Array.isArray(procesosRespuesta)
                        ? procesosRespuesta
                        : []
                };
            })
        );

        // 3. Eventos pertenecientes a cada proceso.
        const gruposEventos = await Promise.all(
            procesosPorDetalle.flatMap(({ detalle, procesos }) =>
                procesos.map(async (proceso) => {
                    const eventosRespuesta = await $.post(
                        "api/eventos.php",
                        {
                            accion: "view",
                            id: proceso.id
                        },
                        null,
                        "json"
                    );

                    const eventos = Array.isArray(eventosRespuesta)
                        ? eventosRespuesta
                        : [];

                    // Se eliminan respuestas vacías como [{ id: 0 }].
                    // Se agregan datos de contexto para el reporte.
                    return eventos
                        .filter(evento => Number(evento?.id) > 0)
                        .map(evento => ({
                            ...evento,
                            proceso_id: proceso.id,
                            tarea: proceso.tarea || "—",
                            detalle_id: detalle.id,
                            tipologia: detalle.tipologia || "—"
                        }));
                })
            )
        );

        const eventos = gruposEventos.flat();
        // 4. Generar el PDF en segundo plano. El popup se abre solo cuando el
        // archivo ya está disponible, por eso no llega a verse about:blank.
        const datosPdf = new FormData();
        datosPdf.append("orden", JSON.stringify(orden));
        datosPdf.append("detalles", JSON.stringify(detalles));
        datosPdf.append("eventos", JSON.stringify(eventos));
        datosPdf.append("lote", JSON.stringify(lote));

        const respuestaPdf = await fetch("include/pages/ordenesView.php", {
            method: "POST",
            body: datosPdf,
            credentials: "same-origin"
        });

        if (!respuestaPdf.ok) {
            throw new Error("El generador de PDF respondió HTTP " + respuestaPdf.status);
        }

        const pdf = await respuestaPdf.blob();
        if (!pdf.size) {
            throw new Error("El generador devolvió un PDF vacío");
        }

        const urlPdf = URL.createObjectURL(pdf);
        ocultarCarga();

        const ventanaOrden = window.open(
            urlPdf,
            "Orden",
            "width=1000,height=750,scrollbars=yes,resizable=yes"
        );

        if (!ventanaOrden) {
            URL.revokeObjectURL(urlPdf);
            mostrarMensaje("El navegador bloqueó la ventana del reporte. Habilitá las ventanas emergentes.", "red", 15000);
            return;
        }

        ventanaOrden.addEventListener("beforeunload", function() {
            URL.revokeObjectURL(urlPdf);
        });
        ventanaOrden.focus();

    } catch (error) {
        const detalleError = error?.responseText || error?.message || String(error);
        logger("ERROR", "ORDENES", "No se pudo generar historial de orden " + id + ": " + detalleError);
        ocultarCarga();
        mostrarMensaje("No se pudieron cargar todos los datos de la orden.", "red",  15000);
    }
}

const eliminacionesOrdenEnCurso = new Set();
async function deleteOrden(id){
    id=Number(id);
    if(!Number.isInteger(id) || id<=0){mostrarMensaje('Identificador de orden inválido.','red',10000);return;}
    if(eliminacionesOrdenEnCurso.has(id))return;
    eliminacionesOrdenEnCurso.add(id);
    try {
        const respuesta=await $.post('api/ordenes.php',{accion:'delete',id},null,'json');
        if(respuesta?.resultado!=='ok'){
            mostrarMensaje('⚠️ '+(respuesta?.mensaje || 'No se pudo eliminar la orden.'),'red',10000);
            return;
        }
        mostrarMensaje(respuesta.advertencia || '✅ '+(respuesta.mensaje || 'La orden se eliminó correctamente.'),respuesta.advertencia?'#B85C00':'green',10000);
        cargarOrdenes();
    }catch(error){
        mostrarMensaje('⚠️ '+(error?.responseJSON?.mensaje || 'No se pudo confirmar la eliminación. Actualice la lista antes de reintentar.'),'red',10000);
    }finally{eliminacionesOrdenEnCurso.delete(id);}
}

function viewArchivo(id, tipo){
	$('#modal').modal('dispose');
	// Título
	$(".modal-title").html('Archivos');
	// Quitamos un botón previo si existía (por si se abre el modal varias veces)
	$(".modal-header").find("#btnAgregarArchivo").remove();
	// Agregamos el botón al header, después del título
	if(tipo != 'dat'){
		$("#title-modal").after(
			'<button type="button" id="btnAgregarArchivo" class="btn btn-primary btn-sm" onclick="agregarArchivo('+ id +',\''+ tipo +'\')">' +
				'<i class="fa fa-plus"></i> Agregar archivo' +
			'</button>'
		);
	}

	$(".modal-body").load("include/pages/unload.php?id=" + encodeURIComponent(id) + "&tipo=" + encodeURIComponent(tipo), function(){
		$("form[id=formViewArchivo]").find("input[id=ido]").val(id);
		$("form[id=formViewArchivo]").find("input[id=tipo]").val(tipo);
		$('#modal').modal('toggle');   
	});				
}

function agregarArchivo(id, tipo){
	var modal = document.getElementById('modal');
	var modalAgregar = document.getElementById('modal_b');
	$(modal).one('hidden.bs.modal', function () {
		$(".modal-body", modalAgregar).load("include/pages/upload.php?modo=solo_pdf", function(){
			$("form", modalAgregar).find("input[id=ido]").val(id);
			$(modalAgregar).modal('show');
		});
	});
	$(modal).modal('hide');
}

function mostrarobservacion(){
	alert(obs);    
}

function logistica(id){
	$('#mensaje').html("⚠️ MODULO NO DESARROLLADO.").css('color', 'red');
}

function tipologias(id){
	
	resetModalCompleto(); // Limpia todo rastro anterior

	// Limpiar modal previo y preparar
	const $modal = $('#modal');
	const $modalBody = $modal.find('.modal-body');
	const $modalTitle = $modal.find('.modal-title');

	$modalTitle.addClass('w-100 d-flex justify-content-between align-items-center pe-2').html(`
		<span>Tipologias</span>
		<button class="btn btn-primary btn-sm" onclick="viewProcesos(${id})">Procesos</button>
	`);
	$modalBody.html(`
		<div class="text-center py-5 my-3">
			<div class="spinner-border text-primary" style="width:3rem;height:3rem" role="status">
				<span class="visually-hidden">Cargando...</span>
			</div>
			<p class="mt-3 fw-bold">Cargando tipologias...</p>
		</div>
	`);

	$modal.modal({ 
		backdrop: 'static',   // evita cerrar clic fuera
		keyboard: true
	}).modal('show');

	$.ajax({
		url: "include/pages/detallesTable.php",
		type: "GET",   // o POST si lo prefieres
		cache: false,
		success: function (htmlEstructura) {
			$modalBody.html(htmlEstructura);
			$.post("./api/detalles.php", {accion: "view", id: id }, function(respuesta){
				if (!respuesta || respuesta.length === 0) {
					mostrarMensaje("⚠️ No hay opciones", "red", 10000);
					return;
				}

				if (!respuesta || !Array.isArray(respuesta) || respuesta.length === 0) {
					$modalBody.html(`
						<div class="alert alert-info text-center py-5">
							<i class="fa fa-info-circle fa-2x mb-3 d-block"></i>
							No hay tipologias
						</div>
					`);
					return;
				}        

				let datosAMostrar = respuesta;
				// 4. Inicializar la tabla con los datos
				inicializarTablaTipologias(id, datosAMostrar);  
			}, "json").fail(function(){
				$modalBody.html(`
					<div class="alert alert-danger text-center py-4">
						Error al cargar datos
					</div>
				`);
			}); 
		}, error: function(){
			$modalBody.html(`
				<div class="alert alert-danger text-center py-4">
					No se pudo cargar la estructura de la tabla
				</div>
			`);            
		}                 
	});    

}

function inicializarTablaTipologias(idOrden, items){
	const $table = $('#detallesTable');
	// 1. Destruir instancia previa si existe (muy importante en modales)
	if ($.fn.DataTable.isDataTable($table)) {
		$table.DataTable().clear().destroy();
		$table.off('click', 'tbody tr');
	}

	// 2. Limpiar contenido previo (por si quedó HTML viejo)
	$table.empty();
	//$table.columns.adjust().draw(false);
	// Inicializar DataTable
	$table.DataTable({
					destroy: true,         // Permite reinicializar
					responsive: false,
					scrollY: '55vh',       // ALTURA FIJA
					scrollX: false,         // 
					scrollCollapse: true,
					autoWidth: false,             // ← Ayuda a que respete los anchos
					paging: items.length > 4,
					pageLength: items.length <= 4 ? -1 : 4,
					lengthChange: false,
					searching: true, //!codigoEscaneado
					ordering: true,
					order: [[0, 'asc']],
					language: {
						url: "plugins/js/es-ES.json",
						zeroRecords: 'No se encontraron registros',
						info: 'Mostrando _START_ a _END_ de _TOTAL_ entradas',
						infoEmpty: 'Mostrando 0 de 0 a 0 entradas',
						infoFiltered: '(Filtrando de _MAX_ total entradas)',
						search: 'Buscar',
						paginate: {
							first: 'Primero',
							last: 'Ultimo',
							next: 'Siguiente',
							previous: 'Anterior'
						}
					},
					data: items,
					columns: [
						{ data: "id", 
							title: "#", 
							width: "10px", 
							render: data => `<strong>${data}</strong>` 
						},
						{ data: "tipologia", width: "10px", title: "Nombre" },
						{ data: "estado", 
							width: "20px",
							title: "Estado", 
							render: function(data, type, row){
								let texto = "";
								let clase = "";
								let boton = "";
								switch(Number(row.estado)){
									case 0: 
										texto = "Pendiente";
										clase = "estado-pendiente";
										boton = "";
										break; 
									case 1: 
										texto = "Pausada";
										clase = "estado-pausada";
										boton = "";
										break;
									case 2: 
										texto = "Suspendido";
										clase = "estado-suspendido";
										boton =  `<button class='btn btn-sm btn-round btn-editar' data-id='${row.id}' onclick='activarTipologia(${row.id})'><img src='./././img/icons/icons8-procesado-20.png' title='Activar'></button>`;
										break;
									default: 
										texto = "Terminada";
										clase = "";
										boton = "";
								};
								return `<span class="${clase}">${texto}</span> ${boton}`;
							}
						}, 
						{data: "detalles", width: "60px", title: "Observaciones"},
						{data: "id", width: "20px", title:"Acciones", render: function(data, type, row){
								let boton = "";
								if (Number(row.estado) === 3){
									boton = `<button class="btn-revertir" 
													data-id="${row.id}" 
													onclick="revertirTipologia(${row.id})" 
													title="Revertir tipología">
													<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
														<path d="M3 12a9 9 0 1 0 3-6.7"/>
														<path d="M3 4v5h5"/>
													</svg>
												</button>`;
												
								}else{
									boton = `<button class="btn-ayudante" 
												data-id="${row.id}" 
												onclick="cargarAyudante(${idOrden},${row.id}, ${Number(row.ayudante) || 0})" 
												title="Operador Ayudante">
												
												<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
													<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
													<circle cx="12" cy="7" r="4" />
													<path d="M14.7 11.3l5.3 5.3-1.5 1.5-5.3-5.3" />
												</svg>                                   
											</button>`;           
								}

								return `${boton}`;
							}
						}
					]                    
	});

}

async function activarTipologia(idTipologia){
	try {
		const respuesta = await $.post("./api/detalles.php", {accion: "status", id: idTipologia, estado: 0}, null, "json");
		if (Number(respuesta) === 0) {
			mostrarMensaje("✅ Se cambió el estado de la tipología, puede continuar con el procesamiento.", 'green', 10000,'mensaje_modal_b');
			// refrescamos la tabla en vez de cerrar el modal entero
			const detalles = await $.post("./api/detalles.php", {accion: "view", id: estado.ordenId}, null, "json");
			inicializarTablaTipologias(detalles);
		} else {
			logger("ERROR", "ORDEN[ACTIVAR]", 'Linea 402, Error al activar la tipología: ' + idTipologia);
			mostrarMensajeModal("⚠️ Error al activar la tipología.", "red", 10000);
		}
	} catch(e) {
		logger("ERROR", "ORDENES[ACTIVAR]", "Linea 409, excepción: " + e.message);
		mostrarMensajeModal("⚠️ Error inesperado al activar la tipología.", "red", 10000);
	}
	setTimeout(() => $('#mensaje_modal').html(''), 3000);
}

async function revertirTipologia(idTipologia){

	if ($('#modal-sector').length === 0) {
		$('body').append(`
			<div id="modal-overlay" style="
				position: fixed; inset: 0;
				background: rgba(0,0,0,0.55);
				z-index: 9998;
				display: flex; align-items: center; justify-content: center;
			">
				<div id="modal-sector" style="
					background: #fff;
					border-radius: 12px;
					padding: 36px 40px;
					box-shadow: 0 8px 32px rgba(0,0,0,0.25);
					text-align: center;
					min-width: 320px;
					z-index: 9999;
				">
					<h4 style="margin-bottom: 8px; font-size: 1.2rem; color: #333;">
						¿Revertir tipología?
					</h4>
					<p style="color: #666; margin-bottom: 28px; font-size: 0.95rem;">
						Se guardará el tiempo acumulado y quedará disponible para rehacerse desde cero.
					</p>
					<div style="display: flex; gap: 16px; justify-content: center;">
						<button id="btn-true" style="
							padding: 14px 32px;
							font-size: 1rem;
							font-weight: 600;
							border: none;
							border-radius: 8px;
							background: #1976d2;
							color: #fff;
							cursor: pointer;
							transition: background 0.2s;
						"> SI</button>
						<button id="btn-false" style="
							padding: 14px 32px;
							font-size: 1rem;
							font-weight: 600;
							border: none;
							border-radius: 8px;
							background: #e53935;
							color: #fff;
							cursor: pointer;
							transition: background 0.2s;
						"> NO</button>
					</div>
				</div>
			</div>
		`);
	}

	// Eventos de los botones
	$('#btn-true').off('click').on('click', async function () {   // ← acá el async
		try {
			const respuesta = await $.post("./api/detalles.php", {accion: "revertir", id: idTipologia}, null, "json");

			if (respuesta && respuesta.status === "ok") {
				$('#modal-overlay').remove();
				mostrarMensajeModal("✅ Tipología revertida. Quedó disponible para rehacerse.", "green", 10000);

				const detalles = await $.post("./api/detalles.php", {accion: "view", id: respuesta.ordenId}, null, "json");
				inicializarTablaTipologias(detalles);
			} else {
				$('#modal-overlay').remove();
				logger("ERROR", "ORDENES", "Fallo al revertir tipología id=" + idTipologia + ": " + (respuesta ? respuesta.valor : ''));
				mostrarMensajeModal("⚠️ Error al revertir la tipología.", "red", 10000);
			}
		} catch(e) {
			$('#modal-overlay').remove();
			logger("ERROR", "ORDENES", "revertirTipologia excepción: " + e);
			mostrarMensajeModal("⚠️ Error inesperado al revertir la tipología.", "red", 10000);
		}

		setTimeout(() => $('#mensaje_modal').html(''), 3000);
	});

	$('#btn-false').off('click').on('click', function () {
		$('#modal-overlay').remove();
	});
}

async function cargarAyudante(idOrden, idTipologia, ayudante){
	const ayudanteActual = Number(ayudante) || 0;
	const valor = ayudanteActual === 1 ? 0 : 1;

	try {
		const respuesta = await $.post('./api/detalles.php', {'accion':'details', 'id': idTipologia, 'opc': 1, 'ayudante': valor}, null, 'json');
		if (respuesta[0].resultado === "ok"){
			mostrarMensajeModal(
				valor === 1 
					? " ✅ Se cargó el Ayudante correctamente." 
					: " ✅ Se descargó el Ayudante correctamente.", 
				"green", 
				10000
			);

			// Refrescamos la tabla dentro del mismo modal
			const detalles = await $.post("./api/detalles.php", {'accion': 'view', 'id': idOrden}, null, "json");
			inicializarTablaTipologias(idOrden, detalles);

		} else {
			mostrarMensajeModal("⚠️ Error inesperado al agregar Ayudante.", "red", 10000);
		}
	} catch(e) {
		logger("ERROR", "ORDENES[AYUDANTE]", "Linea 543, [cargarAyudante] excepción: " + e);
		mostrarMensajeModal("⚠️ Error inesperado al agregar Ayudante.", "red", 10000);
	}
}

function viewProcesos(idOrden) {

	resetModalCompleto();
	mostrarCarga("Generando PDF...");

	// 1. Obtener todos los detalles/tipologías de la orden
	$.post("api/detalles.php", {accion: 'view', id: idOrden }, null, 'json')
		.done(function(detalles) {
			if (!detalles || !Array.isArray(detalles) || detalles.length === 0) {
				mostrarMensaje('No hay tipologías registradas para esta orden.', "red", 15000);
				return;
			}

			

			const promesasProcesos = detalles.map(function(detalle) {
				return $.post("api/procesos.php", {accion: 'view', idDetalle: detalle.id}, null, 'json')
				    .then( function(respuesta) {
						
					    if (Array.isArray(respuesta)) {
						    return {detalle_id: detalle.id, procesos: respuesta	};
					    }
				        if (respuesta && typeof respuesta === 'object') {
						    return { detalle_id: detalle.id, procesos: [respuesta] };
				        }
				        return {detalle_id: detalle.id, procesos: [] };
				    })
				    .fail(function(error){
					    logger("ERROR", "ORDENES", "Linea 559, Fallo al generar el PDF de procesos: " + error.message);
			        })

		    });


		    Promise.all(promesasProcesos).then(function(resultadosProcesos) {
				let procesos = [];
				resultadosProcesos.forEach(function(resultado) {
					if (resultado && Array.isArray(resultado.procesos)) {
						resultado.procesos.forEach(function(proceso) {
							if (proceso.detalle_id === undefined || proceso.detalle_id === null || proceso.detalle_id === "") {
								proceso.detalle_id = resultado.detalle_id;
							}
							procesos.push(proceso);
						});
					}
				});
				fetch("include/pages/procesosView.php", {method: "POST", 
					                                    headers: {"Content-Type": "application/json"}, 
														body: JSON.stringify({ordenId: idOrden, detalles: detalles, procesos: procesos })})
				    .then(function(response) {
					if (!response.ok) {
						throw new Error("Respuesta HTTP " + response.status);
					}
					return response.blob();
				})
				.then(function(blob) {

					const blobUrl = URL.createObjectURL(blob);
					const pdfWindow = window.open(blobUrl, 'Procesos', 'width=800,height=600');
					ocultarCarga();
					if (!pdfWindow) {
						mostrarMensaje('El navegador bloqueó la ventana emergente. Habilitá popups para este sitio.', "red", 15000);
					} else {
						pdfWindow.addEventListener('beforeunload', function() {
								URL.revokeObjectURL(blobUrl);
							}
						);
					}

				})
				.catch(function(err) {
					logger("ERROR", "ORDENES", "Fallo al generar el PDF de procesos: " + err.message);
					mostrarMensaje('No se pudo generar el PDF de procesos.', "red", 15000);
				});


			});

	})
	.fail(function(xhr) {
		logger("ERROR", "ORDENES", "Petición de detalles falló.");
		mostrarMensaje('No se pudieron cargar los datos de los detalles.',"red",15000);
	});

	setTimeout(
		() => $('#mensaje_modal').html(''),
		3000
	);
}
