const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const root=process.argv[2]||'src';
function harness(){
 let now=100000,calls=[],messages=[],elements=new Map(),handlers=[];
 function $(key){if(!elements.has(key)){const e={value:'',running:false,length:1,text(v){if(v===undefined)return this.value;this.value=v;return this;},html(v){return this.text(v)},css(){return this},attr(){return this},data(){return null},ready(){return this},on(...args){handlers.push(args);return this},off(){return this},keydown(){return this},hasClass(){return false},is(){return false},remove(){return this},modal(){return this},find(k){return $(k)},prop(){return this}};elements.set(key,e)}return elements.get(key)}
 let responder=async(url,d)=>{
  if(url.includes('tareas'))return [{id:7,nombre:'Armado',codigo:'T01'}];
  if(d.accion==='add')return {id:42,retomada:false,tiempo_ms:0};
  if(d.accion==='update'&&url.includes('procesos'))return [{status:'ok',id:42,descartada:d.tiempo<=60000,tiempo_ms:d.tiempo}];
  if(d.accion==='select'&&url.includes('ordenes'))return [{id:1,codigo:'15048',estado:1}];
  if(d.accion==='listarPorOrden')return [];
  if(d.accion==='time')return {timestamp_inicio:1,timestamp_ms:1000};
  if(d.accion==='select'&&url.includes('detalles'))return [{id:3,codigo:'150480000000',estado:0,etapas:8,ayudante:0,tipologia:'PF1'}];
  if(d.accion==='select'&&url.includes('pendientes'))return [];
  if(d.accion==='contarCompletadas')return {completadas:2};
  return {status:'ok'};
 };
 $.post=(url,d)=>{if(typeof url==='object'){d=url.data;url=url.url}calls.push({url,d});const p=Promise.resolve().then(()=>responder(url,d));p.fail=fn=>{p.catch(fn);return p};return p};
 const c={$,document:{},window:{location:{search:''}},location:{reload(){},href:''},Date:class extends Date {static now(){return now}},Cronometro:function(){},OPERADOR_ID:1,URLSearchParams,console,setTimeout(){},clearInterval(){},mostrarMensaje:m=>messages.push(m),logger(){},sendMail(){},getMenuActual:()=>1,formatearFecha:x=>x,msToHhmmssms:x=>String(x),iniciarTimer:(t,e,acc)=>{e.running=true;e.started=t;e.acc=acc},detenerTimer:e=>{e.running=false},resetModalCompleto(){},initBarcodeScanner(){}};
 vm.createContext(c);vm.runInContext(fs.readFileSync(root+'/plugins/js/jquery.procesoV1.0.3.js','utf8'),c);
 const run=s=>vm.runInContext(s,c);
 run('Object.assign(estado,{ordenId:1,ordenCodigo:"15048",detalleActualId:3,tareaActualId:7,tareaEnCurso:true,procesoId:42,inicioTareaTs:10000,inicioOrdenTs:1000,tareaMax:8,operadorId:1,sesionId:1})');$('#timerT').running=true;
 return {c,run,$,calls,messages,setNow:n=>now=n,setResponder:f=>{const prev=responder;responder=(u,d)=>f(u,d,prev)},load:p=>vm.runInContext(fs.readFileSync(root+'/plugins/js/'+p,'utf8'),c)};
}
const tests=[];const test=(name,fn)=>tests.push([name,fn]);
test('releer la misma tarea conserva los 90 segundos trabajados',async()=>{const h=harness();await h.run('iniciarTarea("T01",true)');assert.equal(h.run('estado.inicioTareaTs'),10000)});
test('releer la orden conserva tarea y proceso activos',async()=>{const h=harness();await h.run('iniciarOrden("15048")');assert.equal(h.run('estado.tareaEnCurso'),true);assert.equal(h.run('estado.procesoId'),42)});
test('releer tipología activa no borra tarea ni cronómetro',async()=>{const h=harness();await h.run('seleccionarTipologia("150480000000")');assert.equal(h.run('estado.tareaEnCurso'),true);assert.equal(h.run('estado.procesoId'),42)});
test('cierre válido limpia todos los identificadores y timestamps',async()=>{const h=harness();await h.run('finalizarTareaActual()');assert.equal(h.run('estado.tareaActualId'),0);assert.equal(h.run('estado.inicioTareaTs'),0);assert.equal(h.run('estado.tareaActualNro'),1)});
test('cierre duplicado en vuelo no duplica contador',async()=>{const h=harness();await Promise.all([h.run('finalizarTareaActual()'),h.run('finalizarTareaActual()')]);assert.equal(h.run('estado.tareaActualNro'),1);assert.equal(h.calls.filter(x=>x.d.accion==='update').length,1)});

