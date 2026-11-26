$(function () {
  let prefijoTarea = 'T';
  $('#categoria').on('change', function () {
    const tarea = this.value === 'tareas';
    if (!$('#prefijo').prop('readonly')) prefijoTarea = $('#prefijo').val();
    $('#prefijo').val(tarea ? prefijoTarea : (this.value === 'pausas' ? 'CP' : 'CS'))
      .prop('readonly', !tarea).attr('pattern', tarea ? '[Tt][A-Za-z]*' : 'C[PS]');
    $('#campoTipo').prop('hidden', !tarea);
    $('#tipo').prop('disabled', !tarea);
    $('#opcion').prop('checked', false);
    $('#opcionLabel').text(tarea ? 'Tarea inactiva' : 'Sumar horas a la orden');
    $('#codigoHint').text(tarea
      ? 'Tareas: T seguida opcionalmente de otras letras (por ejemplo, TMA). El número se asigna automáticamente.'
      : 'El código se genera con este prefijo y una numeración automática, por ejemplo ' + $('#prefijo').val() + '0001.');
  });
  function alertar(ok, mensaje) {
    $('#alertBox').empty().append($('<div>').addClass('alert ' + (ok ? 'alert-ok' : 'alert-error')).text(mensaje));
  }
  $('#formAlta, #formParametros').on('submit', function (event) {
    event.preventDefault();
    const form = this;
    const alta = form.id === 'formAlta';
    const datos = new FormData(form);
    const botones = $('#formAlta, #formParametros').find('button[type=submit]').prop('disabled', true);
    $.ajax({url: 'api/save_parametros.php', method: 'POST', data: datos,
      processData: false, contentType: false, dataType: 'json'
    }).done(function (resp) {
      if (!resp || !resp.ok) { alertar(false, (resp && resp.error) || 'No se pudo guardar.'); return; }
      if (alta) {
        const item = resp.item;
        const id = item.categoria + '_' + item.id;
        const fila = $('<div>').addClass('checkline').css('margin-bottom', '10px');
        fila.append($('<input>', {type: 'hidden', name: 'ids[' + item.categoria + '][]', value: item.id}));
        fila.append($('<input>', {type: 'checkbox', id: id, name: item.categoria + '[]', value: item.id}).prop('checked', !!item.opcion));
        fila.append($('<label>', {for: id}).text(item.codigo));
        fila.append($('<input>', {type: 'text', name: 'descripciones[' + item.categoria + '][' + item.id + ']',
          maxlength: 100, required: true, 'aria-label': 'Descripción de ' + item.codigo})
          .val(item.descripcion).css({'min-width': 0, flex: 1, width: '100%'}));
        $('#lista_' + item.categoria).append(fila);
        $('#descripcion').val('').trigger('focus');
        alertar(true, 'Tipo agregado correctamente con código ' + item.codigo + '.');
      } else { alertar(true, 'Cambios guardados correctamente.'); }
    }).fail(function (xhr) {
      alertar(false, (xhr.responseJSON && xhr.responseJSON.error) || 'Error de comunicación con el servidor.');
    }).always(function () { botones.prop('disabled', false); });
  });
});
