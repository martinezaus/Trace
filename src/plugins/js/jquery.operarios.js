
let tablaOperarios="";

$(document).ready(() => {
    if (!document.getElementById('tabla_operarios')) return;
    initializeDataOperarios();
    cargarOperadores();
    $("#operario_add").on('click', function(){
        crearOperador();
    });

    $('#asistencia').prop('disabled',true).attr('aria-disabled','true').attr('title','Asistencia: no disponible');

});


function initializeDataOperarios() {
    
    tablaOperarios = $('#tabla_operarios').DataTable({  pageLength: 5,
                                                        searching: false,
                                                        lengthMenu: [ [5, 10, 25, 50], [5, 10, 25, 50] ],
                                                        language: {
                                                            url: './plugins/js/es-ES.json'
                                                        },
                                                        responsive: true,
                                                        autoWidth: true,        // Ancho automático
                                                        initComplete: function(){adminExportButtons(this.api(),'#tabla_operarios',[0,1,2,3],'Operarios','#operariosExports');},
                                                        dom: 'lfrtip', // Posición de botones, filtro, tabla, info, paginación
                                                        
                                                        order: [[0, 'asc']]   // Orden inicial: columna 0 descendente
                                                    });

}

function cargarOperadores(){
    //console.log("cargaOperadores "+1);

    $.post("api/operadores.php", { 'accion': 'load'}, function(respuesta){
        tablaOperarios.clear(); // Limpia el DataTable
        if (respuesta && Array.isArray(respuesta)) {
            respuesta.forEach(function(operario) {
                var edit_o = "<a href='javascript:;' class='btn btn-outline-secondary btn-sm ms-1' aria-label='Editar operario' onclick='editOperador("+operario.id+");'><img src='img/icons/icons8-edit-16.png' title='Editar operario' alt=''></a>";
                var baja_o = "<a href='javascript:;' class='btn btn-outline-secondary btn-sm ms-1' aria-label='Activar o inactivar operario' onclick='bajaOperador("+operario.id+");'><img src='img/icons/icons8-baja-oper-16.png' title='Activar/Inactivar operario' alt=''></a>";
                var reset_token = "<a href='javascript:;' class='btn btn-outline-secondary btn-sm ms-1' aria-label='Restablecer token' onclick='resetTokenOperador("+operario.id+");'><img src='img/icons/icons8-resetear-16.png' title='Restablecer token' alt=''></a>";
                var print_token = "<a href='javascript:;' class='btn btn-outline-secondary btn-sm ms-1' aria-label='Imprimir identificación' onclick='printTokenOperador("+operario.id+");'><img src='img/icons/icons8-imprimir-16.png' title='Imprimir identificación' alt=''></a>";

                var estado = (operario.estado == '0') ? "<span class='badge badge-success badge-pill text-uppercase'>Activo</span>" : "<span class='badge badge-danger badge-pill text-uppercase'>Inactivo</span>";
                tablaOperarios.row.add([operario.id,operario.nombre, estado, operario.token, edit_o + baja_o + reset_token + print_token]);


            });
            tablaOperarios.draw(); // Dibuja la tabla

        }
    }).fail(function(xhr, status, error) {
        console.log(xhr.responseText);
        console.error("Hubo un problema: " + status + ", " + error);
    });
}

function crearOperador(){
    //console.log("crearOperador:"+2);
    
    //$('#modal').modal('dispose');
    $("#modal .modal-title").html('Alta Operador');
    $("#modal .modal-body").load("include/pages/operadorAdd.php",function(){

        $("#foto_operador").on("change", function () {
            const archivo = this.files[0];
            if (!archivo) return;
            const reader = new FileReader();
            reader.onload = function(e) {
                $("#preview-foto").attr("src", e.target.result);
            };
             reader.readAsDataURL(archivo);
        });


        $('#btn_operario_add').on('click', function(e) {
            e.preventDefault(); // Evita el comportamiento por defecto del formulario

            // Obtener los datos del formulario
            var apellido = $('#apellido').val().trim();
            var nombre = $('#nombre').val().trim();
            var sesiones = $('#sesiones').is(':checked') ? 2 : 1;
            var corte = $('#corte').is(':checked') ? 0 : 1;
            var revisador = $('#revisador').is(':checked') ? 1 : 0;
            var foto = $("#foto_operador")[0].files[0];

            // Verificamos si los campos están vacíos
            if (!apellido || !nombre) {
                // Mostrar mensaje de error si alguno de los campos está vacío
                $('#mensaje_modal').html("Por favor, complete ambos campos (Nombre y Apellido).").css('color', 'red');
            } else {
                $('#mensaje_modal').text('');
                var operador = (apellido+", "+nombre);
                var param=[operador, sesiones, corte, revisador, foto];
                //console.log(param);
                processOperador('add',param,0);  

            }
        });    
        $('#modal').modal('show');				   
    });
}

