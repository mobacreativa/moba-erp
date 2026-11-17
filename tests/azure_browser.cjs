// Disposable CI, mocked Azure responses. No cloud requests or real invoices.
const {chromium}=require('playwright'),assert=require('node:assert/strict');
(async()=>{
 const browser=await chromium.launch(),page=await browser.newPage();
 await page.goto('http://127.0.0.1:8080/');
 await page.getByLabel('Usuario',{exact:true}).fill('ci-admin');
 await page.getByLabel('Contraseña',{exact:true}).fill('ci-password-only');
 await page.getByRole('button',{name:'Entrar',exact:true}).click();
 let uploads=0,polls=0,saves=0;
 await page.route('**/?api=account_load',async route=>{
  const response=await route.fetch(),body=await response.json();body.data.reader_enabled=true;
  await route.fulfill({response,json:body});
 });
 await page.route('**/?api=account_invoice_upload',async route=>{
  uploads++;const body=route.request().postData();assert(body.includes('name="payload"'));assert(body.includes('name="csrf"'));assert(body.includes('synthetic.pdf'));
  await route.fulfill({json:{ok:true,data:{id:'azure-fixture',status:'running'}}});
 });
 await page.route('**/?api=account_invoice_status*',async route=>{
  polls++;await route.fulfill({json:{ok:true,data:{id:'azure-fixture',status:polls===1?'running':'succeeded',fields:{party:'Proveedor Azure SL',date:'2026-11-17',due_date:'2026-12-31',reference:'AZ-001',base:'100.00',tax:'21.00',invoice_total:'121.00',currency:'EUR'},confidence:{party:0.7}}}});
 });
 page.on('request',r=>{if(r.url().includes('account_save')||r.url().includes('account_invoice_local'))saves++;});
 await page.goto('http://127.0.0.1:8080/?view=accounting');
 await page.locator('#invoice-azure').setInputFiles({name:'synthetic.pdf',mimeType:'application/pdf',buffer:Buffer.from('%PDF-1.4 synthetic')});
 const dialog=page.getByRole('dialog');await dialog.waitFor();
 assert.equal(await dialog.getByLabel('Proveedor',{exact:true}).inputValue(),'Proveedor Azure SL');
 assert.equal(await dialog.getByLabel('Fecha de vencimiento',{exact:true}).inputValue(),'2026-12-31');
 assert.equal(await dialog.getByLabel('IVA (importe en EUR; 0 si no tiene)',{exact:true}).inputValue(),'21.00');
 assert((await dialog.innerText()).includes('baja confianza'));
 assert.equal(uploads,1);assert.equal(polls,2);assert.equal(saves,0);
 await dialog.getByRole('button',{name:'Cancelar',exact:true}).click();assert.equal(saves,0);
 await browser.close();console.log('Azure upload, polling, review, vendor, due date, confidence and cancel flow OK (mocked service)');
})().catch(e=>{console.error(e);process.exit(1);});
