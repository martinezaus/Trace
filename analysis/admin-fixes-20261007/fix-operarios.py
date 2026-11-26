from pathlib import Path
p=Path('src/include/operadores_admin.php');p.write_text(p.read_text(encoding='utf-8-sig'),encoding='utf-8')
p=Path('src/api/operadores.php');s=p.read_text(encoding='utf-8');a=s.index("\t\t}elseif($_POST['accion']==='add')");b=s.index("\t\t}elseif($_POST['accion']==='print')",a)
s=s[:a]+'''        }elseif(in_array($_POST['accion'],['add','edit','del','reset'],true)){
            require_once __DIR__.'/../include/operadores_admin.php';
            ini_set('display_errors','0');
            try { echo json_encode(guardarOperadorAdmin($_POST['accion'],$_POST,$_FILES),JSON_THROW_ON_ERROR); }
            catch(Throwable $e){
                http_response_code($e instanceof InvalidArgumentException?422:500);
                error_log('Operarios: '.$e->getMessage());
                echo json_encode(['resultado'=>'ERROR','mensaje'=>$e instanceof InvalidArgumentException?$e->getMessage():'No se pudo guardar el operario. Revise el registro con soporte.'],JSON_INVALID_UTF8_SUBSTITUTE);
            }
''' +s[b:]
s=s.replace('YEAR(updated)','YEAR(`update`)').replace('MONTH(updated)','MONTH(`update`)')
a=s.index("            $operadores = R::findAll('operadores', 'estado = 0');",s.index("$_POST['accion']==='productividad'"));b=s.index("        }elseif($_POST['accion']==='validarAyudante')",a)
s=s[:a]+"            try {\n"+s[a:b]+"            echo json_encode(['resultado'=>'OK']);\n            }catch(Throwable $e){http_response_code(500);error_log('Productividad: '.$e->getMessage());echo json_encode(['resultado'=>'ERROR','mensaje'=>'No se pudo actualizar la productividad.']);}\n"+s[b:]
p.write_text(s,encoding='utf-8')
p=Path('src/plugins/js/jquery.operarios.js');s=p.read_text(encoding='utf-8').replace('    initializeDataOperarios();',"    if (!document.getElementById('tabla_operarios')) return;\n    initializeDataOperarios();")
s=s.replace('$(".modal-title")','$("#modal .modal-title")').replace('$(".modal-body")','$("#modal .modal-body")').replace("$('#mensaje_modal').innerText = \"\";","$('#mensaje_modal').text('');")
s=s.replace('if (respuesta) {',"if (Array.isArray(respuesta) && Number(respuesta[0]?.id)>0) {")
s=s.replace("        },'json');", "        },'json').fail(xhr=>mostrarMensaje(xhr.responseJSON?.mensaje || 'No se pudo cargar el operario.','red',10000));")
a=s.index('function processOperador(');b=s.index('function printTokenOperador(',a)
s=s[:a]+'''const operacionesOperario=new Set();
async function processOperador(accion, valores, id){
    const key='guardar';if(operacionesOperario.has(key))return;
    operacionesOperario.add(key);$('#modal button').prop('disabled',true);
    const data=new FormData();
    Object.entries({accion,nombre:valores[0],sesion:valores[1],corte:valores[2],id:id||0}).forEach(([k,v])=>data.append(k,v));
    if(valores[3])data.append('foto',valores[3]);
    try{
        const r=await $.ajax({url:'api/operadores.php',type:'POST',data,processData:false,contentType:false,dataType:'json'});
        if(r?.resultado!=='OK')throw new Error(r?.mensaje||r?.error||'No se pudo guardar el operario.');
        $('#modal').modal('hide');cargarOperadores();mostrarMensaje('Operación realizada correctamente.','green',10000);
    }catch(e){$('#modal #mensaje_modal').text(e.responseJSON?.mensaje||e.message||'No se pudo confirmar el guardado. Actualice el listado antes de reintentar.').css('color','red');}
    finally{operacionesOperario.delete(key);$('#modal button').prop('disabled',false);}
}
async function cambiarOperador(accion,id){
    const key=String(id);if(operacionesOperario.has(key))return;operacionesOperario.add(key);
    try{
        const r=await $.post('api/operadores.php',{accion,id},null,'json');
        if(r?.resultado!=='OK')throw new Error(r?.mensaje||r?.error||'No se pudo completar la operación.');
        cargarOperadores();mostrarMensaje('Operación realizada correctamente.','green',10000);
    }catch(e){mostrarMensaje(e.responseJSON?.mensaje||e.message||'No se pudo confirmar la operación. Actualice el listado.','red',10000);}
    finally{operacionesOperario.delete(key);}
}
function bajaOperador(id){return cambiarOperador('del',id);}
function resetTokenOperador(id){return cambiarOperador('reset',id);}

'''+s[b:];p.write_text(s,encoding='utf-8')
# Rango acorde a validación de servidor.
for f in ['operadorAdd.php','operadorEdit.php']:
 p=Path('src/include/pages')/f;s=p.read_text(encoding='utf-8');s=s.replace('type="number" id="corte"','type="number" min="0" max="1" step="1" id="corte"');s=s.replace('type="number" id="sesiones"','type="number" min="1" step="1" id="sesiones"').replace('type="number" id="sesion"','type="number" min="1" step="1" id="sesion"');p.write_text(s,encoding='utf-8')
