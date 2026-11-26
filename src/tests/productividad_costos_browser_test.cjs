const fs=require('fs'),path=require('path'),os=require('os'),assert=require('assert/strict'),{execFileSync}=require('child_process');
const {chromium}=require(path.join(os.homedir(),'.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright'));
const base='http://127.0.0.1:8080',out='analysis/productividad-costos';fs.mkdirSync(out,{recursive:true});
const php=code=>execFileSync('docker',['compose','exec','-T','--user','www-data','php','php','-r',code],{encoding:'utf8'}).trim();
(async()=>{
 let browser,session;const checks=[],errors=[];const ok=(v,m)=>{assert.ok(v,m);checks.push(m);};
 try{
  session=php('require "/var/www/html/modules/soporte/includes/config.php"; $_SESSION["auth_role"]="admin"; $_SESSION["last_activity"]=time(); echo session_id();');
  assert.match(session,/^[A-Za-z0-9,-]+$/);
  browser=await chromium.launch({channel:'msedge',headless:true});
  const context=await browser.newContext({viewport:{width:1280,height:960}});
  await context.addCookies([{name:'cfg_sess',value:session,url:base}]);
  const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));
  await page.goto(base+'/modules/soporte/productividad.php',{waitUntil:'networkidle'});
  ok(await page.getByRole('heading',{name:'Productividad y costos'}).count()===1,'Sección accesible a administrador');
  ok(await page.locator('#costo_hora').inputValue()==='','No precarga tarifa inventada');
  ok(!(await page.locator('[name=costos_activos]').isChecked()),'Costos desactivados inicialmente');
  await page.locator('[name=costos_activos]').check();await page.fill('#costo_hora','1000');
  await page.locator('[name=adicionales_activos]').check();
  await page.click('#addTier');await page.click('#addTier');
  const rows=page.locator('#scaleRows tr');ok(await rows.count()===2,'Agregar tramos');
  await rows.nth(0).locator('[name="ahorro_desde[]"]').fill('20');await rows.nth(0).locator('[name="adicional_porcentaje[]"]').fill('50');
  await rows.nth(1).locator('[name="ahorro_desde[]"]').fill('10');await rows.nth(1).locator('[name="adicional_porcentaje[]"]').fill('25');
  await page.fill('#sim_estimado','10:00:00');await page.fill('#sim_real','08:00:00');
  let response=page.waitForResponse(r=>r.url().endsWith('/api/save_productividad.php')&&r.request().method()==='POST');await page.click('#simulate');
  let result=await (await response).json();ok(result.ok&&result.productividad===-20,'Simulación conserva signo de productividad');
  ok(result.economia.ahorro_costo===2000&&result.economia.adicional===1000,'Simulación usa tarifa y tramo elegidos');
  await page.waitForFunction(()=>document.querySelector('#simResult').textContent.includes('Ejemplo sin guardar'));
  await page.screenshot({path:out+'/configuracion-simulacion.png',fullPage:true});
  await rows.nth(1).locator('[name="ahorro_desde[]"]').fill('20');
  response=page.waitForResponse(r=>r.url().endsWith('/api/save_productividad.php')&&r.request().method()==='POST');await page.click('#simulate');
  const duplicate=await response;ok(duplicate.status()===422,'Rechazar umbrales duplicados');
  await rows.nth(1).locator('.removeTier').click();ok(await rows.count()===1,'Quitar tramo');
  const csrf=await page.locator('[name=csrf]').inputValue();
  const invalidCsrf=await context.request.post(base+'/modules/soporte/api/save_productividad.php',{form:{csrf:'invalido',version:'0'}});ok(invalidCsrf.status()===400,'CSRF obligatorio');
  const invalidVersion=await context.request.post(base+'/modules/soporte/api/save_productividad.php',{form:{csrf,version:'-1'}});ok(invalidVersion.status()===422,'Versión inválida rechazada');
  const anon=await browser.newContext();const denied=await anon.request.get(base+'/modules/soporte/productividad.php',{maxRedirects:0});ok(denied.status()===302,'Requiere sesión de soporte');await anon.close();
  const count=php('require "/var/www/html/include/reportes_service.php"; echo reportDatabase()->query("SELECT COUNT(*) FROM systemproductividad")->fetchColumn();');ok(count==='0','Simular y validar no guarda tarifas ni pagos');
  ok(errors.length===0,'Sin errores JavaScript');
  fs.writeFileSync(out+'/browser-tests.json',JSON.stringify({checks,errors},null,2));console.log('OK: '+checks.length+' comprobaciones de interfaz; sin guardar valores económicos.');
 } finally {
  if(browser)await browser.close();
  if(session)php('session_name("cfg_sess");session_id("'+session+'");session_start();session_destroy();');
 }
})().catch(e=>{console.error(e);process.exitCode=1;});
