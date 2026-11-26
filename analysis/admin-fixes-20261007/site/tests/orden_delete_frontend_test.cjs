const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
let checks=0;
function harness(){let calls=0,reloads=0,messages=[],responder=()=>Promise.resolve({resultado:'ok'});const $=()=>({ready(){},on(){},html(){}});$.post=(...args)=>{calls++;return responder(...args)};const c={$,document:{},console,setTimeout(){},mostrarMensaje:(m,color)=>messages.push({m,color})};vm.createContext(c);vm.runInContext(fs.readFileSync('src/plugins/js/jquery.ordenes.js','utf8'),c);const render=c.cargarOrdenes;c.cargarOrdenes=()=>reloads++;return {c,render,messages,calls:()=>calls,reloads:()=>reloads,set:f=>responder=f};}
async function test(n,f){await f();checks++;console.log('PASS '+n)}
(async()=>{
 await test('respuesta error no genera TypeError',async()=>{const h=harness();h.set(()=>Promise.resolve({resultado:'error'}));await h.c.deleteOrden(1);assert.equal(h.reloads(),0);assert.match(h.messages[0].m,/No se pudo/)});
 for(const value of [undefined,null,{},'html'])await test('respuesta inválida '+JSON.stringify(value),async()=>{const h=harness();h.set(()=>Promise.resolve(value));await h.c.deleteOrden(1);assert.equal(h.messages[0].color,'red')});
 await test('409 muestra restricción de estado',async()=>{const h=harness();h.set(()=>Promise.reject({status:409,responseJSON:{mensaje:'Sólo Cargado'}}));await h.c.deleteOrden(1);assert.match(h.messages[0].m,/Sólo Cargado/);assert.equal(h.reloads(),0)});
 await test('fallo de red muestra mensaje recuperable',async()=>{const h=harness();h.set(()=>Promise.reject(Error('offline')));await h.c.deleteOrden(1);assert.match(h.messages[0].m,/Actualice la lista/)});
 await test('doble clic envía una sola eliminación',async()=>{const h=harness();await Promise.all([h.c.deleteOrden(1),h.c.deleteOrden(1)]);assert.equal(h.calls(),1);assert.equal(h.reloads(),1)});
 await test('error libera bloqueo para reintentar',async()=>{const h=harness();h.set(()=>Promise.reject(Error('offline')));await h.c.deleteOrden(1);h.set(()=>Promise.resolve({resultado:'ok'}));await h.c.deleteOrden(1);assert.equal(h.calls(),2);assert.equal(h.reloads(),1)});
 await test('éxito actualiza listado',async()=>{const h=harness();await h.c.deleteOrden(1);assert.equal(h.reloads(),1);assert.equal(h.messages[0].color,'green')});
 await test('advertencia de limpieza se informa',async()=>{const h=harness();h.set(()=>Promise.resolve({resultado:'ok',advertencia:'Revisar temporales'}));await h.c.deleteOrden(1);assert.equal(h.messages[0].m,'Revisar temporales');assert.equal(h.reloads(),1)});
 await test('ID inválido no envía solicitud',async()=>{const h=harness();await h.c.deleteOrden(0);await h.c.deleteOrden('x');assert.equal(h.calls(),0)});
 await test('botón sólo para estado cero numérico o textual',async()=>{const h=harness();const rows=[];h.c.table={clear(){},row:{add:r=>rows.push(r)},draw(){}};vm.runInContext('tablaOrdenes=table',h.c);h.set((url,data,cb)=>{cb([0,'0',1,'1',2,3,4,null].map((estado,i)=>({id:i+1,estado})));return {fail(){return this}}});h.render();assert.equal(rows.length,8);rows.forEach((r,i)=>assert.equal(r[5].includes('deleteOrden('),i<2));});
 console.log('TOTAL '+checks+' comprobaciones correctas');
})().catch(e=>{console.error(e);process.exitCode=1});
