<?php
if (!isset($modo)) {
    $modo = isset($_GET['modo']) ? $_GET['modo'] : (isset($_POST['modo']) ? $_POST['modo'] : 'completo');
}
if (!in_array($modo, ['completo', 'solo_pdf', 'reemplazo'], true)) {
    $modo = 'completo';
}
if (!isset($idOrden)) { $idOrden = ''; }

$formIds = [
    'completo'  => 'formArchivo',
    'solo_pdf'  => 'formArchivoPdf',
    'reemplazo' => 'formArchivoActPdf',
];
$modalIds = [
    'completo'  => 'modal',
    'solo_pdf'  => 'modal_b',
    'reemplazo' => 'modal_b',
];
$formId  = $formIds[$modo];
$modalId = $modalIds[$modo];
?>

<form id="<?php echo $formId; ?>" enctype="multipart/form-data" novalidate data-modo="<?php echo $modo; ?>" data-modal="<?php echo $modalId; ?>">
    <div class="plano-card">
        <div class="plano-eyebrow">Procesamiento de archivo plano</div>
        <div class="plano-title">Carga de documentación</div>

        <?php if ($modo === 'completo'): ?>
        <div>
            <div class="campo-label">
                Archivo plano detallado <span class="obligatorio">[.dat] *</span>
            </div>
            <div class="dropzone" id="dz_dat" data-target="archivo_dat">
                <input type="file" class="dz-input" id="archivo_dat" name="archivo_dat" accept=".dat,.txt" required />
                <svg class="dz-icono" viewBox="0 0 22 22" fill="none" stroke="currentColor" stroke-width="1.4">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <path d="M14 2v6h6"/>
                </svg>
                <div class="dz-texto"><strong>Arrastrá el archivo .dat</strong><br>o hacé clic para seleccionarlo</div>
                <div class="dz-lista-archivos" id="lista_dat"></div>
            </div>
            <div class="invalid-feedback" id="feedback_dat">Debés subir un archivo.</div>
        </div>
        <?php endif; ?>

        <?php if ($modo === 'completo' || $modo === 'solo_pdf'): ?>
        <div>
            <div class="campo-label" style="margin-bottom:14px;">
                Archivos OP / PLA
            </div>
            <div class="row g-2">
                <div class="col-md-6 campo-archivo">
                    <div class="campo-label"><small>Orden de trabajo · [.pdf]</small> <span class="obligatorio">*</span></div>
                    <div class="dropzone" id="dz_pdf1" data-target="archivo_pdf1">
                        <input type="file" class="dz-input archivo_plano" id="archivo_pdf1" name="archivo_pdf1" accept=".pdf" />
                        <svg class="dz-icono" viewBox="0 0 22 22" fill="none" stroke="currentColor" stroke-width="1.4">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <path d="M14 2v6h6"/>
                        </svg>
                        <div class="dz-texto"><strong>OP</strong> · subir archivo .pdf</div>
                        <div class="dz-lista-archivos" id="lista_pdf1"></div>
                    </div>
                    <input type="hidden" name="descripciones[]" value="Orden de Trabajo">
                    <input type="hidden" name="tipo[]" value="1">
                </div>

                <div class="col-md-6 campo-archivo">
                    <div class="campo-label"><small>Optimización perfil · [.pdf]</small></div>
                    <div class="dropzone" id="dz_pdf2" data-target="archivo_pdf2">
                        <input type="file" class="dz-input archivo_plano" id="archivo_pdf2" name="archivo_pdf2" accept=".pdf" />
                        <svg class="dz-icono" viewBox="0 0 22 22" fill="none" stroke="currentColor" stroke-width="1.4">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <path d="M14 2v6h6"/>
                        </svg>
                        <div class="dz-texto"><strong>PLA</strong> · subir archivo .pdf</div>
                        <div class="dz-lista-archivos" id="lista_pdf2"></div>
                    </div>
                    <input type="hidden" name="descripciones[]" value="Optimizacion Perfil">
                    <input type="hidden" name="tipo[]" value="2">
                </div>
            </div>
        </div>

        <div class="campo-archivo">
            <div class="campo-label" style="margin-bottom:14px;">
                Archivos Vidrio PLA · [.pdf] <small>(podés seleccionar varios)</small>
            </div>
            <div class="dropzone" id="dz_vidrio" data-target="archivo_plano_vidrio">
                <input type="file" class="dz-input archivo_plano" id="archivo_plano_vidrio" accept=".pdf" multiple />
                <svg class="dz-icono" viewBox="0 0 22 22" fill="none" stroke="currentColor" stroke-width="1.4">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <path d="M14 2v6h6"/>
                </svg>
                <div class="dz-texto"><strong>Vidrio PLA</strong> · arrastrá uno o más .pdf</div>
                <div class="dz-lista-archivos" id="lista_vidrio"></div>
            </div>
            <input type="hidden" name="descripciones[]" value="Optimizacion Vidrios">
            <input type="hidden" name="tipo[]" value="3">
        </div>

        <?php endif; ?>

        <?php if ($modo === 'reemplazo'): ?>
        <div>
            <div class="campo-label" style="margin-bottom:14px;">
                Archivo
            </div>
            <div class="row g-2">
                <div class="col-md-6 campo-archivo">
                    <div class="campo-label"><small>[.pdf]</small> <span class="obligatorio">*</span></div>
                    <div class="dropzone" id="dz_pdf1" data-target="archivo_pdf1">
                        <input type="file" class="dz-input archivo_plano" id="archivo_pdf1" name="archivo_pdf1" accept=".pdf" />
                        <svg class="dz-icono" viewBox="0 0 22 22" fill="none" stroke="currentColor" stroke-width="1.4">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <path d="M14 2v6h6"/>
                        </svg>
                        <div class="dz-texto"><strong>subir archivo .pdf</strong></div>
                        <div class="dz-lista-archivos" id="lista_pdf1"></div>
                    </div>
                    <input type="hidden" name="descripciones[]" value="Orden de Trabajo">
                    <input type="hidden" name="tipo[]" value="0">
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($modo === 'solo_pdf'): ?>
        <input type="hidden" name="ido" id="ido" value="<?php echo htmlspecialchars($idOrden, ENT_QUOTES); ?>">
        <?php endif; ?>

        <?php if ($modo === 'reemplazo'): ?>
        <input type="hidden" name="id" id="id">
        <!-- Bug corregido: este input no existía en uploadPdfUpdate.php, por eso
             $("#tipo_doc").val() siempre daba undefined al armar el FormData. -->
        <input type="hidden" name="tipo_doc" id="tipo_doc">
        <?php endif; ?>

        <div class="text-end mt-4">
            <button type="submit" class="btn btn-procesar" id="btn_subir">Procesar</button>
        </div>
    </div>
