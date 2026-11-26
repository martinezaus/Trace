from pathlib import Path
p=Path('src/plugins/js/jquery.procesoV1.0.3.js');s=p.read_text(encoding='utf-8-sig')
helper='''// Todas las acciones que cambian trabajo comparten un bloqueo hasta confirmar el servidor.
async function transicionArmado(accion) {
    if (estado.transicionActiva || estado.jornadaCerrada) {
        mostrarMensaje("Hay una operación en curso. Espere la confirmación y vuelva a escanear.", "#F27100", 5000, 'mensaje');
        return;
    }
    estado.transicionActiva = true;
    try { return await accion(); }
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

'''
s=s.replace('let operador = null;', 'let operador = null;\n\n'+helper)
for name in ['iniciarOrden','seleccionarTipologia','iniciarTarea']:
 s=s.replace('async function '+name+'(', 'async function '+name+'Interna(')
s=s.replace('async function finalizarTareaActual()', 'async function finalizarTareaInterna()').replace('async function finalizarTipologiaActual()', 'async function finalizarTipologiaInterna()')
s=s.replace('async function iniciarOrdenInterna(codigo) {','''async function iniciarOrdenInterna(codigo) {
    if (estado.ordenId && String(Number(codigo)) === String(Number(estado.ordenCodigo))) {
        mostrarMensaje("La orden ya está cargada. Continúe con la tarea actual.", "#1E88E5", 9000, 'mensaje');
        return;
    }
    if (estado.tareaEnCurso || estado.pausaActiva) {
        mostrarMensaje("Finalice o suspenda la tarea antes de cambiar de orden.", "#BD1A00", 12000, 'mensaje');
        return;
    }''')
s=s.replace('estado.ordenId=orden.id;', '''limpiarTareaArmado();
        estado.detalleActualId = 0;
        estado.tareaActualNro = 0;
        estado.tiempoTareaTotalMs = 0;
        estado.operadorIdAux = 0;
        $("#topologia,#label_tpo_ideal,#label_operario_aux").text("");
        estado.ordenId=orden.id;''',1)
# Single selection; never restart the same active task via an unawaited parallel request.
a=s.index('\t\t\t\tif (detalleId === estado.detalleActualId)');b=s.index('\n\t\t\t} catch (error)',a)
s=s[:a]+'''                await seleccionarTipologia(codigoSeleccionado);'''+s[b:]
s=s.replace('async function seleccionarTipologiaInterna(codigoDetalle) {','''async function seleccionarTipologiaInterna(codigoDetalle) {
    if (estado.tareaEnCurso || estado.pausaActiva) {
        mostrarMensaje("Hay una tarea activa. Finalícela o suspéndala antes de seleccionar otra tipología.", "#1E88E5", 10000, 'mensaje');
        return;
    }''')
s=s.replace('if (Number(detalle.estado) === 2) {','if ([2,3].includes(Number(detalle.estado))) {',1)
s=s.replace('Esta tipología está suspendida. Debe activarla Administración desde el dashboard.','Esta tipología está suspendida o terminada. Debe activarla Administración desde el dashboard.',1)
s=s.replace('estado.detalleActualId = detalle.id;','''limpiarTareaArmado();
        estado.tiempoTareaTotalMs = 0;
        estado.tiempoPausaTotalMs = 0;
        $("#label_operario_aux").text("");
        estado.detalleActualId = detalle.id;''',1)
s=s.replace('if(detalle.ayudante === 1)', 'if(Number(detalle.ayudante) === 1)')
s=s.replace('estado.tareaActualNro = 0;\n\t\testado.tareaEnCurso   = false;', '''const conteoActual = await $.post(URL_API.proceso, {accion: "contarCompletadas", detalle: detalle.id});
        if (!Number.isFinite(Number(conteoActual?.completadas))) throw new Error('No se pudo recuperar el avance');
        estado.tareaActualNro = Number(conteoActual.completadas);
        estado.tareaEnCurso = false;''')
