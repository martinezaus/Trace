/* Corte por PDF: una sola transición en vuelo; estado y tiempo confirmados por servidor. */
(() => {
    'use strict';
    let task = null, order = null, busy = false, confirmedAt = 0, operatorToken = '', ready = false;
    let chain = Promise.resolve();
    let completing = false;
    // Heartbeat e ítems deben confirmar en el mismo orden: una respuesta vieja
    // nunca puede reactivar un PDF que acaba de finalizar con el último tilde.
    let mutationQueue = Promise.resolve();
    function withMutation(fn) {
        const next = mutationQueue.then(fn);
        mutationQueue = next.catch(() => {});
        return next;
    }
    const api = (accion, extra={}) => withMutation(async () => {
        try {
            const r=await $.post('api/corte_pdf.php',{accion,...extra},null,'json');
            if(!r?.ok)throw new Error(r?.error||'Respuesta inválida del servidor.');
            return r;
        }catch(e){throw new Error(e.responseJSON?.error||e.message||'No se pudo conectar. El trabajo sigue pendiente de confirmación.');}
    });
    const message = (s,error=false) => $('#mensaje').text(s).css('color',error?'#BD1A00':'#28742a');
    function accept(t){task=t;confirmedAt=Date.now();$('#timerT').text(msToHhmmssms(Number(task?.elapsed_ms||0)));update();if(task?.estado==='finalizada')refreshPending().catch(e=>message(e.message,true));}
    function update(){
        const active=task?.estado==='activa',paused=task?.estado==='pausa';
        if(task?.estado==='finalizada')$('[data-work-doc="'+Number(task.documento_id)+'"]').remove();
        $('#tarea-actual').text(task ? `${task.documento_id} · ${task.estado}` : 'Sin tarea');
        $('#btnFinalizarCorte').prop('disabled',!active||busy);
        $('#estado-pausa').toggle(!!paused);
        $('#btnCerrarCorte,.btnBuscarOrden,.corte-abrir').prop('disabled',busy||!ready);
        $('#tabla-body .chk-tilde,#tabla-body .obs-input').prop('disabled',!active||busy||completing);
    }
    function run(fn){
        if(busy||!ready)return Promise.resolve();
        busy=true;update();
        // Los guardados periódicos se ordenan con las acciones del operario.
        const p=chain.then(fn).catch(e=>message(e.message,true)).finally(()=>{busy=false;update();});
        chain=p;return p;
    }
    async function flush(){if(window.esperarGuardadosDocumentos)await window.esperarGuardadosDocumentos();}
    async function transition(action,extra={}){
        const r=await api(action,{documento:action==='jornada'&&task?.estado==='finalizada'?0:(task?.documento_id||0),token:task?.token||'',...extra});
        if(r.tarea)accept(r.tarea);return r;
    }
    function clearDocument(){
        if(window.limpiarDocumentoActivo)window.limpiarDocumentoActivo();
        $('#visor-pdf').attr('src','').hide();$('#tabla-body').empty();$('#panel-trabajo').hide();
        $('#titulo-doc').text('');$('#progreso-items').text('0/0 cortados');
    }
    async function pending(){
        if(task?.estado==='pausa')throw new Error('Cierre la pausa antes de cambiar de tarea.');
        await flush();
        if(task?.estado==='activa')await transition('pendiente');
        task=null;clearDocument();update();await refreshPending();
    }
    async function leaveCurrent(){
        if(!task||task.estado==='finalizada')return;
        if(!window.confirm('La tarea actual quedará pendiente, con su tiempo y avance guardados. ¿Continuar?'))throw new Error('Se mantiene la tarea actual.');
        await pending();
    }
    async function refreshPending(){
        const r=await api('pendientes');const box=$('#corte-pendientes').empty();
        if(!r.pendientes.length){box.text('No tiene tareas pendientes.');return;}
        for(const p of r.pendientes){
            $('<button type="button" class="btn btn-outline-primary btn-sm m-1 corte-abrir">').text(`${p.codigo} · ${p.nombre} · ${p.estado}`).on('click',()=>run(async()=>{await leaveCurrent();await loadOrder(p.codigo);await openDocument(p.documento_id,true);})).appendTo(box);
        }
    }
    async function loadOrder(code){
        const r=await api('documentos',{codigo:code});order=r.orden;
        $('#orden').val(order.codigo);$('#orden-actual').text(order.codigo);
        const list=$('#lista-archivos').empty().show();
        for(const d of r.documentos){
            const row=$('<div class="list-group-item d-flex flex-wrap align-items-center gap-2">');
            $('<span class="flex-grow-1">').text(`${d.nombre} · ${Number(d.completo)===1?'Completo: solo consulta':(d.estado||'Sin tarea nueva')}`).appendTo(row);
            $('<button type="button" class="btn btn-outline-secondary btn-sm corte-abrir">Ver PDF</button>').on('click',()=>run(()=>openDocument(d.id,false))).appendTo(row);
            if([2,3].includes(Number(d.tipo))&&d.estado!=='finalizada'&&Number(d.completo)!==1&&![2,3].includes(Number(order.c_estado))){
                $('<button type="button" class="btn btn-primary btn-sm corte-abrir">').attr('data-work-doc',d.id).text(d.estado?'Retomar tarea':'Iniciar tarea').on('click',()=>run(()=>openDocument(d.id,true))).appendTo(row);
            }
            list.append(row);
        }
    }
    async function openDocument(id,work){
        if(task&&Number(task.documento_id)!==Number(id))await leaveCurrent();
        if(!work&&task?.estado==='activa'&&Number(task.documento_id)!==Number(id))await leaveCurrent();
        if(work){
            await flush();
            const r=await api('iniciar',{documento:id});accept(r.tarea||null);if(r.soloLectura)work=false;
        }else if(!task||Number(task.documento_id)!==Number(id)){task=null;}
        const rows=await $.post('api/documentos.php',{accion:'lista',documento_id:id},null,'json');
        const d=rows?.[0];if(!d?.id)throw new Error('No se pudo cargar el PDF.');
        clearDocument();
        const modified=d.mod_disponible===true;
        $('#visor-pdf').attr('src',(modified?'tmp/modificados/':'tmp/')+encodeURIComponent(modified?d.titulo_mod:d.titulo)+'?t='+Date.now()).show();
        $('#titulo-doc').text(d.titulo);$('#panel-trabajo').show();
        await cargarItems(id,'proceso_corte',Number(OPERADOR_ID));
        if(task?.estado==='pausa')message('Esta tarea tiene una pausa activa. Escanee el QR de pausa para continuar.',true);
        else message(work?'Tarea del PDF recuperada. El tiempo y los tildes pertenecen a este documento.':'Vista del PDF. No se inició una tarea.');
        update();await refreshPending();
    }
    async function finish(){
        if(task?.estado!=='activa')throw new Error('No hay una tarea activa para finalizar.');
        await flush();
        const remaining=$('#tabla-body .chk-tilde:not(:checked)').length;
        if(!window.confirm(remaining?`Quedan ${remaining} ítems sin tildar. ¿Finalizar este PDF de todos modos? Para continuarlo luego, cancele y cierre la jornada para continuar luego.`:'¿Finalizar la tarea de este PDF?'))return;
        await transition('finalizar',{parcial:remaining?1:0});task=null;clearDocument();update();await refreshPending();await loadOrder(order.codigo);message('Tarea del PDF finalizada. Puede abrir otro documento.');
    }
    async function closeDay(){
        if(task?.estado==='pausa')throw new Error('Cierre la pausa antes de cerrar la jornada.');
        if(!window.confirm('¿Cerrar la jornada? La tarea actual quedará pendiente para retomarla después.'))return;
        await flush();await transition('jornada');task=null;ready=false;window.location.href='index.php';
    }
    async function showPauses(){
        if(task?.estado!=='activa')throw new Error('Inicie una tarea para pausarla.');
        await flush();
        const rows=await $.post('api/pausas.php',{accion:'listar',idc:3},null,'json');
        const modal=$('#modal').removeClass('fade');modal.find('.modal-title').text('Seleccionar pausa');const body=modal.find('.modal-body').empty();
        for(const p of rows){$('<button type="button" class="btn btn-outline-primary d-block w-100 mb-2">').text(p.descripcion+(Number(p.flag)===1?' · Suma tiempo':' · No suma tiempo')).on('click',()=>run(async()=>{await transition('pausa',{pausa:p.id});modal.modal('hide');message('Pausa activa. Escanee el QR de pausa para continuar.');})).appendTo(body);}
        modal.find('#btnModalsalir').show();modal.modal({backdrop:'static',keyboard:true}).modal('show');
        modal.data('cortePausas',rows);
    }
    window.cortePdf={
        accept,
        withMutation,
        lockCompletion:value=>{completing=value;update();},
        canEdit:doc=>!busy&&!completing&&task?.estado==='activa'&&Number(task.documento_id)===Number(doc),
        editContext:()=>({corte_token:task?.token||''}),
        update
    };
    $(document).ready(async()=>{
        $('#btnFinalizarCorte').on('click',()=>run(finish));$('#btnCerrarCorte').on('click',()=>run(closeDay));
        const search=()=>run(async()=>{const code=String($('#orden').val()||'').trim().replace(/^O(?=\d)/i,'');if(!/^\d+$/.test(code))throw new Error('Ingrese un número de orden válido.');await leaveCurrent();await loadOrder(code);});
        $('.btnBuscarOrden').on('click',search);$('#formProcesoCorte').on('submit',e=>e.preventDefault());
        $('#orden').on('keydown',e=>{if(e.key==='Enter'){e.preventDefault();e.stopImmediatePropagation();search();}});
        $(document).on('keydown.cortePdf',e=>{if(e.key==='F2'){e.preventDefault();run(finish);}});
        $(document).on('change','#tabla-body .chk-tilde',()=>$('#progreso-items').text($('#tabla-body .chk-tilde:checked').length+'/'+$('#tabla-body .chk-tilde').length+' cortados'));
        try{
            busy=true;update();
            const r=await api('inicio');operatorToken=String(r.operador.token||'').toUpperCase();$('#operario').text(r.operador.nombre);ready=true;
            $.post('api/system.php',{accion:'operacion',id:3},null,'json').then(q=>{if(q.barcode2d)$('#barcode_pausa').attr('src','data:image/gif;base64,'+q.barcode2d);}).catch(()=>$('#barcode_pausa').hide());
            initBarcodeScanner(code=>{code=String(code||'').trim().toUpperCase();if(code===operatorToken)return run(closeDay);if(code==='C0003')return run(task?.estado==='pausa'?async()=>transition('reanudar'):showPauses);if(code==='C0002')return run(finish);
                if($('#modal').hasClass('show')){const p=($('#modal').data('cortePausas')||[]).find(p=>String(p.codigo).toUpperCase()===code);if(p)return run(async()=>{await transition('pausa',{pausa:p.id});$('#modal').modal('hide');});return;}
                if(/^(?:O)?\d{5,}$/.test(code)){$('#orden').val(code.replace(/^O/,''));search();}
            });
            await refreshPending();
            const active=r.pendientes.find(p=>['activa','pausa'].includes(p.estado));
            if(active){await loadOrder(active.codigo);await openDocument(active.documento_id,true);}
        }catch(e){message(e.message,true);}finally{busy=false;update();}
    });
    setInterval(()=>{
        if(!task){$('#timerT').text('00:00:00.000');return;}
        const ms=Number(task.elapsed_ms)+(task.estado==='activa'?Math.min(30000,Math.max(0,Date.now()-confirmedAt)):0);
        $('#timerT').text(msToHhmmssms(ms));
        $('#estado-pausa').text(task.estado==='pausa'?'Pausa activa · '+msToHhmmssms(Math.max(0,Date.now()-Number(task.pause_since))):'Cerrar pausa');
    },200);
    setInterval(()=>{
        if(!ready||busy||!task||!['activa','pausa'].includes(task.estado))return;
        const current=task;
        chain=chain.then(async()=>{if(task!==current)return;await transition('guardar');}).catch(e=>message('No se confirmó el guardado del tiempo: '+e.message,true));
    },10000);
    window.addEventListener('beforeunload',e=>{if(task&&['activa','pausa'].includes(task.estado)){e.preventDefault();e.returnValue='';}});
})();
