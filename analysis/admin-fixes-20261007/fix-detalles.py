from pathlib import Path
p=Path('src/include/detalle_admin_service.php');p.write_text(p.read_text(encoding='utf-8-sig'),encoding='utf-8')
p=Path('src/api/detalles.php');s=p.read_text(encoding='utf-8');a=s.index("\t\t\t$detalle = R::findOne",s.index("$_POST['accion']==='revertir'"));b=s.index("\t\t}elseif($_POST['accion']==='status')",a)
s=s[:a]+'''            require_once __DIR__.'/../include/detalle_admin_service.php';
            ini_set('display_errors','0');
            try{
                $service=new DetalleAdminService(R::getPDO());
                echo json_encode($service->revertir((int)($_POST['id']??0),operadorId()?:null,(string)($_POST['motivo']??'')),JSON_THROW_ON_ERROR);
            }catch(Throwable $e){
                http_response_code($e instanceof InvalidArgumentException?409:500);
                registrarLog('ERROR','DETALLES','Reversión: '.$e->getMessage());
                echo json_encode(['status'=>'error','valor'=>$e instanceof InvalidArgumentException?$e->getMessage():'No se pudo revertir. No se guardaron cambios.'],JSON_INVALID_UTF8_SUBSTITUTE);
            }
            exit;
''' + s[b:];p.write_text(s,encoding='utf-8')
p=Path('src/plugins/js/jquery.ordenes.js');s=p.read_text(encoding='utf-8');s=s.replace('\tinitializeDataOrdenes();',"    if (!document.getElementById('tabla_ordenes')) return;\n\tinitializeDataOrdenes();")
s=s.replace("$.fn.dataTable.tables({ visible: true, api: true }).columns.adjust().draw(false);", "$('#modal table').each(function(){if($.fn.DataTable.isDataTable(this))$(this).DataTable().columns.adjust();});")
s=s.replace('onclick=\'activarTipologia(${row.id})\'', 'onclick=\'activarTipologia(${row.id},${idOrden})\'')
s=s.replace('async function activarTipologia(idTipologia){','async function activarTipologia(idTipologia,idOrden){')
s=s.replace('id: estado.ordenId','id: idOrden').replace('inicializarTablaTipologias(detalles);','inicializarTablaTipologias(idOrden,detalles);',1)
s=s.replace('inicializarTablaTipologias(detalles);','inicializarTablaTipologias(respuesta.ordenId,detalles);')
s=s.replace('mostrarMensajeModal(', 'mensajeTipologia(')
s=s.replace("\t\ttry {\n\t\t\tconst respuesta = await $.post(\"./api/detalles.php\", {accion: \"revertir\"", "\t\tif(this.disabled)return;this.disabled=true;\n\t\ttry {\n\t\t\tconst respuesta = await $.post(\"./api/detalles.php\", {accion: \"revertir\"")
s=s.replace('async function cargarAyudante(idOrden, idTipologia, ayudante){', "async function cargarAyudante(idOrden, idTipologia, ayudante){\n    if(cambiosTipologia.has(idTipologia))return;cambiosTipologia.add(idTipologia);")
s=s.replace("\t\tlogger(\"ERROR\", \"ORDENES[AYUDANTE]\", \"Linea 543, [cargarAyudante] excepción: \" + e);\n\t\tmensajeTipologia(\"⚠️ Error inesperado al agregar Ayudante.\", \"red\", 10000);\n\t}", "\t\tlogger(\"ERROR\", \"ORDENES[AYUDANTE]\", String(e));\n\t\tmensajeTipologia('No se pudo completar o actualizar el cambio de ayudante. Recargue la lista antes de reintentar.', 'red',10000);\n\t}finally{cambiosTipologia.delete(idTipologia);}")
s=s.replace('async function activarTipologia(idTipologia,idOrden){', "const cambiosTipologia=new Set();\nfunction mensajeTipologia(text,color='red',ms=10000){mostrarMensaje(text,color,ms,'mensaje_modal');}\nasync function activarTipologia(idTipologia,idOrden){\n    if(cambiosTipologia.has(idTipologia))return;cambiosTipologia.add(idTipologia);")
s=s.replace("\t\tmensajeTipologia(\"⚠️ Error inesperado al activar la tipología.\", \"red\", 10000);\n\t}","\t\tmensajeTipologia('No se pudo completar o actualizar la activación. Recargue la lista antes de reintentar.','red',10000);\n\t}finally{cambiosTipologia.delete(idTipologia);}")
s=s.replace("onclick='logistica(\"+orden.id+\");'", "aria-disabled='true' tabindex='-1'")
s=s.replace("title='Transporte y colocacion'", "title='Transporte y colocación: no disponible'")
a=s.index('function viewProcesos(')
s=s[:a]+'''async function viewProcesos(idOrden) {
    resetModalCompleto();mostrarCarga('Generando PDF...');
    try{
        const detalles=await $.post('api/detalles.php',{accion:'view',id:idOrden},null,'json');
        if(!Array.isArray(detalles)||!detalles.length)throw new Error('No hay tipologías registradas para esta orden.');
        const grupos=await Promise.all(detalles.map(async d=>{
            const ps=await $.post('api/procesos.php',{accion:'view',idDetalle:d.id},null,'json');
            if(!Array.isArray(ps))throw new Error('Respuesta de procesos inválida.');
            return ps.map(p=>({...p,detalle_id:d.id}));
        }));
        const r=await fetch('include/pages/procesosView.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({ordenId:idOrden,detalles,procesos:grupos.flat()})});
        if(!r.ok)throw new Error('No se pudo generar el PDF.');
        const blob=await r.blob();if(await blob.slice(0,5).text()!=='%PDF-')throw new Error('El servidor no devolvió un PDF válido.');
        const url=URL.createObjectURL(blob);const w=window.open(url,'Procesos','width=800,height=600');
        if(!w){URL.revokeObjectURL(url);throw new Error('El navegador bloqueó el PDF. Habilite ventanas emergentes.');}
        w.addEventListener('beforeunload',()=>URL.revokeObjectURL(url));
    }catch(e){mostrarMensaje(e.message||'No se pudieron consultar los procesos.','red',15000);}
    finally{ocultarCarga();}
}
'''
s=s.replace("if (!pdf.size) {","if (!pdf.size || await pdf.slice(0,5).text() !== '%PDF-') {")
p.write_text(s,encoding='utf-8')
