const cronoo = new Cronometro("timerO"); //Tiempo de la Orden -> este nunca corta
const cronot = new Cronometro("timerT"); //Tiempo de la Tarea -> corta al finalizar la tarea
const cronop = new Cronometro("timerP"); //Tiempo de Pausa -> Inicia y corta segun la pausa

const URL_API = {
	orden:     "api/ordenes.php",
	detalles:  "api/detalles.php",
	evento:    "api/eventos.php",
	tarea:     "api/tareas.php",       // asumimos que existe o se creará
	system:    "api/system.php",
	operador:  "api/operadores.php",
	proceso:   "api/procesos.php",
	pendiente: "api/pendientes.php",
	pausa:     "api/pausas.php",
	sesion:    "api/sesiones.php"
};

let estado = {
	ordenId:         0,
	ordenCodigo:     "",
	ordenEstado:     0,               // 0=cargada, 1=procesando, 2=pausada, 3=suspendida, 4=finalizada
	detalleActualId: 0,               // topología / abertura actual
	tareaActualId:   0,
	tareaActualNro:  0,               // 1 a 8
	tareaMax:        0,               // Tarea Max a cumplir sale del campo [etapas]:[DETALLES]
	pausaActiva:     false,
	suspensionActiva:false,
	tareaEnCurso:    false,           // nuevo flag para saber si hay una tarea iniciada y AÚN NO finalizada
	pausaTipo:       0,
	operadorId:      0,
	operadorToken:   "",
	operadorIdAux:   0,
	procesoId:       0,
	pendienteId:     0,
	sesionId:        0,
	operadorAdmin:   '',   // Contiene la clave Administrados.

	// Temporizadores
	timerOrden:      0,
	timerTarea:      0,
	timerPausa:      0,

	// Tiempos acumulados en milisegundos
	tiempoOrdenMs:      0,
	tiempoTareaMs:      0,
	tiempoTareaTotalMs: 0,
	tiempoPausaMs:      0,
	tiempoPausaTotalMs: 0,
	tiempoOperario:     0,
	intervaloTimer:     0,

};
let operador = null;

// Todas las acciones que cambian trabajo comparten un bloqueo hasta confirmar el servidor.
async function transicionArmado(accion) {
    if (estado.transicionActiva || estado.jornadaCerrada) {
        mostrarMensaje("Hay una operación en curso. Espere la confirmación y vuelva a escanear.", "#F27100", 5000, 'mensaje');
        return;
    }
    estado.transicionActiva = true;
    try { return await accion(); }
    catch(e) { logger('ERROR','PROCESO',e.message || e); mostrarMensaje('No se pudo completar la operación. Vuelva a intentarlo.','#BD1A00',12000,'mensaje'); }
    finally { estado.transicionActiva = false; }
}
function tiempoTareaActualMs() {
    return Number(estado.tiempoTareaMs || 0) + (estado.tareaEnCurso && !estado.pausaActiva && estado.inicioTareaTs
        ? Math.max(0, Date.now() - estado.inicioTareaTs) : 0);
}
function limpiarTareaArmado() {
    detenerTimer($("#timerT"));
    Object.assign(estado, {tareaEnCurso:false, tareaActualId:0, procesoId:0, pendienteId:0,
        tiempoTareaMs:0, inicioTareaTs:0, timerTarea:0});
    $("#timerT").text("00:00:00.00");
    $("#tarea_nombre").text("");
}
function iniciarOrden(codigo) { return transicionArmado(() => iniciarOrdenInterna(codigo)); }
function seleccionarTipologia(codigo) { return transicionArmado(() => seleccionarTipologiaInterna(codigo)); }
function iniciarTarea(codigo, band) { return transicionArmado(() => iniciarTareaInterna(codigo, band)); }
function finalizarTareaActual() { return transicionArmado(finalizarTareaInterna); }
function finalizarTipologiaActual() { return transicionArmado(finalizarTipologiaInterna); }



$(document).ready(async () => {
	const inicializado = await initializeDataProceso(new URLSearchParams(window.location.search));
	if (inicializado) initBarcodeScanner(onCodeScanned);
});

