<form id="formEditarOperador">

<div class="container py-4">
	<div class="main-body">
		<div class="row g-4">
			<div class="col-md-8">            
                <div class="row g-3">     
                    <div class="col-md-6">
                        <input type="text" class="form-control is-invalid" id="nombre" name="nombre" maxlength="50" required>
                        <small class="text-muted"><label class="form-label">Apellido, Nombre</label></small>
                    </div>
                </div>
                <div class="row g-3">     
                    <div class="col-md-6">
                        <input type="number" class="form-control is-invalid" id="sesion" name="sesion" min="1" max="1" value="1" readonly required>
                        <small class="text-muted">
                            <label class="form-label">Sesiones</label>. 
                            Actualmente se admite una sesión por operario.
                        </small>
                    </div>
                </div>
                <div class="row g-3">     
                    <div class="col-md-6">
                        <input type="number" class="form-control is-invalid" id="corte" name="corte" min="0" max="1" step="1" required>
                        <small class="text-muted">
                            <label class="form-label">Proceso Corte</label>. 
                            Permiso: [0: permitido, 1: no permitido]
                        </small>
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
			<button type="button" class="btn btn-primary" id="btn_operario_edit" name="btn_operario_edit">Guardar</button>
		</div>
	</div>
</div>        
</form>



