<?php
require_once __DIR__ . '/include/auth.php';
requerirOperadorPagina();
$mostrarMenu=false;
$headerType='corte';
include_once("include/header.php");
?>
<script>const OPERADOR_ID = <?php echo operadorId(); ?>;</script>
<div class="page-wrapper corte-page">
<main class="content corte-content">
<section class="corte-hero">
  <div class="corte-brand">
    <img src="img/logo_ferroalum_blco.png" alt="Ferroalum">
    <div><span class="corte-eyebrow">Sistema de producción</span><h1>Proceso de Corte</h1><p>Seleccione una orden y continúe con el documento de trabajo.</p></div>
  </div>
  <div class="corte-search">
    <label for="orden">Número de orden</label>
    <div class="input-group">
      <input type="text" class="form-control" id="orden" name="orden" inputmode="numeric" autocomplete="off" placeholder="Escanee o ingrese la orden">
      <button type="button" class="btn btn-primary btnBuscarOrden" id="btnBuscarOrden" name="btnBuscarOrden"><img src="img/icons/icons8-ingresar-32.png" alt=""><span>Buscar</span></button>
    </div>
  </div>
  <div class="corte-exit">
    <button type="button" class="btn btn-outline-danger" id="btnCerrarCorte"><i class="fa fa-sign-out"></i><span>Cerrar jornada</span></button>
    <small>También puede escanear su credencial.</small>
  </div>
</section>
<section class="card mb-3"><div class="card-body"><strong>Mis tareas pendientes</strong><div id="corte-pendientes">Cargando...</div><small>Los registros anteriores se conservan como históricos. Las nuevas tareas se registran por PDF.</small></div></section>
<div id="mensaje" class="corte-message" role="status" aria-live="polite"></div>
<section class="row g-3 corte-status">
  <div class="col-lg-4"><div class="card card-stats h-100"><div class="card-body"><div class="status-icon"><i class="fa fa-list-alt"></i></div><div><span class="status-label">Orden activa</span><strong class="status-value" id="orden-actual">Sin orden</strong></div></div></div></div>
  <div class="col-lg-4"><div class="card card-stats h-100"><div class="card-body"><div class="status-icon"><i class="fa fa-clock-o"></i></div><div class="status-grow"><span class="status-label">Tarea actual</span><strong class="status-value" id="tarea-actual">Sin tarea</strong><div class="cronometro" id="timerT">00:00:00.00</div></div></div></div></div>
  <div class="col-lg-4"><div class="card card-stats h-100"><div class="card-body"><div class="status-icon"><i class="fa fa-user"></i></div><div><span class="status-label">Operador</span><strong class="status-value" id="operario"></strong></div></div></div></div>
</section>
<form id="formProcesoCorte">
<section class="card card-archivos shadow-sm">
  <div class="card-header corte-card-header"><div><span class="card-kicker">Documentación</span><h2>Selección de documento</h2></div><strong id="progreso-items" class="progress-pill">0/0 cortados</strong></div>
  <div class="card-body">
    <div id="lista-archivos" class="document-list"><div class="list-group" id="listGroup"></div></div>
    <div class="corte-tools">
      <button type="button" id="btnFinalizarCorte" class="btn btn-success" disabled>Finalizar PDF (F2)</button>
      <span id="estado-pausa"></span><div class="tool-qr"><img id="barcode_pausa" alt="Código de pausa"><div><strong>Pausas</strong><small>Escanee el código para seleccionar una pausa.</small></div></div>
      <div class="tool-shortcut"><small>Atajo de teclado</small><div><kbd>F2</kbd><span>Finalizar tarea</span></div></div>
    </div>
  </div>
</section>
<small class="document-title" id="titulo-doc"></small>
<section class="panel-trabajo" id="panel-trabajo" style="display:none;">
  <div class="panel-pdf-wrap" id="panel-pdf"><div class="panel-label"><i class="fa fa-file-pdf-o"></i> Vista previa PDF</div><iframe id="visor-pdf" src="" title="Vista previa del documento PDF"></iframe></div>
  <div class="panel-tabla-wrap" id="panel-tabla" style="display:none;"><div class="panel-label"><i class="fa fa-check-square-o"></i> Ítems a cortar</div><div class="tabla-scroll"><table class="table table-sm table-bordered tabla-items mb-0"><thead><tr><th style="width:50px;">✓</th><th>Perfil / Descripción</th><th style="width:65px;">Barra</th><th>Detalle</th><th style="width:30%;">Observación</th></tr></thead><tbody id="tabla-body"><tr><td colspan="5" class="text-center text-muted">Cargando...</td></tr></tbody></table></div></div>
</section>
</form>
</main>
</div>
<?php include_once('./include/modal.php'); include_once('include/footer.php'); ?>