/**
 * Proceso que inicializa todo el proceso. Mostrando las tareas, codigos de Pausa, Suspension y Finalizacion de la tarea 
 * Muestra operador logueado, Ayudante si la tarea requerida lo solicita
*/
async function initializeDataProceso(params) {
	operador = OPERADOR_ID;
	
	let respuesta;
	try {
		respuesta = await $.post(URL_API.operador, { accion: 'select', id: operador });
	} catch (error) {
		logger("ERROR", "PROCESO", "No se pudo identificar al operador: " + (error.responseText || error.message || error));
		mostrarMensaje("No se pudo iniciar la sesión del operador.", "#BD1A00", 15000, 'mensaje');
		return false;
	}
        if (respuesta && respuesta.ok === false && respuesta.motivo === 'sesion') {
			location.href = 'index.php';
			return false;
		}

		if (respuesta && respuesta[0] && respuesta[0].id) {
			estado.operadorId=respuesta[0].id;
			estado.operadorToken = (respuesta[0].token || '').trim().toUpperCase();
            sendMail('Operador se logueado (Ingreso) en el proceso de armado, por el operador:' + estado.operadorId + 'Fecha/Hora:' + new Date().toISOString());

            /** Verifico si puede tener mas de una session abierta */
			/** ================ VER COMO LO HACEMOSSSSSS ================= */
			$.post(URL_API.system, { accion: 'sesion', id: operador });

			const datosSesion = {
				operadorId:  estado.operadorId,
				estado:      'activa',
			};
	
			let respuestaSesion;
			try {
				respuestaSesion = await $.post(URL_API.sesion, {accion: 'add', datos: JSON.stringify(datosSesion)});
			} catch (error) {
				logger("ERROR", "PROCESO", "No se pudo crear la sesión: " + (error.responseText || error.message || error));
				mostrarMensaje("No se pudo iniciar la sesión del operador.", "#BD1A00", 15000, 'mensaje');
				return false;
			}
			if (!respuestaSesion || !respuestaSesion[0] || respuestaSesion[0].status !== 'ok' || !respuestaSesion[0].id) {
				mostrarMensaje("No se pudo iniciar la sesión del operador.", "#BD1A00", 15000, 'mensaje');
				return false;
			}
			estado.sesionId = Number(respuestaSesion[0].id);

			/** TERMINAR SUMAR PARA ESA ORDEN TODO EL TIEMPO QUE LLEVA */
			$("#label_operario").html(`${respuesta[0].nombre}`)
								.css({"color": "#B85C00",
									  "background-color": "#FFF3E0",
									  "border": "1px solid #F27100",
									  "border-radius": "20px",
									  "padding": "6px 16px",
									  "font-weight": "600",
									  "display": "inline-block",
									  "transition": "transform 0.25s ease"
			});

			$("#label_tpo_operario").html(`00:00:00.00`)
									.css({"color": "#B85C00",
										  "background-color": "#FFF3E0",
										  "border": "1px solid #F27100",
										  "border-radius": "20px",
										  "padding": "6px 16px",
										  "font-weight": "600",
										  "display": "inline-block",
										  "transition": "transform 0.25s ease"                                                                          
			});
		} else {
			mostrarMensaje("No se pudo identificar al operador.", "#BD1A00", 15000, 'mensaje');
			return false;
		}

	/** Carga códigos de barra iniciales */
	/** Opcion Terminacion de tarea */ 
	$.post("api/system.php", { accion: 'operacion', id: 2 }, function(data) {
		$("#barcode_close").attr("src", 'data:image/gif;base64,' + data.barcode2d);
	}, "json");
	
	/**QR - Pausa */
	$.post("api/system.php", { accion: 'operacion', id: 3 }, function(data) {
		$("#barcode_accion").attr("src", 'data:image/gif;base64,' + data.barcode2d);
	}, "json");


	/** IA: verifico si esta hablitado el modulo de IA (Valor de Referencia mia:1) */
	/** Si esta habilitado muestro chat */
	$.post("api/system.php", { 'accion': 'modulo', 'modulo':'mia'}, function(data) {
		//console.log(data);
		if(data==1){ 
			$('#ia-button').show();  // Mostrar el botón flotante
			// Área de hover combinada: botón + chat
			const $triggerArea = $('#ia-button, #chat-container');
		
			$triggerArea.hover(
				function() {
					// mouse entra → mostrar chat
					$('#chat-container').addClass('visible').stop().fadeIn(200);
				},
				function() {
					// mouse sale → ocultar chat
					//$('#chat-container').removeClass('visible').stop().fadeOut(200);
				}
			);        
			initChat(); //scrip: plugins/js/jquery.functions.js
			// Cierre manual con el botón X
			$('#close-btn').on('click', function(e) {
				e.preventDefault();
				$('#chat-container')
					.stop()
					.fadeOut(250)
					.removeClass('visible');
				
				// Opcional: volver a enfocar el botón flotante
				$('#ia-button').focus();
			});
		}    
	}, "json");  
	
	/** Teclas de funcion */
	$(document).keydown(function(e) {
		const keyCode = e.which || e.keyCode;
		// por teclado. Teclas: F2 (113), F3 (114), F4 (115)
		if (keyCode === 113 || keyCode === 114 || keyCode === 115) {
			e.preventDefault(); // Evita comportamiento por defecto del navegador
			switch (keyCode) {
				case 113: // F2 - Cargar Observaciones
					if(estado.detalleActualId!=0){
						cargaObservaciones();   // <- jquery.functions.js
					}else{
						mostrarMensaje("Debe tener seleccionada una tipologia, para realizar observacion.","#BD1A00",15000);
						return;
					}	    
					break;
				case 114: // F3 - Dasactivo pausa
					if(estado.tareaActualId !=0 ){
						if(estado.pausaActiva){
							finpausaTareaActual();      //Reinicia Tarea, fin pausa jquery.procesopausaV1.0.3
						}else{
							mostrarMensaje("NO hay pausas activas para desactivar.","#BD1A00",15000);
							return;
						}
					}else{
						mostrarMensaje("Debe tener seleccionada una tarea.","#BD1A00",15000);
						return;
					}
					break;
				case 115: // F4 - Activar la suspension
					if(estado.tareaActualId != 0){
						suspendeTipologiaActual();   // <- jquery.procesosuspenderV1.0.3
					}else{
						mostrarMensaje("Debe seleccionar una TAREA antes de SUSPENDER el proceso","#BD1A00",30000);
						return;
					}
					break;
			}
		}
	});

	return true;

}

// Vidrios y telas pertenecen al mismo menú de colocación.
function esDetalleVidrio(detalle) {
	const tipologia = String(detalle.tipologia || '');
	return tipologia.startsWith('V-') || tipologia.startsWith('TELA-M(');
}