function editOperador(id){
    $("#modal .modal-title").html('Editar');
    $("#modal .modal-body").load("include/pages/operadorEdit.php",function(){
        //cargo los datos del operador en el form
        $.post("api/operadores.php", {'accion': 'select', 'id': id}, function(respuesta){

            if (Array.isArray(respuesta) && Number(respuesta[0]?.id)>0) {
                // Rellenar los campos del formulario con los datos del operario
                $('#nombre').val(respuesta[0].nombre);
                $('#sesiones').prop('checked', Number(respuesta[0].sesion) === 2);
                $('#corte').prop('checked', Number(respuesta[0].corte) === 0);
                $('#revisador').prop('checked', Number(respuesta[0].revisador) === 1);
                if (respuesta[0].foto) { 
                    $('#preview-foto').attr('src', 'data:image/jpeg;base64,' + respuesta[0].foto);
                }
              
                $('#btn_operario_edit').on('click', function(e) {
                    e.preventDefault(); // Evita el comportamiento por defecto del formulario
        
                     // Obtener los datos del formulario
                    var nombre = $('#nombre').val();
                    var sesiones = $('#sesiones').is(':checked') ? 2 : 1;
                    var corte = $('#corte').is(':checked') ? 0 : 1;
                    var revisador = $('#revisador').is(':checked') ? 1 : 0;
                    var foto   = $("#foto_operador")[0].files[0];

                    // Verificamos si los campos están vacíos
                    if (!nombre) {
                        // Mostrar mensaje de error si alguno de los campos está vacío
                        $('#mensaje_modal').html("Por favor, complete ambos campos (Nombre y Apellido).").css('color', 'red');
                    } else {
                        $('#mensaje_modal').text('');
                        var param=[nombre, sesiones, corte, revisador, foto];
                        //console.log(param);
                        processOperador('edit', param, id);  
                    }
                }); 
                
            }
            $('#modal').modal('show');
        },'json').fail(xhr=>mostrarMensaje(xhr.responseJSON?.mensaje || 'No se pudo cargar el operario.','red',10000));


        $("#foto_operador").on("change", function () {
            const archivo = this.files[0];
            if (!archivo) return;
            const reader = new FileReader();
            reader.onload = function(e) {
                $("#preview-foto").attr("src", e.target.result);
            };
             reader.readAsDataURL(archivo);
        });

    });
}

const operacionesOperario=new Set();
async function processOperador(accion, valores, id){
    const key='guardar';if(operacionesOperario.has(key))return;
    operacionesOperario.add(key);$('#modal button').prop('disabled',true);
    const data=new FormData();
    Object.entries({accion,nombre:valores[0],sesion:valores[1],corte:valores[2],revisador:valores[3],id:id||0}).forEach(([k,v])=>data.append(k,v));
    if(valores[4])data.append('foto',valores[4]);
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

function printTokenOperador(id){
    const panel = document.getElementById('panelOperarios');
    const url = new URL('include/pages/operadorCredencial.php', window.location.href);
    url.searchParams.set('id', id);
    url.searchParams.set('impresion', panel ? panel.dataset.impresion : '');
    // Abrir durante el clic; desligar opener después para conservar la referencia.
    const ventana = window.open('about:blank', '_blank', 'width=900,height=650');
    const mensaje = $('#mensaje').empty();
    if (ventana) {
        ventana.opener = null;
        ventana.location.replace(url.href);
    } else {
        mensaje.text('No se pudo abrir otra ventana. Puede abrir la identificación aquí: ')
            .append($('<a>').attr('href', url.href).text('Abrir identificación'))
            .css('color', 'red');
    }
}