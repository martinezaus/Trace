from pathlib import Path
p=Path('src/plugins/js/jquery.corte_pdf.js');s=p.read_text(encoding='utf-8');s=s.replace("{accion,...extra}","{accion,...extra,corte_context:CORTE_CONTEXT}")
s=s.replace("if(busy||!ready)return Promise.resolve();", "if(busy)return Promise.resolve();\n        if(!ready){message('Corte no está inicializado. Pulse Reintentar conexión o Salir del acceso.',true);return Promise.resolve();}")
s=s.replace("$('#btnCerrarCorte,.btnBuscarOrden,.corte-abrir').prop('disabled',busy||!ready);", "$('#btnCerrarCorte').prop('disabled',busy);\n        $('.btnBuscarOrden,.corte-abrir').prop('disabled',busy||!ready);\n        $('#btnReintentarCorte,#btnSalirCorte').prop('hidden',ready).prop('disabled',busy);")
s=s.replace("for(const d of r.documentos){", "if(!r.documentos.length)list.text('La orden no tiene documentos cargados.');\n        for(const d of r.documentos){")
s=s.replace("openDocument(d.id,true)","openDocument(d.id,true,Number(d.operador_id)!==Number(OPERADOR_ID)&&['activa','pausa'].includes(d.estado))")
s=s.replace("async function openDocument(id,work){", "async function openDocument(id,work,recover=false){\n        if(recover&&!window.confirm('Este PDF figura ocupado por otro operario. ¿Recuperarlo si lleva al menos dos minutos sin conexión? Se conservarán tiempo, pausa y avances.'))return;")
s=s.replace("api('iniciar',{documento:id})", "api('iniciar',{documento:id,recuperar:recover?1:0})")
s=s.replace("editContext:()=>({corte_token:task?.token||''})", "editContext:()=>({corte_token:task?.token||'',corte_context:CORTE_CONTEXT})")
start=s.index('    $(document).ready(async()=>{');end=s.index('    setInterval(()=>{',start)
s=s[:start]+'''    async function exitAccess(){
        if(!window.confirm('¿Salir del acceso? No se finalizan tareas ni se borra trabajo. Al ingresar nuevamente podrá recuperarlo.'))return;
        busy=true;update();
        try{await api('salir');task=null;ready=false;window.location.href='index.php';}
        catch(e){message(e.message,true);}finally{busy=false;update();}
    }
    async function initialize(){
        if(busy)return;
        busy=true;ready=false;update();$('#corte-pendientes').text('Cargando...');
        try{
            const r=await api('inicio');operatorToken=String(r.operador.token||'').toUpperCase();$('#operario').text(r.operador.nombre);ready=true;
            $.post('api/system.php',{accion:'operacion',id:3},null,'json').then(q=>{if(q.barcode2d)$('#barcode_pausa').attr('src','data:image/gif;base64,'+q.barcode2d);}).catch(()=>$('#barcode_pausa').hide());
            await refreshPending();
            const active=r.pendientes.find(p=>['activa','pausa'].includes(p.estado));
            if(active){await loadOrder(active.codigo);await openDocument(active.documento_id,true);}
            else message('Corte listo. Escanee una orden o su credencial para cerrar jornada.');
        }catch(e){
            if(!ready)$('#corte-pendientes').text('No se pudo iniciar corte. Reintente la conexión.');
            message(e.message,true);
        }finally{busy=false;update();}
    }
    function handleCode(value){
        const code=String(value||'').trim().toUpperCase();
        if(!code)return;
        if(!ready){message('Corte no está inicializado. Pulse Reintentar conexión o Salir del acceso.',true);return;}
        if(code===operatorToken)return run(closeDay);
        if(code==='C0003')return run(task?.estado==='pausa'?async()=>transition('reanudar'):showPauses);
        if(code==='C0002')return run(finish);
        if($('#modal').hasClass('show')){
            const pause=($('#modal').data('cortePausas')||[]).find(p=>String(p.codigo).toUpperCase()===code);
            if(pause)return run(async()=>{await transition('pausa',{pausa:pause.id});$('#modal').modal('hide');});
            message('Código de pausa no reconocido.',true);return;
        }
        if(/^(?:O)?\\d+$/.test(code)){
            const number=code.replace(/^O/,'');$('#orden').val(number);
            return run(async()=>{await leaveCurrent();await loadOrder(number);});
        }
        message('Código no reconocido para corte.',true);
    }
    $(document).ready(()=>{
        $('#btnFinalizarCorte').on('click',()=>run(finish));
        $('#btnCerrarCorte').on('click',()=>ready?run(closeDay):exitAccess());
        $('#btnReintentarCorte').on('click',initialize);$('#btnSalirCorte').on('click',exitAccess);
        $('.btnBuscarOrden').on('click',()=>handleCode($('#orden').val()));
        $('#formProcesoCorte').on('submit',e=>e.preventDefault());
        // Lector exclusivo de corte: no cambia el lector ni las llamadas de armado.
        let buffer='',last=0;
        document.addEventListener('keydown',e=>{
            if(e.ctrlKey||e.altKey||e.metaKey)return;
            const target=e.target,isOrder=target?.id==='orden';
            if(!isOrder && target?.matches?.('input,textarea,[contenteditable="true"]'))return;
            if(e.key==='F2'){e.preventDefault();buffer='';run(finish);return;}
            const now=Date.now();
            if(now-last>250){buffer='';if(isOrder&&e.key.length===1)target.select();}
            last=now;
            if(e.key==='Enter'){
                e.preventDefault();e.stopImmediatePropagation();
                const code=isOrder?target.value:buffer;buffer='';handleCode(code);return;
            }
            if(e.key.length===1)buffer+=e.key;
            if(buffer.length>128)buffer='';
        },true);
        $('#orden').on('focus',function(){this.select();});
        $(document).on('change','#tabla-body .chk-tilde',()=>$('#progreso-items').text($('#tabla-body .chk-tilde:checked').length+'/'+$('#tabla-body .chk-tilde').length+' cortados'));
        initialize();
    });
'''+s[end:]
p.write_text(s,encoding='utf-8')
# PDF updates require a corte context and an active task, including on the shared API.
p=Path('src/api/documentos.php');s=p.read_text(encoding='utf-8');s=s.replace("\t\t\t$itemId = (int)($datos['item_id'] ?? 0);", """            require_once __DIR__.'/../include/corte_auth.php';
            ini_set('display_errors','0');
            try { corteValidarAcceso(R::getPDO(),is_array($datos)?$datos:[]); }
            catch(Throwable $e){if(http_response_code()<400)http_response_code(403);echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_INVALID_UTF8_SUBSTITUTE);exit;}
            $itemId = (int)($datos['item_id'] ?? 0);""")
s=s.replace("R::getDatabaseAdapter()->getDatabase()->getPDO()", "R::getPDO()")
s=s.replace("// Bloquear tarea antes del documento, igual que la API de corte.", """try { corteValidarAcceso(R::getPDO(),$datos,true); }
            catch(Throwable $e){R::rollback();echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);exit;}
            // Bloquear operador, tarea y documento en el mismo orden que corte.""")
s=s.replace("if (!$cortePdf && isset($datos['corte_token']))", "if (!$cortePdf)")
s=s.replace("$info['filename'] . '_1.' . $info['extension']", "$info['filename'] . '_corte_' . $docId . '_' . bin2hex(random_bytes(12)) . '.pdf'")
s=s.replace("if (!rename($rutaTemporal, $rutaFinal))", "// Version inmutable: un rollback nunca reemplaza el PDF confirmado anterior.\n                if (!rename($rutaTemporal, $rutaFinal))")
p.write_text(s,encoding='utf-8')
