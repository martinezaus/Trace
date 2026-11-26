const URL_API = {
	orden:     "api/ordenes.php",
	detalle:   "api/detalles.php",
	proceso:   "api/procesos.php",
	operador:  "api/operadores.php",
	documento: "api/documentos.php",
	pendiente: "api/pendientes.php",
	pausa:     "api/pausas.php",
	evento:    "api/eventos.php",
	sesion:   "api/sesiones.php",
	detalles:  "api/detalles.php"
};

/**
 * Estado único del proceso de corte.
 *
 * Antes existían dos objetos: "corte" (orden/tarea) y "cronometro"
 * (pausa), pero "cronometro" nunca se usaba: todos los campos de
 * pausa se terminaban leyendo/escribiendo dinámicamente sobre "corte"
 * desde cortepausa.js. Se unifica todo acá, en un solo objeto, con
 * todos los campos declarados desde el inicio.
 */
let corte = {
	// --- Orden / documento / tarea ---
	ordenId: 0,
	ordenCodigo: "",
	operadorId: 0,
	operadorToken: "",
	sesionId: 0,
	detalleId: 0,
	procesoId: 0,
	tareaId: 0,
	documentoId: 0,
	inicioOrdenTs: 0,
	tiempoOrdenMs: 0,
	inicioTareaTs: 0,
	tiempoTareaMs: 0,
	ordenEstado: 1,

	// --- Pausa ---
	pausaActiva: false,
	inicioPausaTs: 0,
	pausaTipo: {id: 0, codigo: "", descripcion: "", flag: 0},
	ultimoEventoId: 0,
	tiempoPausaSumaMs: 0,
	tiempoPausaNoSumaMs: 0,
	pausaDuracionMs: 0,
	cierreEnCurso: false,
};

$(document).ready(async () => {
	const inicializado = await initializeDataProceso();
	if (!inicializado) return;
	initBarcodeScanner(onCodeScanned);

	/** Teclas de funcion */
	$(document).keydown(function(e) {
		const keyCode = e.which || e.keyCode;
		// por teclado. Teclas: F2 (113), F3 (114), F4 (115)
		if (keyCode !== 113 && keyCode !== 114) return;
		e.preventDefault();
		if (keyCode === 113) {
			finalizarTarea();
		} else {
			// console.log("-> P1.0");
			// iniciarPausaCorte();
		}
	});

	$("#btnCerrarCorte").on("click", function(e) {
		e.preventDefault();
		confirmarCierreCorte();
	});
	$("#btnPausa").on("click", iniciarPausaCorte);
	$(document).on("click", ".pdf_option", abrirDocumentoCorte);
	$(document).on("change", "#tabla-body .chk-tilde", actualizarProgresoCorte);
	$(document).on("click", ".btnBuscarOrden", function(e) {
		e.preventDefault();
		buscarOrdenCorteDesdeInput();
	});
	$("#orden").on("keydown", function(e) {
		if (e.key !== "Enter" && e.keyCode !== 13) return;
		e.preventDefault();
		e.stopPropagation();
		buscarOrdenCorteDesdeInput();
	});
	//actualizarProgresoCorte();
 	const tabla = document.getElementById("tabla-body");
	if (tabla) {
		new MutationObserver(actualizarProgresoCorte).observe(tabla, {childList: true, subtree: true});
	}
});

function normalizarCodigoOrdenCorte(valor) {
	const texto = String(valor || "").trim().toUpperCase();
	const coincidencia = texto.match(/\d{5}/);
	return coincidencia ? coincidencia[0] : "";
}

function buscarOrdenCorteDesdeInput() {
	const codigo = normalizarCodigoOrdenCorte($("#orden").val());
	if (!codigo) {
		mostrarMensaje("Ingrese o escanee un número de orden válido.", "#BD1A00", 10000);
		return;
	}
	$("#orden").val(codigo);
	iniciarOrden(codigo);
}

