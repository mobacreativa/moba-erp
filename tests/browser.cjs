// Runs only against the disposable CI instance; never against production.
const {chromium}=require('playwright');
const assert=require('node:assert/strict');
(async()=>{
 const browser=await chromium.launch();const page=await browser.newPage();
 const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto('http://127.0.0.1:8080/');
 await page.getByLabel('Usuario',{exact:true}).fill('ci-admin');
 await page.getByLabel('Contraseña',{exact:true}).fill('ci-password-only');
 await page.getByRole('button',{name:'Entrar',exact:true}).click();
 await page.getByRole('link',{name:'Abrir presupuestos y catálogo →'}).click();
 await page.getByRole('heading',{name:'Presupuestos recientes'}).waitFor();
 const nav=page.getByRole('navigation',{name:'Secciones'});
 const dialog=page.getByRole('dialog');
 async function create(section,fields){
  await nav.getByRole('button',{name:section,exact:true}).click();
  await page.getByRole('button',{name:'+ Añadir',exact:true}).click();
  for(const [label,value] of fields)await dialog.getByLabel(label,{exact:true}).fill(value);
 }
 async function save(){await dialog.getByRole('button',{name:'Guardar',exact:true}).click();await dialog.waitFor({state:'hidden'});}
 await create('Proveedores',[['Nombre','Browser supplier']]);await save();
 await create('Marcas',[['Nombre','Browser brand']]);await save();
 await create('Colores',[['Nombre','Browser red'],['Código','RED']]);
 await dialog.getByLabel('Proveedor de la biblioteca').selectOption({label:'Browser supplier'});await save();
 await create('Tallas',[['Nombre','Browser XL']]);await save();
 await create('Productos y servicios',[['Nombre','Browser garment'],['Referencia','UI-001'],['Categoría','Textil'],['Coste compra (vacío = desconocido)','2']]);
 await dialog.getByLabel('Marca / fabricante').selectOption({label:'Browser brand'});
 await dialog.getByLabel('Proveedor de compra').selectOption({label:'Browser supplier'});
 await dialog.getByLabel('Biblioteca de colores').selectOption({label:'Browser supplier'});
 await dialog.getByLabel('DTF',{exact:true}).check();
 await dialog.getByLabel('RED · Browser red',{exact:true}).check();
 await dialog.getByLabel('Browser XL',{exact:true}).check();await save();
 await create('Tarifas y costes',[['Nombre','Browser DTF'],['Técnica (DTF, Bordado, Láser…)','DTF'],['Zona (Pecho, Espalda, Manga derecha…)','Pecho'],['Tamaño / formato','8x8'],['Importe sin IVA','1.2']]);await save();
 await nav.getByRole('button',{name:'Nuevo presupuesto',exact:true}).click();
 console.log('New quote UI:',await page.locator('#content').innerText(),'Notice:',await page.locator('#notice').innerText(),'Errors:',errors);
 await page.screenshot({path:'/tmp/moba-before-quote.png',fullPage:true});
 await page.getByLabel('Cliente',{exact:true}).selectOption({label:'Integration customer'});
 await page.getByRole('button',{name:'+ Configurar producto'}).click();
 await dialog.getByLabel('Producto',{exact:true}).selectOption({label:'UI-001 · Browser garment'});
 await dialog.getByLabel('Browser red Browser XL',{exact:true}).fill('30');
 await dialog.getByLabel('DTF · Pecho · 8x8 (coste)',{exact:true}).check();
 await dialog.getByLabel('PVP manual sin IVA (vacío = automático)').fill('7');
 await dialog.getByRole('button',{name:'Calcular precio',exact:true}).click();
 await dialog.getByRole('heading',{name:'Browser garment con DTF Pecho'}).waitFor();
 await page.screenshot({path:'/tmp/moba-configurator.png',fullPage:true});
 await dialog.getByRole('button',{name:'Añadir al presupuesto',exact:true}).click();
 await dialog.waitFor({state:'hidden'});
 await page.getByRole('button',{name:'Guardar borrador',exact:true}).click();
 await page.getByRole('button',{name:'Marcar emitido',exact:true}).waitFor();
 assert((await page.locator('#content').innerText()).includes('Browser red / Browser XL: 30'));
 await page.getByRole('button',{name:'Marcar emitido',exact:true}).click();
 await page.getByRole('button',{name:'Marcar aceptado',exact:true}).click();
 await page.getByText(/Aceptado · versión/).waitFor();
 await page.screenshot({path:'/tmp/moba-quote.png',fullPage:true});
 // Replace only the native print dialog to inspect the produced commercial DOM.
 await page.evaluate(()=>window.print=()=>{});
 await page.getByRole('button',{name:'Imprimir / PDF',exact:true}).click();
 const print=await page.locator('#print').textContent();
 assert(print.includes('Browser garment con DTF Pecho'));
 assert(!print.includes('8x8')&&!print.includes('Rentabilidad')&&!print.includes('Coste'));
 await page.emulateMedia({media:'print'});await page.pdf({path:'/tmp/moba-quote.pdf',format:'A4'});
 await page.emulateMedia({media:'screen'});await page.setViewportSize({width:390,height:844});
 await nav.getByRole('button',{name:'Inicio',exact:true}).click();
 await page.screenshot({path:'/tmp/moba-mobile.png',fullPage:true});
 assert.deepEqual(errors,[],'No JavaScript runtime errors');
 await browser.close();console.log('Browser: catalog, selectors, quote lifecycle, print privacy and mobile OK');
})().catch(e=>{console.error(e);process.exit(1)});
