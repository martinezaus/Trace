(() => {
  const form = document.getElementById('formNegocio');
  const input = document.getElementById('logo');
  const preview = document.getElementById('logoPreview');
  const estado = document.getElementById('logoEstado');
  let temporal = null;
  let guardado = preview.getAttribute('src');
  function alertar(ok, mensaje) {
    const alerta = document.createElement('div');
    alerta.className = 'alert ' + (ok ? 'alert-ok' : 'alert-error');
    alerta.textContent = mensaje;
    document.getElementById('alertBox').replaceChildren(alerta);
  }
  function liberar() { if (temporal) URL.revokeObjectURL(temporal); temporal = null; }
  function validar(archivo) {
    if (archivo.size === 0 || archivo.size > 3 * 1024 * 1024) return 'Seleccione una imagen de hasta 3 MB que no esté vacía.';
    if (archivo.type && !['image/png', 'image/jpeg', 'image/gif', 'image/webp'].includes(archivo.type)) return 'Use una imagen PNG, JPG, GIF o WEBP.';
    return '';
  }
  input.addEventListener('change', () => {
    liberar();
    const archivo = input.files[0];
    const error = archivo ? validar(archivo) : '';
    if (error) { alertar(false, error); input.value = ''; }
    if (archivo && !error) {
      temporal = URL.createObjectURL(archivo);
      preview.src = temporal;
      preview.hidden = false;
      estado.textContent = 'Imagen seleccionada: ' + archivo.name + '. Pulse Guardar cambios para subirla.';
    } else {
      if (guardado) preview.src = guardado; else preview.removeAttribute('src');
      preview.hidden = !guardado;
      estado.textContent = guardado ? 'Logo guardado actualmente.' : 'Sin logo cargado.';
    }
  });
  form.addEventListener('submit', async event => {
    event.preventDefault();
    const archivo = input.files[0];
    const error = archivo ? validar(archivo) : '';
    if (error) { alertar(false, error); return; }
    const datos = new FormData(form);
    const boton = form.querySelector('button[type=submit]');
    boton.disabled = true;
    input.disabled = true;
    document.getElementById('savingMsg').textContent = 'Guardando…';
    try {
      const response = await fetch(form.action, {method: 'POST', body: datos, credentials: 'same-origin'});
      const resp = await response.json().catch(() => null);
      if (!response.ok || !resp || !resp.ok) {
        throw new Error((resp && resp.error) || (response.status === 413
          ? 'La imagen supera el límite de carga del servidor.'
          : 'No se pudo guardar. Compruebe su sesión e intente nuevamente.'));
      }
      if (resp.logo_actualizado) {
        liberar();
        guardado = 'api/get_logo.php?v=' + Date.now();
        preview.src = guardado;
        preview.hidden = false;
        input.value = '';
        estado.textContent = 'Logo guardado correctamente.';
      }
      alertar(true, resp.logo_actualizado ? 'Datos y logo guardados correctamente.' : 'Cambios guardados correctamente.');
    } catch (error) {
      alertar(false, error.message || 'Error de comunicación con el servidor.');
    } finally {
      boton.disabled = false;
      input.disabled = false;
      document.getElementById('savingMsg').textContent = '';
    }
  });
})();