async function initializeDataProceso() {
	const operador = Number(OPERADOR_ID) || 0;
	let respuesta;
	try {
		respuesta = await $.post(URL_API.operador, {accion: "select", id: operador}, null, "json");
	} catch (error) {
		logger("ERROR", "PROCESO-CORTE", "No se pudo identificar al operador: " + (error.responseText || error.message || error));
		mostrarMensaje("No se pudo iniciar la sesión del operador.", "#BD1A00", 15000);
		return false;
	}
	if (respuesta && respuesta.ok === false && respuesta.motivo === "sesion") {
		location.href = "index.php";
		return false;
	}
	if (respuesta && respuesta[0] && respuesta[0].id) {
		corte.operadorId = Number(respuesta[0].id);
		corte.operadorToken = String(respuesta[0].token || "").trim().toUpperCase();
		$("#operario").text(respuesta[0].nombre);
		sendMail("Operador ingresó en el proceso de corte: " + operador + " - " + respuesta[0].nombre + " Fecha/Hora:" + new Date().toISOString());
		await $.post("api/system.php", {accion: "sesion"}, null, "json");
		try {
			const sesion = await $.post(URL_API.sesion, {
				accion: "add",
				datos: JSON.stringify({operadorId: corte.operadorId, estado: "activa"})
			}, null, "json");
			if (!sesion || !sesion[0] || sesion[0].status !== "ok" || !sesion[0].id) {
				throw new Error(sesion?.[0]?.valor || "Respuesta de sesión inválida");
			}
			corte.sesionId = Number(sesion[0].id);
			return true;
		} catch (error) {
			logger("ERROR", "PROCESO-CORTE", "No se pudo crear la sesión: " + (error.responseText || error.message || error));
			mostrarMensaje("No se pudo iniciar la sesión del operador.", "#BD1A00", 15000);
			return false;
		}
	}
}

function onCodeScanned(code){
	code = (code || '').trim().toUpperCase();

    // Ignora códigos vacíos o muy cortos
	if (!code || code.length < 3) {
		mostrarMensaje("⚠️ Código inválido (demasiado corto o vacío).", "#BD1A00", 9000);
		return;
	}

	// La credencial del operador tiene prioridad: al escanearla nuevamente
	// se cierra la jornada y se conserva el trabajo como pendiente.
	if (corte.operadorToken && code === corte.operadorToken) {
		confirmarCierreCorte();
		return;
	}
	if ($("#modal").hasClass("show") && $("#pausasTable").is(":visible")) {
		seleccionarPausaPorCodigo(code);
		return;
	}

	if (code === "C0003") {
		iniciarPausaCorte();
		return;
	}

	if (/^O\d{4}$/.test(code)) {
		$.post(URL_API.operador, {accion: "validarAyudante", token: code}, null, "json")
			.done(function(operador) {
				if (!operador || !operador.ok) {
					mostrarMensaje("El operador no existe, está inhabilitado o el código es inválido.", "#BD1A00", 12000);
					return;
				}
				mostrarMensaje("El código pertenece a " + operador.nombre + ", pero no corresponde al operador de esta sesión.", "#FF7700", 12000);
			})
			.fail(function(xhr) {
				logger("ERROR", "PROCESO-CORTE", "No se pudo validar el operador escaneado: " + (xhr.responseText || xhr.statusText));
				mostrarMensaje("No se pudo validar el código del operador.", "#BD1A00", 12000);
			});
		return;
	}
	const codigoOrden = normalizarCodigoOrdenCorte(code);
	if (codigoOrden) {
		$("#orden").val(codigoOrden);
		iniciarOrden(codigoOrden);
		return;
	}

	mostrarMensaje("Código no válido para el proceso de corte.", "#BD1A00", 10000);

}

function confirmarCierreCorte() {
	if (corte.cierreEnCurso) return;
	if (corte.pausaActiva) {
		mostrarMensaje("Debe finalizar la pausa antes de cerrar la jornada.", "#BD1A00", 12000);
		return;
	}
	if (!window.confirm("¿Desea cerrar la sesión de corte? El trabajo actual quedará guardado como pendiente.")) return;
	cerrarSesionCorte();
}

