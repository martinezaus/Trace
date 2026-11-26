function dosDigitos(n) {
    return n < 10 ? '0' + n : n;
}

function formatearFecha(fecha) {
    const date = new Date(fecha);

    const dia = dosDigitos(date.getDate());
    const mes = dosDigitos(date.getMonth() + 1); // Los meses van de 0 a 11
    const anio = date.getFullYear();

    const horas = dosDigitos(date.getHours());
    const minutos = dosDigitos(date.getMinutes());
    const segundos = dosDigitos(date.getSeconds());

    // Opcional: agrega ceros a la izquierda si deseas 2 dígitos
    const formato = dia+"-"+mes+"-"+anio+" "+horas+":"+minutos+":"+segundos;
    return formato;
}

function FechaTime(fecha, tipo) {
    const date = new Date(fecha);

    const dia = dosDigitos(date.getDate());
    const mes = dosDigitos(date.getMonth() + 1); // Los meses van de 0 a 11
    const anio = date.getFullYear();

    // Opcional: para fecha de inicio o fecha de fin
    if(tipo === 'i'){
        const formato = anio+"-"+mes+"-"+dia+" 00:00:01";
        return formato;
    }else{
        const formato = anio+"-"+mes+"-"+dia+" 23:59:59";
        return formato;
    }
    
}