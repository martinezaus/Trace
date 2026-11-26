<form id="formOperador">
    <div class="container py-4">
    	<div class="main-body">
            <div class="row g-4">
                <div class="col-md-8"> 
                    <div class="row g-3"> 
                        <div class="col-md-6">
                            <input type="text" maxlength="50" id="apellido" class="form-control is-valid" required>
                            <label class="form-label" for="apellido">Apellido</label>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <input type="text" maxlength="50" id="nombre" class="form-control is-valid" required>
                            <label class="form-label" for="nombre">Nombre</label>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <input type="number" min="1" max="1" step="1" readonly id="sesiones" class="form-control is-invalid" value="1" required>
                            <label class="form-label" for="sesiones">
                                Sesiones.
                                <small class="text-muted">Actualmente se admite una sesión por operario.</small> 
                            </label>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <input type="number" min="0" max="1" step="1" id="corte" class="form-control is-invalid" value="1" required>
                            <label class="form-label" for="corte">
                                Proceso Corte.
                                <small class="text-muted">Permiso para acceder alproceso de corte. [0: permitido, 1: no permitido]</small>
                            </label>
                        </div>
                    </div>
                </div>

            <!-- Foto -->
            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-body text-center">

                        <img id="preview-foto"
                             src="img/avatar.png"
                             alt="Foto Operador"
                             class="img-fluid rounded border mb-3"
                             style="width:200px;height:240px;object-fit:cover;">

                        <label for="foto_operador" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-camera-fill"></i>
                            Seleccionar Foto
                        </label>
                        <input type="file" id="foto_operador" name="foto" accept="image/*" hidden>
                    </div>
                </div>
            </div>
        </div>
        <div class="row g-4">         
			<button type="button" class="btn btn-primary" id="btn_operario_add" name="btn_operario_add">Guardar</button>
		</div>
	</div>
</div>        


</form>