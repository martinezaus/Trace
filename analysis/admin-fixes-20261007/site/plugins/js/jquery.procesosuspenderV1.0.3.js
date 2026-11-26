function suspendeTipologiaActual() { return transicionArmado(suspenderTipologiaArmado); }
async function suspenderTipologiaArmado() {
    if (!estado.tareaEnCurso || !estado.procesoId || estado.pausaActiva) {
        mostrarMensaje('Debe tener una tarea activa sin pausa para suspender.','#BD1A00',12000,'mensaje');
        return;
    }
    const datos={ordenId:estado.ordenId,detalleId:estado.detalleActualId,procesoId:estado.procesoId,
        sesionId:estado.sesionId,operadorId:estado.operadorId,tareaActualId:estado.tareaActualId,
        tiempoOrdenMs:estado.inicioOrdenTs ? Date.now()-estado.inicioOrdenTs : (estado.tiempoOrdenMs||0),
        tiempoTareaMs:tiempoTareaActualMs(),tiempoTareaTotalMs:estado.tiempoTareaTotalMs||0,
        tiempoOperadorMs:estado.tiempoOperario||0,menu:getMenuActual()};
    try {
        const r=await $.post(URL_API.proceso,{accion:'suspender',datos:JSON.stringify(datos)},null,'json');
        if (r?.status !== 'ok' || !Number(r.pendienteId)) throw new Error('Suspensión no confirmada');
        limpiarTareaArmado();
        Object.assign(estado,{detalleActualId:0,tareaActualNro:0,tiempoTareaTotalMs:0,operadorIdAux:0});
        $('#topologia,#label_tpo_ideal,#label_operario_aux').text('');
        mostrarMensaje('Tipología suspendida. El tiempo y la tarea quedaron guardados.','#32A123',15000,'mensaje');
    } catch(e) {
        mostrarMensaje('No se pudo suspender. La tarea sigue activa; vuelva a intentarlo.','#BD1A00',15000,'mensaje');
        logger('ERROR','SUSPENDER',e.message || e);
    }
}
