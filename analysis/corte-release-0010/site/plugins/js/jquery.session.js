
$(document).ready(() => {
    initializeDataSession();
});

function initializeDataSession(){
    $("form[id=validarOperario]").on("submit",function(e){
        e.preventDefault();
        var token= $('input[id=operario]').val().trim(); //Token

        // ✅ Validación
        if(token === ""){
            $('#mensaje').html("⚠️ Ingrese un token válido.").css('color', 'orange');
            $('input[id=operario]').focus();
            return; // Detiene la ejecución
        }
        
        $.post("api/operadores.php", { 'accion': 'login', 'token': token}, function(respuesta){
            
            if(respuesta.ok){
                const modo = respuesta.corte;
                if (modo == 1) {
                    logger("INFO", "LOGIN", "["+token+"].El usuario se logeo correctamente, se envio email y se abrio pantalla de procesos.");
                    sendMail("Logeo e ingreso al sistema usuario: "+token);
                    location.href='proceso_armado.php';
                } else {
                    mostrarModalSector(token);
                }
            }else if(respuesta.motivo === 'credencial'){
                logger("ERROR", "LOGIN", "El usuario NO existe/Inhabilitado. [" + token + "]");
                
                $('#mensaje').html("⚠️ Error. Usuario NO existe/Inhabilitado.").css('color', 'red');
            }else if(respuesta.motivo === 'licencia'){
                logger("WARNING", "LOGIN","El usuario tiene acceso inhabilitado - Token: [" + token + "]");
                $('#mensaje').html("⚠️ Acceso Inhabilitado. Comunicarse con Soporte.").css('color', 'blue');
            }else{
                
                logger("ERROR", "LOGIN", "Error inesperado al validar el usuario. [" + token + "]");
                $('#mensaje').html("⚠️ Error inesperado. Intente nuevamente.").css('color', 'red');
            }                
        },"json").fail(function(){
            $('#mensaje').html("⚠️ Error inesperado. Intente nuevamente.").css('color', 'red');
        });
    });
}

// function mostrarModalSector(operario) {
//     // Crear overlay y modal dinámicamente si no existe
//     if ($('#modal-sector').length === 0) {
//         $('body').append(`
//             <div id="modal-overlay" style="
//                 position: fixed; inset: 0;
//                 background: rgba(0,0,0,0.55);
//                 z-index: 9998;
//                 display: flex; align-items: center; justify-content: center;
//             ">
//                 <div id="modal-sector" style="
//                     background: #fff;
//                     border-radius: 12px;
//                     padding: 36px 40px;
//                     box-shadow: 0 8px 32px rgba(0,0,0,0.25);
//                     text-align: center;
//                     min-width: 320px;
//                     z-index: 9999;
//                 ">
//                     <h4 style="margin-bottom: 8px; font-size: 1.2rem; color: #333;">
//                         Seleccionar Sector
//                     </h4>
//                     <p style="color: #666; margin-bottom: 28px; font-size: 0.95rem;">
//                         ¿A qué sector desea ingresar?
//                     </p>
//                     <div style="display: flex; gap: 16px; justify-content: center;">
//                         <button id="btn-armado" style="
//                             padding: 14px 32px;
//                             font-size: 1rem;
//                             font-weight: 600;
//                             border: none;
//                             border-radius: 8px;
//                             background: #1976d2;
//                             color: #fff;
//                             cursor: pointer;
//                             transition: background 0.2s;
//                         ">🔧 Armado</button>
//                         <button id="btn-corte" style="
//                             padding: 14px 32px;
//                             font-size: 1rem;
//                             font-weight: 600;
//                             border: none;
//                             border-radius: 8px;
//                             background: #e53935;
//                             color: #fff;
//                             cursor: pointer;
//                             transition: background 0.2s;
//                         ">✂️ Corte</button>
//                     </div>
//                 </div>
//             </div>
//         `);
//     }

//     // Eventos de los botones
//     $('#btn-armado').off('click').on('click', function () {
//         logger("INFO", "Login", "linea 109, ["+operario+"] Seleccionó sector: Armado.");
//         location.href = 'proceso_armado.php?operario=' + operario;
//     });

//     $('#btn-corte').off('click').on('click', function () {
//         logger("INFO", "Login", "linea 114, ["+operario+"] Seleccionó sector: Corte.");
//         location.href = 'proceso_corte.php?operarioId=' + operario;
        
//     });
// }



function mostrarModalSector(identificador) {
    // Crear overlay y modal dinámicamente si no existe
    if ($('#modal-sector').length === 0) {
        $('body').append(`
            <div id="modal-overlay" style="
                position: fixed; inset: 0;
                background: rgba(0,0,0,0.55);
                z-index: 9998;
                display: flex; align-items: center; justify-content: center;
            ">
                <div id="modal-sector" style="
                    background: #fff;
                    border-radius: 12px;
                    padding: 36px 40px;
                    box-shadow: 0 8px 32px rgba(0,0,0,0.25);
                    text-align: center;
                    min-width: 320px;
                    z-index: 9999;
                ">
                    <h4 style="margin-bottom: 8px; font-size: 1.2rem; color: #333;">
                        Seleccionar Sector
                    </h4>
                    <p style="color: #666; margin-bottom: 28px; font-size: 0.95rem;">
                        ¿A qué sector desea ingresar?
                    </p>
                    <div style="display: flex; gap: 16px; justify-content: center;">
                        <button id="btn-armado" style="
                            padding: 14px 32px;
                            font-size: 1rem;
                            font-weight: 600;
                            border: none;
                            border-radius: 8px;
                            background: #1976d2;
                            color: #fff;
                            cursor: pointer;
                            transition: background 0.2s;
                        ">🔧 Armado</button>
                        <button id="btn-corte" style="
                            padding: 14px 32px;
                            font-size: 1rem;
                            font-weight: 600;
                            border: none;
                            border-radius: 8px;
                            background: #e53935;
                            color: #fff;
                            cursor: pointer;
                            transition: background 0.2s;
                        ">✂️ Corte</button>
                    </div>
                </div>
            </div>
        `);
    } 
    // Eventos de los botones
    $('#btn-armado').off('click').on('click', function () {
        logger("INFO", "Login", "linea 109, ["+identificador+"] Seleccionó sector: Armado.");
        location.href = 'proceso_armado.php';
    });
 
    $('#btn-corte').off('click').on('click', function () {
        logger("INFO", "Login", "linea 114, ["+identificador+"] Seleccionó sector: Corte.");
        location.href = 'proceso_corte.php';
    });
}