(() => {
  'use strict';
  const form = document.getElementById('formProductividad');
  const rows = document.getElementById('scaleRows');
  const alertBox = document.getElementById('alertBox');
  function alertar(ok, message) {
    const div = document.createElement('div');
    div.className = 'alert ' + (ok ? 'alert-ok' : 'alert-error');
    div.textContent = message; alertBox.replaceChildren(div);
  }
  document.getElementById('addTier').addEventListener('click', () => {
    if (rows.children.length >= 30) { alertar(false, 'Máximo 30 tramos.'); return; }
    const tr = document.createElement('tr');
    for (const [name, label] of [['ahorro_desde[]', 'Ahorro mínimo'], ['adicional_porcentaje[]', 'Porcentaje de adicional']]) {
      const td = document.createElement('td'), input = document.createElement('input');
      Object.assign(input, {type: 'number', name, min: '0', max: '100', step: '0.01', required: true});
      input.setAttribute('aria-label', label); td.append(input); tr.append(td);
    }
    const td = document.createElement('td'), button = document.createElement('button');
    button.type = 'button'; button.className = 'removeTier'; button.textContent = 'Quitar'; td.append(button); tr.append(td); rows.append(tr); tr.querySelector('input').focus();
  });
  rows.addEventListener('click', event => { if (event.target.closest('.removeTier')) event.target.closest('tr').remove(); });
  document.getElementById('simulate').addEventListener('click', async () => {
    const button = document.getElementById('simulate'), output = document.getElementById('simResult');
    const data = new FormData(form); data.set('accion', 'simular');
    button.disabled = true;
    try {
      const response = await fetch(form.action, {method: 'POST', body: data, credentials: 'same-origin'});
      const result = await response.json();
      if (!response.ok || !result.ok) throw new Error(result.error || 'No se pudo calcular.');
      const e = result.economia;
      const money = value => value === null ? 'Sin determinar' : new Intl.NumberFormat('es-AR', {style: 'currency', currency: e.moneda}).format(value);
      output.textContent = 'Productividad: ' + result.productividad.toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' %. Costo estimado: ' + money(e.costo_estimado) + '. Costo real: ' + money(e.costo_real) + '. Ahorro: ' + money(e.ahorro_costo) + '. Adicional propuesto: ' + money(e.adicional) + '. Ejemplo sin guardar.';
    } catch (error) { output.textContent = error.message || 'No se pudo calcular.'; }
    finally { button.disabled = false; }
  });
  form.addEventListener('submit', async event => {
    event.preventDefault();
    const data = new FormData(form), button = form.querySelector('button[type=submit]');
    button.disabled = true; document.getElementById('savingMsg').textContent = 'Guardando…';
    try {
      const response = await fetch(form.action, {method: 'POST', body: data, credentials: 'same-origin'});
      const result = await response.json();
      if (!response.ok || !result.ok) throw new Error(result.error || 'No se pudo guardar.');
      form.elements.version.value = result.version;
      alertar(true, 'Configuración guardada como versión #' + result.version + '.');
      window.location.reload();
    } catch (error) { alertar(false, error.message || 'Error de comunicación.'); }
    finally { button.disabled = false; document.getElementById('savingMsg').textContent = ''; }
  });
})();