async function onCodeScanned(code){
	code = (code || '').trim().toUpperCase();

	 // Ignora códigos vacíos o muy cortos
	if (!code || code.length < 3) {
		mostrarMensaje("⚠️ Código inválido (demasiado corto o vacío).",'#BD1A00',9000);
		return;
	}

	// ====================  PRIORIDAD 0: CIERRE DE JORNADA ====================
	// Si el código escaneado coincide con el ID o código del operador actual, cerramos jornada
	// Nota: Si tu tarjeta devuelve un ID numérico puro, asegúrate de comparar de forma compatible
	if (estado.operadorToken && code === estado.operadorToken) {
		logger("INFO", "PROCESO", "linea 202, ["+estado.operadorToken+"]. Cerro sesion.");
		confirmarCierreJornada();
		return;
	}

	// // ====================  ACCIÓN USUARIO ADMIN / NO TERMINADO / VER COMO TERMINAR ESTA OPCION ====================
	// if (code === "admin01") {
	// 	logger("INFO", "PROCESO", "linea 209, SUPERUSER - CANCELO PROCESO. Cerro sesion.");
	// 	solicitarCancelacionMaster(code);
	// 	return; // Corta la ejecución para que no lo tome como orden o tarea
	// } 

	// ==================== PRIORIDAD 1: PAUSA ACTIVA ====================
	if (estado.pausaActiva && $('#modal').hasClass('show') && $('#pausasTable').is(':visible')) {
		if (!estado.ordenId) {
			mostrarMensaje("Opcion NO permitida !!!", "#FF7700", 9000);
			return;
		}else{
			initBarcodeScannerPausa(code);   // ← Importante: Activamos la pausa -> jquery.procesopausaV1.0.3.js
			return;                          // ← Importante: salimos aquí
		}
	}

	// ==================== PRIORIDAD 2: OTRAS LÓGICAS - Modals ====================
	if ($('#modal').hasClass('show') && $('#detallesTable').is(':visible')) {
		const table = $('#detallesTable').DataTable();
		let codigoSeleccionado = null;
		if (getMenuActual() === 2) {
			// El lector puede haber escrito en el filtro: recuperar todas las filas.
			table.search('').draw();
			$('#detallesTable_filter input').val('');
			const ordenConsultada = estado.ordenId;
			try {
				const resultado = await $.post(URL_API.detalles, {
					accion: 'resolverVidrio', orden: ordenConsultada, barcode: code
				}, null, 'json');
				if (estado.ordenId !== ordenConsultada || getMenuActual() !== 2 || !$('#modal').hasClass('show')) return;
				if (resultado.error) {
					mostrarMensaje(resultado.error, "#BD1A00", 12000, 'mensaje_modal');
					return;
				}
				codigoSeleccionado = resultado.detalle ? resultado.detalle.codigo : null;
			} catch (error) {
				mostrarMensaje("No se pudo consultar la etiqueta de vidrio. Intente nuevamente.", "#BD1A00", 12000, 'mensaje_modal');
				return;
			}
		} else {
			const digitosEscaneados = extraerDigitosClave(code);
			if (!digitosEscaneados) return;
			table.rows().every(function () {
				const rowData = this.data();
				if (extraerDigitosClave(rowData.codigo) === digitosEscaneados) {
					codigoSeleccionado = rowData.codigo;
					return false;
				}
			});
		}
		
		if (codigoSeleccionado) {
			$('#modal').modal('hide');
			try {
				const respuesta = await $.post(URL_API.detalles, {'accion': 'select', 'barcode': codigoSeleccionado});
				const detalleId = respuesta[0].id;
                await seleccionarTipologia(codigoSeleccionado);
			} catch (error) {
				mostrarMensaje("[ERROR] No se pudo obtener el detalle", "#BD1A00", 9000, 'mensaje_modal');
			}
			return; // Terminamos aquí, no procesamos más reglas
		} else {
			mostrarMensaje("[NO ENCONTRADO] Código no coincide con ninguna fila", "#BD1A00", 9000, 'mensaje_modal');
			return;
		}	
	}

	// ==================== PRIORIDAD 3: OTRAS LOGICAS - NO Modals ====================
	const prefix = code.charAt(0).toUpperCase(); //solo me da el 1 caracter
	if (/^\d+$/.test(code)) { //solo numeros
		const codigoOrden = code.substring(0,5);
		iniciarOrden(codigoOrden);
		return;
	}
 
	// ── Tipologias - detalles ─────────────────
	if (/^O\d{4}$/.test(code)) {
		try {
			const operadorEscaneado = await $.post(URL_API.operador, {accion: "validarAyudante", token: code}, null, "json");
			if (!operadorEscaneado || !operadorEscaneado.ok) {
				mostrarMensaje("El operador no existe, está inhabilitado o el código es inválido.", "#BD1A00", 12000, 'mensaje');
			} else {
				mostrarMensaje(`El código pertenece al operador ${operadorEscaneado.nombre}, pero no corresponde a esta acción.`, "#FF7700", 12000, 'mensaje');
			}
		} catch (error) {
			logger("ERROR", "PROCESO", "No se pudo validar el código de operador: " + (error.responseText || error.message || error));
			mostrarMensaje("No se pudo validar el código del operador.", "#BD1A00", 12000, 'mensaje');
		}
		return;
	}

	if (/^O\d{5,}$/.test(code)) {
		const ordenEscaneada = code.substring(1);
		if (!estado.ordenId || ordenEscaneada !== String(estado.ordenCodigo)) {
			mostrarMensaje("El código de Tipologías no corresponde a la orden cargada.", "#BD1A00", 12000, 'mensaje');
			return;
		}
		mostrarTipologiasPendientes();
		return;
	}

	if (prefix === "O") {
		mostrarMensaje("Código de operador o de Tipologías inválido.", "#BD1A00", 12000, 'mensaje');
		return;
	}
	
	//──  View Orden  ─────────────────
	if (prefix === "V") {
        resetModalCompleto(); //<- jquery.functions.js

        const $modal = $('#modal');

        // Guardás los datos en el modal antes de cargar
        $modal.data('ordenId', estado.ordenId);
        $modal.data('operarioId', estado.operadorId);
        $modal.data('modulo', 'proceso');
        $modal.find('.modal-title').html('OP - Orden de Produccion');
        $modal.find('.modal-body').load("include/pages/unload.php?modo=proceso", function(response, status, xhr){
            if (status === "error") {
                logger("ERROR", "ORDENES", "Fallo al cargar unload.php?modo=proceso: " + xhr.status + " " + xhr.statusText);
                mostrarMensaje("⚠️ No se pudo cargar el diagrama de corte.", "#BD1A00", 10000,'mensaje_modal');
                return;
            }
            $modal.modal('show');
        });
        return;
	}

	// ── Inicio de Tareas ────────────────────────
	if (prefix === "T") {
		const prefixT = code.charAt(2).toUpperCase(); //solo me da el 1 caracter
		if(prefixT === "F"){
			if(estado.pausaActiva){
				mostrarMensaje("Hay una Pausa activa, debe desactivarla antes. Presione la tecla [F3].", "#BD1A00", 9000);
				return;
			}else{
				
				if(estado.tareaEnCurso){
					// Hay una tarea iniciada que todavía no fue cerrada con "Finalizar Tarea".
					mostrarMensaje("Debe finalizar la tarea actual antes de cerrar la tipología. Escanee el código de Finalizar Tarea.", "#BD1A00", 12000);
					return;
				}else{

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
                                        Finalizar Tipologia
                                    </h4>
                                    <p style="color: #666; margin-bottom: 28px; font-size: 0.95rem;">
                                        ¿Desea cerrar la Tipologia?
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
                    $('#btn-true').off('click').on('click', function () {
                        finalizarTipologiaActual();
                    });
                
                    $('#btn-false').off('click').on('click', function () {
                        mostrarMensaje("Puede continuar con el proceso", "#32A123", 15000, 'mensaje');
                        $('#modal-overlay').remove();
                    });
				
				}
			}        
		}else{
			iniciarTarea(code, estado.tareaEnCurso);
		}
	}

	// ──   ────────────────────────
	if (prefix === "C") {
		switch(code){
			case 'C0001': //Reanudar - Se activa si esta en pausa
				mostrarMensaje("reanuda tarea " + code, "#FF3C00", 15000, 'mensaje');
				break; 
			case 'C0002': //Finalizar la Tarea 
				if (!estado.tareaEnCurso || !estado.procesoId) {
					mostrarMensaje("No hay una tarea activa para finalizar.", "#BD1A00", 9000, 'mensaje');
					return;
				}
				if(estado.pausaActiva){
					mostrarMensaje(" ⚠️ Hay una Pausa activa, debe desactivarla antes. Presione la tecla [F3].", "#BD1A00", 9000, 'mensaje');
					return;
				}else{
					finalizarTareaActual();
				}
				break; //suspendes
			case 'C0003': 				         
				iniciarpausaTareaActual();       // -> inicio pausa, jquery.procesopausaV1.0.3.js
				break;
			default:
				mostrarMensaje(" ❌ Código de acción no reconocido. Escanee nuevamente el QR.", "#BD1A00", 9000, 'mensaje');
				return;
		}
	}
	
}

