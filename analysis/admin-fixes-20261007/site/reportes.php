<?php
require_once __DIR__ . '/include/reportes_access.php';
require_once __DIR__ . '/include/reportes_service.php';
$reportToken = reportCsrf();
$headerType = 'admin';
include_once __DIR__ . '/include/header.php';
?>
<script>document.body.classList.add('reportes-page');</script>
<style>
.reportes-page .main-panel { height:auto !important; min-height:100vh; }
.reportes-page .footer { position:static !important; width:auto !important; max-width:none; margin:16px 20px 0 280px; transform:none; }
#contenidoReportes .text-muted, #contenidoReportes label { color:#526175 !important; }
#contenidoReportes .dataTables_filter input { border:1px solid #ccd3dd; border-radius:4px; padding:5px 8px; }
#contenidoReportes .dataTables_length select { border:1px solid #ccd3dd; border-radius:4px; padding:4px; }
@media (max-width:991px) { .reportes-page .footer { margin:16px 15px; } }
</style>
<div class="content" id="contenidoReportes">
    <script type="application/json" id="reportCatalog"><?= json_encode(reportCatalog(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
    <input type="hidden" id="reportCsrf" value="<?= htmlspecialchars($reportToken, ENT_QUOTES, 'UTF-8') ?>">
    <div id="reportAlerts" role="status" aria-live="polite"></div>
    <div id="reportWorkspace">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Reportes de producción</h5>
            </div>
            <div class="card-body">
                <form id="advancedReportForm">
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label for="reportType">Tipo de reporte</label>
                            <select class="form-control" id="reportType" required><option value="">Seleccionar tipo</option>
                            <?php foreach (reportCatalog() as $key => $config): ?>
                                <option value="<?= $key ?>"><?= htmlspecialchars($config['title'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 form-group"><label for="dateFrom">Desde</label><input type="date" class="form-control" id="dateFrom" min="2000-01-01" max="2099-12-31" required></div>
                        <div class="col-md-4 form-group"><label for="dateTo">Hasta (inclusive)</label><input type="date" class="form-control" id="dateTo" min="2000-01-01" max="2099-12-31" required></div>
                    </div>
                    <p id="reportPeriod" class="text-muted"></p>
                    <div class="row">
                        <div class="col-md-3 form-group"><label for="statusFilter">Estado</label><select class="form-control" id="statusFilter" disabled></select></div>
                        <div class="col-md-3 form-group"><label for="additionalCondition">Condición adicional</label><select class="form-control" id="additionalCondition" disabled></select></div>
                        <div class="col-md-2 form-group"><label for="conditionOperator">Comparación</label><select class="form-control" id="conditionOperator" disabled></select></div>
                        <div class="col-md-2 form-group"><label for="conditionValue">Valor / mínimo</label><input type="text" class="form-control" id="conditionValue" disabled maxlength="191"></div>
                        <div class="col-md-2 form-group" id="conditionMaxGroup" hidden><label for="conditionValueTo">Máximo</label><input type="text" class="form-control" id="conditionValueTo" disabled maxlength="191"></div>
                    </div>
                    <p id="conditionHelp" class="text-muted"></p>
                    <button type="submit" id="searchReports" class="btn btn-primary">Buscar datos</button>
                    <button type="button" id="clearFilters" class="btn btn-outline-secondary">Limpiar filtros</button>
                </form>
                <p id="reportNote" class="text-muted mt-3"></p>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h5>Resultados</h5><p id="resultsCount">Configure los filtros y busque datos.</p></div>
            <div class="card-body">
                <div id="loadingSpinner" hidden role="status">Procesando datos…</div>
                <div id="emptyState">No hay datos para mostrar.</div>
                <div id="resultsTableContainer" hidden>
                    <p id="reportApplied" class="text-muted"></p>
                    <button type="button" class="btn btn-success" id="exportExcel" disabled>Exportar CSV (Excel)</button>
                    <button type="button" class="btn btn-danger" id="exportPDF" disabled>Exportar PDF</button>
                    <p class="text-muted">Se exportan todas las filas que coinciden con la búsqueda de la tabla, en el orden mostrado, incluyendo las demás páginas.</p>
                    <div class="table-responsive"><table class="table table-striped table-bordered" id="resultsTable" style="width:100%"><thead></thead><tbody></tbody></table></div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include_once __DIR__ . '/include/footer.php'; ?>
