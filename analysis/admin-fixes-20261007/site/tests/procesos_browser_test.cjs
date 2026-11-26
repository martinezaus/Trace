const fs=require('node:fs'),assert=require('node:assert/strict');
const path=require('node:path'),os=require('node:os');
const {chromium}=(()=>{try{return require('playwright')}catch{return require(path.join(process.env.TRACE_NODE_MODULES||path.join(os.homedir(),'.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules'),'playwright'))}})();
const read=p=>fs.readFileSync('src/'+p,'utf8');
// Emular la ráfaga del lector en un mismo turno del navegador. CDP por carácter
// puede superar los 50 ms del lector cuando Windows está ocupado.
const scan=(page,code)=>page.evaluate(code=>{
 for(const key of code)document.dispatchEvent(new KeyboardEvent('keydown',{key,keyCode:key.charCodeAt(0),which:key.charCodeAt(0),bubbles:true}));
 document.dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',keyCode:13,which:13,bubbles:true}));
},code);
(async()=>{
 const browser=await chromium.launch({channel:'msedge',headless:true});let checks=0;
 const check=(v,s)=>{assert.ok(v,s);console.log('PASS '+s);checks++};
 try {
  const p=await browser.newPage();const errors=[];p.on('pageerror',e=>(errors.push(e.message),console.log('BROWSER ERROR '+e.message)));
  await p.route('http://trace.test/**',async route=>{const u=new URL(route.request().url());if(u.pathname==='/'){await route.fulfill({contentType:'text/html',body:'<html><head><script>const OPERADOR_ID=1;</script></head><body><div id="mensaje"></div><div id="timerO"></div><div id="timerT"></div><div id="timerP"></div><div id="tarea_nombre"></div><div id="label_tpo_operario"></div></body></html>'});return;}
   const d=new URLSearchParams(route.request().postData()||'');let r=[];
   if(u.pathname.endsWith('tareas.php'))r=[{id:7,nombre:'Mecanizado manual',codigo:'T01'}];
   else if(d.get('accion')==='add')r={id:42,tiempo_ms:0};
   else if(d.get('accion')==='update')r=[{id:42,status:'ok',descartada:Number(d.get('tiempo'))<=60000,tiempo_ms:Number(d.get('tiempo'))}];
   await route.fulfill({json:r});
  });
  await p.goto('http://trace.test/');await p.addScriptTag({content:read('plugins/js/jquery.min_3.6.0.js')});await p.evaluate(()=>$.holdReady(true));
  for(const f of ['jquery.functions.js','jquery.cronometro.js','jquery.procesoV1.0.3.js','jquery.lecturabarcode.js'])await p.addScriptTag({content:read('plugins/js/'+f)});
  await p.evaluate(()=>{logger=()=>{};sendMail=()=>{};mostrarMensaje=m=>$('#mensaje').text(m);Object.assign(estado,{ordenId:1,ordenCodigo:'15048',detalleActualId:3,tareaMax:8,operadorId:1});initBarcodeScanner(onCodeScanned)});
  await scan(p,'T01');await p.waitForFunction(()=>estado.tareaEnCurso&&!estado.transicionActiva);
  check(await p.locator('#tarea_nombre').innerText()==='Mecanizado manual (1)','Lector real inicia y muestra tarea');
  await p.evaluate(()=>{estado.inicioTareaTs=Date.now()-90000;iniciarTimer(estado.inicioTareaTs,$('#timerT'),0)});
  await scan(p,'15048');
  check(await p.evaluate(()=>estado.tareaEnCurso&&estado.procesoId===42),'Relectura de orden en navegador conserva tarea');
  await scan(p,'T01');await p.waitForFunction(()=>!estado.transicionActiva);
  check(await p.evaluate(()=>tiempoTareaActualMs()>=90000),'Relectura no reinicia cronómetro en navegador');
  await scan(p,'C0002');await p.waitForFunction(()=>!estado.tareaEnCurso&&!estado.transicionActiva);
  check(await p.evaluate(()=>estado.tareaActualNro===1&&!$('#timerT').data('intervalo')),'Cierre válido detiene intervalo real y cuenta una tarea');
  await scan(p,'T01');await p.waitForFunction(()=>estado.tareaEnCurso&&!estado.transicionActiva);
  await scan(p,'C0002');await p.waitForFunction(()=>!estado.tareaEnCurso&&!estado.transicionActiva);
  check((await p.locator('#mensaje').innerText()).includes('descartada'),'Cierre menor de 60 s muestra descarte');
  check(await p.evaluate(()=>estado.tareaActualNro===1&&estado.inicioTareaTs===0),'Descarte no incrementa etapa ni deja timestamp');
  await p.screenshot({path:'analysis/procesos-20261005/armado-browser.png'});check(errors.length===0,'Armado sin errores de JavaScript');await p.close();
  const c=await browser.newPage();const cutErrors=[];c.on('pageerror',e=>(cutErrors.push(e.message),console.log('CORTE ERROR '+e.message)));c.on('dialog',d=>d.accept());
  let heartbeatResolve;const heartbeatSeen=new Promise(resolve=>heartbeatResolve=resolve);
  let task=null,failSave=false,docRequests=0,jornada=0,networkActive=0,networkMax=0;const items=[1,2,3].map(id=>({id,perfil:'P',descripcion:'Perfil',barra:id,detalle:'Corte',tilde:0,observacion:''}));
  await c.route('http://corte.test/**',async route=>{
   const u=new URL(route.request().url());if(u.pathname==='/'){let html=read('proceso_corte.php').replace(/<\?php[\s\S]*?\?>/g,'').replace('const OPERADOR_ID = ;','const OPERADOR_ID = 1;');await route.fulfill({contentType:'text/html',body:html});return;}
   if(!u.pathname.startsWith('/api/')){await route.fulfill({status:200,body:''});return;}
   const d=new URLSearchParams(route.request().postData()||'');const action=d.get('accion');let r={};
   if(u.pathname.endsWith('corte_pdf.php')){
    if(action==='inicio')r={ok:true,operador:{nombre:'Operador prueba',token:'O0001'},pendientes:[]};
    else if(action==='pendientes')r={ok:true,pendientes:[]};
    else if(action==='documentos')r={ok:true,orden:{id:1,codigo:'15048',c_estado:1},documentos:[{id:1,nombre:'Prueba.pdf',tipo:2,completo:0}]};
    else if(action==='iniciar'){task={documento_id:1,estado:'activa',elapsed_ms:0,token:'test',pause_since:0};r={ok:true,tarea:task};}
    else if(action==='guardar'){networkActive++;networkMax=Math.max(networkMax,networkActive);r={ok:true,tarea:{...task}};heartbeatResolve();await new Promise(r=>setTimeout(r,800));networkActive--;}
    else if(action==='jornada'){jornada++;r={ok:true};}
    else if(action==='finalizar'){task.estado='finalizada';r={ok:true,tarea:task};}
   }else if(u.pathname.endsWith('system.php'))r={escala:1,tamanio:8};
   else if(u.pathname.endsWith('documentos.php')){
    if(action==='lista')r=[{id:1,titulo:'prueba.pdf',items}];
    if(action==='update'){
     docRequests++;networkActive++;networkMax=Math.max(networkMax,networkActive);await new Promise(r=>setTimeout(r,30));
     if(failSave)r={ok:false,error:'Fallo simulado de guardado'};
     else {const item=items.find(x=>x.id===Number(d.get('datos[item_id]')));if(d.has('datos[tilde]'))item.tilde=Number(d.get('datos[tilde]'));if(d.has('datos[observacion]'))item.observacion=d.get('datos[observacion]');if(items.every(x=>x.tilde===1))task.estado='finalizada';r={ok:true,tarea:{...task}};}networkActive--;
    }
   }
   await route.fulfill({json:r});
  });
  await c.goto('http://corte.test/');await c.addScriptTag({content:read('plugins/js/jquery.min_3.6.0.js')});await c.evaluate(()=>{$.holdReady(true);window.testIntervals=[];const original=setInterval;window.setInterval=(fn,ms)=>{window.testIntervals.push({fn,ms});return original(fn,ms)}});
  for(const f of ['jquery.functions.js','jquery.lecturabarcode.js','jquery.corte_pdf.js','jquery.corte_documentos.js'])await c.addScriptTag({content:read('plugins/js/'+f)});
  await c.evaluate(()=>$.holdReady(false));await c.waitForFunction(()=>!$('#btnBuscarOrden').prop('disabled'));
  await c.locator('#orden').fill('15048');await c.locator('#btnBuscarOrden').click();await c.getByRole('button',{name:'Iniciar tarea'}).click();await c.waitForFunction(()=>$('#tabla-body .chk-tilde').length===3&&!$('#tabla-body .chk-tilde').first().prop('disabled'));
  check(await c.locator('#tabla-body .chk-tilde').count()===3,'Corte abre PDF y carga ítems');
  await c.locator('.obs-input').first().fill('Observación antes del cierre');
  await c.locator('.chk-tilde').first().check();await c.evaluate(()=>window.esperarGuardadosDocumentos());
  check(items[0].observacion==='Observación antes del cierre'&&items[0].tilde===1,'Tilde guarda primero observación pendiente');
  failSave=true;await c.locator('.chk-tilde').nth(1).check();await c.waitForFunction(()=>guardadosPendientes===0);await c.locator('#btnCerrarCorte').click();await c.waitForFunction(()=>!$('#btnCerrarCorte').prop('disabled'));
  check(jornada===0,'Guardado fallido impide cerrar jornada');
  failSave=false;await c.evaluate(()=>window.esperarGuardadosDocumentos());check(items[1].tilde===1,'Reintento recupera cambio fallido');
  await c.evaluate(()=>window.testIntervals.find(x=>x.ms===10000).fn());await heartbeatSeen;
  await c.locator('.chk-tilde').nth(2).check();await c.waitForFunction(()=>$('#tarea-actual').text().includes('finalizada'));
  check(await c.locator('#btnFinalizarCorte').isDisabled(),'Último tilde detiene tarea y deshabilita cierre');
  check(await c.locator('.chk-tilde:disabled').count()===3,'PDF completado queda en consulta');
  check(networkMax===1,'Heartbeat en vuelo y último tilde se confirman sin superposición');
  check(docRequests>=5,'Se ejercitaron guardados, fallo y reintento');
  check(cutErrors.length===0,'Corte sin errores de JavaScript');
  await c.screenshot({path:'analysis/procesos-20261005/corte-browser.png'});await c.close();
  console.log('TOTAL '+checks+' comprobaciones de navegador correctas (API simulada; JS real).');
 }finally{await browser.close()}
})().catch(e=>{console.error(e);process.exitCode=1});