</form>

<script>
$(document).ready(function(){
    var $form   = $("#<?php echo $formId; ?>");
    var modo    = $form.data('modo');        // 'completo' | 'solo_pdf' | 'reemplazo'
    var modalKey = $form.data('modal');
    var modalId =  '#' + modalKey; // '#modal' | '#modal_b'
    var msgId    = 'mensaje_' + modalKey; // 'mensaje_modal' | 'mensaje_modal_b'


    $form.on('submit', function(e){
        e.preventDefault();

        if (modo === 'completo' && !window.validarArchivoDat()) {
            return;
        }

        var formData = new FormData();

        if (modo === 'completo') {
            formData.append("archivo_dat", $('#archivo_dat')[0].files[0]);
        } else if (modo === 'solo_pdf') {
            formData.append("id", $("#ido").val());
        } else if (modo === 'reemplazo') {
            formData.append("id", $("#id").val());
            formData.append("tipo", $("#tipo_doc").val());
        }

        // Recorrer todos los inputs de planos opcionales (común a los 3 modos)
        $form.find('.archivo_plano').each(function() {
            // Bug corregido: antes se buscaba '.col-md-4', que no existe;
            // el wrapper correcto es '.campo-archivo'.
            var $wrapper = $(this).closest('.campo-archivo');
            var descripcion = $wrapper.find('input[name="descripciones[]"]').val();
            var tipo = $wrapper.find('input[name="tipo[]"]').val();

            if (this.files.length > 0) {
                $.each(this.files, function(index, file) {
                    formData.append("planos[]", file);
                    formData.append("planos_descripciones[]", descripcion);
                    formData.append("planos_tipo[]", tipo);
                });
            }
        });

        var urlSubida = (modo === 'completo') ? './include/pages/fileV1.0.1.php' : './include/pages/filePdf.php';

        $.ajax({
            url: urlSubida,
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: "json",
            success: function(response){
                logger("INFO", "FILE[uploadUnificado]", "Datos: " + JSON.stringify(response));
                mostrarMensaje('⏳ Subiendo archivos...', 'blue', 10000, msgId);

                if (response.error) {
                    logger("ERROR", "FILE[uploadUnificado]", "Error: " + JSON.stringify(response));
                    mostrarMensaje("❌ Error:" + response.error, 'red', 10000, msgId);
                    return;
                }

                if (modo === 'completo') {
                    // ---- Flujo original de upload.php ----
                    // response.orden = [cabecera, aberturas, vidrios, documentos]
                    var cabecera   = response.orden[0];
                    var documentos = response.orden[3];

                    $.ajax({
                        url: './include/pages/validarPlanos.php',
                        type: 'POST',
                        data: {
                            numero: cabecera.numero,
                            dat: cabecera.dat,
                            documentos: JSON.stringify(documentos)
                        },
                        dataType: 'json',
                        success: function(validacion){
                            if (validacion.error) {
                                logger("ERROR", "FILE[uploadUnificado]", "Validación de planos falló: " + JSON.stringify(validacion));
                                mostrarMensaje("❌ " + validacion.error, 'red', 10000, msgId);
                                $(modalId).modal('hide');
                                return;
                            }

                            $.ajax({
                                url: 'api/ordenes.php',
                                type: 'POST',
                                data: {'accion': 'add', 'orden': JSON.stringify(response.orden)},
                                dataType: 'json',
                                success: function(result){
                                    if (result.error) {
                                        logger("ERROR", "FILE[uploadUnificado]", "Error. Datos: " + JSON.stringify(response.orden));
                                        mostrarMensaje("❌ " + result.error, 'red', 10000, msgId);
                                        $(modalId).modal('hide');
                                    } else if (result.resultado === 'OK') {
                                        mostrarMensaje("✅ Archivos cargados correctamente", 'green', 10000, msgId);
                                        $(modalId).modal('hide');
                                        resetFormulario();
                                        setTimeout(function() { location.reload(); }, 1000);
                                    }
                                }
                            });
                        },
                        error: function(xhr, status, error) {
                            logger("ERROR", "ORDENES", "validarPlanos.php: " + xhr.status + ', ' + xhr.responseText + ', ' + status + ', ' + error);
                            mostrarMensaje("❌ Error de conexion al validar los planos. ", 'red', 10000, msgId);
                        }
                    });

                } else if (modo === 'solo_pdf') {
                    // ---- Flujo original de uploadPdf.php ----
                    $.ajax({
                        url: 'api/documentos.php',
                        type: 'POST',
                        data: {'accion': 'add', 'datos': JSON.stringify(response.documentos)},
                        dataType: 'json',
                        success: function(result){
                            if (result.error) {
                                mostrarMensaje("❌ " + result.error, 'red', 10000, msgId);
                                // $(modalId).modal('hide');
                            } else if (result.ok === true) {
                                mostrarMensaje("✅ Archivos cargados correctamente, se generan los items", 'green', 10000, msgId);
                                logger("INFO", "UPLOADPDF", "Procesando items de " + JSON.stringify(result.documentos));

                                $.ajax({
                                    url: 'api/documentos.php',
                                    type: 'POST',
                                    data: {'accion': 'items_add', 'datos': JSON.stringify(result.documentos)},
                                    dataType: 'json',
                                    success: function(){
                                        $(modalId).modal('hide');
                                        resetFormulario();
                                        setTimeout(function() { location.reload(); }, 10000);
                                    }
                                });
                            }
                        }
                    });

                } else if (modo === 'reemplazo') {
                    // ---- Flujo original de uploadPdfUpdate.php ----
                    var docId = $("#id").val();
                    $.ajax({
                        url: 'api/documentos.php',
                        type: 'POST',
                        data: {
                            accion: 'replace',
                            id: docId,
                            nombre: response.nombre, // nombre físico devuelto por filePdf.php
                            tipo: $("#tipo_doc").val()
                        },
                        dataType: 'json',
                        success: function(result){
                            if (result.error) {
                                mostrarMensaje("❌ " + result.error, 'red', 10000, msgId);
                            } else {
                                mostrarMensaje("✅ Archivo actualizado" + (result.items_regenerados ? " y items regenerados" : ""), 'green', 10000, msgId);
                                
                                setTimeout(function(){ 
                                    $(modalId).modal('hide'); 
                                    location.reload(); 
                                }, 2000);
                            }
                        }
                    });
                }
            },
            error: function(xhr, status, error) {
                logger("ERROR", "ORDENES", "[uploadUnificado] " + xhr.status + ', ' + xhr.responseText + ', ' + status + ', ' + error);
                mostrarMensaje("❌ Error de conexion o el servidor no devolvió JSON válido. ", 'red', 10000, msgId);
            }
        });

        function resetFormulario(){
            $form.trigger("reset");
            document.querySelectorAll(".dropzone").forEach(function(dz){
                dz.classList.remove("tiene-archivo", "es-invalido");
                dz.querySelector(".dz-lista-archivos").innerHTML = "";
            });
        }
    });
});
</script>