/** Funcion para mostrar, buscar y ver archivo PDF de la Orden */
async function iniciarOrdenInterna(codigo) {
    if (estado.ordenId && String(Number(codigo)) === String(Number(estado.ordenCodigo))) {
        if (estado.cierreOrdenPendiente) return await finalizarOrdenCompleta();
        mostrarMensaje("La orden ya está cargada. Continúe con la tarea actual.", "#1E88E5", 9000, 'mensaje');
        return;
    }
    if (estado.tareaEnCurso || estado.pausaActiva) {
        mostrarMensaje("Finalice o suspenda la tarea antes de cambiar de orden.", "#BD1A00", 12000, 'mensaje');
        return;
    }
	try{
		const respuesta = await $.post(URL_API.orden, {accion: "select", barcode:Number(codigo)});
		if(respuesta && respuesta[0] && respuesta[0].id === 0){
			mostrarMensaje("⚠️ Orden no encontrada", "#BD1A00", 15000, 'mensaje');
			return;
		}

		const orden=respuesta[0];
		if([2, 3].includes(Number(orden.estado))){
			const estadoNombre = (orden.estado === 2 ? "Terminada" : "Entrega/Instalacion");
			mostrarMensaje(`⚠️ La Orden: ${orden.codigo} ya esta ${estadoNombre}`,"#FF7700",9000);
			return;
		}

		limpiarTareaArmado();
        estado.detalleActualId = 0;
        estado.tareaActualNro = 0;
        estado.tiempoTareaTotalMs = 0;
        estado.operadorIdAux = 0;
        $("#topologia,#label_tpo_ideal,#label_operario_aux").text("");
        estado.ordenId=orden.id;
		estado.ordenCodigo=orden.codigo;
		estado.ordenEstado=1; //procesando
        estado.cierreOrdenPendiente=false;

        // Esto es por si queremos 
        // estado.tiempoOperario = 0;
        // $('#label_tpo_operario').text('00:00:00.00');

		const pendientesOrden = await $.post(URL_API.pendiente, {accion: "listarPorOrden", 'ordenId': orden.id});
		const ahora = Date.now();

		if (pendientesOrden && pendientesOrden.length > 0) {

			const masReciente = pendientesOrden[0]; // el backend ya los devuelve ordenados por created DESC

			estado.tiempoOrdenMs = Number(masReciente.tiempoOrdenMs) || 0;
			estado.inicioOrdenTs = ahora - estado.tiempoOrdenMs;
			iniciarTimer(ahora, $("#timerO"), estado.tiempoOrdenMs);

			estado.tareaEnCurso = false; // se define recien al escanear la tipologia puntual

			const plural = pendientesOrden.length > 1 ? `tiene ${pendientesOrden.length} tipologías pendientes` : `tiene 1 tipología pendiente`;
			mostrarMensaje(`✅ Orden reanudada -> ${plural}. Escanee la tipología a continuar.`, "#1E88E5", 15000, 'mensaje');

		}else{
			estado.tareaEnCurso  = false;
			estado.tiempoOrdenMs = 0;       // orden nueva: sin tiempo previo heredado
            
            estado.tiempoOperario = 0;
            $('#label_tpo_operario').text('00:00:00.00');

			const tiempoResp = await $.post(URL_API.orden, { accion: "time", id: estado.ordenId, estado: estado.ordenEstado }, null, "json");        
			if (!tiempoResp || !tiempoResp.timestamp_inicio) {
				mostrarMensaje("❌ Error al obtener tiempo de la orden", "#BD1A00", 15000, 'mensaje');
				return;
			}        
			const inicioReal = Number(tiempoResp.timestamp_ms);
			if (!Number.isFinite(inicioReal) || inicioReal <= 0) {
				mostrarMensaje("Hora de inicio invalida", "#BD1A00", 15000, 'mensaje');
				return;
			}
			estado.inicioOrdenTs = inicioReal;
			iniciarTimer(inicioReal, $("#timerO"), 0);

			mostrarMensaje("Orden iniciada -> escanee una topologia","#32A123", 15000, 'mensaje');
		}
		
		$.post(URL_API.orden, {accion: "update", ido: estado.ordenId, estado: estado.ordenEstado})
			.fail((jqXHR, textStatus) => logger("ERROR", "PROCESO", "Fallo update orden id=" + estado.ordenId + ": " + textStatus));

		$("#detalle").html('<strong>Nro: '+orden.codigo + ' - Cliente: ' +orden.cliente+'</strong></br>'+
			'<strong>Fecha: '+ formatearFecha(orden.fecha)+'</strong></br>');
		$("#barcode_orden_img").attr("src", 'data:image/png;base64,'+orden.btipologias); // O+número de orden: abre el modal de tipologías
		$("#barcode_Opdf").attr("src", 'data:image/gif;base64,'+orden.bpdf); //View Orden

	}catch(e){
		mostrarMensaje(" ❌ Error al iniciar orden "+ (e.message || e), "#BD1A00", 15000, 'mensaje');
		//console.error(e);
	}
}

