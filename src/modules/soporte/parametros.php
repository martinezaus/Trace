<?php
require_once __DIR__ . '/includes/auth.php';
require_role(['admin', 'master']);
use RedBeanPHP\R;
$grupos = ['tareas' => 'Tareas', 'pausas' => 'Pausas', 'suspensiones' => 'Suspensiones'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Parámetros de soporte</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="assets/css/style.css">
<style>
.parametros-container { max-width: 1440px; }
.parametros-layout { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 24px; align-items: start; margin-top: 20px; }
.parametros-layout > .panel { max-width: none; min-width: 0; margin-top: 0; }
.parametros-listas { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr)); gap: 16px; align-items: start; }
.parametros-listas > .parametro-grupo { min-width: 0; margin: 0; padding: 15px; overflow-wrap: anywhere; }
.parametro-grupo .checkline {display:grid; grid-template-columns:18px minmax(0,1fr); gap:8px;}
.parametro-grupo input[type="text"] {grid-column:1 / -1; box-sizing:border-box;}
@media (max-width: 900px) {
  .parametros-layout { grid-template-columns: minmax(0, 1fr); }
}
</style>
</head>
<body>
<?php include __DIR__ . '/partials/header.php'; ?>
<main class="container parametros-container">
  <h2>Parámetros</h2>
  <div id="alertBox" role="status" aria-live="polite"></div>

  <div class="parametros-layout">
  <form id="formParametros" class="panel">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
    <input type="hidden" name="accion" value="guardar">
    <div class="parametros-listas">
    <?php foreach ($grupos as $tabla => $titulo): ?>
      <section class="panel parametro-grupo">
        <h3><?= $titulo ?></h3>
        <p class="field-hint"><?= $tabla === 'tareas' ? 'El tilde indica que la tarea está inactiva.' : 'El tilde indica que se suman horas a la orden.' ?></p>
        <div id="lista_<?= $tabla ?>">
        <?php foreach (R::findAll($tabla, ' ORDER BY codigo, id') as $item):
            $campo = $tabla === 'tareas' ? 'enable' : 'flag'; ?>
          <div class="checkline" style="margin-bottom:10px;">
            <input type="hidden" name="ids[<?= $tabla ?>][]" value="<?= (int)$item->id ?>">
            <input type="checkbox" id="<?= $tabla ?>_<?= (int)$item->id ?>" name="<?= $tabla ?>[]" value="<?= (int)$item->id ?>" <?= (int)$item->$campo ? 'checked' : '' ?>>
            <label for="<?= $tabla ?>_<?= (int)$item->id ?>"><?= htmlspecialchars($item->codigo, ENT_QUOTES, 'UTF-8') ?></label>
            <input type="text" name="descripciones[<?= $tabla ?>][<?= (int)$item->id ?>]" value="<?= htmlspecialchars($item->descripcion, ENT_QUOTES, 'UTF-8') ?>" maxlength="100" required aria-label="Descripción de <?= htmlspecialchars($item->codigo, ENT_QUOTES, 'UTF-8') ?>" style="min-width:0; flex:1; width:100%;">
          </div>
        <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>
    </div>
    <div class="actions"><button type="submit">Guardar cambios</button></div>
  </form>
  <form id="formAlta" class="panel">
    <h3>Agregar tipo</h3>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
    <input type="hidden" name="accion" value="crear">
    <label for="categoria">Categoría</label>
    <select id="categoria" name="categoria">
      <?php foreach ($grupos as $tabla => $titulo): ?>
      <option value="<?= $tabla ?>"><?= $titulo ?></option>
      <?php endforeach; ?>
    </select>
    <label for="descripcion">Descripción</label>
    <input type="text" id="descripcion" name="descripcion" maxlength="100" required>
    <label for="prefijo">Prefijo del código</label>
    <input type="text" id="prefijo" name="prefijo" value="T" maxlength="10" pattern="[Tt][A-Za-z]*" required>
    <p class="field-hint" id="codigoHint">Tareas: T seguida opcionalmente de otras letras (por ejemplo, TMA). El número se asigna automáticamente.</p>
    <div id="campoTipo">
      <label for="tipo">Aplicación de la tarea</label>
      <select id="tipo" name="tipo">
        <option value="0">Metal</option><option value="1">Vidrio</option>
        <option value="2">Cortes</option><option value="3">Mosquiteros</option><option value="9">Todos</option>
      </select>
    </div>
    <div class="checkline">
      <input type="checkbox" id="opcion" name="opcion" value="1">
      <label for="opcion" id="opcionLabel">Tarea inactiva</label>
    </div>
    <div class="actions"><button type="submit">Agregar tipo</button></div>
  </form>
  </div>
</main>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="assets/js/parametros.js?v=release-0003"></script>
</body>
</html>
