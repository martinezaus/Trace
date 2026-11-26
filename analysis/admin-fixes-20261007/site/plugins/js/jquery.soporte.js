$(document).ready(() => {
    
    setTimeout(() => $("#passw").focus(), 0);

    $("#btn_aceptar_sys").on("click",function(){
        var pass=$("#passw").val();

        //compruebo la clave maestra para poder ingresar.
        $.ajax({url: "api/system.php",
                type: "POST",
                data: { 'accion': 'logueo', 'clave': pass},
                success: function(respuesta) {
                    console.log("resp.: " + respuesta);
                    if(respuesta==0){
                        //TERMINAR
                        location.href='./soporte/index.php';
                    }else{
                        logger("ERROR", "SOPORTE", "ACCESO DENEGADO. Clave ingresada: "+pass);
                        $('#mensaje').html("❌ Acceso denegado").css('color', 'red');
                    }
                } 								
        });
    });

    $("#btn_cerrar_sys").on('click',function(){
        $('#modal').modal('hide');
    });
});
