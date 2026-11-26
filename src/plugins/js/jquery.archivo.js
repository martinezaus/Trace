(function($){

    function formatBytes(bytes){
        if(bytes < 1024) return bytes + " B";
        if(bytes < 1024*1024) return (bytes/1024).toFixed(1) + " KB";
        return (bytes/(1024*1024)).toFixed(1) + " MB";
    }

    function renderArchivos(dz, files){
        const lista = dz.querySelector(".dz-lista-archivos");
        lista.innerHTML = "";
        if(!files || files.length === 0){
            dz.classList.remove("tiene-archivo");
            return;
        }
        dz.classList.add("tiene-archivo");
        dz.classList.remove("es-invalido");
        Array.from(files).forEach((f) => {
            const item = document.createElement("div");
            item.className = "dz-archivo-item";
            item.innerHTML = `<span class="nombre">${f.name}</span><span class="text-muted">${formatBytes(f.size)}</span>`;
            lista.appendChild(item);
        });
    }

    // Validación visual del campo obligatorio .dat. Devuelve true si es válido.
    window.validarArchivoDat = function(){
        const datInput = document.getElementById("archivo_dat");
        const dzDat = document.getElementById("dz_dat");
        const feedback = document.getElementById("feedback_dat");

        if(!datInput || !dzDat){
            // El formulario no está en el DOM en este momento.
            return true;
        }

        if(!datInput.files.length){
            dzDat.classList.add("es-invalido");
            if(feedback) feedback.style.display = "block";
            return false;
        }
        dzDat.classList.remove("es-invalido");
        if(feedback) feedback.style.display = "none";
        return true;
    };

    // --- Delegación de eventos: funciona sin importar cuándo se inserta el modal en el DOM ---

    $(document).on("change", ".dz-input", function(){
        const dz = this.closest(".dropzone");
        if(dz) renderArchivos(dz, this.files);
    });

    $(document).on("dragenter dragover", ".dropzone", function(e){
        e.preventDefault();
        this.classList.add("dragover");
    });

    $(document).on("dragleave", ".dropzone", function(e){
        e.preventDefault();
        this.classList.remove("dragover");
    });

    $(document).on("drop", ".dropzone", function(e){
        e.preventDefault();
        this.classList.remove("dragover");
        const dt = e.originalEvent.dataTransfer;
        const input = this.querySelector(".dz-input");
        if(dt && dt.files && dt.files.length && input){
            input.files = dt.files;
            renderArchivos(this, input.files);
        }
    });

})(jQuery);