async function cerrarSesionCorte() {
	if (corte.cierreEnCurso) return;
	corte.cierreEnCurso = true;
	$("#btnCerrarCorte").addClass("disabled").attr("aria-disabled", "true");

	try {
		if (!corte.sesionId) throw new Error("La sesión todavía no está lista. Espere unos segundos e intente nuevamente.");

		if (corte.ordenId && !(await guardarPendienteCorte())) {
			throw new Error("No se pudo guardar el trabajo pendiente.");
		}

		const estadoSesion = corte.ordenId ? "cerrada_con_actividad" : "cerrada_sin_actividad";
		const respuesta = await $.post(URL_API.sesion, {
			accion: "update",
			id: corte.sesionId,
			datos: JSON.stringify({fechaf: new Date(), estado: estadoSesion})
		}, null, "json");

		if (!respuesta || !respuesta[0] || respuesta[0].status !== "ok") {
			throw new Error(respuesta?.[0]?.valor || "No se pudo cerrar la sesión.");
		}

		mostrarMensaje(corte.ordenId
			? "Jornada finalizada. El proceso quedó pendiente para continuarlo más tarde."
			: "Jornada finalizada con éxito.", "#32A123", 10000);
		setTimeout(function() { window.location.href = "index.php"; }, 1200);
	} catch (error) {
		corte.cierreEnCurso = false;
		$("#btnCerrarCorte").removeClass("disabled").removeAttr("aria-disabled");
		logger("ERROR", "PROCESO-CORTE", "No se pudo cerrar la jornada: " + (error.responseText || error.message || error));
		mostrarMensaje(error.message || "No se pudo cerrar la jornada.", "#BD1A00", 15000);
	}
}
/**
 * Guarda en "pendientes" el progreso actual, para poder retomarlo
 * más adelante (ej: el operario corta la jornada a mitad de una tarea
 * y la retoma al otro día). Se llama antes de cerrar la pantalla.
 *
 * Guarda hasta DOS filas:
 *   - Una fila "placeholder" de orden (detalle_id=0): permite reanudar
 *     el cronómetro de la ORDEN aunque no haya ninguna tarea abierta
 *     en ese momento (ej: cerró entre la tarea de perfil y la de vidrio).
 *   - Si hay una tarea en curso, además una fila puntual de esa tarea
 *     (detalle_id real), con el tiempo acumulado hasta ese instante.
 *
 * Ambas filas guardan el mismo tiempoOrdenMs, para que el valor sea
 * correcto sin importar cuál de las dos lea primero listarPorOrden().
 */
async function guardarPendienteCorte() {
	if (!corte.ordenId) return true; // nada que guardar

	const ahora = Date.now();
	const tiempoOrdenMs = corte.inicioOrdenTs ? (ahora - corte.inicioOrdenTs) : (corte.tiempoOrdenMs || 0);

	const datosOrden = {
		ordenId: corte.ordenId,
		detalleId: 0,
		sesionId: corte.sesionId,
		procesoId: 0,
		tareaActualId: 0,
		tiempoOrdenMs: tiempoOrdenMs,
		tiempoTareaMs: 0,
		tiempoTareaTotalMs: 0,
		tiempoOperadorMs: 0,
		menu: 3
	};

	const pendientesAGuardar = [
		$.post(URL_API.pendiente, {accion: "add", datos: JSON.stringify(datosOrden)}, null, "json")
	];

	if (corte.procesoId) {
		const tiempoTareaMs = corte.tiempoTareaMs
			+ (corte.inicioTareaTs ? ahora - corte.inicioTareaTs : 0)
			+ corte.tiempoPausaSumaMs;

		const datosTarea = {
			ordenId: corte.ordenId,
			detalleId: corte.detalleId,
			sesionId: corte.sesionId,
			procesoId: corte.procesoId,
			tareaActualId: corte.tareaId,
			tiempoOrdenMs: tiempoOrdenMs,
			tiempoTareaMs: tiempoTareaMs,
			tiempoTareaTotalMs: tiempoTareaMs,
			tiempoOperadorMs: 0,
			menu: 3
		};

		pendientesAGuardar.push(
			$.post(URL_API.pendiente, {accion: "add", datos: JSON.stringify(datosTarea)}, null, "json")
		);
	}

	try {
		const respuestas = await Promise.all(pendientesAGuardar);
		const error = respuestas.find(resp => !resp || !resp[0] || resp[0].status !== "ok");
		if (error) throw new Error(error?.[0]?.valor || "Respuesta inválida al guardar el pendiente");
		return true;
	} catch (e) {
		logger("ERROR", "PROCESO-CORTE", "No se pudo guardar el pendiente al cerrar: " + (e.message || e));
		return false;
	}
}

