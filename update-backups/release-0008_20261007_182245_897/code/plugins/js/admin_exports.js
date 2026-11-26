/* Exporta todas las filas filtradas y ordenadas; no necesita DataTables Buttons. */
function adminExportButtons(table, selector, columns, title){
    const panel=$('<div class="admin-exports mb-2" role="group" aria-label="Exportar tabla"></div>');
    $(selector).closest('.dataTables_wrapper').before(panel);
    const plain=v=>$('<div>').html(String(v??'')).text().trim();
    const headers=columns.map(i=>plain(table.column(i).header().textContent));
    const rows=()=>table.rows({search:'applied',order:'applied'}).data().toArray().map(row=>columns.map(i=>plain(row[i])));
    const escape=v=>String(v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const safe=v=>/^[\s]*[=+\-@]/.test(v)?"'"+v:v;
    const download=(blob,name)=>{const url=URL.createObjectURL(blob),a=document.createElement('a');a.href=url;a.download=name;a.click();setTimeout(()=>URL.revokeObjectURL(url),30000);};
    const button=(label,fn)=>$('<button type="button" class="btn btn-sm btn-outline-primary"></button>').text(label).on('click',async function(){this.disabled=true;try{await fn();}catch(e){mostrarMensaje(e.message||'No se pudo exportar.','red',10000);}finally{this.disabled=false;}}).appendTo(panel);
    button('Copiar',async()=>{const text=[headers,...rows()].map(r=>r.join('\t')).join('\n');if(navigator.clipboard && window.isSecureContext){await navigator.clipboard.writeText(text);}else{const input=document.createElement('textarea');input.value=text;input.style.position='fixed';input.style.opacity='0';document.body.appendChild(input);try{input.select();if(!document.execCommand('copy'))throw new Error('El navegador no permite copiar. Utilice CSV.');}finally{input.remove();}}mostrarMensaje('Tabla copiada.','green',5000);});
    button('CSV (Excel)',()=>download(new Blob(['\ufeff'+[headers,...rows()].map(r=>r.map(v=>'"'+safe(v).replace(/"/g,'""')+'"').join(';')).join('\r\n')],{type:'text/csv;charset=utf-8'}),title+'.csv'));
    button('PDF',async()=>{const r=await fetch('include/pages/admin_export.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf:document.getElementById('adminExportCsrf').value,title,headers,rows:rows()})});if(!r.ok){const e=await r.json();throw new Error(e.mensaje||'No se pudo generar el PDF.');}const blob=await r.blob();if(await blob.slice(0,5).text()!=='%PDF-')throw new Error('PDF inválido.');download(blob,title+'.pdf');});
    button('Imprimir',()=>{const w=window.open('about:blank','_blank');if(!w)throw new Error('Habilite las ventanas emergentes para imprimir.');w.opener=null;w.document.write('<!doctype html><meta charset="utf-8"><title>'+escape(title)+'</title><style>body{font-family:Arial}table{border-collapse:collapse;width:100%}td,th{border:1px solid #aaa;padding:6px}thead{display:table-header-group}</style><h1>'+escape(title)+'</h1><table><thead><tr>'+headers.map(v=>'<th>'+escape(v)+'</th>').join('')+'</tr></thead><tbody>'+rows().map(r=>'<tr>'+r.map(v=>'<td>'+escape(v)+'</td>').join('')+'</tr>').join('')+'</tbody></table>');w.document.close();w.focus();w.print();});
}
