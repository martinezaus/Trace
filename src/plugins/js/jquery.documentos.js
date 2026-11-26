let _docActivo = { id: null, form: null, user: null };
let userId = null;

let guardadosPendientes = 0;

function cargarItems(documento_id, form, user) {
    if (user !== undefined && user !== null && Number(user) > 0) {
        userId = Number(user);
    }
    if (documento_id === undefined) {
        if (!_docActivo.id) return;
        documento_id = _docActivo.id;
        form         = _docActivo.form;
        userId       = _docActivo.user;
    } else {
        _docActivo.id   = documento_id;
        _docActivo.form = form;
        _docActivo.user = userId;
        limpiarPanel();
    }

    $.post('api/documentos.php', {'accion': 'lista', documento_id: documento_id}, function (respuesta) {
        $('#titulo-doc').text(respuesta[0].titulo || '');
        if (form == "proceso_corte") {
            renderTablaCorte(respuesta[0].id, respuesta[0].items);
        } else {
            renderTabla(respuesta[0].id);
        }
    }, 'json');
}

function limpiarPanel() {
    $('#panel-trabajo').show();     // siempre visible al cargar
    $('#panel-tabla').hide();       // tabla oculta por defecto
    $('#panel-pdf').css('flex', '1');
    $('#tabla-body').empty();
}

function renderTablaCorte(documento_id, items) {
    const $body = $('#tabla-body');
    $body.empty();

    $('#panel-trabajo').show();

    if (!items || items.length === 0) {
        $('#panel-tabla').hide();
        $('#panel-pdf').css('flex', '1');
        return;
    }

    $('#panel-tabla').show();
    $('#panel-pdf').css('flex', '0 0 58%');
    items = items.slice().sort((a, b) => a.id - b.id);
    let perfilAnterior = null;
    items.forEach(function (item) {
        const clave = item.perfil + '|' + item.descripcion;
        if (clave !== perfilAnterior) {
            $body.append('<tr class="perfil-header"><td colspan="5">' +
                item.perfil + ' — ' + item.descripcion +
                '</td></tr>');
            perfilAnterior = clave;
        }

        const checked = item.tilde == 1 ? 'checked' : '';
        const $fila = $(`
            <tr data-item-id="${item.id}" data-documento-id="${documento_id}">
                <td class="text-center">
                    <input type="checkbox" class="form-check-input chk-tilde" ${checked}>
                </td>
                <td class="text-muted small">${item.descripcion}</td>
                <td>${item.barra}</td>
                <td class="small">${item.detalle || ''}</td>
                <td>
                    <input type="text" class="form-control form-control-sm obs-input"
                        value="${(item.observacion || '').replace(/"/g, '&quot;')}" placeholder="Observación...">
                </td>
            </tr>`);
        $body.append($fila);
    });
}

function renderTabla(documento_id) {
    const $body = $('#tabla-body');
    $body.empty();
    $('#panel-trabajo').hide();
    $('#panel-tabla').hide();
    $('#panel-pdf').css('flex', '1');

    // let perfilAnterior = null;

    // items.forEach(function (item) {
    //     const clave = item.perfil + '|' + item.descripcion;
    //     if (clave !== perfilAnterior) {
    //         $body.append('<tr class="perfil-header"><td colspan="3">' +
    //             item.perfil + ' — ' + item.descripcion +
    //             '</td></tr>');
    //         perfilAnterior = clave;
    //     }

    //     const checked = item.tilde == 1 ? 'checked' : '';
    //     const $fila = $(`
    //         <tr data-item-id="${item.id}" data-documento-id="${documento_id}">
    //             <td class="text-center">
    //                 <input type="checkbox" class="form-check-input chk-tilde" ${checked}>
    //             </td>
    //             <td class="text-muted small">${item.descripcion} ${item.barra}</td>
    //             <td>
    //                 <input type="text" class="form-control form-control-sm obs-input"
    //                     value="${(item.observacion || '').replace(/"/g, '&quot;')}" placeholder="Observación...">
    //             </td>
    //         </tr>`);
    //     $body.append($fila);
    // });
}

// --- Guardar tilde ---
$(document).on('change', '.chk-tilde', function () {
    const fila   = $(this).closest('tr');
    const itemId = fila.data('item-id');
    const docId  = fila.data('documento-id');
    const valor  = $(this).is(':checked') ? 1 : 0;
    guardarCampo(docId, itemId, { tilde: valor }, fila, userId);
});

// --- Guardar observación con debounce ---
// let timer = null;
$(document).on('input', '.obs-input', function () {
    const input  = $(this);
    const fila   = input.closest('tr');
    const itemId = fila.data('item-id');
    const docId  = fila.data('documento-id');
    clearTimeout(fila.data('timer'));
    fila.data('timer', setTimeout(function () {
        guardarCampo(docId, itemId, { observacion: input.val() }, fila, userId);
    }, 800));
});

function guardarCampo(docId, itemId, datos, fila, user) {
    datos.doc_id       = docId;
    datos.item_id      = itemId;
    datos.user         = user;

    guardadosPendientes++; // ✅ FIX: marca que hay un guardado en curso

    $.post('api/system.php', {'accion': 'negocio'}, function(respuesta) {
        datos.escala = respuesta.escala;  
        datos.tamanio = respuesta.tamanio;
        //console.log(respuesta.escala+" "+respuesta.tamanio);
        $.post('api/documentos.php', {'accion': 'update', datos: datos}, function (resp) {
            if (resp.ok) {
                $('#mensaje_modal').html("✅ Guardado.").css('color', 'green');
                $('#mensaje').html("✅ Guardado.").css('color', 'green');
                setTimeout(() => $('#mensaje_modal').html(''), 300);

                if (resp.archivo) {
                    const urlVisor = 'tmp/modificados/' + resp.archivo + '?t=' + Date.now();
                    $('#visor-pdf').attr('src', urlVisor).show();
                }
            } else {
                const error = resp.error || 'No se pudo generar el PDF modificado.';
                $('#mensaje_modal, #mensaje').html('❌ ' + error).css('color', 'red');
                logger('ERROR', 'DOCUMENTOS', error);
            }
        }, 'json').fail(function () {
            $('#mensaje_modal').html("❌ Error al guardar.").css('color', 'red');
            $('#mensaje').html("❌ Error al guardar.").css('color', 'red');
            setTimeout(() => $('#mensaje_modal').html(''), 300);
        }).always(function () {
            guardadosPendientes--; // ✅ FIX: se libera al terminar (éxito o error)
        });
    }, 'json').fail(function () {
        guardadosPendientes--; // ✅ FIX: también se libera si falla la primera llamada
        $('#mensaje_modal, #mensaje').text('❌ No se pudo cargar la configuración del marcado. El cambio no se guardó.').css('color', 'red');
    });
}


setInterval(function () {
    if (guardadosPendientes === 0) {
        cargarItems();  //<-- jquery.documentos.js
    }
}, 10000);