/** Funcion para mostrar, buscar y ver archivo PDF de la Orden */
async function iniciarOrden(codigo) {
	try {
		const respuesta = await $.post(URL_API.orden, {accion: "select", barcode: Number(codigo)}, null, "json");
		if (!respuesta || !respuesta[0] || respuesta[0].id === 0) {
			mostrarMensaje("Orden no encontrada", "#BD1A00", 15000);
			return;
		}

		const orden = respuesta[0];
		if ([2, 3].includes(Number(orden.estado))) {
			const estadoNombre = Number(corte.ordenEstado) === 2 ? "Terminada" : "Entrega/Instalacion";
			mostrarMensaje("La Orden: " + orden.codigo + " ya esta " + estadoNombre, "#FF7700", 9000);
			return;
		}

        if (corte.procesoId) {
			if (Number(corte.ordenId) === Number(orden.id)) {
				mostrarMensaje("La orden ya está activa.", "#1E88E5", 9000);
			} else {
				mostrarMensaje("Finalizá la tarea actual con F2 antes de cambiar de orden", "#BD1A00", 12000);
			}
			return;
		}

		if (corte.ordenId && Number(corte.ordenId) !== Number(orden.id)) {
			// detenerTimer($("#timerT"));
 			$("#timerT").text("00:00:00.00");
			$("#visor-pdf").attr("src", "").hide();
			$("#titulo-doc").text("");
			$("#tabla-body").empty();
			$("#panel-tabla").hide();
			$("#panel-trabajo").hide();
			$("#lista-archivos").empty();
			$("#progreso-items").text("0/0 cortados");


			corte.documentoId = 0;
			corte.tareaId = 0;
			corte.inicioTareaTs = 0;
			corte.tiempoTareaMs = 0;
			corte.tiempoPausaSumaMs = 0;
			corte.tiempoPausaNoSumaMs = 0;

			actualizarTareaActual();
		}


 		corte.ordenId = Number(orden.id);
		corte.ordenCodigo = orden.codigo;
		$("#orden-actual").text(corte.ordenCodigo);

		
		const pendientesOrden = await $.post(URL_API.pendiente, {accion: "listarPorOrden", ordenId: corte.ordenId}, null, "json");
		const ahora = Date.now();

		if (pendientesOrden && pendientesOrden.length > 0) {
			// Puede haber más de una fila (la placeholder de orden y/o una
			// de tarea puntual); ambas guardan el mismo tiempoOrdenMs al
			// cerrar, así que tomamos el máximo por las dudas.
			corte.tiempoOrdenMs = Math.max(...pendientesOrden.map(p => Number(p.tiempoOrdenMs) || 0));
			corte.inicioOrdenTs = ahora - corte.tiempoOrdenMs;

			// iniciarTimer(ahora, $("#timerO"), corte.tiempoOrdenMs);

			// Solo consumimos la fila "placeholder" de orden (detalle_id = 0).
			// Las filas de tareas puntuales (detalle_id real) se consumen
			// recién cuando se reabre ESE documento específico, no acá.
			const placeholderOrden = pendientesOrden.find(p => Number(p.detalle_id) === 0);
			if (placeholderOrden) {
				$.post(URL_API.pendiente, {accion: "status", id: placeholderOrden.id}, null, "json");
			}

			mostrarMensaje("Orden corte reanudada. Abra un documento para continuar.", "#1E88E5", 15000);
		} else {
			// const tiempoResp = await $.post(URL_API.orden, {
			// 	accion: "time",
			// 	id: corte.ordenId,
			// 	estado: corte.ordenEstado
			// }, null, "json");
			// if (!tiempoResp || !tiempoResp.timestamp_inicio) {
			// 	mostrarMensaje("Error al obtener tiempo de la orden", "#BD1A00", 15000);
			// 	return;
			// } 
			
			corte.inicioOrdenTs = new Date().getTime(); //new Date(tiempoResp.timestamp_inicio).getTime();
			corte.tiempoOrdenMs = 0;
		    // iniciarTimer(corte.inicioOrdenTs, $("#timerO"), 0);
			mostrarMensaje("Orden iniciada -> abra un documento", "#32A123", 15000);
		}         

 		// $.post(URL_API.orden, {accion: "update", ido: corte.ordenId, estado: corte.ordenEstado}).fail((jqXHR, textStatus) => 
		// 	logger("ERROR", "PROCESO-CORTE", "Linea 203, Fallo update orden id=" + corte.ordenId + ": " + textStatus.toISOString)
		// );
 
		$.ajax({
			url: URL_API.documento,
			type: "POST",
			data: {accion: "archive", id: corte.ordenId},
			dataType: "json",
			success: function(response) {
				let html = "";
				$("#visor-pdf").hide();
				$.each(response, function(i, doc) {
					if (doc.titulo) {
						html += `<a href="#" class="pdf_option list-group-item list-group-item-action d-flex justify-content-between align-items-center"
									data-file="${doc.titulo}" data-value="${doc.id}">
									<span><i class="fas fa-file-pdf text-danger"></i>&nbsp;${doc.descripcion}</span>
									<span class="badge bg-primary">Abrir</span>
								</a>`;
					}
				});
				$("#lista-archivos").html(html || '<p class="text-muted">Sin archivos PDF.</p>').show();
			},
			error: function(jqXHR, textStatus) {
				logger("ERROR", "PROCESO-CORTE", "Fallo al listar documentos de la orden id=" + corte.ordenId + ": " + textStatus);
				$("#lista-archivos").html('<p class="text-danger">Error al cargar documentos.</p>').show();
			}
		});
	} catch (e) {
		mostrarMensaje("Error al iniciar orden " + (e.message || e), "#BD1A00", 15000);
	}

}

