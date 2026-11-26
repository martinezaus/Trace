/* Guardados de corte serializados. El cierre espera también observaciones con debounce. */
let _docActivo = {id:null,form:null,user:null};
let guardadosPendientes = 0;
let corteDocGeneration = 0;
const corteDocChanges = new Map();
let corteDocQueue = Promise.resolve();
function limpiarDocumentoActivo(){_docActivo={id:null,form:null,user:null};corteDocGeneration++;}
function corteDocEscape(s){return $('<span>').text(String(s??'')).html();}
async function cargarItems(id,form,user){
    _docActivo={id:Number(id),form,user};const generation=++corteDocGeneration;
    const r=await $.post('api/documentos.php',{accion:'lista',documento_id:id},null,'json');
    if(generation!==corteDocGeneration)return;
    const d=r?.[0];if(!d?.id)throw new Error('No se pudieron cargar los ítems.');
    const body=$('#tabla-body').empty();let previous=null;
    for(const item of d.items||[]){
        const group=String(item.perfil)+'|'+String(item.descripcion);
        if(group!==previous){$('<tr class="perfil-header">').append($('<td colspan="5">').text((item.perfil||'')+' — '+(item.descripcion||''))).appendTo(body);previous=group;}
        const row=$('<tr>').attr('data-item-id',item.id).attr('data-documento-id',id);
        $('<td class="text-center">').append($('<input type="checkbox" class="form-check-input chk-tilde">').prop('checked',Number(item.tilde)===1)).appendTo(row);
        $('<td>').text(item.descripcion||'').appendTo(row);$('<td>').text(item.barra||'').appendTo(row);$('<td>').text(item.detalle||'').appendTo(row);
        $('<td>').append($('<input class="form-control form-control-sm obs-input" placeholder="Observación">').val(item.observacion||'')).appendTo(row);body.append(row);
    }
    $('#panel-tabla').toggle((d.items||[]).length>0);$('#panel-trabajo').show();$('#panel-pdf').css('flex',(d.items||[]).length?'0 0 58%':'1');
    $('#progreso-items').text($('#tabla-body .chk-tilde:checked').length+'/'+$('#tabla-body .chk-tilde').length+' cortados');
    window.cortePdf?.update();
}
function guardarCambioCorte(row,field,value){
    const doc=Number(row.data('documento-id')),item=Number(row.data('item-id'));
    if(!window.cortePdf?.canEdit(doc))return;
    if(field==='tilde'&&Number(value)===1)for(const pending of corteDocChanges.values())enqueueCambioCorte(pending);
    const key=doc+':'+item+':'+field;const old=corteDocChanges.get(key);if(old?.timer)clearTimeout(old.timer);
    const change={key,doc,item,field,value,context:window.cortePdf.editContext(),user:_docActivo.user,timer:null};
    change.completesPdf=field==='tilde'&&Number(value)===1&&$('#tabla-body .chk-tilde:not(:checked)').length===0;
    if(change.completesPdf)window.cortePdf.lockCompletion(true);
    corteDocChanges.set(key,change);
    if(field==='observacion')change.timer=setTimeout(()=>enqueueCambioCorte(change),800);else enqueueCambioCorte(change);
}
function enqueueCambioCorte(change){
    if(change.queued)return;
    if(change.timer)clearTimeout(change.timer);change.timer=null;change.queued=true;guardadosPendientes++;
    const work=corteDocQueue.then(()=>window.cortePdf.withMutation(async()=>{
        const config=await $.post('api/system.php',{accion:'negocio'},null,'json');
        const datos={doc_id:change.doc,item_id:change.item,user:change.user,escala:config.escala,tamanio:config.tamanio,...change.context,[change.field]:change.value};
        const r=await $.post('api/documentos.php',{accion:'update',datos},null,'json');
        if(!r?.ok)throw new Error(r?.error||'No se pudo guardar el PDF.');
        if(r.tarea)window.cortePdf.accept(r.tarea);
        if(corteDocChanges.get(change.key)===change)corteDocChanges.delete(change.key);
        if(Number(_docActivo.id)===change.doc&&r.archivo)$('#visor-pdf').attr('src','tmp/modificados/'+encodeURIComponent(r.archivo)+'?t='+Date.now());
        $('#mensaje').text(r.tarea?.estado==='finalizada'?'PDF completo. Cronometro detenido; solo consulta.':'Tilde y PDF guardados.').css('color','green');
    }));
    corteDocQueue=work.catch(e=>{change.error=e.responseJSON?.error||e.message||'Error al guardar';$('#mensaje').text('No se guardó el cambio: '+change.error+'. Reintente antes de salir.').css('color','red');}).finally(()=>{guardadosPendientes--;change.queued=false;if(change.completesPdf)window.cortePdf.lockCompletion(false);});
    return corteDocQueue;
}
window.esperarGuardadosDocumentos=async()=>{
    for(const change of corteDocChanges.values())enqueueCambioCorte(change);
    await corteDocQueue;
    if(corteDocChanges.size)throw new Error('Hay cambios del PDF sin guardar. No se cambió ni cerró la tarea. Intente nuevamente.');
};
window.limpiarDocumentoActivo=limpiarDocumentoActivo;
$(document).on('change','#tabla-body .chk-tilde',function(){guardarCambioCorte($(this).closest('tr'),'tilde',$(this).is(':checked')?1:0);});
$(document).on('input','#tabla-body .obs-input',function(){guardarCambioCorte($(this).closest('tr'),'observacion',$(this).val());});