# Duplicate scan is not resume: only pause handler resumes a clock.
a=s.index('\tif(band === true){',s.index('async function iniciarTareaInterna'));b=s.index('\n\t}else{',a)
s=s[:a]+'''    if (estado.tareaEnCurso) {
        const misma = Number(tarea.id) === Number(estado.tareaActualId);
        mostrarMensaje(misma ? "La tarea ya está en curso; se conserva el tiempo trabajado."
            : "Debe finalizar la tarea actual antes de escanear otra.", "#F27100", 9000, 'mensaje');
        return;'''+s[b:]
s=s.replace('\t\testado.tareaActualId = tarea.id;','',1)
s=s.replace('iniciarTimer(ahora, $("#timerT"), 0);','// El reloj se inicia cuando el servidor confirma el proceso.',1)
s=s.replace('tarea: estado.tareaActualId,','tarea: tarea.id,',1)
s=s.replace('if (!resultado || resultado.id === 0)', 'if (!resultado || !(Number(resultado.id) > 0))',1)
s=s.replace('detenerTimer($("#timerT")); // paramos el timer huérfano, ya que no hay proceso real','limpiarTareaArmado();',1)
s=s.replace('estado.procesoId      = resultado.id;', 'estado.tareaActualId = Number(tarea.id);\n            estado.procesoId      = Number(resultado.id);',1)
s=s.replace('estado.tiempoTareaMs  = 0;\n\t\t\testado.inicioTareaTs  = ahora;', '''estado.tiempoTareaMs = Number(resultado.tiempo_ms) || 0;
            estado.inicioTareaTs = Date.now();
            iniciarTimer(estado.inicioTareaTs, $("#timerT"), estado.tiempoTareaMs);''',1)
s=s.replace('detenerTimer($("#timerT"));\n\t\t\tmostrarMensaje("Error al iniciar tarea"','limpiarTareaArmado();\n\t\t\tmostrarMensaje("Error al iniciar tarea"',1)
s=s.replace('async function finalizarTareaInterna() {\n    try {','''async function finalizarTareaInterna() {
    if (!estado.tareaEnCurso || !estado.procesoId || estado.pausaActiva) return;
    try {''')
s=s.replace('const duracionMs = estado.tiempoTareaMs + (Date.now() - estado.inicioTareaTs);','let duracionMs = tiempoTareaActualMs();',1)
s=s.replace('// Recién ahora, confirmado el éxito, comprometemos el estado local:\n        detenerTimer', '''// En un reintento usamos la duración que ya confirmó el servidor.
        if (Number.isFinite(Number(respuesta[0].tiempo_ms))) duracionMs = Number(respuesta[0].tiempo_ms);
        detenerTimer''',1)
a=s.index('            estado.tareaEnCurso = false;',s.index('if (respuesta[0].descartada'));b=s.index('            mostrarMensaje("Lectura descartada',a)
s=s[:a]+'            limpiarTareaArmado();\n'+s[b:]
s=s.replace('estado.tareaActualNro += 1;','estado.tareaActualNro = Number(estado.tareaActualNro) + 1;\n        limpiarTareaArmado();',1)
s=s.replace('            finalizarTipologiaActual();','            await finalizarTipologiaInterna();',1)
s=s.replace('async function finalizarTipologiaInterna() {\n    try {','''async function finalizarTipologiaInterna() {
    if (!estado.detalleActualId || estado.tareaEnCurso || estado.pausaActiva) return;
    try {''')
s=s.replace('            finalizarOrdenCompleta();','            await finalizarOrdenCompleta();',1)
s=s.replace('clearInterval(estado.timerOrden);','detenerTimer($("#timerO"));')
# Replace callback-based closing (including unreachable legacy tail) with a single awaitable transition.
a=s.index('function registrarFinJornada()');b=s.index('\n}',a)+2
s=s[:a]+'''function registrarFinJornada() { return transicionArmado(cerrarJornadaArmado); }
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
}'''+s[b:]
p.write_text(s,encoding='utf-8')