async function abrirDocumentoCorte(e) {

	e.preventDefault();
	if (!corte.ordenId) {
		mostrarMensaje("Primero debe seleccionar una orden", "#BD1A00", 9000);
		return;
	}
 
	const id = Number($(this).data("value"));
	try {
		const respuesta = await $.post(URL_API.documento, {accion: "lista", documento_id: id}, null, "json");
		const documento = respuesta && respuesta[0];
		if (!documento) {
			mostrarMensaje("No se pudo cargar el documento", "#BD1A00", 9000);
			return;
		}
		if (corte.procesoId && corte.documentoId !== id) {
			mostrarMensaje("Finalizá la tarea actual con F2 antes de abrir otro documento", "#BD1A00", 12000);
			return;
		}
		const visor = documento.titulo_mod ? "tmp/modificados/" + documento.titulo_mod : "tmp/" + documento.titulo;
		$("#visor-pdf").attr("src", visor + "?t=" + Date.now()).show();
		$("#titulo-doc").text(documento.titulo || "");
		cargarItems(documento.id, "proceso_corte", corte.operadorId);

		const tipo = Number(documento.tipo);

		if (tipo === 1) {
			mostrarMensaje("Este documento es la OP y no inicia una tarea de corte.", "#1E88E5", 12000);
			return;
		}

		if (tipo !== 2 && tipo !== 3) {
			mostrarMensaje("Tipo de documento no válido para corte", "#BD1A00", 9000);
			return;
		}
		if (!corte.procesoId) {
			await iniciarTareaDocumento(documento, tipo === 2 ? 20 : 21);
		}
	} catch (e) {
		mostrarMensaje("Error al abrir documento " + (e.message || e), "#BD1A00", 10000);
	}
}

async function iniciarTareaDocumento(documento, tareaId) {
	
	/**QR - Pausa */
	$.post("api/system.php", { accion: 'operacion', id: 3 }, function(data) {
		$("#barcode_pausa").attr("src", 'data:image/gif;base64,' + data.barcode2d);
	}, "json");

	const detalle = await $.post(URL_API.detalle, {accion: "add_corte", ordenId: corte.ordenId, tareaId: tareaId}, null, "json");
	if (!detalle || !detalle.id) {
		mostrarMensaje("No se pudo preparar el detalle de corte", "#BD1A00", 15000);
		return;
	}
	corte.detalleId = Number(detalle.id);

	// ¿Quedó esta tarea pendiente de una sesión anterior (el operario
	// cortó la jornada a mitad de camino)? La consultamos ANTES de tocar
	// el proceso, para poder restaurar el tiempo acumulado si corresponde.
	let pendienteTarea = null;
	try {
		const pendientes = await $.post(URL_API.pendiente, {accion: "select", ordenId: corte.ordenId, detalleId: corte.detalleId}, null, "json");
		pendienteTarea = (pendientes && pendientes[0]) ? pendientes[0] : null;
	} catch (e) {
		logger("ERROR", "PROCESO-CORTE", "No se pudo consultar pendiente de tarea, detalleId=" + corte.detalleId + ": " + (e.message || e));
	}

	const respuesta = await $.post(URL_API.proceso, { accion: "add",
													  tarea: tareaId,
													  detalle: corte.detalleId,
													  estado: 1,
													  operador: corte.operadorId,
													  operadorAux: 0
													}, null, "json");
	if (!respuesta || !respuesta.id) {
		mostrarMensaje("No se pudo iniciar la tarea de corte", "#BD1A00", 10000);
		return;
	}

	corte.procesoId = Number(respuesta.id);
	corte.tareaId = tareaId;
	corte.documentoId = Number(documento.id);

	if (respuesta.retomada && pendienteTarea) {
		// Proceso retomado (procesos.php ya lo reactivó) y con un snapshot
		// de tiempo guardado: restauramos el tiempo acumulado en vez de
		// arrancar el cronómetro desde 00:00:00.
		corte.tiempoTareaMs = Number(pendienteTarea.tiempoTareaMs) || 0;
		corte.inicioTareaTs = Date.now();
		mostrarMensaje("Tarea retomada, continuando desde " + msToHhmmssms(corte.tiempoTareaMs), "#1E88E5", 15000);
		$.post(URL_API.pendiente, {accion: "status", id: pendienteTarea.id}, null, "json");
	} else {
		if (respuesta.retomada && !pendienteTarea) {
			// El proceso seguía abierto en la base pero no hay snapshot de
			// tiempo asociado (ej: se abandonó sin pasar por el cierre que
			// guarda el pendiente). Arrancamos en 0 y lo dejamos registrado
			// para poder auditar el caso.
			logger("WARNING", "PROCESO-CORTE", "Proceso id=" + corte.procesoId + " retomado sin pendiente de tiempo asociado, detalleId=" + corte.detalleId);
		}
		corte.inicioTareaTs = Date.now();
		corte.tiempoTareaMs = 0;
	}

	iniciarTimer(corte.inicioTareaTs, $("#timerT"), corte.tiempoTareaMs);
	actualizarTareaActual();
}

