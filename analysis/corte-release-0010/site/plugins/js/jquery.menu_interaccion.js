
	$('#tablero').on('click',function(){
		location.href='admin.php';
	});

	$('#ordenes').on('click',function(){
		location.href='ordenes.php';
	});    
   
	$('#operarios').on('click',function(){
		location.href='operarios.php';
	});

	$('#reportes').on('click',function(){
		location.href='reportes.php';
	});

	$('#estadisticas').on('click',function(){
		location.href='estadisticas.php';
	});

	// sub-menu
	$('#soporte').on('click',function(){
        location.href='modules/soporte/index.php';
	});   
	
	$('#ayuda').on('click',function(){
        const link = document.createElement('a');
        link.href = './include/ayuda.php';
        link.download = 'MANUAL_ADMIN.md';
    
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
	});   

	// sub-menu -> token
	$('#token').on('click',function(){
		// Carga códigos de barra iniciales
       
		$.post("./api/system.php", { accion: 'select', id: 1 }, function(respuesta) {
			$.each(respuesta,function(index, data){
				//console.log(data.master);

				let ventana = window.open('', '', 'height=800,width=600');
				let src = data.master.startsWith("data:image") ? data.master : "data:image/gif;base64," + data.master;
				let etiqueta = `<div style="border:1px solid #000; padding:20px; text-align:center; width:300px; margin:auto;">
									<img src="${src}" style="max-width:100%; height:auto;" />
									<div style="margin-top:10px; font-size:14px; font-weight:bold;">
										${data.leyenda}
									</div>
								</div>`;

                ventana.document.write('<html><head><title>Orden</title>');
                ventana.document.write('<style>body{margin:0;font-family:Arial;} div{margin:auto;}</style>');
                ventana.document.write('</head><body>');
                ventana.document.write(etiqueta);
                ventana.document.write(`</body></html>`);
                ventana.document.close();
                ventana.focus();
                ventana.onload = function() { setTimeout(() => {ventana.print(); ventana.close(); }, 300); };
			});
		}, "json");
        
	});   

	$('#licencia').on('click',function(){
        $('#modal').modal('dispose');
		$(".modal-title").html('<img src="img/logo.png" width="300px"></img>' + 'Términos y Condiciones');
        $(".modal-body").css({
            "max-height": "60vh",
            "overflow-y": "auto"
        });
		$(".modal-body").load("./include/terms.php",function(){
			$('#modal').modal('toggle');				   
		});                
	});    

    $('#backups').on('click', function(){
        if ($('#backupOverlay').length) return;
        // Bloquear UI mientras corre el respaldo
        let overlayId = 'backupOverlay';
        if ($('#' + overlayId).length === 0) {
            $('body').append(`
                <div id="${overlayId}" style="position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:99999;display:flex;align-items:center;justify-content:center;">
                    <div style="background:#fff;padding:18px;border-radius:8px;min-width:420px;">
                        <div style="font-weight:700;margin-bottom:8px;">Generando respaldo... <span id="backupStage"></span></div>
                        <div class="progress" style="height:18px;">
                            <div id="backupBar" class="progress-bar" role="progressbar" style="width:0%;">0%</div>
                        </div>
                        <div id="backupMsg" style="margin-top:10px;color:#555;font-size:13px;">Por favor espere.</div>
                    </div>
                </div>
            `);
        }

        $('#backupBar').css('width', '0%').text('0%');
        $('#backupMsg').text('Preparando...');
        $('#backupStage').text('');
        $('#backups').prop('disabled', true);

        $.ajax({url: "./include/backup.php", method: "POST", dataType: "json", timeout: 15000}).done(function(request){

            if (request.status !== 'ok') {
                logger("ERROR", "BACKUPS", 'Error al iniciar respaldo. '+ request.mensaje);
                $('#backupMsg').text('❌ ' + (request.mensaje || 'Error al iniciar respaldo'));
                $('#' + overlayId).remove();
                $('#backups').prop('disabled', false);
                return;
            }

            const jobId = request.jobId;
            let pollFailures = 0;
            const pollUrl = "./include/backup_status.php?jobId=" + encodeURIComponent(jobId);

            const timer = setInterval(function(){
                $.ajax({url: pollUrl, dataType: "json", cache: false, timeout: 10000}).done(function(st){
                    pollFailures = 0;
                    if (!st) return;
                    const progress = st.progress ?? 0;
                    const stage = st.stage ?? '';
                    $('#backupStage').text(stage ? '(' + stage + ')' : '');
                    $('#backupBar').css('width', progress + '%').text(progress + '%');
                    $('#backupMsg').text(st.error ? '❌ ' + st.error : 'Procesando...');

                    // Si hay error en el estado, detener el polling y mostrarlo ya.
                    if (st.error || st.status === 'error') {
                        clearInterval(timer);
                        $('#' + overlayId).remove();
                        $('#backups').prop('disabled', false);
                        $("#mensaje").text("❌ " + (st.error || st.mensaje));
                        logger("ERROR", "BACKUPS", "Error de respaldo: " + st.error);
                        return;
                    }

                    if (st.done === true || st.status === 'ok') {
                        clearInterval(timer);
                        $('#' + overlayId).remove();
                        $('#backups').prop('disabled', false);
                    
                        // Disparar descarga automática en la PC del usuario
                        const link = document.createElement('a');
                        link.href = './include/backup_download.php?jobId=' + encodeURIComponent(jobId);
                        link.download = '';        // el servidor pone el nombre real via Content-Disposition
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                    
                        $("#mensaje").html("✅ Respaldo creado. La descarga comenzó automáticamente.");
                        logger("INFO", "BACKUPS", 'Respaldo descargado. ' + stage);

                        setTimeout(() => {
                            sendMail('backup creado.');
                            $("#mensaje").html("");
                        }, 15000);
                    }
                }).fail(function(){
                    if (++pollFailures >= 3) {
                        clearInterval(timer);
                        $('#' + overlayId).remove();
                        $('#backups').prop('disabled', false);
                        $('#mensaje').text('No se pudo consultar el respaldo. Compruebe la conexión.');
                    }
                });
            }, 1000);
        }).fail(function(xhr){
            $('#' + overlayId).remove();
            $('#backups').prop('disabled', false);
            $('#mensaje').text(xhr.responseJSON?.mensaje || 'No se pudo iniciar el respaldo. Compruebe la conexión.');
        });
    });
