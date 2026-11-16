// Synthetic invoices on disposable CI only. Real OCR, no cloud traffic.
const {chromium}=require('playwright'),assert=require('node:assert/strict');
function pdfFixture(){
 const text=['Proveedor: Taller Demo SL','Factura numero F-123','Fecha factura: 16/11/2026','Vencimiento: 16/12/2026','Base imponible: 100,00 EUR','IVA 21% 21,00 EUR','Total factura: 121,00 EUR'];
 const stream='BT /F1 18 Tf 50 760 Td '+text.map((t,i)=>(i?'0 -32 Td ':'')+'('+t+') Tj').join('\n')+' ET';
 const objects=['<< /Type /Catalog /Pages 2 0 R >>','<< /Type /Pages /Kids [3 0 R] /Count 1 >>','<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>','<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',`<< /Length ${Buffer.byteLength(stream)} >>\nstream\n${stream}\nendstream`];
 let body='%PDF-1.4\n',offsets=[0];for(let i=0;i<objects.length;i++){offsets.push(Buffer.byteLength(body));body+=`${i+1} 0 obj\n${objects[i]}\nendobj\n`;}const xref=Buffer.byteLength(body);body+='xref\n0 6\n0000000000 65535 f \n'+offsets.slice(1).map(n=>String(n).padStart(10,'0')+' 00000 n \n').join('')+`trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n${xref}\n%%EOF`;return Buffer.from(body);
}
(async()=>{
 const browser=await chromium.launch(),context=await browser.newContext(),page=await context.newPage();page.setDefaultTimeout(90000);
 const outside=[],errors=[],posts=[];await context.route('**/*',route=>{if(new URL(route.request().url()).hostname!=='127.0.0.1'){outside.push(route.request().url());return route.abort();}return route.continue();});
 page.on('pageerror',e=>errors.push(e.message));page.on('request',r=>{if(r.method()==='POST')posts.push(r.url());});
 await page.goto('http://127.0.0.1:8080/?view=accounting');await page.getByLabel('Usuario',{exact:true}).fill('ci-admin');await page.getByLabel('Contraseña',{exact:true}).fill('ci-password-only');await page.getByRole('button',{name:'Entrar',exact:true}).click();
 await page.goto('http://127.0.0.1:8080/?view=accounting');await page.getByRole('heading',{name:'Contabilidad',exact:true}).waitFor();
 const dialog=page.getByRole('dialog');let before=posts.length;
 await page.locator('#invoice').setInputFiles({name:'synthetic.pdf',mimeType:'application/pdf',buffer:pdfFixture()});
 await dialog.waitFor();assert.equal(await dialog.getByLabel('Proveedor',{exact:true}).inputValue(),'Taller Demo SL');assert.equal(await dialog.getByLabel('Base imponible (EUR)',{exact:true}).inputValue(),'100.00');assert.equal(await dialog.getByLabel('IVA (importe en EUR; 0 si no tiene)',{exact:true}).inputValue(),'21.00');assert.equal(await dialog.getByLabel('Fecha de vencimiento',{exact:true}).inputValue(),'2026-12-16');assert.equal(posts.length,before,'No invoice uploaded before confirmation');
 await dialog.getByRole('button',{name:'Guardar',exact:true}).click();await dialog.waitFor({state:'hidden'});assert(posts.some(x=>x.includes('account_invoice_local')));assert(!posts.some(x=>x.includes('account_invoice_upload')));
 const png=await page.evaluate(()=>{const c=document.createElement('canvas');c.width=1400;c.height=900;const x=c.getContext('2d');x.fillStyle='white';x.fillRect(0,0,c.width,c.height);x.fillStyle='black';x.font='36px Arial';['Proveedor: Taller Demo SL','Factura numero F-456','Fecha factura: 16/11/2026','Vencimiento: 16/12/2026','Base imponible: 100,00 EUR','IVA 21% 21,00 EUR','Total factura: 121,00 EUR'].forEach((t,i)=>x.fillText(t,60,90+i*95));return c.toDataURL('image/png').split(',')[1];});
 before=posts.length;await page.locator('#invoice').setInputFiles({name:'synthetic.png',mimeType:'image/png',buffer:Buffer.from(png,'base64')});await dialog.waitFor();assert.equal(await dialog.getByLabel('Base imponible (EUR)',{exact:true}).inputValue(),'100.00');assert.equal(await dialog.getByLabel('Fecha de vencimiento',{exact:true}).inputValue(),'2026-12-16');assert.equal(posts.length,before);await page.screenshot({path:'/tmp/moba-local-ocr.png',fullPage:true});await dialog.getByRole('button',{name:'Cancelar',exact:true}).click();assert.equal(posts.length,before,'Cancel does not upload');assert.deepEqual(outside,[],'No third-party requests');assert.deepEqual(errors,[],'No runtime errors');
 await browser.close();console.log('Real browser OCR and PDF text extraction, review, upload on save and privacy OK');
})().catch(e=>{console.error(e);process.exit(1)});