async function finalizarTarea() {
	if (!corte.procesoId) {
		mostrarMensaje("Debe abrir un documento de corte antes de finalizar la tarea.", "#BD1A00", 10000);
		return;
	}
	if (corte.pausaActiva) {
		mostrarMensaje("Cierre la pausa antes de finalizar la tarea.", "#BD1A00", 10000);
		return;
	}
 
	const pendientes = $("#tabla-body .chk-tilde:not(:checked)").length;
	if (pendientes > 0 && !window.confirm("Quedan " + pendientes + " ítems sin tildar. ¿Finalizar la tarea igual?")) {
		return;
	}
 
	const tiempoEnCurso = corte.inicioTareaTs ? Date.now() - corte.inicioTareaTs : 0;
	const tiempoFinalMs = corte.tiempoTareaMs + tiempoEnCurso + corte.tiempoPausaSumaMs;
	// detenerTimer($("#timerT"));
 
	try {
		const respuesta = await $.post(URL_API.proceso, {
			accion: "update",
			id: corte.procesoId,
			estado: 2,
			tiempo: tiempoFinalMs
		}, null, "json");
		const resultado = respuesta && respuesta[0];
		if (!resultado || resultado.status !== "ok") {
			logger("ERROR", "PROCESO-CORTE", "No se pudo finalizar el proceso id=" + corte.procesoId);
			mostrarMensaje("Error al actualizar el proceso", "#D32F2F", 10000);
			const ahora = Date.now();
			corte.inicioTareaTs = ahora;
			iniciarTimer(ahora, $("#timerT"), corte.tiempoTareaMs);
			return;
		}
 
		const nombre = nombreTarea(corte.tareaId);
		corte.procesoId = 0;
		corte.tareaId = 0;
		corte.documentoId = 0;
		corte.inicioTareaTs = 0;
		corte.tiempoTareaMs = 0;
		corte.tiempoPausaSumaMs = 0;
		corte.tiempoPausaNoSumaMs = 0;

		actualizarTareaActual();

		$("#timerT").text("00:00:00.00");
		$("#visor-pdf").attr("src", "").hide();
		$("#tabla-body").empty();
		$("#panel-tabla").hide();
		mostrarMensaje("Tarea " + nombre + " finalizada en " + msToHhmmssms(tiempoFinalMs) + " — abrí el otro documento de la orden o escaneá otra orden", "#32A123", 20000);
	} catch (e) {
		logger("ERROR", "PROCESO-CORTE", "Error al finalizar tarea: " + (e.message || e));
		mostrarMensaje("Error al actualizar el proceso", "#D32F2F", 10000);
		const ahora = Date.now();
		corte.inicioTareaTs = ahora;
		iniciarTimer(ahora, $("#timerT"), corte.tiempoTareaMs);
	}
}

function actualizarTareaActual() {
	$("#tarea-actual").text(corte.tareaId ? nombreTarea(corte.tareaId) : "Sin tarea");
	//$("body").toggleClass("corte-pausa-activa", corte.pausaActiva);
}

function nombreTarea(tareaId) {
	return Number(tareaId) === 20 ? "Aluminio" : "Vidrio";
}

function actualizarProgresoCorte() {
	const $checks = $("#tabla-body .chk-tilde");
	$("#progreso-items").text($checks.filter(":checked").length + "/" + $checks.length + " cortados");
}

/* =========================================================
 * PAUSAS DEL PROCESO DE CORTE
 * (antes en jquery.cortepausa.js — unificado acá porque usa
 * el mismo objeto de estado "corte" y no tiene sentido
 * mantenerlo en un archivo aparte)
 * ========================================================= */

