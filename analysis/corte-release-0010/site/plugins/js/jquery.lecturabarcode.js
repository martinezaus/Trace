(function(global) {
    global.initBarcodeScanner = function (callback) {
        let barcodeBuffer = ''; // Buffer para acumular caracteres del escaneo
        let lastKeyTime = Date.now(); // Para detectar secuencia rápida
        //let timeout; // Para procesar después de un delay si no hay "Enter"

        $(document).on('keydown', function(e) {
            var currentTime = Date.now();
            if (currentTime - lastKeyTime > 50) { // Reset if input is too slow (not a scan)
                barcodeBuffer = '';
            }
            lastKeyTime = currentTime;
    
            if (e.keyCode === 13) { // Enter key signals end of scan
                const cleanBarcode = barcodeBuffer.replace(/Shift/g, '');
                if (cleanBarcode) {
                    callback(cleanBarcode); // Llama al callback con el code acumulado
                }
                barcodeBuffer = '';
            } else if (e.key !== 'Shift') { // Ignora la tecla Shift
                barcodeBuffer += e.key;
            }

            //Esta linea me bloquea el teclado para escribir - VER SI LA SACAMOS O LA DEJAMOS.
            //e.preventDefault(); // Prevent any typing in inputs or elsewhere
        });
    }
})(window);