for(const ms of [0,59999,60000,60001,90000])test('cierre frontend límite '+ms+' ms',async()=>{const h=harness();h.setNow(10000+ms);await h.run('finalizarTareaActual()');assert.equal(h.run('estado.tareaActualNro'),ms<=60000?0:1);assert.equal(h.run('estado.procesoId'),0);assert.equal(h.$('#timerT').running,false);assert.equal(h.run('estado.inicioTareaTs'),0)});
test('descarte permite escanear de nuevo sin tiempo heredado',async()=>{const h=harness();h.setNow(20000);await h.run('finalizarTareaActual()');await h.run('iniciarTarea("T01",false)');assert.equal(h.run('estado.tareaEnCurso'),true);assert.equal(h.run('estado.tiempoTareaMs'),0)});
test('otra orden no pisa una tarea activa',async()=>{const h=harness();await h.run('iniciarOrden("99999")');assert.equal(h.run('estado.ordenId'),1);assert.equal(h.calls.length,0)});
test('otra tarea no cambia la activa',async()=>{const h=harness();h.setResponder(async(u,d,old)=>u.includes('tareas')?[{id:8,nombre:'Otra'}]:old(u,d));await h.run('iniciarTarea("T02",true)');assert.equal(h.run('estado.tareaActualId'),7);assert.equal(h.run('estado.inicioTareaTs'),10000)});
test('doble alta confirma un único proceso',async()=>{const h=harness();h.run('limpiarTareaArmado()');await Promise.all([h.run('iniciarTarea("T01",false)'),h.run('iniciarTarea("T01",false)')]);assert.equal(h.calls.filter(x=>x.d.accion==='add').length,1);assert.equal(h.run('estado.tareaEnCurso'),true)});
test('alta rechazada no deja nombre ni reloj huérfano',async()=>{const h=harness();h.run('limpiarTareaArmado()');h.setResponder(async(u,d,old)=>d.accion==='add'?{id:0}:old(u,d));await h.run('iniciarTarea("T01",false)');assert.equal(h.run('estado.tareaEnCurso'),false);assert.equal(h.$('#tarea_nombre').value,'');assert.equal(h.$('#timerT').running,false)});
test('fallo de red al iniciar permite reintentar',async()=>{const h=harness();h.run('limpiarTareaArmado()');h.setResponder(async(u,d,old)=>{if(d.accion==='add')throw Error('offline');return old(u,d)});await h.run('iniciarTarea("T01",false)');assert.equal(h.run('estado.transicionActiva'),false);assert.equal(h.run('estado.procesoId'),0)});
test('cierre fallido conserva tarea y reloj',async()=>{const h=harness();h.setResponder(async()=>{throw Error('offline')});await h.run('finalizarTareaActual()');assert.equal(h.run('estado.tareaEnCurso'),true);assert.equal(h.run('estado.procesoId'),42);assert.equal(h.$('#timerT').running,true)});
test('cierre rechazado conserva contador',async()=>{const h=harness();h.setResponder(async()=>[{status:'error'}]);await h.run('finalizarTareaActual()');assert.equal(h.run('estado.tareaActualNro'),0);assert.equal(h.run('estado.tareaEnCurso'),true)});
test('reintento usa tiempo confirmado sin sumar espera de red',async()=>{const h=harness();h.setResponder(async()=>[{status:'ok',id:42,descartada:false,tiempo_ms:65000}]);await h.run('finalizarTareaActual()');assert.equal(h.run('estado.tiempoOperario'),65000)});
test('selección sin pendiente recupera completadas',async()=>{const h=harness();h.run('limpiarTareaArmado()');await h.run('seleccionarTipologia("150480000000")');assert.equal(h.run('estado.tareaActualNro'),2)});
test('cierre en pausa no envía finalización',async()=>{const h=harness();h.run('estado.pausaActiva=true');await h.run('finalizarTareaActual()');assert.equal(h.calls.length,0)});
function pauseHarness(){const h=harness();h.load('jquery.procesopausaV1.0.3.js');h.run('mostrartiposPausas=function(){}');return h;}
test('selección de pausa congela tiempo una única vez',async()=>{const h=pauseHarness();await h.run('iniciarpausaTareaActual()');h.setNow(110000);await h.run('iniciarpausaTareaActual()');assert.equal(h.run('estado.tiempoTareaMs'),90000);assert.equal(h.run('estado.inicioTareaTs'),0)});
test('cancelar selección de pausa conserva tramo trabajado',async()=>{const h=pauseHarness();await h.run('iniciarpausaTareaActual()');h.setNow(110000);await h.run('finpausaTareaActual()');assert.equal(h.run('estado.tiempoTareaMs'),100000);assert.equal(h.run('estado.pausaActiva'),false);assert.equal(h.$('#timerT').running,true)});
test('pausa registrada excluye descanso del tiempo de tarea',async()=>{const h=pauseHarness();h.setResponder(async(u,d,old)=>u.includes('eventos')?(d.accion==='add'?[{id:11}]:0):d.accion==='status'?0:old(u,d));await h.run('iniciarpausaTareaActual()');await h.run('seleccionarPausa("P01","Descanso")');h.setNow(160000);await h.run('finpausaTareaActual()');assert.equal(h.run('estado.tiempoTareaMs'),90000);assert.equal(h.run('estado.tiempoPausaTotalMs'),60000);assert.equal(h.run('estado.pausaActiva'),false)});
test('error de alta de pausa permite cancelar y continuar',async()=>{const h=pauseHarness();h.setResponder(async()=>{throw Error('offline')});await h.run('iniciarpausaTareaActual()');await h.run('seleccionarPausa("P01","Descanso")');h.setNow(110000);await h.run('finpausaTareaActual()');assert.equal(h.run('estado.pausaActiva'),false);assert.equal(h.run('estado.tiempoTareaMs'),100000)});
test('error al cerrar pausa conserva pausa y reloj visibles',async()=>{const h=pauseHarness();h.run('Object.assign(estado,{pausaActiva:true,ultimoEventoId:11,inicioPausaTs:50000,inicioTareaTs:0,tiempoTareaMs:40000})');h.$('#timerP').running=true;h.setResponder(async()=>{throw Error('offline')});await h.run('finpausaTareaActual()');assert.equal(h.run('estado.pausaActiva'),true);assert.equal(h.$('#timerP').running,true)});
test('suspensión fallida mantiene tarea activa',async()=>{const h=harness();h.load('jquery.procesosuspenderV1.0.3.js');h.setResponder(async()=>({status:'error'}));await h.run('suspendeTipologiaActual()');assert.equal(h.run('estado.tareaEnCurso'),true);assert.equal(h.$('#timerT').running,true)});
test('suspensión confirmada limpia tarea y tipología',async()=>{const h=harness();h.load('jquery.procesosuspenderV1.0.3.js');h.setResponder(async()=>({status:'ok',pendienteId:5}));await h.run('suspendeTipologiaActual()');assert.equal(h.run('estado.detalleActualId'),0);assert.equal(h.run('estado.tareaEnCurso'),false);assert.equal(h.$('#timerT').running,false)});
test('cierre de jornada entre tareas guarda cero tiempo de tarea',async()=>{const h=harness();await h.run('finalizarTareaActual()');h.setResponder(async()=>[{status:'ok'}]);await h.run('registrarFinJornada()');const c=JSON.parse(h.calls.find(x=>x.d.accion==='cerrarConPendiente').d.datosCierre);assert.equal(c.tiempoTareaMs,0);assert.equal(c.procesoId,0);assert.equal(c.tareaActualId,0);assert.equal(h.run('estado.jornadaCerrada'),true)});
test('cierre de jornada fallido deja trabajo activo',async()=>{const h=harness();h.setResponder(async()=>{throw Error('offline')});await h.run('registrarFinJornada()');assert.equal(h.run('estado.tareaEnCurso'),true);assert.equal(h.$('#timerT').running,true)});
test('restaurar pendiente confirma servidor y conserva 65001 ms',async()=>{const h=harness();h.run('limpiarTareaArmado()');h.setResponder(async(u,d,old)=>{
 if(u.includes('pendientes')&&d.accion==='select')return [{id:9,proceso_id:42,tareaActualId:7,tiempoTareaMs:65001}];
 if(u.includes('procesos')&&d.accion==='select')return [{id:42,tarea_codigo:'T01',estado:0}];
 if(u.includes('procesos')&&d.accion==='add')return {id:42,tiempo_ms:65001,retomada:true};
 return old(u,d);
});await h.run('seleccionarTipologia("150480000000")');assert.equal(h.run('estado.tareaEnCurso'),true);assert.equal(h.run('estado.tiempoTareaMs'),65001);assert.equal(h.calls.find(x=>x.d.accion==='add').d.pendienteId,9)});