function iniciarPausaCorte() {

	if (!corte.procesoId) {
		mostrarMensaje("Debe abrir un documento de corte antes de iniciar una pausa.", "#BD1A00", 10000);
		return;
	}

 
	if (corte.pausaActiva) return;
 
	const $modal = $("#modal");
	const $body = $modal.find(".modal-body");
	$modal.find(".modal-title").text("Pausas");

	$body.html(`
		<div class="text-center py-5 my-3">
			<div class="spinner-border text-primary" style="width:3rem;height:3rem" role="status">
				<span class="visually-hidden">Cargando...</span>
			</div>
			<p class="mt-3 fw-bold">Cargando tipos de pausas...</p>
		</div>
	`);
	$modal.modal({backdrop: "static", keyboard: false}).modal("show");
	$modal.find("#btnModalsalir").hide();
 
	$.get("include/pages/pausasTable.php", function(html) {
		$body.html(html);
		$.post(URL_API.pausa, {accion: "listar", idc: 3}, function(respuesta) {
			if (!Array.isArray(respuesta) || respuesta.length === 0) {
				$body.html('<div class="alert alert-info text-center py-5">No hay códigos de pausas registrados.</div>');
				$modal.find("#btnModalsalir").show();
				return;
			}
			inicializarTablaPausasCorte(respuesta);
		}, "json").fail(function() {
			$body.html('<div class="alert alert-danger text-center py-4">Error al cargar los códigos.</div>');
			$modal.find("#btnModalsalir").show();
		});
	}).fail(function() {
		$body.html('<div class="alert alert-danger text-center py-4">No se pudo cargar la estructura de la tabla.</div>');
		$modal.find("#btnModalsalir").show();
	});
}
 
function inicializarTablaPausasCorte(items) {
	const $table = $("#pausasTable");
	if ($.fn.DataTable.isDataTable($table)) {
		$table.DataTable().clear().destroy();
	}
 
	$table.DataTable({
		destroy: true,
		responsive: true,
		scrollY: "55vh",
		scrollCollapse: true,
		paging: items.length > 4,
		pageLength: items.length <= 4 ? -1 : 4,
		lengthChange: false,
		searching: true,
		ordering: true,
		order: [[0, "asc"]],
		initComplete: function() {
			$(this.api().table().header()).hide();
		},
		language: {
			url: "plugins/js/es-ES.json",
			zeroRecords: "No se encontraron registros",
			info: "Mostrando _START_ a _END_ de _TOTAL_ entradas",
			infoEmpty: "Mostrando 0 de 0 entradas",
			infoFiltered: "(Filtrando de _MAX_ total entradas)",
			search: "Buscar",
			paginate: {first: "Primero", last: "Ultimo", next: "Siguiente", previous: "Anterior"}
		},
		data: items,
		columns: [
			{data: "id", title: "#", render: data => "<strong>" + data + "</strong>"},
			{data: "codigo", title: "Código"},
			{data: "descripcion", title: "Nombre"},
			{data: "flag", title: "Suma", render: data => Number(data) === 1 ? "Sí" : "No"},
			{data: "barcode2d", title: "QR", className: "text-center", orderable: false, render: function(data, type, row) {
				if (!data) return "—";
				return '<img src="data:image/png;base64,' + data + '" class="barcode_item_det shadow rounded" style="height:64px; cursor:pointer;" title="Seleccionar ' + row.codigo + '">';
			}}
		]
	});
 
	$table.off("click.cortePausa", "tbody tr").on("click.cortePausa", "tbody tr", function() {
		const row = $table.DataTable().row(this).data();
		if (row) seleccionarPausaCorte(row);
	});
}
 
function seleccionarPausaPorCodigo(codigo) {
	const $table = $("#pausasTable");
	if (!$.fn.DataTable.isDataTable($table)) return;
 
	const buscado = String(codigo || "").trim().toUpperCase();
	let seleccionada = null;
	$table.DataTable().rows().every(function() {
		const row = this.data();
		if (row && String(row.codigo || "").trim().toUpperCase() === buscado) {
			seleccionada = row;
		}
	});
	if (seleccionada) {
		seleccionarPausaCorte(seleccionada);
	} else {
		mostrarMensaje("Código no encontrado en la lista actual", "#BD1A00", 9000);
	}
}
 
