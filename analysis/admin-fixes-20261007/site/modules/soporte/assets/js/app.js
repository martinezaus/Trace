/**
 * app.js — lógica común del panel de configuraciones.
 * Sin dependencias más allá de jQuery.
 */

function showAlert(type, message) {
  const cls = type === 'ok' ? 'alert-ok' : 'alert-error';
  $('#alertBox').html('<div class="alert ' + cls + '">' + message + '</div>');
  if (type === 'ok') {
    setTimeout(() => $('#alertBox').empty(), 4000);
  }
}

/**
 * Conecta un formulario simple (con o sin archivos) a un endpoint AJAX.
 * El endpoint debe devolver JSON: { ok: true } o { ok:false, error:"..." }
 */
function bindSimpleForm(formSelector, endpoint) {
  $(formSelector).on('submit', function (e) {
    e.preventDefault();
    const $form = $(this);
    const $btn = $form.find('button[type=submit]');
    const formData = new FormData(this);

    $btn.prop('disabled', true);
    $('#savingMsg').text('Guardando…');

    $.ajax({
      url: endpoint,
      method: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      dataType: 'json'
    }).done(function (resp) {
      if (resp && resp.ok) {
        showAlert('ok', 'Cambios guardados correctamente.');
      } else {
        showAlert('error', (resp && resp.error) ? resp.error : 'No se pudo guardar.');
      }
    }).fail(function (xhr) {
      let msg = 'Error de comunicación con el servidor.';
      try {
        const j = JSON.parse(xhr.responseText);
        if (j && j.error) msg = j.error;
      } catch (e) {}
      showAlert('error', msg);
    }).always(function () {
      $btn.prop('disabled', false);
      $('#savingMsg').text('');
    });
  });
}

/* ---------- Visor de logs ---------- */
function initLogViewer() {
  let currentFile = null;

  function loadList() {
    $.getJSON('api/get_log.php', { action: 'list' }, function (resp) {
      const $ul = $('#logFileList').empty();
      if (!resp.ok) {
        $ul.append('<li class="field-hint" style="border:none;">' + (resp.error || 'Error al listar logs') + '</li>');
        return;
      }
      if (!resp.files.length) {
        $ul.append('<li class="field-hint" style="border:none;">No hay archivos de log.</li>');
        return;
      }


      console.log(resp);

      resp.files.forEach(function (f) {
        const $li = $('<li></li>')
          .attr('data-file', f.name)
          .html(f.name + '<span class="meta">' + f.modified + ' · ' + formatBytes(f.size) + '</span>');
        $li.on('click', function () { openFile(f.name); });
        $ul.append($li);
      });
    }).fail(function () {
      $('#logFileList').html('<li class="field-hint" style="border:none;">Error al listar logs.</li>');
    });
  }

  function openFile(name) {
    currentFile = name;
    $('#logFileList li').removeClass('active');
    $('#logFileList li[data-file="' + name + '"]').addClass('active');
    $('#logName').text(name);
    $('#btnRefresh').show();
    $('#logContent').html('<span class="empty">Cargando…</span>');

    $.getJSON('api/get_log.php', { action: 'view', file: name, lines: 500 }, function (resp) {
      if (!resp.ok) {
        $('#logContent').html('<span class="empty">' + (resp.error || 'No se pudo leer el archivo') + '</span>');
        return;
      }
      $('#logContent').text(resp.content || '(archivo vacío)');
    }).fail(function () {
      $('#logContent').html('<span class="empty">Error al leer el archivo.</span>');
    });
  }

  $('#btnRefresh').on('click', function () {
    if (currentFile) openFile(currentFile);
  });

  loadList();
}

function formatBytes(bytes) {
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}
