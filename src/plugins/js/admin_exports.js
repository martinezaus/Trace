/* Exporta todas las filas filtradas y ordenadas; no necesita DataTables Buttons. */
function adminExportButtons(table, selector, columns, title, containerSelector=null){
    const panel=$('<div class="admin-exports"></div>')
        .attr('role','group')
        .attr('aria-label','Exportar tabla')
        .toggleClass('btn-toolbar',Boolean(containerSelector))
        .toggleClass('mb-2',!containerSelector);
    if(containerSelector){
        $(containerSelector).append(panel);
    }else{
        $(selector).closest('.dataTables_wrapper').before(panel);
    }
    const plain=v=>$('<div>').html(String(v??'')).text().trim();
    const headers=columns.map(i=>plain(table.column(i).header().textContent));
    const rows=()=>table.rows({search:'applied',order:'applied'}).data().toArray().map(row=>columns.map(i=>plain(row[i])));
    const escape=v=>String(v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const safe=v=>/^[\s]*[=+\-@]/.test(v)?"'"+v:v;
    const download=(blob,name)=>{const url=URL.createObjectURL(blob),a=document.createElement('a');a.href=url;a.download=name;a.click();setTimeout(()=>URL.revokeObjectURL(url),30000);};
    const buttonClass=containerSelector?'btn btn-sm btn-light text-primary':'btn btn-sm btn-outline-primary';
    const button=(label,fn)=>$(`<button type="button" class="${buttonClass}"></button>`).text(label).on('click',async function(){this.disabled=true;try{await fn();}catch(e){mostrarMensaje(e.message||'No se pudo exportar.','red',10000);}finally{this.disabled=false;}}).appendTo(panel);
    button('CSV (Excel)',()=>download(new Blob(['\ufeff'+[headers,...rows()].map(r=>r.map(v=>'"'+safe(v).replace(/"/g,'""')+'"').join(';')).join('\r\n')],{type:'text/csv;charset=utf-8'}),title+'.csv'));
    button('PDF',async()=>{const r=await fetch('include/pages/admin_export.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf:document.getElementById('adminExportCsrf').value,title,headers,rows:rows()})});if(!r.ok){const e=await r.json();throw new Error(e.mensaje||'No se pudo generar el PDF.');}const blob=await r.blob();if(await blob.slice(0,5).text()!=='%PDF-')throw new Error('PDF inválido.');download(blob,title+'.pdf');});
    button('Imprimir',async()=>{
        // Abrir durante el clic evita el bloqueo de ventanas tras esperar la red.
        const w=window.open('about:blank','_blank');
        if(!w)throw new Error('Habilite las ventanas emergentes para imprimir.');
        w.opener=null;
        try {
            const logo=await obtenerLogoNegocio();
            if(w.closed)throw new Error('La ventana de impresión se cerró.');
            w.document.write('<!doctype html><html><head><meta charset="utf-8"><title>'+escape(title)+'</title><style>body{font-family:Arial}table{border-collapse:collapse;width:100%}td,th{border:1px solid #aaa;padding:6px}thead{display:table-header-group}tr{break-inside:avoid}.negocio-logo{max-width:150px;max-height:65px;object-fit:contain;display:block;margin-bottom:12px}</style></head><body>'+(logo?'<img class="negocio-logo" src="'+escape(logo)+'" alt="Logo del negocio">':'')+'<h1>'+escape(title)+'</h1><table><thead><tr>'+headers.map(v=>'<th>'+escape(v)+'</th>').join('')+'</tr></thead><tbody>'+rows().map(r=>'<tr>'+r.map(v=>'<td>'+escape(v)+'</td>').join('')+'</tr>').join('')+'</tbody></table></body></html>');
            w.document.close();
            const image=w.document.querySelector('.negocio-logo');
            if(image)await image.decode();
            w.focus();w.print();
        }catch(error){if(!w.closed)w.close();throw error;}
    });
}