async function seleccionarPausaCorte(pausa) {
	if (!pausa || corte.pausaActiva) return;
 
	corte.pausaActiva = true;
	corte.pausaTipo = {
		id: Number(pausa.id),
		codigo: pausa.codigo || "",
		descripcion: pausa.descripcion || "",
		flag: Number(pausa.flag) === 1 ? 1 : 0
	};
	corte.tiempoTareaMs += corte.inicioTareaTs ? Date.now() - corte.inicioTareaTs : 0;
	corte.inicioTareaTs = 0;
	detenerTimer($("#timerT"));
	corte.inicioPausaTs = Date.now();
	corte.pausaDuracionMs = 0;
	actualizarTareaActual();
 
	try {
		const datosEvento = {
			descripcion: corte.pausaTipo.descripcion,
			procesoId: corte.procesoId,
			tipo: "pausa",
			codigo_id: 3,
			codigo_sub: corte.pausaTipo.id,
			tiempo: 0,
			operadorId: corte.operadorId
		};
		const respuestaEvento = await $.post(URL_API.evento, {
			accion: "add",
			datos: JSON.stringify(datosEvento)
		}, null, "json");
		corte.ultimoEventoId = respuestaEvento && respuestaEvento[0] ? Number(respuestaEvento[0].id) : 0;
		if (!corte.ultimoEventoId) throw new Error("No se pudo registrar el evento de pausa");
 
		const estado = await $.post(URL_API.detalles, {
			accion: "status",
			id: corte.detalleId,
			estado: 1
		}, null, "json");
		if (Number(estado) !== 0) {
			logger("ERROR", "PROCESO-CORTE", "No se pudo marcar el detalle como pausado, id=" + corte.detalleId);
		}
 
        // <p class="lead">${corte.pausaTipo.flag === 1 ? "Esta pausa suma al tiempo de la orden." : "Esta pausa no suma al tiempo de la orden."}</p>


		const $modal = $("#modal");
		$modal.find(".modal-title").text("Pausa activa");
		$modal.find(".modal-body").html(`
			<div class="text-center py-4">
				<div id="timerP" class="display-3 fw-bold">00:00:00.00</div>
				<h4 class="mt-3">${corte.pausaTipo.descripcion}</h4>
				<button type="button" class="btn btn-warning btn-lg fw-bold" id="btnCerrarPausa">CERRAR PAUSA</button>
				<div id="mensajePausa" class="text-danger mt-3"></div>
			</div>
		`);
		$modal.find("#btnModalsalir").hide();
		$modal.off("click.cerrarPausa", "#btnCerrarPausa").on("click.cerrarPausa", "#btnCerrarPausa", cerrarPausaCorte);
		iniciarTimer(corte.inicioPausaTs, $("#timerP"), 0);
	} catch (e) {
		corte.pausaActiva = false;
		corte.inicioTareaTs = Date.now();
		corte.inicioPausaTs = 0;
		corte.ultimoEventoId = 0;
		corte.pausaDuracionMs = 0;
		actualizarTareaActual();
		iniciarTimer(corte.inicioTareaTs, $("#timerT"), corte.tiempoTareaMs);
		$("#modal").modal("hide");
		$("#modal").find("#btnModalsalir").show();
		logger("ERROR", "PROCESO-CORTE", "No se pudo activar la pausa: " + (e.message || e));
		mostrarMensaje("Error al activar la pausa.", "#BD1A00", 10000);
	}
}
 
async function cerrarPausaCorte() {
	if (!corte.pausaActiva || !corte.ultimoEventoId) return;
	const duracion = corte.pausaDuracionMs || (Date.now() - corte.inicioPausaTs);
	corte.pausaDuracionMs = duracion;
	$("#btnCerrarPausa").prop("disabled", true);
 
	try {
		const respuesta = await $.post(URL_API.evento, {
			accion: "update",
			evento: corte.ultimoEventoId,
			tiempo: duracion,
			operador: corte.operadorId
		}, null, "json");
		if (Number(respuesta) !== 0) throw new Error("No se pudo actualizar el evento");
 
		if (corte.pausaTipo.flag === 1) {
			corte.tiempoPausaSumaMs += duracion;
		} else {
			corte.tiempoPausaNoSumaMs += duracion;
		}
		const estado = await $.post(URL_API.detalles, {
			accion: "status",
			id: corte.detalleId,
			estado: 0
		}, null, "json");
		if (Number(estado) !== 0) {
			logger("ERROR", "PROCESO-CORTE", "No se pudo reactivar el detalle, id=" + corte.detalleId);
		}
 
		detenerTimer($("#timerP"));
		corte.pausaActiva = false;
		corte.inicioPausaTs = 0;
		corte.ultimoEventoId = 0;
		corte.pausaDuracionMs = 0;
		corte.inicioTareaTs = Date.now();
		iniciarTimer(corte.inicioTareaTs, $("#timerT"), corte.tiempoTareaMs);
		actualizarTareaActual();
		$("#modal").modal("hide");
		$("#modal").find("#btnModalsalir").show();
		mostrarMensaje("Pausa finalizada. Continuando tarea.", "#32A123", 10000);
	} catch (e) {
		$("#btnCerrarPausa").prop("disabled", false);
		$("#mensajePausa").text("Error al cerrar la pausa. Intente nuevamente.");
		logger("ERROR", "PROCESO-CORTE", "No se pudo cerrar la pausa: " + (e.message || e));
	}
}