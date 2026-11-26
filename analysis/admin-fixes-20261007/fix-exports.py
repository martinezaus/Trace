from pathlib import Path
p=Path('src/include/pages/admin_export.php');p.write_text(p.read_text(encoding='utf-8-sig'),encoding='utf-8')
p=Path('src/include/header.php');s=p.read_text(encoding='utf-8');s=s.replace('// Título según contexto',"if ($headerType === 'admin') {\n    require_once __DIR__.'/auth.php';\n    if(empty($_SESSION['admin_export_csrf']))$_SESSION['admin_export_csrf']=bin2hex(random_bytes(32));\n}\n\n// Título según contexto")
s=s.replace('<body class="">','<body class="<?= $headerType === \'admin\' ? \'admin-page\' : \'\' ?>">\n<?php if($headerType === \'admin\'): ?><input type="hidden" id="adminExportCsrf" value="<?= htmlspecialchars($_SESSION[\'admin_export_csrf\'],ENT_QUOTES,\'UTF-8\') ?>"><?php endif; ?>');p.write_text(s,encoding='utf-8')
p=Path('src/include/footer.php');s=p.read_text(encoding='utf-8');s=s.replace('<script src="<?= $baseUrl ?>plugins/js/jquery.ordenes.js', '<script src="<?= $baseUrl ?>plugins/js/admin_exports.js?v=0008-r2"></script>\n            <script src="<?= $baseUrl ?>plugins/js/jquery.ordenes.js')
import re
for file in ['jquery.ordenes.js','jquery.operarios.js','jquery.grafico.js','jquery.archivo.js']:
 s=re.sub(r'('+re.escape(file)+r')(?:\?[^"\s]*)?',r'\1?v=0008-r2',s)
p.write_text(s,encoding='utf-8')
for f,table,cols,title in [('jquery.ordenes.js','tablaOrdenes','[0,1,2,3]','Órdenes'),('jquery.operarios.js','tablaOperarios','[0,1,2,3]','Operarios')]:
 p=Path('src/plugins/js')/f;s=p.read_text(encoding='utf-8');s=re.sub(r"buttons: \[[^\n]+",'',s);s=s.replace("dom: 'Bfrtip'","dom: 'lfrtip'")
 marker='function cargarOrdenes' if table=='tablaOrdenes' else 'function cargarOperadores';b=s.index(marker);a=s.rfind('}',0,b);selector='#tabla_ordenes' if table=='tablaOrdenes' else '#tabla_operarios';s=s[:a]+f"    adminExportButtons({table},'{selector}',{cols},'{title}');\n"+s[a:];p.write_text(s,encoding='utf-8')
