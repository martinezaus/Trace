function iniciarpausaTareaActual() { return transicionArmado(iniciarPausaArmado); }
async function iniciarPausaArmado() {
    if (estado.pausaActiva) return;
    if (!estado.detalleActualId || !estado.tareaEnCurso || !estado.procesoId) {
        mostrarMensaje("Debe tener una tarea activa para iniciar una pausa.", "#BD1A00", 9000, 'mensaje');
        return;
    }
    estado.tiempoTareaMs = tiempoTareaActualMs();
    estado.inicioTareaTs = 0;
    estado.pausaActiva = true;
    estado.pausaTipo = 0;
    estado.ultimoEventoId = 0;
    estado.seleccionPausaTs = Date.now();
    detenerTimer($("#timerT"));

    mostrartiposPausas();
    mostrarMensaje("PAUSA ACTIVA", "#FF3C00", 30000);
}

/**Funcion que abre modal mostrando los tipos de pausa */
function mostrartiposPausas(){

	// Limpiar modal previo y preparar
	const $modal = $('#modal');
	const $modalBody = $modal.find('.modal-body');
	const $modalTitle = $modal.find('.modal-title');

	$modalTitle.text(`Pausas`);
	$modalBody.html(`
		<div class="text-center py-5 my-3">
			<div class="spinner-border text-primary" style="width:3rem;height:3rem" role="status">
				<span class="visually-hidden">Cargando...</span>
			</div>
			<p class="mt-3 fw-bold">Cargando tipos de pausas...</p>
		</div>
	`);

	$modal.off('hidden.bs.modal.armadoPausa').on('hidden.bs.modal.armadoPausa', () => {
        if (estado.pausaActiva && !estado.ultimoEventoId && !estado.transicionActiva) finpausaTareaActual();
    });
    $modal.modal({ 
        backdrop: 'static',   // evita cerrar clic fuera
		keyboard: true
	}).modal('show');

	$.ajax({
		url: "include/pages/pausasTable.php",
		type: "GET",   // o POST si lo prefieres
		cache: false,
		success: function (htmlEstructura) {
			$modalBody.html(htmlEstructura);
			$.post(URL_API.pausa, {accion: "listar", idc: 3 }, function(respuesta){
				if (!respuesta || respuesta.length === 0 || !Array.isArray(respuesta)) {
					$modalBody.html(`<div class="alert alert-info text-center py-5">
                        				<i class="fa fa-info-circle fa-2x mb-3 d-block"></i>
                                        No hay codigos de pausas registrados
                                    </div>`);
					return;
				}

				let datosAMostrar = respuesta;
				//console.log(datosAMostrar);
				inicializarTablaPausas(datosAMostrar);                  // 4. Inicializar la tabla con los datos
			}, "json").fail(function(){
				$modalBody.html(`
					<div class="alert alert-danger text-center py-4">
						Error al cargar los codigos
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

/** Funcion que me configura la tabla que va a mostrar los tipos de pausas */
function inicializarTablaPausas(items){
	const $table = $('#pausasTable');

	// 1. Destruir instancia previa si existe (muy importante en modales)
	if ($.fn.DataTable.isDataTable($table)) {
		$table.DataTable().clear().destroy();
		// También limpiamos eventos para evitar duplicados
		$table.off('click', 'img.barcode_item_det');
		$table.off('click', 'tbody tr');
	}

	// 2. Limpiar contenido previo (por si quedó HTML viejo)
	$table.empty();

	// Inicializar DataTable
	$table.DataTable({
					destroy: true,         // Permite reinicializar
					responsive: true,
					scrollY: '55vh',       // ALTURA FIJA
					scrollCollapse: true,
					paging: items.length > 4,
					pageLength: items.length <= 4 ? -1 : 4,
					lengthChange: false,
					searching: true, //!codigoEscaneado
					ordering: true,
					order: [[0, 'asc']],
					initComplete: function () {
						$(this.api().table().header()).hide();
					},
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
						 { data: "id", title: "#", render: data => `<strong>${data}</strong>` },
						 { data: "descripcion", title: "Nombre" },
						 { data: "barcode2d", title: "QR", className: "text-center", orderable: false, render: function(data, type, row) {
							if (!data) return "—";
							return `
							<img src="data:image/png;base64,${data}" 
								 class="barcode_item_det shadow rounded" 
								 style="height:64px; cursor:pointer;" 
								 data-codigo="${row.codigo}"
								 title="Seleccionar ${row.codigo}">`;
							}
						}
					]
	});

}

/**Funcion que al leer QR del tipo de pausa genera la activacion de la pausa */
function initBarcodeScannerPausa(code){
	code = (code || '').trim().toUpperCase();
    //console.log("onCodeScannedPausa()" + code);
 	// Ignora códigos vacíos o muy cortos
	if (!code || code.length < 3) {
		mostrarMensaje("⚠️ Código inválido (demasiado corto o vacío).",'#BD1A00',9000);
		return;
	}

	// 2. Prioridad máxima: si el modal de topologías está abierto
	if ($('#modal').hasClass('show') && $('#pausasTable').is(':visible')) {
		//console.log("[MODAL ABIERTO] Buscando coincidencia en tabla...");

        const rows = $('#pausasTable').DataTable().rows().data().toArray();
        const seleccion = rows.find(p => String(p.codigo).trim().toUpperCase() === code || String(p.id) === code);
        if (seleccion) {
            seleccionarPausa(seleccion.codigo, seleccion.descripcion);
            return;
		} else {
			mostrarMensaje("Código no encontrado en la lista actual", "#BD1A00", 9000);
			// Opcional: mostrar advertencia solo una vez cada cierto tiempo
			// mostrarMensaje("Código no encontrado en la lista actual", "warning", 4000);
			return;
		}
	}    
}

/** Funcion que una vez seleccionada el tipo de pausa actualiza datos en la base de datos */
function seleccionarPausa(codigoPausa,nombrePausa) { return transicionArmado(() => seleccionarPausaInterna(codigoPausa,nombrePausa)); }
async function seleccionarPausaInterna(codigoPausa, nombrePausa) {
	if (!codigoPausa || !estado.pausaActiva || estado.ultimoEventoId) {
		mostrarMensaje("NO ha escaneado ningun codigo.", "#BD1A00", 15000);
		return;
	}

	try {
        estado.inicioPausaTs = Date.now();

        estado.pausaTipo = codigoPausa;

        const datosEvento = {
			descripcion:  "pausa",
            procesoId:    estado.procesoId || null,
            tipo:         "pausa",
            codigo_id:    3,
            codigo_sub:   estado.pausaTipo,
            tiempo:       0,
            operadorId:   estado.operadorId
        };

        const respuestaEvento = await $.post(URL_API.evento, {'accion': 'add', 'datos': JSON.stringify(datosEvento)});
        estado.ultimoEventoId = (respuestaEvento && respuestaEvento[0]) ? respuestaEvento[0].id : 0;
        if (!estado.ultimoEventoId) {
            throw new Error('No se pudo registrar el evento de pausa');
        }
        
        iniciarTimer(estado.inicioPausaTs, $("#timerP"), 0);
        $('#modal').modal('hide');
        //Coloco a la Tipologia estado: pausa
        const band = await $.post(URL_API.detalles, {accion: "status", id: estado.detalleActualId, estado: 1}).catch(() => 1);
        if (band !== 0) {
            logger("ERROR", "PROCESO(PAUSA)", "No se pudo marcar la tipología como pausada, detalleId=" + estado.detalleActualId);
        }

        $("#label_pausa_descripcion")
            .html(`<i class="fa fa-pause-circle me-1"></i> ${nombrePausa}`)
            .css({"color": "#B85C00",
                "background-color": "#FFF3E0",
                "border": "1px solid #F27100",
                "border-radius": "20px",
                "padding": "6px 16px",
                "font-weight": "600",
                "display": "inline-block",
                "transition": "transform 0.25s ease"
            });

        // Pulso sutil al mostrarse (una sola vez)
        $("#label_pausa_descripcion").css("transform", "scale(1.08)");
        setTimeout(() => $("#label_pausa_descripcion").css("transform", "scale(1)"), 250);
        sendMail('Pausa activada por el usuario:' + estado.operadorId + 'Fecha/Hora:' + new Date().toISOString());
	}catch(e) {
        if (!estado.ultimoEventoId) detenerTimer($("#timerP"));
        // El evento no fue confirmado: conservar selección y tiempo para reintentar o cancelar.
        if (!estado.ultimoEventoId) { estado.inicioPausaTs = 0; estado.pausaTipo = 0; }
        logger("ERROR", "PROCESO(PAUSA)", "linea 219, " + e);
		mostrarMensaje("Error!!! Al activar la pausa", "#8C1701", 9000);
	}
}   

function finpausaTareaActual() { return transicionArmado(finalizarPausaArmado); }
async function finalizarPausaArmado() {
    if (!estado.pausaActiva) return;
    try {
        if (estado.ultimoEventoId) {
            const duracionPausaMs = Math.max(0, Date.now() - estado.inicioPausaTs);
            const band = await $.post(URL_API.evento, {accion:'update',evento:estado.ultimoEventoId,tiempo:duracionPausaMs,operador:estado.operadorId});
            if (band !== 0) throw new Error('No se confirmó el cierre de pausa');
            estado.tiempoPausaTotalMs += duracionPausaMs;
            await $.post(URL_API.detalles,{accion:'status',id:estado.detalleActualId,estado:0}).catch(e=>logger('ERROR','PAUSA',e));
        } else {
            // Cancelar la selección no es una pausa registrada: conservar ese tramo de trabajo.
            estado.tiempoTareaMs += Math.max(0,Date.now()-estado.seleccionPausaTs);
        }
        detenerTimer($('#timerP'));
        estado.pausaActiva=false;
        estado.pausaTipo=0;
        estado.ultimoEventoId=0;
        estado.inicioPausaTs=0;
        estado.tiempoPausaMs=0;
        estado.inicioTareaTs=Date.now();
        iniciarTimer(estado.inicioTareaTs,$('#timerT'),estado.tiempoTareaMs);
        $('#timerP').text('00:00:00.00');
        $('#label_pausa_descripcion').text('');
        $('#modal').modal('hide');
        mostrarMensaje('Pausa finalizada → continuando proceso','#32A123',15000,'mensaje');
    } catch(e) {
        mostrarMensaje('No se pudo finalizar la pausa. Sigue activa; intente nuevamente.','#BD1A00',15000,'mensaje');
        logger('ERROR','PAUSA',e.message || e);
    }
}
