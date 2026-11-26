from pathlib import Path
p=Path('src/plugins/js/jquery.procesopausaV1.0.3.js');s=p.read_text(encoding='utf-8-sig')
s=s.replace('async function iniciarpausaTareaActual()', 'function iniciarpausaTareaActual() { return transicionArmado(iniciarPausaArmado); }\nasync function iniciarPausaArmado()')
s=s.replace('    estado.pausaActiva = true;\n\n    estado.tiempoTareaMs = estado.inicioTareaTs ? ((estado.tiempoTareaMs || 0) + Date.now() - estado.inicioTareaTs) : (estado.tiempoTareaMs || 0);', '''    estado.tiempoTareaMs = tiempoTareaActualMs();
    estado.inicioTareaTs = 0;
    estado.pausaActiva = true;
    estado.pausaTipo = 0;
    estado.ultimoEventoId = 0;
    estado.seleccionPausaTs = Date.now();''')
s=s.replace("$modal.modal({", """$modal.off('hidden.bs.modal.armadoPausa').on('hidden.bs.modal.armadoPausa', () => {
        if (estado.pausaActiva && !estado.ultimoEventoId && !estado.transicionActiva) finpausaTareaActual();
    });
    $modal.modal({""",1)
a=s.index('\t\t// Intentamos encontrar coincidencia');b=s.index('\n\t\t} else {',a)
s=s[:a]+'''        const rows = $('#pausasTable').DataTable().rows().data().toArray();
        const seleccion = rows.find(p => String(p.codigo).trim().toUpperCase() === code || String(p.id) === code);
        if (seleccion) {
            seleccionarPausa(seleccion.codigo, seleccion.descripcion);
            return;'''+s[b:]
s=s.replace('async function seleccionarPausa(codigoPausa, nombrePausa)', '''function seleccionarPausa(codigoPausa,nombrePausa) { return transicionArmado(() => seleccionarPausaInterna(codigoPausa,nombrePausa)); }
async function seleccionarPausaInterna(codigoPausa, nombrePausa)''')
s=s.replace('if (!codigoPausa) {','if (!codigoPausa || !estado.pausaActiva || estado.ultimoEventoId) {',1)
s=s.replace('        iniciarTimer(estado.inicioPausaTs, $("#timerP"), 0);','',1)
s=s.replace("        //Coloco a la Tipologia estado: pausa",'''        iniciarTimer(estado.inicioPausaTs, $("#timerP"), 0);
        $('#modal').modal('hide');
        //Coloco a la Tipologia estado: pausa''')
s=s.replace('const band = await $.post(URL_API.detalles, {accion: "status", id: estado.detalleActualId, estado: 1});','const band = await $.post(URL_API.detalles, {accion: "status", id: estado.detalleActualId, estado: 1}).catch(() => 1);')
s=s.replace('        estado.pausaActiva = false;\n        estado.pausaTipo = 0;', '''        // El evento no fue confirmado: conservar selección y tiempo para reintentar o cancelar.
        estado.inicioPausaTs = 0;
        estado.pausaTipo = 0;''')
a=s.index('async function finpausaTareaActual()');s=s[:a]+'''function finpausaTareaActual() { return transicionArmado(finalizarPausaArmado); }
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
''';p.write_text(s,encoding='utf-8')
p=Path('src/plugins/js/jquery.procesosuspenderV1.0.3.js');s=p.read_text(encoding='utf-8-sig');s=s.replace('async function suspendeTipologiaActual()', 'function suspendeTipologiaActual() { return transicionArmado(suspenderTipologiaArmado); }\nasync function suspenderTipologiaArmado()')
s=s.replace('    try {','    try {\n      if (!estado.tareaEnCurso || !estado.procesoId) return;',1)
s=s.replace('      detenerTimer($("#timerT"));','',1).replace('      detenerTimer($("#timerP"));','',1)
s=s.replace('estado.inicioTareaTs ? ((estado.tiempoTareaMs || 0) + Date.now() - estado.inicioTareaTs) : (estado.tiempoTareaMs || 0)', 'tiempoTareaActualMs()')
s=s.replace('estado.pendienteId = respuesta && respuesta[0] ? Number(respuesta[0].valor) : 0;', "estado.pendienteId = respuesta?.[0]?.status === 'ok' && Number(respuesta[0].valor) > 0 ? Number(respuesta[0].valor) : 0;")
s=s.replace('const bandDetalle = await $.post(URL_API.detalles, {accion: "status", id: estado.detalleActualId, estado: 2 });','const bandDetalle = await $.post(URL_API.detalles, {accion: "status", id: estado.detalleActualId, estado: 2 }).catch(() => 1);')
s=s.replace("JSON.stringify(datosEvento)});", "JSON.stringify(datosEvento)}).catch(() => []);")
s=s.replace('          return;\n      }\n      mostrarMensaje(`La Tipologia', '      }\n      mostrarMensaje(`La Tipologia',1)
s=s.replace('      estado.detalleActualId    = 0;', '      limpiarTareaArmado();\n      estado.detalleActualId    = 0;',1)
p.write_text(s,encoding='utf-8')
# All edited production JS receives a new cache version.
p=Path('src/include/footer.php');s=p.read_text(encoding='utf-8-sig')
import re
for name in ['jquery.procesoV1.0.3.js','jquery.procesopausaV1.0.3.js','jquery.procesosuspenderV1.0.3.js','jquery.corte_pdf.js','jquery.corte_documentos.js']:
 s=re.sub(re.escape(name)+r'(?:\?v=[^"\s]+)?',name+'?v=20261005-procesos-1',s)
p.write_text(s,encoding='utf-8')
