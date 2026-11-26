
const menu1 = [];
const menu2 = [];
let buffer = ""; // Acumula caracteres del lector
let timeout = null;    
let currentMenu = 1;
let  titlecurrentMenu = "Perfiles";

$(document).ready(() => {
    $('#menuTitle').text("Menu Tareas " + titlecurrentMenu + ' - QR');
    initializeDataTarea();
});

function initializeDataTarea(){
   
    // Escuchar teclas
    document.addEventListener("keydown", function(e) {
        // Cuando llega Enter (fin del código)
        if (e.key === "Enter") {
            if (buffer.length > 0) {
                $("#info")
                    .removeClass("alert-secondary")
                    .addClass("alert-info")
                    .html(`<strong>Código recibido:</strong> ${buffer}`);
                buffer = "";
            }
            e.preventDefault();
            return;
        }

        // Evitar teclas especiales
        if (e.key.length === 1) buffer += e.key;

        // Reset si el lector tarda demasiado
        clearTimeout(timeout);
        timeout = setTimeout(() => buffer = "", 200);
    });
    
    $('#switchMenu').click(() => {
        currentMenu = (currentMenu === 1) ? 2 : 1;
        titlecurrentMenu = (currentMenu === 1) ? "Perfiles" : "Vidrios";
        localStorage.setItem("tipoProceso",(currentMenu)); // 👈 persistencia
        $('#menuTitle').text(`Menú Tareas ${ titlecurrentMenu} - QR`);
        if (currentMenu === 1) {
            cargarTareasArmado();
        } else {
            cargarTareasVidrio();
        }
        //renderMenu(currentMenu === 1 ? menu1 : menu2);
    });

    window.addEventListener("resize", autoResizeQR);

    $('#qrGrid').on('click', '.qr-item', function(){
        const id = $(this).data('id');
        const data = (currentMenu === 1 ? menu1.find(m => m.id === id) : menu2.find(m => m.id === id));
        $('#info')
            .removeClass('alert-secondary')
            .addClass('alert-success')
            .html(`<strong>${data.label}</strong> seleccionado.<br>Dato escaneable: ${data.img}`);
    });    

    // Arranca cargando menú 1
    cargarTareasArmado();

}

/** Tareas de armado de metal aluminio */
function cargarTareasArmado(){
    menu1.length = 0; // limpiar
    $.ajax({
        url: "././api/tareas.php",
        type: "POST",
        data: { accion: "load", opcion: 0},
        dataType: "json",
        success: function (respuesta) {
            //console.log(respuesta);
            respuesta.forEach(tarea => {
                menu1.push({
                    id: tarea.codigo,
                    label: tarea.nombre,
                    img: (tarea.codigo === "99" ? tarea.barcode2d : tarea.barcode2d)
                });
            });

            //console.log(menu1);
            renderMenu(menu1);
        },
        error: function(xhr, status, error) {
            logger("ERROR","TAREAS","Error al cargar tareas (aluminio): " + error);
        }
    });
}

/** Tareas de armado y colocacion de vidrio */
function cargarTareasVidrio(){
    menu2.length = 0; // limpiar
    $.ajax({
        url: "././api/tareas.php",
        type: "POST",
        data: { accion: "load", opcion: 1},
        dataType: "json",
        success: function (respuesta) {
            //console.log(respuesta);
            respuesta.forEach(tarea => {
                menu2.push({
                    id: tarea.codigo,
                    label: tarea.nombre,
                    img: (tarea.codigo === "99" ? tarea.barcode2d : tarea.barcode2d)
                });
            });
            
            renderMenu(menu2);
        },
        error: function(xhr, status, error) {
            logger("ERROR","TAREAS","Error al cargar tareas (vidrio): " + error);
        }
    });
}

/** TERMINAR -----> Tareas de armado Mosquitero */
function cargarTareasMosquitero(){
    menu2.length = 0; // limpiar
    $.ajax({
        url: "././api/tareas.php",
        type: "POST",
        data: { accion: "load", opcion: 2},
        dataType: "json",
        success: function (respuesta) {
            //console.log(respuesta);
            respuesta.forEach(tarea => {
                menu2.push({
                    id: tarea.codigo,
                    label: tarea.nombre,
                    img: (tarea.codigo === "99" ? tarea.barcode2d :tarea.barcode2d)
                });
            });
            renderMenu(menu2);
        },
        error: function(xhr, status, error) {
            logger("ERROR","TAREAS","Error al cargar tareas (vidrio): " + error);
        }
    });
}