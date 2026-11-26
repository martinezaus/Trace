
// Funciones seguras para codificar y decodificar en Base64 UTF-8
/*
function btoaUTF8(str) {
    return btoa(unescape(encodeURIComponent(str)));
}

function atobUTF8(str) {
    return decodeURIComponent(escape(atob(str)));
}
    */

/** menu de Tareas QR - FUNCIÓN PARA RENDERIZAR */
function renderMenu(lista) {
    const grid = $('#qrGrid');
    grid.empty();

    lista.forEach(item => {
        const especial = Number(item.id) === 99;
        grid.append(`
            <div class="qr-item ${especial ? 'qr-item-special' : ''}" data-id="${item.id}">
                <img src="data:image/gif;base64,${item.img}" class="qr-img">
                <div class="qr-title">${item.label}</div>
            </div>
        `);
    });

    autoResizeQR();
}

/**  menu de Tareas QR - AUTO-RESIZE INTELIGENTE */
function autoResizeQR() {
    const size = Math.min(window.innerWidth / 5, 160);
    $(".qr-img").css({
        width: `${size}px`,
        height: `${size}px`
    });
}

// 2. Función que inicializa el chat (solo se llama si está habilitado) en IA
function initChat() {
    $('#send-btn').click(function() {
        let pregunta = $('#chat-input').val().trim();
        if (!pregunta) return;

        $('#chat-messages').append('<div class="message user">' + pregunta + '</div>');
        $('#chat-input').val('').focus();
        $('#chat-messages').scrollTop($('#chat-messages')[0].scrollHeight);

        $.ajax({
            url: 'modules/ia/ia.php',
            type: 'POST',
            data: JSON.stringify({ pregunta: pregunta }),
            contentType: 'application/json',
            success: function(data) {
                let respuesta = data.respuesta || data.error || 'Error desconocido';
                $('#chat-messages').append('<div class="message bot">' + respuesta.replace(/\n/g, '<br>') + '</div>');
                $('#chat-messages').scrollTop($('#chat-messages')[0].scrollHeight);
            },
            error: function() {
                $('#chat-messages').append('<div class="message bot">Error de conexión. Intenta de nuevo.</div>');
            }
        });
    });

    // Enviar con Enter (sin Shift)
    $('#chat-input').keypress(function(e) {
        if (e.which === 13 && !e.shiftKey) {
            e.preventDefault();
            $('#send-btn').click();
        }
    });
}

/** Funcion utilizada jquery.procesoV1.0.3 */
function msToHhmmssms(ms) {
    let seg = Math.floor(ms / 1000);
    let hh = String(Math.floor(seg / 3600)).padStart(2,"0");
    let mm = String(Math.floor((seg % 3600) / 60)).padStart(2,"0");
    let ss = String(seg % 60).padStart(2,"0");
    let cs = String(Math.floor((ms % 1000) / 10)).padStart(2,"0");
    return `${hh}:${mm}:${ss}:${cs}`;
}

/** Funciones utilizada en todo el proyecto */
function mostrarMensaje(texto, color, duracion, obj='mensaje') {
    $('#' + obj)
        .html(texto)
        .css('color', color)
        .fadeIn(200);

    setTimeout(() => {
        $('#' + obj).fadeOut(300, function () {
            $(this).html('');
        });
    }, duracion);
}

/** Funcion que registra Logs:  */
function logger(nivel, modulo, mensaje) {
    $.ajax({
        url: "include/logger.php",
        type: "POST",
        data: {
            nivel: nivel,
            modulo: modulo,
            mensaje: mensaje
        }
    });

}

function getMenuActual() {
	const v = parseInt(localStorage.getItem("tipoProceso"), 10);
	return Number.isNaN(v) ? 1 : v; // por defecto: Menú 1
}

function iniciarTimer(inicioReal, objeto, tiempoAcumuladoMs = 0) {
    // 1. Buscamos si este objeto específico ya tenía un timer corriendo
    const intervaloPrevio = objeto.data('intervalo');
    
    // 2. Si existía, lo limpiamos
    if (intervaloPrevio) clearInterval(intervaloPrevio);

    // 3. Calculamos
    const inicioEfectivo = inicioReal - tiempoAcumuladoMs;

    // 4. Creamos el nuevo intervalo
    const nuevoIntervalo = setInterval(() => {
        const transcurrido = Date.now() - inicioEfectivo;
        objeto.text(msToHhmmssms(transcurrido));
    }, 100);

    // 5. Guardamos la referencia del intervalo DENTRO del objeto (DOM element)
    objeto.data('intervalo', nuevoIntervalo);
}