function mostrarTipologiasPendientes() {
	if (!estado.ordenId) {
		mostrarMensaje(" ⚠️ Primero escanee la orden", "#FF7700", 9000, 'mensaje');
		return;
	}
 
	// Limpiar modal previo y preparar
	const $modal = $('#modal');
	const $modalBody = $modal.find('.modal-body');
	const $modalTitle = $modal.find('.modal-title');
 
	$modalTitle.text(`Topologías de Orden ${estado.ordenCodigo}`);
	$modalBody.html(`
		<div class="text-center py-5 my-3">
			<div class="spinner-border text-primary" style="width:3rem;height:3rem" role="status">
				<span class="visually-hidden">Cargando...</span>
			</div>
			<p class="mt-3 fw-bold">Cargando topologías pendientes...</p>
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
			const cargarTabla = function(respuesta, pendientes) {
				if (!respuesta || !Array.isArray(respuesta) || respuesta.length === 0) {
					$modalBody.html(`
						<div class="alert alert-info text-center py-5">
							<i class="fa fa-info-circle fa-2x mb-3 d-block"></i>
							No hay topologías registradas para esta orden
						</div>
					`);
					return;
				}        
				// Un pendiente con proceso_id es una tarea que quedó en curso.
				const pendientesPorDetalle = new Set(
					pendientes
						.filter(p => Number(p.detalle_id) > 0 && Number(p.proceso_id) > 0)
						.map(p => Number(p.detalle_id))
				);

				const detallesConPendiente = respuesta.map(item => ({
					...item,
					tareaPendiente: pendientesPorDetalle.has(Number(item.id))
				}));

				// FIX: antes usaba estado.menu (cacheado al cargar el script). Ahora lee el valor actual.
				let datosAMostrar = getMenuActual() === 1
					? detallesConPendiente.filter(item => !esDetalleVidrio(item))
					: detallesConPendiente.filter(esDetalleVidrio);
				if (datosAMostrar.length === 0) {
					$modalBody.html(`
						<div class="alert alert-success text-center py-5">
							<i class="fa fa-check-circle fa-2x mb-3 d-block text-success"></i>
							NO hay topologías para realizar
						</div>
					`);
					return;
				}
				
				// 4. Inicializar la tabla con los datos
				inicializarTablaDetalles(datosAMostrar);
			};

			// La tabla depende de detalles; los pendientes solo agregan el resaltado.
			// Si esa consulta secundaria falla, se mantiene la tabla y su navegación.
			$.post(URL_API.detalles, {accion: "listar", orden: estado.ordenId }, function(respuesta){
				$.post(URL_API.pendiente, {accion: "listarPorOrden", ordenId: estado.ordenId}, function(pendientes){
					cargarTabla(respuesta, Array.isArray(pendientes) ? pendientes : []);
				}, "json").fail(function(jqXHR, textStatus, errorThrown){
					logger("WARNING", "PROCESO", "No se pudieron cargar pendientes: " + textStatus + " " + errorThrown);
					cargarTabla(respuesta, []);
				});
			}, "json").fail(function(jqXHR, textStatus, errorThrown){
				logger("ERROR", "PROCESO", "linea 551"
					+ " | status: "     + jqXHR.status
					+ " | textStatus: " + textStatus
					+ " | thrown: "     + errorThrown
					+ " | response: "   + jqXHR.responseText
				);
				$modalBody.html(`
					<div class="alert alert-danger text-center py-4">
						Error al cargar los datos de las topologías
					</div>
				`);
			});
		}, error: function(jqXHR, textStatus, errorThrown){
			// FIX: había una llamada a logger() anidada dentro de otra (copy-paste),
			// pasaba el valor de retorno de una como argumento de la otra.
			logger("ERROR", "PROCESO", "linea 566, [" + textStatus + "] " + errorThrown);
			$modalBody.html(`
				<div class="alert alert-danger text-center py-4">
					No se pudo cargar la estructura de la tabla
				</div>
			`);            
		}                 
	});
}    

function inicializarTablaDetalles(items){
	const $table = $('#detallesTable');
    const esFilaVidrio = item => String(item.tipologia || '').startsWith('V-');
    const escapar = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    if (!document.getElementById('estilo-lectura-vidrios')) {
        $('<style>', {id:'estilo-lectura-vidrios'}).text(`
            #detallesTable tbody tr.detalle-vidrio-row > td {
                padding:32px 12px !important; vertical-align:middle;
                border-top:12px solid #edf1f5;
            }
            #detallesTable .vidrio-qr-bloque {min-width:140px; text-align:center;}
            #detallesTable .vidrio-qr-bloque img {
                display:block; width:88px; height:88px; padding:12px;
                box-sizing:content-box; margin:0 auto; background:#fff;
                border:0; border-radius:0; box-shadow:none;
            }
            #detallesTable .vidrio-qr-codigo {
                display:block; margin-top:10px; font-size:14px;
                font-weight:700; color:#17324d; white-space:nowrap;
            }
        `).appendTo('head');
    }
    $('#aviso-lectura-vidrios').remove();
    if (items.some(esFilaVidrio)) {
        $('<p>', {id:'aviso-lectura-vidrios', class:'alert alert-info'})
            .text('Vidrios: apunte al centro del QR y compruebe el código de la fila que desea trabajar.')
            .insertBefore($table);
    }


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
					createdRow: function(row, data) {
                        $(row).toggleClass('detalle-vidrio-row', esFilaVidrio(data));
						if (!data.tareaPendiente) return;

						$(row)
							.attr('title', 'Esta tipología tiene una tarea pendiente')
							.css({
								'background-color': '#FFF3CD',
								'box-shadow': 'inset 5px 0 0 #FF9800'
							});
						$(row).children('td').css('background-color', '#FFF3CD');
					},
					columns: [
						 { data: "codigo", searchable: false, title: "#", render: data => `<strong>${data}</strong>` },
						 { data: "tipologia", title: "T", searchable: false },
						 { data: "descripcion", title: "Descripción", defaultContent: "—", searchable: false},
						 { data: "barcode2d", 
                           title: "QR", 
                           className: "text-center", 
                           orderable: false, 
                           searchable: false, 
                           render: function(data, type, row) {
							if (Number(row.c_estado) === 2 || !data) return "—";
                            if (esFilaVidrio(row)) {
                                const codigo = escapar(row.codigo);
                                return `<div class="vidrio-qr-bloque">
                                    <img src="data:image/png;base64,${escapar(data)}" class="barcode_item_det"
                                        data-codigo="${codigo}" alt="QR del vidrio ${codigo}" title="Seleccionar ${codigo}">
                                    <span class="vidrio-qr-codigo">${codigo}</span>
                                </div>`;
                            }
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

async function seleccionarTipologiaInterna(codigoDetalle) {
    if (estado.tareaEnCurso || estado.pausaActiva) {
        mostrarMensaje("Hay una tarea activa. Finalícela o suspéndala antes de seleccionar otra tipología.", "#1E88E5", 10000, 'mensaje');
        return;
    }
	try {
		const respuesta = await $.post(URL_API.detalles, {accion: "select", barcode: codigoDetalle});
		if (!respuesta || !respuesta[0] || respuesta[0].id === 0) {
			mostrarMensaje("Topología no encontrada", "#BD1A00", 9000, 'mensaje');
			return;
		}
		const detalle = respuesta[0];

		if ([2,3].includes(Number(detalle.estado))) {
			mostrarMensaje("Esta tipología está suspendida o terminada. Debe activarla Administración desde el dashboard.", "#FF7700", 15000, 'mensaje');
			return;
		}

		limpiarTareaArmado();
        estado.tiempoTareaTotalMs = 0;
        estado.tiempoPausaTotalMs = 0;
        $("#label_operario_aux").text("");
        estado.detalleActualId = detalle.id;
		estado.tareaMax        = detalle.etapas;
		estado.operadorIdAux   = 0;

		$("#topologia")
			.html(`Tipología: ${detalle.tipologia}<br>
				   Código   : ${detalle.codigo}`)
			.css({"color": "#B85C00",
				"background-color": "#FFF3E0",
				"border": "1px solid #F27100",
				"border-radius": "20px",
				"padding": "6px 16px",
				"font-weight": "600",
				"display": "inline-block",
				"transition": "transform 0.25s ease"
			});
		$("#label_tpo_ideal").html(`${detalle.tiempo_estimado}`);

        if(Number(detalle.ayudante) === 1){
            const operador = await escanearCodigoOperadorAyudante();
            estado.operadorIdAux = operador.id;
			$("#label_operario_aux").html(`Ayudante: ${operador.nombre}`)
								    .css({"color": "#F0350E",
									      "background-color": "#FFF3E0",
									      "border": "1px solid #F38400",
									      "border-radius": "20px",
									      "padding": "6px 16px",
									      "font-weight": "600",
									      "display": "inline-block",
									      "transition": "transform 0.25s ease"
			                            });
        }

		const pendiente = await $.post(URL_API.pendiente, {accion: "select", ordenId: estado.ordenId, detalleId: detalle.id});

		if (pendiente && pendiente.length > 0 && pendiente[0].id) {
			const ahora = Date.now();

			estado.pendienteId        = Number(pendiente[0].id);
			estado.procesoId          = Number(pendiente[0].proceso_id);
			estado.tareaActualId      = Number(pendiente[0].tareaActualId);
			estado.tiempoTareaMs      = Number(pendiente[0].tiempoTareaMs) || 0;
			estado.tiempoTareaTotalMs = Number(pendiente[0].tiempoTareaTotalMs) || 0;

			// Cuántas tareas de esta tipología ya se completaron: se calcula desde la
			// fuente de verdad (tabla procesos), no desde un contador que viaja por
			// el cliente y puede desincronizarse entre sesiones/dispositivos.
			const conteo = await $.post(URL_API.proceso, {accion: "contarCompletadas", detalle: estado.detalleActualId});
			// tareaActualNro representa exclusivamente tareas FINALIZADAS. La tarea
			// que quedó en curso se muestra como la siguiente, pero no se cuenta hasta
			// que procesos.php confirme su finalización.
			estado.tareaActualNro = (conteo && conteo.completadas != null)
				? Number(conteo.completadas)
				: 0;

			if (estado.procesoId > 0) {
				// Había una tarea puntual en curso. El pendiente guarda IDs y tiempos,
				// pero no el nombre visible: se recompone desde proceso -> tarea.
				const procesoPendiente = await $.post(URL_API.proceso, {
					accion: "select",
					id: estado.procesoId
				});
				const codigoTarea = procesoPendiente && procesoPendiente[0]
					? procesoPendiente[0].tarea_codigo
					: null;

				const tareaPendiente = codigoTarea
					? await $.post(URL_API.tarea, {accion: "select", codigo: codigoTarea})
					: null;

				if (!tareaPendiente || !tareaPendiente[0] || !tareaPendiente[0].id) {
					mostrarMensaje("No se pudo recuperar la tarea pendiente. No se reanudó el proceso.", "#BD1A00", 15000, 'mensaje');
					return;
				}

				const retomada = await $.post(URL_API.proceso,{accion:'add',tarea:Number(tareaPendiente[0].id),
                    detalle:detalle.id,operador:estado.operadorId,operadorAux:estado.operadorIdAux,
                    pendienteId:estado.pendienteId},null,'json');
                if (!Number(retomada?.id)) throw new Error('No se pudo recuperar la tarea pendiente.');
                estado.procesoId = Number(retomada.id);
                estado.tiempoTareaMs = Number(retomada.tiempo_ms) || 0;
                estado.tareaActualId = Number(tareaPendiente[0].id);
				estado.tareaEnCurso  = true;
				estado.inicioTareaTs = Date.now();
				iniciarTimer(estado.inicioTareaTs, $("#timerT"), estado.tiempoTareaMs);
				$("#tarea_nombre").html(`${tareaPendiente[0].nombre} (${estado.tareaActualNro + 1})`)
					.css({
						"color": "#B85C00",
						"background-color": "#FFF3E0",
						"border": "1px solid #F27100",
						"border-radius": "20px",
						"padding": "6px 16px",
						"font-weight": "600",
						"display": "inline-block",
						"transition": "transform 0.25s ease"
					});
			} else {
				// Estaba entre tareas: falta escanear la próxima
				estado.tareaEnCurso = false;
			}

			mostrarMensaje(`Tipología retomada [${detalle.tipologia}] → continúe con la tarea.`, "#1E88E5", 15000, 'mensaje');

			// Recién ahora, confirmado que se restauró todo, consumimos el pendiente
			const respStatus = estado.tareaEnCurso ? [{status:"ok"}] : await $.post(URL_API.pendiente, {accion: "status", id: estado.pendienteId});
			logger("INFO", "PROCESO", "Pendiente consumido: " + JSON.stringify(respStatus[0]));

			return;
		}

		// Tipología nueva (o ya activa, sin nada que resumir): arrancamos de cero
		estado.tareaActualId  = 0;
		const conteoActual = await $.post(URL_API.proceso, {accion: "contarCompletadas", detalle: detalle.id});
        if (!Number.isFinite(Number(conteoActual?.completadas))) throw new Error('No se pudo recuperar el avance');
        estado.tareaActualNro = Number(conteoActual.completadas);
        estado.tareaEnCurso = false;

		mostrarMensaje(`Topología seleccionada[`+detalle.tipologia+`] → escanee tarea.`, "#32A123", 15000, 'mensaje');
	} catch(e) {
		// logger("ERROR", "PROCESO","linea 752, [" + e.responseText + " " + e.statusText +"]");
        logger("ERROR", "PROCESO", "linea 458, [" + e.message + "] stack: " + e.stack);
		mostrarMensaje("Error al cargar tipología", "#8C1701", 9000, 'mensaje');
	}
}    

async function iniciarTareaInterna(codigoTarea, band){
	// console.log(codigoTarea + " " + band + " : " + estado);

	if (!estado.detalleActualId) {
		mostrarMensaje("Primero seleccione una topología", "#F27100", 9000, 'mensaje');
		return;
	}    

	const respuesta = await $.post(URL_API.tarea, { accion: "select", codigo: codigoTarea });
	if (!respuesta || !respuesta[0]) {
		mostrarMensaje("Código de tarea no válido", "#F27100", 15000, 'mensaje');
		return;
	}

	const tarea = respuesta[0];
    if (estado.tareaEnCurso) {
        const misma = Number(tarea.id) === Number(estado.tareaActualId);
        mostrarMensaje(misma ? "La tarea ya está en curso; se conserva el tiempo trabajado."
            : "Debe finalizar la tarea actual antes de escanear otra.", "#F27100", 9000, 'mensaje');
        return;
	}else{
		if (estado.tareaEnCurso) {
			mostrarMensaje(`Ya hay una tarea en curso (${estado.tareaActualNro}). Debe finalizarla antes.`, "#F27100", 9000, 'mensaje');
			return;
		}

		try {
			const ahora = Date.now();
			// El reloj se inicia cuando el servidor confirma el proceso.
			$("#tarea_nombre").html(`${tarea.nombre} (${estado.tareaActualNro + 1})`)
							  .css({"color": "#B85C00",
									"background-color": "#FFF3E0",
									"border": "1px solid #F27100",
									"border-radius": "20px",
									"padding": "6px 16px",
									"font-weight": "600",
									"display": "inline-block",
									"transition": "transform 0.25s ease"
								});
		
			const resultado = await $.post(URL_API.proceso, {
				accion: "add",
				tarea: tarea.id,
				detalle: estado.detalleActualId,
				estado: 1,
				operador: estado.operadorId,
				operadorAux: estado.operadorIdAux
			});
		
			if (!resultado || !(Number(resultado.id) > 0)) {
				limpiarTareaArmado();
				mostrarMensaje(`Tarea ya realizada o Error`, "#F27100", 9000, 'mensaje');
				return;
			}

			// Recién ahora, confirmado el éxito, comprometemos el estado local:
			estado.tareaActualId = Number(tarea.id);
            estado.procesoId      = Number(resultado.id);
			estado.tareaEnCurso   = true;
			estado.tiempoTareaMs = Number(resultado.tiempo_ms) || 0;
            estado.inicioTareaTs = Date.now();
            iniciarTimer(estado.inicioTareaTs, $("#timerT"), estado.tiempoTareaMs);
		
			if (resultado.retomada) {
				mostrarMensaje(`Tarea retomada`, "#1E88E5", 9000, 'mensaje');
			} else {
				mostrarMensaje(`Tarea iniciada`, "#32A123", 9000), 'mensaje';
			}
	
		} catch(e) {
			limpiarTareaArmado();
			mostrarMensaje("Error al iniciar tarea", "#8C1701", 15000, 'mensaje');
		}
	}
}

async function finalizarTareaInterna() {
    if (!estado.tareaEnCurso || !estado.procesoId || estado.pausaActiva) return;
    try {
        // logger("INFO", "PROCESO", "linea 697: " + JSON.stringify(estado));
        let duracionMs = tiempoTareaActualMs();
        let respuesta = await $.post({
            url: URL_API.proceso,
            data: { accion: "update", 
                    id: estado.procesoId, 
                    circuito: "armado",
                    estado: 2, 
                    tiempo: duracionMs},
            dataType: "json"
        });

        if (!respuesta || !respuesta[0] || respuesta[0].status !== "ok") {
            mostrarMensaje("Error al actualizar el proceso (proceso inválido)", "#D32F2F", 10000, 'mensaje');
            logger("ERROR", "PROCESO", "Linea 864, Finalizar terea Actual ha fallado, procesoId:[" + estado.procesoId+"]");
            return; // no tocamos timers ni contadores, queda todo consistente
        }

        // En un reintento usamos la duración que ya confirmó el servidor.
        if (Number.isFinite(Number(respuesta[0].tiempo_ms))) duracionMs = Number(respuesta[0].tiempo_ms);
        detenerTimer($("#timerT"));
        if (respuesta[0].descartada === true) {
            const ahora = Date.now();
            const tiempoOrdenActual = estado.inicioOrdenTs
                ? ahora - estado.inicioOrdenTs : (estado.tiempoOrdenMs || 0);
            estado.tiempoOrdenMs = Math.max(0, tiempoOrdenActual - duracionMs);
            estado.inicioOrdenTs = ahora - estado.tiempoOrdenMs;
            iniciarTimer(ahora, $("#timerO"), estado.tiempoOrdenMs);
            limpiarTareaArmado();
            mostrarMensaje("Lectura descartada por duración de hasta 60 segundos. Escanee la tarea correcta.", "#F27100", 10000, 'mensaje');
            return;
        }
        estado.tiempoTareaMs       = duracionMs;
        estado.tiempoTareaTotalMs += duracionMs;
        estado.tiempoOperario     += duracionMs;

        $('#label_tpo_operario').text(`${msToHhmmssms(estado.tiempoOperario)}`);
        $("#timerT").text("00:00:00.00");
        $("#tarea_nombre").text("");

        logger("INFO", "PROCESO", "linea 878: proceso " + respuesta[0].id + " finalizado, status: " + respuesta[0].status);

        // El contador avanza una única vez: al terminar y persistir la tarea.
        // Antes se incrementaba tanto al iniciarla como al cerrarla, por lo que
        // podía saltear etapas y finalizar la tipología de forma prematura.
        estado.tareaActualNro = Number(estado.tareaActualNro) + 1;
        limpiarTareaArmado();

        if (Number(estado.tareaActualNro) >= Number(estado.tareaMax)) {
            mostrarMensaje(`Se finalizaron todas las Tarea, elija una nueva Tipologia`, "#32A123", 15000, 'mensaje');
            await finalizarTipologiaInterna();
        } else {
            mostrarMensaje(`Tarea finalizada → escanee la siguiente tarea`, "#32A123", 15000, 'mensaje');

        }
    } catch (e) {
        mostrarMensaje("Error al actualizar el proceso", "#D32F2F", 10000, 'mensaje');
    }


}

/** FINALIZAR TOPOLOGÍA (cuando termina las 8 tareas) */
async function finalizarTipologiaInterna() {
    if (!estado.detalleActualId || estado.tareaEnCurso || estado.pausaActiva) return;
    try {
        const respuestaDetalle = await $.post(URL_API.detalles, {
            accion: "update",
            id: estado.detalleActualId,
            estado: 3,
            operador: estado.operadorId
        });

        // Ajustar esta validación según lo que realmente devuelva detalles.php
        if (respuestaDetalle?.status !== "ok") {
            mostrarMensaje("Error al finalizar la tipología", "#D32F2F", 10000, 'mensaje');
            logger("ERROR", "PROCESO", "Fallo update detalle id=" + estado.detalleActualId);
            return; // no tocamos DOM ni estado, queda todo consistente para reintentar
        }

        const tipologias_pendientes = await $.post(URL_API.detalles, {accion: "last", id_o: estado.ordenId});
        if (!Number.isFinite(Number(tipologias_pendientes)) || tipologias_pendientes === null) throw new Error('No se pudo consultar el avance de la orden');
        // Limpiar después de confirmar también el avance de la orden.
        limpiarTareaArmado();
        $("#topologia,#label_operario_aux").text("");
        $("#label_tpo_ideal").text("00:00:00.00");

        estado.detalleActualId    = 0;
        estado.tareaActualNro     = 0;
        estado.tareaActualId      = 0;
        estado.tareaEnCurso       = false;
        estado.tiempoTareaMs      = 0;
        estado.tiempoTareaTotalMs = 0;
        estado.tiempoPausaTotalMs = 0;
        // estado.tiempoOperario NO se resetea acá: es el acumulado total del operario

        if (Number(tipologias_pendientes) === 0) {
            estado.cierreOrdenPendiente = true;
            await finalizarOrdenCompleta();
        } else {
            mostrarMensaje("Tipología terminada → escanee la siguiente Tipologia a trabajar", "#32A123", 15000, 'mensaje');
        }

    } catch (e) {
        mostrarMensaje("Error al finalizar la tipología", "#D32F2F", 10000, 'mensaje');
        logger("ERROR", "PROCESO", "finalizarTipologiaActual excepción: " + (e.responseText || e.message || e));
    }
    $('#modal-overlay').remove();
}

/** FINALIZAR ORDEN COMPLETA */
async function finalizarOrdenCompleta() {
    try {

        // Tiempo real de la orden hasta este instante, no el acumulado desactualizado
        const tiempoOrdenActual = estado.inicioOrdenTs ? (Date.now() - estado.inicioOrdenTs) : (estado.tiempoOrdenMs || 0);

        const respuesta = await $.post(URL_API.orden, {
            accion: "end",
            ido: estado.ordenId,
            tiempo: msToHhmmssms(tiempoOrdenActual)
        });

        if (!respuesta || respuesta.status === "error") {
            mostrarMensaje("Error al finalizar la orden. Vuelva a escanear su número para reintentar.", "#D32F2F", 10000, 'mensaje');
            logger("ERROR", "PROCESO", "Fallo al finalizar orden id=" + estado.ordenId);
            return;
        }

        detenerTimer($("#timerO"));
        estado.cierreOrdenPendiente = false;
        mostrarMensaje("¡ORDEN FINALIZADA COMPLETAMENTE!", "#32A123", 15000, 'mensaje');
        setTimeout(() => location.reload(), 2000); // ver punto 2

    } catch (e) {
        mostrarMensaje("Error al finalizar la orden. Vuelva a escanear su número para reintentar.", "#D32F2F", 10000, 'mensaje');
        logger("ERROR", "PROCESO", "finalizarOrdenCompleta excepción: " + (e.responseText || e.message || e));
    }
}

/** Abre un modal o alerta para confirmar que el operario desea cerrar su jornada laboral */
function confirmarCierreJornada() {
    resetModalCompleto();
	// Crear overlay y modal dinámicamente si no existe
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
						Cerrar Sesion
					</h4>
					<p style="color: #666; margin-bottom: 28px; font-size: 0.95rem;">
						¿Desea cerrar la sesion?
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
	$('#btn-true').off('click').on('click', function () {
		registrarFinJornada();
	});

	$('#btn-false').off('click').on('click', function () {
		mostrarMensaje("Puede continuar con el proceso", "#32A123", 15000);
		$('#modal-overlay').remove();
	});
}

/** Envía el estado actual al backend y limpia la sesión */
function registrarFinJornada() { return transicionArmado(cerrarJornadaArmado); }
async function cerrarJornadaArmado() {
    $('#modal-overlay').remove();
    if (!estado.sesionId || estado.pausaActiva) {
        mostrarMensaje("Verifique la sesión y finalice la pausa antes de cerrar la jornada.", "#BD1A00", 12000, 'mensaje');
        return;
    }
    try {
        const datosSesion = {estado: estado.detalleActualId || estado.tiempoOperario > 0 ? 'cerrada_con_actividad' : 'cerrada_sin_actividad'};
        let respuesta;
        if (!estado.detalleActualId) {
            respuesta = await $.post(URL_API.sesion, {accion:'update', id:estado.sesionId, datos:JSON.stringify(datosSesion)}, null, 'json');
        } else {
            const datosCierre = {
                ordenId:estado.ordenId, detalleId:estado.detalleActualId, sesionId:estado.sesionId,
                operadorId:estado.operadorId, procesoId:estado.tareaEnCurso ? estado.procesoId : 0,
                tareaActualId:estado.tareaEnCurso ? estado.tareaActualId : 0,
                tiempoOrdenMs:estado.inicioOrdenTs ? Date.now()-estado.inicioOrdenTs : (estado.tiempoOrdenMs||0),
                tiempoTareaMs:estado.tareaEnCurso ? tiempoTareaActualMs() : 0,
                tiempoTareaTotalMs:estado.tiempoTareaTotalMs||0, tiempoOperadorMs:estado.tiempoOperario||0,
                menu:getMenuActual()
            };
            const evento={tipo:'cierre_jornada',descripcion:'cierre_jornada',procesoId:datosCierre.procesoId,operadorId:estado.operadorId,tiempo:0};
            respuesta=await $.post(URL_API.sesion,{accion:'cerrarConPendiente',sesionId:estado.sesionId,
                datosSesion:JSON.stringify(datosSesion),datosCierre:JSON.stringify(datosCierre),datosEvento:JSON.stringify(evento)},null,'json');
        }
        if (respuesta?.[0]?.status !== 'ok') throw new Error('El servidor no confirmó el cierre.');
        estado.jornadaCerrada = true;
        limpiarTareaArmado();
        detenerTimer($("#timerO"));
        detenerTimer($("#timerP"));
        mostrarMensaje("Jornada finalizada con éxito. Trabajo guardado.", "#32A123", 10000, 'mensaje');
        setTimeout(() => { location.href='index.php'; }, 1500);
    } catch (e) {
        mostrarMensaje("No se pudo cerrar la jornada. El trabajo sigue activo; vuelva a intentarlo.", "#BD1A00", 15000, 'mensaje');
        logger('ERROR','PROCESO',e.message || e);
    }
}

function cargaObservaciones(){
    resetModalCompleto();
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
						Cargar Observacion?
					</h4>
					<p style="color: #666; margin-bottom: 28px; font-size: 0.95rem;">
						<textarea class="form-control" id="observacion" rows="3"></textarea> 
					</p>
					<div style="display: flex; gap: 16px; justify-content: center;">
						<button id="btn-ok" style="
							padding: 14px 32px;
							font-size: 1rem;
							font-weight: 600;
							border: none;
							border-radius: 8px;
							background: #1976d2;
							color: #fff;
							cursor: pointer;
							transition: background 0.2s;
						"> Guardar</button>
						<button id="btn-cancel" style="
							padding: 14px 32px;
							font-size: 1rem;
							font-weight: 600;
							border: none;
							border-radius: 8px;
							background: #e53935;
							color: #fff;
							cursor: pointer;
							transition: background 0.2s;
						"> Cancelar</button>
					</div>
				</div>
			</div>
		`);
    }

	$('#btn-ok').off('click').on('click', function () {
        const observaciones = $("#observacion").val().trim();
        if (observaciones === "") {
            mostrarMensaje("Debes escribir al menos una observación antes de guardar.","#BD1A00",15000);
            return;
        }

        $.post(URL_API.detalles, {'accion': "details", 'id': estado.detalleActualId, 'descripcion': observaciones, 'opc': 0}, function(respuesta){
            if(respuesta[0].resultado === 'ok'){
                mostrarMensaje("Observacion guardada correctamente","#32A123", 15000);
            }else{
                mostrarMensaje("Error!!! NO se cargo la observacion. Contacte con administracion.","#BD1A00", 15000); 
            }
        });
        $('#modal-overlay').remove();
    });

	$('#btn-cancel').off('click').on('click', function () {
       return;   
    });

}

