

// Definimos la clase Cronometro como global
class Cronometro {
    constructor(displayId) {
        this.display = document.getElementById(displayId);
        this.segundos = 0;
        this.cronometrar = false;
        this.tiempoRef = null;
        this.intervalo = null;
    }

    iniciar() {
        if (!this.cronometrar) {
            this.cronometrar = true;
            this.tiempoRef = Date.now();
            this.intervalo = setInterval(() => {
                if (this.cronometrar) {
                    this.segundos += Date.now() - this.tiempoRef;
                    this.tiempoRef = Date.now();
                    this.mostrar();
                }
            }, 1000 / 60); // 60 FPS
        }
    }

    pausar() {
        this.cronometrar = false;
    }

    reiniciar() {
        this.pausar();
        this.segundos = 0;
        this.mostrar();
    }

    mostrar() {
        let totalMs = this.segundos;
        let ms = Math.floor(totalMs % 1000 / 10).toString().padStart(2, '0'); // Centésimas
        let totalSegundos = Math.floor(totalMs / 1000);

        let h = Math.floor(totalSegundos / 3600).toString().padStart(2, '0');
        let m = Math.floor((totalSegundos % 3600) / 60).toString().padStart(2, '0');
        let s = (totalSegundos % 60).toString().padStart(2, '0');

        this.display.textContent = `${h}:${m}:${s}:${ms}`;
    }

    formatoHMS(ms) {
        let totalSegundos = Math.floor(ms / 1000);
        let horas = Math.floor(totalSegundos / 3600);
        let minutos = Math.floor((totalSegundos % 3600) / 60);
        let segundos = totalSegundos % 60;
        return `${String(horas).padStart(2, '0')}:${String(minutos).padStart(2, '0')}:${String(segundos).padStart(2, '0')}`;
    }


    // Obtener tiempo formateado
    obtenerTiempoFormateado() {
        return this.formatoHMS(this.segundos);
    }

}

// Lo dejamos en window para que sea accesible desde otros scripts
window.Cronometro = Cronometro;
