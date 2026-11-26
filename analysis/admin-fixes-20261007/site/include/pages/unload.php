<?php
if (!isset($modoProceso)) {
    $modoProceso = (isset($_GET['modo']) && $_GET['modo'] === 'proceso') || (isset($_POST['modo']) && $_POST['modo'] === 'proceso');
}
?>

<?php if ($modoProceso): ?>

<div class="container-fluid">
    <form id="formViewPdfProceso">
        <div class="row">
            <div id="lista-archivos" class="mb-3">
                <div class="list-group" id="listGroup"></div>
            </div>
        </div>
        <div class="row">
            <small class="text-muted" id="titulo-doc"></small>
            <!-- PDF original, solo lectura -->
            <div class="col-md-7" id="panel-pdf">
                <iframe id="visor-pdf" src="" style="width:100%; height:85vh; border:none;"></iframe>
            </div>
        </div>
    </form>
</div>

<?php else: ?>

<form id="formViewArchivo" enctype="multipart/form-data">
    <div>
        <input type="hidden" id="ido" name="ido" value="<?php echo htmlspecialchars((string)($_GET['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" id="tipo" name="tipo" value="<?php echo htmlspecialchars((string)($_GET['tipo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        <div id="lista-archivos" class="mb-3">
            <div class="list-group" id="listGroup"></div>
        </div>
        <iframe id="iframe_arc" frameborder="0" scrolling="yes" width="100%" height="500px" style="display:none;"></iframe>
    </div>
</form>

<?php endif; ?>

<script>
(function ($) {
$(document).ready(function () {
    var modoProceso = <?php echo $modoProceso ? 'true' : 'false'; ?>;

    if (modoProceso) {
        // ---- Lógica original de unloadPdfEdit.php ----
        const ordenId = $('#modal').data('ordenId');
        const usuario = $('#modal').data('operarioId');
        const modulo  = $('#modal').data('modulo');

        $.ajax({
            url: "api/documentos.php",
            type: "POST",
            data: { accion: 'archive', id: ordenId },
            dataType: "json",
            success: function (response) {
                var html = '';
                $("#visor-pdf").hide();
                $.each(response, function (i, doc) {
                    if (doc.titulo) {
                        html += `<a href="#" class="pdf_option list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                                    data-file="${doc.titulo}" data-value="${doc.id}">
                                        <span>
                                            <i class="fas fa-file-pdf text-danger"></i>
                                            &nbsp;${doc.descripcion}
                                        </span>
                                        <span class="badge bg-primary">Abrir</span>
                                </a>`;
                    }
                });
                $("#lista-archivos").html(html || '<p class="text-muted">Sin archivos PDF.</p>').show();
            }
        });

        $(document).on('click', '.pdf_option', function (e) {
            e.preventDefault();
            var file = $(this).data('file');
            var id = $(this).data('value');
            $("#visor-pdf").attr('src', "./tmp/" + file).show();

            logger("ERROR", "PROCESO", "linea 458, [documento: " + id + " " + modulo + " " + usuario + "]");

            cargarItems(id, modulo, usuario); //Cargo los documentos.[jquery.documento.js]
        });

    } else {
        // ---- Lógica original de unload.php ----
        var tipo = $("#tipo").val();
        var id   = $("#ido").val();

        if (tipo === 'dat') {
            $.ajax({
                url: "api/ordenes.php",
                type: "POST",
                data: { accion: 'archive', id: id },
                dataType: "json",
                success: function (response) {
                    if (!response || !response.titulo) {
                        $("#lista-archivos").html('<div class="alert alert-warning">La orden no tiene archivo .dat asociado.</div>').show();
                        return;
                    }
                    $.ajax({
                        url: "./tmp/" + encodeURIComponent(response.titulo),
                        type: "GET",
                        dataType: "text",
                        success: function (contenido) {
                            const $visor = $('<pre class="bg-light border rounded p-3 mb-0"></pre>')
                                .css({ maxHeight: '65vh', overflow: 'auto', whiteSpace: 'pre-wrap' })
                                .text(contenido);
                            $("#iframe_arc").hide();
                            $("#lista-archivos").empty().append($visor).show();
                        },
                        error: function () {
                            $("#lista-archivos").html('<div class="alert alert-danger">No se pudo leer el archivo .dat.</div>').show();
                        }
                    });
                }
            });
        } else {
            $.ajax({
                url: "api/documentos.php",
                type: "POST",
                data: { accion: 'archive', id: id },
                dataType: "json",
                success: function (response) {
                    var html = '';
                    $("#iframe_arc").hide();
                    $.each(response, function (i, doc) {
                        if (doc.titulo) {
                            html += `<a href="#" class="pdf_option list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                                        data-file="${doc.titulo}">
                                        <span>
                                            <i class="fas fa-file-pdf text-danger"></i>
                                            &nbsp;${doc.descripcion}
                                        </span>
                                        <span>
                                            <span class="badge bg-primary me-2 btn-abrir" data-plano="${doc.titulo}">
                                                Abrir
                                            </span>`;
                            if (Number(doc.tipo) !== 1) {
                                if (doc.titulo_mod && doc.mod_disponible) {
                                    html += `   <span class="badge bg-success btn-ver-plano" data-id="${doc.id}">
                                                    Mod.
                                                </span>`;
                                } else {
                                    html += `   <span class="badge bg-secondary" title="El PDF modificado todavía no fue generado">
                                                    Sin Mod.
                                                </span>`;
                                }
                            }
                            html += `       <span class="badge bg-warning btn-ver-actualizar" data-id="${doc.id}" data-tipo="${doc.tipo}">
                                                Act.
                                            </span>
                                        </span>
                                     </a>`;
                        }
                    });
                    $("#lista-archivos").html(html || '<p class="text-muted">Sin archivos PDF.</p>').show();
                }
            });

            $(document).on('click', '.btn-abrir', function (e) {
                e.preventDefault();
                var file = $(this).data('plano');
                $("#iframe_arc").attr('src', "./tmp/" + file).show();
            });

            $(document).on('click', '.btn-ver-plano', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var documentoId = Number($(this).data('id')) || 0;
                if (!documentoId) {
                    mostrarMensaje("El PDF modificado todavía no fue generado.", 'orange', 10000, 'mensaje_modal');
                    $("#iframe_arc").hide();
                    return;
                }
                var ruta = "include/pages/documentoModificado.php?id=" + encodeURIComponent(documentoId) + "&t=" + Date.now();
                $("#iframe_arc").attr('src', ruta).show();
            });
            $(document).on('click', '.btn-ver-actualizar', function (e) {
                e.preventDefault();
                var tipo = $(this).data('tipo');
                var id = $(this).data('id');

                var modal = document.getElementById('modal');
                var modalAgregar = document.getElementById('modal_b');
                $(modal).one('hidden.bs.modal', function () {
                    $(".modal-body", modalAgregar).load("include/pages/upload.php?modo=reemplazo", function () {
                        $("form", modalAgregar).find("input[id=id]").val(id);
                        $("form", modalAgregar).find("input[id=tipo_doc]").val(tipo);
                        $(modalAgregar).modal('show');
                    });
                });

                $(modal).modal('hide');
            });
        }
    }
});
})(jQuery);
</script>