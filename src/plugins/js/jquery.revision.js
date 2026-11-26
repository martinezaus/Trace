$(function () {
    if (!document.getElementById('revisionDocumentos')) return;
    $('#revisionOperador').text(REVISION_OPERADOR || '');
    cargarDocumentosRevision();
    $('#revisionBuscar').on('input', filtrarDocumentosRevision);
    $('#revisionDocumentos').on('click', '.btn-mark-reviewed', marcarDocumentoRevisado);
    $('#revisionCerrarSesion').on('click', () => cerrarSesionRevision());
    $('#revisionCerrarConTarjeta').on('click', abrirLectorSalidaRevision);
    $('#revisionCancelarTarjeta').on('click', cerrarLectorSalidaRevision);
    $('#revisionLogoutScanner').on('submit', cerrarSesionConTarjetaRevision);
    $('#revisionLogoutToken').on('keydown', function (evento) {
        if (evento.key !== 'Enter') return;
        evento.preventDefault();
        cerrarSesionConTarjetaRevision(evento);
    });
});

function textoRevisionSeguro(valor) {
    return String(valor ?? '').replace(/[&<>"']/g, caracter => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[caracter]));
}

function normalizarBusquedaRevision(valor) {
    return String(valor ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase();
}

function cargarDocumentosRevision() {
    $('#revisionDocumentos').html('<tr><td colspan="4" class="text-center text-muted">Cargando documentos…</td></tr>');
    $.post('api/revision.php', { accion: 'lista' }, null, 'json')
        .done(respuesta => {
            if (!respuesta?.ok || !Array.isArray(respuesta.documentos)) {
                $('#revisionDocumentos').html('<tr><td colspan="4" class="text-center text-danger"></td></tr>');
                $('#revisionDocumentos td').text(respuesta?.mensaje || 'No se pudo cargar la lista de documentos.');
                return;
            }
            if (!respuesta.documentos.length) {
                $('#revisionDocumentos').html('<tr><td colspan="4" class="text-center text-muted">No hay documentos para revisar.</td></tr>');
                $('#revisionSinResultados').prop('hidden', true);
                return;
            }
            $('#revisionDocumentos').html(respuesta.documentos.map(filaDocumentoRevision).join(''));
            $('#revisionOperador').text(respuesta.operador || REVISION_OPERADOR || '');
            filtrarDocumentosRevision();
        })
        .fail(xhr => {
            const mensaje = xhr.responseJSON?.mensaje || 'No se pudo cargar la lista. Actualice la página o vuelva a iniciar sesión.';
            $('#revisionDocumentos').html('<tr><td colspan="4" class="text-center text-danger"></td></tr>');
            $('#revisionDocumentos td').text(mensaje);
        });
}

function filaDocumentoRevision(documento) {
    const orden = textoRevisionSeguro(documento.orden);
    const cliente = textoRevisionSeguro(documento.cliente);
    const nombre = textoRevisionSeguro(documento.documento);
    const buscar = textoRevisionSeguro(normalizarBusquedaRevision(`${documento.orden ?? ''} ${documento.cliente ?? ''}`));
    const accion = Number(documento.revisado) === 1
        ? `<span class="revision-reviewed">Revisado${documento.fecha_hora_revisado ? ` · ${textoRevisionSeguro(documento.fecha_hora_revisado)}` : ''}${documento.revisor ? ` · ${textoRevisionSeguro(documento.revisor)}` : ''}</span>`
        : `<button type="button" class="btn btn-sm btn-mark-reviewed" data-documento-id="${Number(documento.id)}">Marcar revisado</button>`;
    return `<tr data-search="${buscar.toLowerCase()}">
        <td class="revision-order">${orden}</td>
        <td>${cliente}</td>
        <td class="revision-document">${nombre}</td>
        <td>${accion}</td>
    </tr>`;
}

function filtrarDocumentosRevision() {
    const buscar = normalizarBusquedaRevision(String($('#revisionBuscar').val() || '').trim());
    let visibles = 0;
    $('#revisionDocumentos tr[data-search]').each(function () {
        const fila = $(this);
        const coincidencia = !buscar || fila.attr('data-search').includes(buscar);
        fila.toggle(coincidencia);
        if (coincidencia) visibles++;
    });
    $('#revisionSinResultados').prop('hidden', visibles > 0 || !$('#revisionDocumentos tr[data-search]').length);
}

function marcarDocumentoRevisado() {
    const boton = this;
    const documentoId = Number(boton.dataset.documentoId);
    if (!Number.isInteger(documentoId) || documentoId < 1 || boton.disabled) return;
    if (!window.confirm('¿Confirma que el documento fue revisado? Esta acción no se puede deshacer desde esta pantalla.')) return;
    boton.disabled = true;
    $.post('api/revision.php', { accion: 'marcar', documento_id: documentoId }, null, 'json')
        .done(respuesta => {
            if (!respuesta?.ok) {
                $('#revisionMensaje').removeClass('is-success').addClass('is-error')
                    .text(respuesta?.mensaje || 'No se pudo marcar el documento.');
                boton.disabled = false;
                return;
            }
            cargarDocumentosRevision();
        })
        .fail(xhr => {
            $('#revisionMensaje').removeClass('is-success').addClass('is-error')
                .text(xhr.responseJSON?.mensaje || 'No se pudo confirmar la revisión. Actualice la lista antes de reintentar.');
            boton.disabled = false;
        });
}

function abrirLectorSalidaRevision() {
    $('#revisionLogoutError').text('');
    $('#revisionLogoutToken').val('');
    $('#revisionLogoutScanner').prop('hidden', false);
    $('#revisionLogoutToken').trigger('focus');
}

function cerrarLectorSalidaRevision() {
    $('#revisionLogoutScanner').prop('hidden', true);
    $('#revisionLogoutToken').val('');
    $('#revisionCerrarConTarjeta').trigger('focus');
}

function cerrarSesionConTarjetaRevision(evento) {
    evento.preventDefault();
    const token = String($('#revisionLogoutToken').val() || '').trim();
    if (!token) {
        $('#revisionLogoutError').text('Escanee la tarjeta de acceso antes de confirmar.');
        $('#revisionLogoutToken').trigger('focus');
        return;
    }
    cerrarSesionRevision(token);
}

function cerrarSesionRevision(token = '') {
    if (!token && !window.confirm('¿Desea cerrar la sesión?')) return;

    const botones = $('#revisionCerrarSesion, #revisionCerrarConTarjeta, #revisionLogoutScanner button');
    botones.prop('disabled', true);
    const datos = { accion: 'logout' };
    if (token) datos.token = token;

    $.post('api/logout.php', datos, null, 'json')
        .done(respuesta => {
            if (!respuesta?.ok) {
                mostrarErrorCierreRevision(respuesta?.mensaje || 'No se pudo cerrar la sesión.');
                return;
            }
            window.location.replace('index.php');
        })
        .fail(xhr => {
            mostrarErrorCierreRevision(xhr.responseJSON?.mensaje || 'No se pudo cerrar la sesión. Intente nuevamente.');
        })
        .always(() => botones.prop('disabled', false));
}

function mostrarErrorCierreRevision(mensaje) {
    if (!$('#revisionLogoutScanner').prop('hidden')) {
        $('#revisionLogoutError').text(mensaje);
        $('#revisionLogoutToken').val('').trigger('focus');
        return;
    }
    $('#revisionMensaje').removeClass('is-success').addClass('is-error').text(mensaje);
}
