    <form id="formOperarioAux">
        <div class="mb-3">
            <input type="hidden" id="operador" name="operador" />
            <label class="form-label" for="operario_aux">Operador Auxiliar</label>
            <input type="text"   class="form-control" id="operario_aux" name="operario_aux" />
        </div>
        <br/>
        <div class="mb-3">
            <button type="button" class="btn btn-primary mb-3">Aceptar</button>
        </div>
        
	</form>


	<script>
		$(document).ready(function (){ 
           $('input[id=operario_aux]').on('', function(){
                var codigo = $(this).val();

                $.ajax({
                    url: "api/operadores.php",
                    type: "POST",
                    data: { "accion": "select", "token": codigo},
                    dataType: "json",
                    success: function (respuesta) {
                        $.each(respuesta, function (index, operario_aux) {
                            //console.log(operario.ayudante);
                            if(operario_aux.id != operador){
                                $("form[id=formProcesoProduccion]").find("input[id=]").text(operario_aux.nombre);
                                $('#modal').modal('hide');
                            }else{
                                //sweetalert -----        
                            }
                        });
                    },
                    error: function () {
                        //sweetalert -----
                    }
                }); 

                
           }); 

        });
    </script>    