test('última etapa completa tipología y orden tras confirmación',async()=>{const h=harness();h.run('estado.tareaMax=1');h.$('#timerO').running=true;h.setResponder(async(u,d,old)=>d.accion==='last'?0:old(u,d));await h.run('finalizarTareaActual()');assert.equal(h.run('estado.detalleActualId'),0);assert.equal(h.$('#timerO').running,false);assert.equal(h.calls.filter(x=>x.d.accion==='end').length,1)});
test('fallo de cierre de orden mantiene reloj y permite relectura para reintentar',async()=>{const h=harness();h.run('estado.tareaMax=1');h.$('#timerO').running=true;let fail=true;h.setResponder(async(u,d,old)=>d.accion==='last'?0:d.accion==='end'&&fail?{status:'error'}:old(u,d));await h.run('finalizarTareaActual()');assert.equal(h.$('#timerO').running,true);assert.equal(h.run('estado.cierreOrdenPendiente'),true);fail=false;await h.run('iniciarOrden("15048")');assert.equal(h.$('#timerO').running,false);assert.equal(h.run('estado.cierreOrdenPendiente'),false)});
test('fallo al cerrar tipología no vuelve a contar la tarea al reintentar',async()=>{const h=harness();h.run('estado.tareaMax=1');h.setResponder(async(u,d,old)=>u.includes('detalles')&&d.accion==='update'?{status:'error'}:old(u,d));await h.run('finalizarTareaActual()');await h.run('finalizarTareaActual()');assert.equal(h.run('estado.tareaActualNro'),1);assert.equal(h.run('estado.detalleActualId'),3)});

(async()=>{let failed=0;for(const [n,f]of tests){try{await f();console.log('PASS '+n)}catch(e){failed++;console.log('FAIL '+n+': '+e.message)}}console.log(`${tests.length-failed}/${tests.length} correctas`);process.exitCode=failed?1:0})();
