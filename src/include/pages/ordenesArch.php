    <form id="formViewArchivoO" enctype="multipart/form-data">
        <div>
            <input type="hidden" id="ido" name="ido">
            <input type="hidden" id="tipo" name="tipo">
            <iframe id="iframe_arc" frameborder="0" scrolling="yes" width="100%" height="500px"></iframe>
        </div>
        <div>
            <button type="button" class="btn btn-primary" id="btn_salir">Salir</button>
        </div>        
    </form>  

    <script>
        
        var id=$("#ido").val();
        var tipo=$("#tipo").val();
        console.log(id+" "+tipo);

        $.ajax({url: "api/ordenes.php", 
            type: "POST", 
            data: { 'accion': 'archive', 'id': id, 'tipo': tipo},
            success: function(request) {
                //console.log(request);
                //$("iframe[id=view_arc]").src = request; //Sacar el nombre del fichero pdf desde el parametro
                $("#iframe_arc").attr('src', "./tmp/"+request); /**Abrir archivo pdf */
            }
        });
         
        
        
        $("#btn_salir").on('click',function(){
            $("#modal").modal('hide');
        });

    </script>