async function escanearCodigoOperadorAyudante() {
    return new Promise((resolve, reject) => {

        resetModalCompleto();

        const modalEl   = document.getElementById('modal');
        const modal     = bootstrap.Modal.getOrCreateInstance(modalEl);
        const tituloEl  = document.getElementById('title-modal');
        const mensajeEl = document.getElementById('mensaje_modal');
        const bodyEl    = modalEl.querySelector('.modal-body');

        let finalizado = false;
        tituloEl.textContent = 'Escanear operador ayudante';
        mensajeEl.innerHTML = '';
        bodyEl.innerHTML = `
            <label class="form-label">Código de acceso</label>
            <input type="text" class="form-control" id="inputCodigoOperadorAyudante" autocomplete="off">
            <div class="form-text text-danger d-none" id="errorCodigoOperadorAyudante"></div>
        `;

        const input    = document.getElementById('inputCodigoOperadorAyudante');
        const errorMsg = document.getElementById('errorCodigoOperadorAyudante');

        const limpiarListeners = () => {
            input.removeEventListener('keydown', onKeydown);
            modalEl.removeEventListener('hidden.bs.modal', onHidden);
        };
        // se dispara con "Salir" (resetModalCompleto) o Esc/click afuera
        const onHidden = () => {
            limpiarListeners();
            if (!finalizado) {
                finalizado = true;
                reject(new Error('Escaneo cancelado por el usuario'));
            }
        };

        const onKeydown = async (e) => {
            if (e.key !== 'Enter') return;
            e.preventDefault();

            const codigo = input.value.trim();
            if (!codigo) return;

            try {
                // buscarOperadorPorCodigo(codigo)
                const operador = await $.post(URL_API.operador, {'accion': 'validarAyudante', 'token': codigo});
                if (!operador || !operador.ok) {
                    errorMsg.textContent = 'Código no encontrado. Intente nuevamente.';
                    errorMsg.classList.remove('d-none');
                    input.value = '';
                    input.focus();
                    return;
                }

                finalizado = true;
                limpiarListeners();
                resetModalCompleto('modal'); // cierra y limpia el modal <- jquery.functions.js
                resolve({ id: operador.id, nombre: operador.nombre });
                 
            } catch (err) {
                errorMsg.textContent = 'Error al validar el código.';
                errorMsg.classList.remove('d-none');
                input.value = '';
                input.focus();
            }
        };

        input.addEventListener('keydown', onKeydown);
        modalEl.addEventListener('hidden.bs.modal', onHidden);

        modal.show();
        modalEl.addEventListener('shown.bs.modal', () => input.focus(), { once: true });
             
    });
}