function detenerTimer(objeto) {
    // 1. Recuperamos el ID del intervalo guardado en el DOM
    const intervalo = objeto.data('intervalo');
    
    // 2. Si existe, lo limpiamos
    if (intervalo) {
        clearInterval(intervalo);
        // 3. Eliminamos el dato del elemento para que quede limpio
        objeto.removeData('intervalo');
    }
}
 
function extraerDigitosClave(codigo) {
	codigo = String(codigo || '').trim();
	if (codigo.length < 12) return '';
	return codigo.slice(9, 12); // 0-based 9,10,11 == posiciones 10,11,12
}

async function sendMail(mensaje = null){
    let mail =  await $.post('./api/system.php', {'accion': 'smtp', 'id':1}, null, 'json');

    let parametros = {
        host: mail.host,
        puerto: mail.port,
        cifrado: mail.encryption,
        user: mail.user,
        pass: mail.pass,
        destinos: mail.to,
    }; 

    $.post('./include/mailer.php', {'accion': 'enviar', 'parametros': JSON.stringify(parametros), 'asunto': 'Trace Alertas', 'mensaje': mensaje }, function(resp) {
        if (resp.ok) {
            console.log('Correo enviado');
        } else {
            console.error('Error:', resp.error);
        }
    }, 'json');

}

function resetModalCompleto(modalId = 'modal') {
    const $modal = $('#' + modalId);
    const modalEl = $modal[0];
    if (!modalEl) return;
 
    // Descartamos la instancia de Bootstrap sin animación, sea cual sea
    // la forma en que esté expuesta la librería:
    //  - Bootstrap 5: bootstrap.Modal.getInstance(el) (API estática nativa)
    //  - Bootstrap 4: la instancia vive en $(el).data('bs.modal') (vía jQuery)
    if (window.bootstrap && bootstrap.Modal && typeof bootstrap.Modal.getInstance === 'function') {
        const instancia = bootstrap.Modal.getInstance(modalEl);
        if (instancia) instancia.dispose();
    } else {
        const instanciaBs4 = $modal.data('bs.modal');
        if (instanciaBs4) {
            if (typeof instanciaBs4.dispose === 'function') instanciaBs4.dispose();
            $modal.removeData('bs.modal');
        }
    }
 
    // 2. Removemos a mano las clases/atributos que deja Bootstrap al mostrar
    $modal.removeClass('show').css('display', 'none')
          .attr('aria-hidden', 'true').removeAttr('aria-modal');
    $('body').removeClass('modal-open').css({ overflow: '', paddingRight: '' });
    $('.modal-backdrop').remove();
 
    // 3. Limpiar título y restaurarlo a su estado base (remover clases flex y botones inyectados)
    const $title = $modal.find('.modal-title');
    $title.removeClass().addClass('modal-title').html('');
 
    // 4. Remover cualquier botón extra que se haya agregado al header (ej: "+ Agregar archivo")
    $modal.find('.modal-header').find('#btnAgregarArchivo').remove();
 
    // 5. Vaciar por completo el cuerpo del modal (destruye tablas, formularios o HTML viejo)
    $modal.find('.modal-body').empty();
 
    // 6. Remover estilos inline que Bootstrap u otros scripts le hayan inyectado
    $modal.removeAttr('style');
}

function mostrarCarga(mensaje = "Procesando...") {
    $("#title-modal").text("Espere por favor");
    $("#mensaje_modal").html("");
    $("#modal .modal-body").html(`<div class="text-center py-4"> 
                                    <div class="spinner-border mb-3" role="status"> 
                                        <span class="visually-hidden"> Procesando... </span> 
                                    </div>
                                    <div> ${mensaje} </div> 
                                </div> `);
    $("#btnModalsalir").hide();
    $("#modal").modal("show");
    
}

function ocultarCarga() {
    $("#modal").modal("hide");
    $("#btnModalsalir").show();
}