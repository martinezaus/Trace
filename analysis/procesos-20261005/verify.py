import json,subprocess,sys
from pathlib import Path
out=Path('analysis/procesos-20261005');results=[]
commands=[
 ['node','src/tests/procesos_frontend_test.cjs'],
 ['docker','exec','trace-php-1','php','/var/www/html/tests/procesos_service_test.php'],
 ['docker','exec','trace-php-1','php','/var/www/html/tests/procesos_mysql_test.php'],
 ['node','src/tests/procesos_browser_test.cjs'],
 ['docker','exec','trace-php-1','php','/var/www/html/tests/reportes_test.php'],
 ['docker','exec','trace-php-1','php','/var/www/html/tests/codigos_vidrio_pla_test.php'],
]
for f in ['api/procesos.php','api/sesiones.php','include/proceso_service.php','include/footer.php','scripts/migrar_eventos_descartados.php','tests/procesos_service_test.php','tests/procesos_mysql_test.php']:
 commands.append(['docker','exec','trace-php-1','php','-l','/var/www/html/'+f])
for f in ['jquery.procesoV1.0.3.js','jquery.procesopausaV1.0.3.js','jquery.procesosuspenderV1.0.3.js','jquery.corte_pdf.js','jquery.corte_documentos.js']:
 commands.append(['node','--check','src/plugins/js/'+f])
for cmd in commands:
 r=subprocess.run(cmd,capture_output=True,encoding='utf-8',errors='replace',timeout=90)
 results.append({'command':cmd,'exit_code':r.returncode,'stdout':r.stdout,'stderr':r.stderr})
 print(('PASS ' if r.returncode==0 else 'FAIL ')+' '.join(cmd),flush=True)
 if r.returncode:print(r.stdout+r.stderr,flush=True)
(out/'verification.json').write_text(json.dumps(results,ensure_ascii=False,indent=2),encoding='utf-8')
(out/'verification.log').write_text('\n\n'.join(' '.join(r['command'])+'\n'+r['stdout']+r['stderr'] for r in results),encoding='utf-8')
print(f'{sum(r["exit_code"]==0 for r in results)}/{len(results)} comandos correctos')
sys.exit(any(r['exit_code'] for r in results))
