// Siempre consultar al generar la salida, incluso si la pantalla lleva tiempo abierta.
async function obtenerLogoNegocio() {
    const response = await fetch('api/negocio_logo.php', {cache: 'no-store'});
    const data = await response.json();
    if (!response.ok || !data.ok) throw new Error(data.error || 'No se pudo obtener el logo del negocio.');
    return data.logo;
}

function botonExportacionConLogo(tipo) {
    let logoNegocio = '';
    return {
        extend: tipo,
        action: async function (...args) {

            try {
                logoNegocio = await obtenerLogoNegocio();
                $.fn.dataTable.ext.buttons[tipo].action.apply(this, args);
            } catch (error) {
                window.alert(error.message || 'No se pudo preparar el reporte.');
                if (typeof args[4] === 'function') args[4]();
            }
        },
        customize: tipo === 'pdfHtml5' ? function (doc, config) {
            if (logoNegocio) doc.content.unshift({image: logoNegocio, fit: [150, 65], margin: [0, 0, 0, 12]});
        } : function (win, config) {
            if (!logoNegocio) return;
            const logo = win.document.createElement('img');
            logo.src = logoNegocio;
            logo.alt = 'Logo del negocio';
            logo.style.cssText = 'max-width:150px;max-height:65px;object-fit:contain;display:block;margin-bottom:12px';
            win.document.body.prepend(logo);
        }
    };
}
