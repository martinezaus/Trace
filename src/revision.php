<?php
require_once __DIR__ . '/include/auth.php';
requerirOperadorPagina();
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/operator_access.php';

try {
    $db = new PDO(
        'mysql:host=' . hostBD . ';dbname=' . nombreBD . ';charset=utf8mb4',
        usuarioBD,
        claveBD,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (Throwable $e) {
    error_log('Revision page database: ' . $e->getMessage());
    http_response_code(500);
    exit('No se pudo validar el acceso al sector Revisión.');
}
try {
    $operadorRevision = operadorRequerirPermiso($db, 'revision');
} catch (Throwable $e) {
    if (http_response_code() < 400) http_response_code(403);
    exit('No tiene permiso para ingresar al sector Revisión. Vuelva a iniciar sesión.');
}

$headerType = 'revision';
include_once __DIR__ . '/include/header.php';
?>
<script>const REVISION_OPERADOR = <?= json_encode($operadorRevision['nombre'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<main class="revision-shell">
    <header class="revision-header">
        <div class="revision-brand">
            <img src="<?= $baseUrl ?>img/logo_ferroalum_blco.png" alt="Ferro Alum Aberturas TRACE">
        </div>
        <div class="revision-heading">
            <span class="revision-eyebrow">Sistema de producción</span>
            <h1>Revisión de documentos</h1>
            <p>Operador: <strong id="revisionOperador"></strong></p>
        </div>
        <div class="revision-actions">
            <button type="button" class="btn revision-logout-button" id="revisionCerrarSesion">Cerrar sesión</button>
            <button type="button" class="btn revision-card-logout-button" id="revisionCerrarConTarjeta">Cerrar con tarjeta</button>
        </div>
        <form id="revisionLogoutScanner" class="revision-logout-scanner" hidden>
            <label for="revisionLogoutToken">Escanee la tarjeta del operador conectado</label>
            <div class="revision-scanner-controls">
                <input type="password" class="form-control" id="revisionLogoutToken" autocomplete="off" aria-describedby="revisionLogoutError">
                <button type="button" class="btn revision-card-logout-button" id="revisionCancelarTarjeta">Cancelar</button>
            </div>
            <small>La sesión se cerrará automáticamente al finalizar la lectura.</small>
            <p id="revisionLogoutError" class="revision-logout-error" role="status" aria-live="polite"></p>
        </form>
    </header>
    <section class="card revision-card">
        <div class="card-header">
            <div>
                <h2>Documentos</h2>
                <p>Marque cada documento una vez finalizada su revisión.</p>
            </div>
            <label class="revision-search" for="revisionBuscar">
                <span>Buscar por orden o cliente</span>
                <input type="search" class="form-control" id="revisionBuscar" autocomplete="off" placeholder="Número de orden o cliente">
            </label>
        </div>
        <div id="revisionMensaje" class="revision-message" role="status" aria-live="polite"></div>
        <div class="table-responsive">
            <table class="table revision-table">
                <thead>
                    <tr>
                        <th scope="col">Orden</th>
                        <th scope="col">Cliente</th>
                        <th scope="col">Documento</th>
                        <th scope="col">Revisión</th>
                    </tr>
                </thead>
                <tbody id="revisionDocumentos">
                    <tr><td colspan="4" class="text-center text-muted">Cargando documentos…</td></tr>
                </tbody>
            </table>
        </div>
        <p id="revisionSinResultados" class="revision-empty" hidden>No hay documentos que coincidan con la búsqueda.</p>
    </section>
</main>
<?php include_once __DIR__ . '/include/footer.php'; ?>
