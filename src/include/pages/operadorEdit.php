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
                        <div class="form-check">
                            <label class="form-check-label" for="sesiones">
                                <input type="checkbox" id="sesiones">
                                <span class="form-check-sign"></span>
                                Sesiones
                            </label>
                        </div>
                        <small class="text-muted">Desmarcado: 1 sesión. Marcado: 2 sesiones (el límite simultáneo aún no está implementado).</small>
                    </div>
                </div>
                <div class="row g-3">     
                    <div class="col-md-6">
                        <div class="form-check">
                            <label class="form-check-label" for="corte">
                                <input type="checkbox" id="corte">
                                <span class="form-check-sign"></span>
                                Proceso de corte
                            </label>
                        </div>
                        <small class="text-muted">Permite ingresar al sector Corte.</small>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-check">
                            <label class="form-check-label" for="revisador">
                                <input type="checkbox" id="revisador">
                                <span class="form-check-sign"></span>
                                Revisador
                            </label>
                        </div>
                        <small class="text-muted">Permite ingresar al sector Revisión.</small>
                    </div>
                </div>                
			</div>
            <!-- Foto -->
            <div class="col-md-4">
                <div class="card shadow-sm operator-photo-card">
                    <div class="card-body text-center">

                        <img id="preview-foto"
                             src="img/avatar.png"
                             alt="Foto Operador"
                             class="img-fluid rounded border mb-3 operator-photo-preview">

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



