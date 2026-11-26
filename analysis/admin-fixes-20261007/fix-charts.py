from pathlib import Path
import json
p=Path('src/admin.php');s=p.read_text(encoding='utf-8');s=s.replace('        // Inicializa el gráfico\n        inicializarGrafico();','        // La instancia se crea una sola vez al estar lista la página.')
s=s.replace("        let chart;", "        let chart, actualizandoGrafico=false;")
s=s.replace("            chart = new Chart(ctx, {", "            if(chart)chart.destroy();\n            chart = new Chart(ctx, {")
s=s.replace("plugins: {\n                  title: { display: true, text: 'Ordenes por Mes/Año (Cargadas/Procesando/Terminadas/Instaladas)'}\n                },", "title: { display: true, text: 'Órdenes por mes/año: estados actuales' },")
s=s.replace("x: { ticks: { autoSkip: true, maxRotation: 0, minRotation: 0 } },\n                  y: { beginAtZero: true }", "xAxes: [{ ticks: { autoSkip: true, maxRotation: 0, minRotation: 0 } }],\n                  yAxes: [{ ticks: { beginAtZero: true, precision: 0 } }]")
s=s.replace('        async function actualizarGrafico() {', '        async function actualizarGrafico() {\n            if(!chart || actualizandoGrafico)return;actualizandoGrafico=true;\n            try {')
s=s.replace('            chart.update();\n        }','            chart.update();\n            }catch(e){console.error(e);}finally{actualizandoGrafico=false;}\n        }')
s=s.replace('            inicializarGrafico();',"            inicializarGrafico().catch(e=>mostrarMensaje('No se pudo cargar el gráfico. Actualice la página.','red',10000));")
p.write_text(s,encoding='utf-8')
lang={'sEmptyTable':'No hay datos disponibles','sInfo':'Mostrando _START_ a _END_ de _TOTAL_ registros','sInfoEmpty':'Sin registros','sInfoFiltered':'(de _MAX_ registros)','sLengthMenu':'Mostrar _MENU_ registros','sLoadingRecords':'Cargando...','sProcessing':'Procesando...','sSearch':'Buscar:','sZeroRecords':'No se encontraron coincidencias','oPaginate':{'sFirst':'Primera','sLast':'Última','sNext':'Siguiente','sPrevious':'Anterior'}}
Path('src/plugins/js/es-ES.json').write_text(json.dumps(lang,ensure_ascii=False,indent=2),encoding='utf-8')
for f in ['src/plugins/datatables/datatables.min.css','src/plugins/datatables/datatables.css']:
 p=Path(f);s=p.read_text(encoding='utf-8');s=s.replace('DataTables-1.10.16/images/','images/');p.write_text(s,encoding='utf-8')
# Corregir pie sin depender de dónde cerró wrapper el template.
p=Path('src/css/footer.css');s=p.read_text(encoding='utf-8');s+='\nbody.admin-page .footer { position:static; margin:20px 20px 12px 280px; clear:both; }\nbody.admin-page .main-panel { height:auto !important; min-height:100vh; }\n@media(max-width:991px){body.admin-page .footer{margin:16px;}}\n';p.write_text(s,encoding='utf-8')
