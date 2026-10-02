'use strict';
const $ = id => document.getElementById(id);
const esc = value => String(value ?? '').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const money = value => value == null ? 'Coste pendiente' : new Intl.NumberFormat('es-ES',{style:'currency',currency:'EUR'}).format(value);
const labels = {product:'Productos y servicios',rate:'Tarifas y costes',supplier:'Proveedores',brand:'Marcas',color:'Colores',size:'Tallas',settings:'Datos de empresa',quotes:'Presupuestos',home:'Inicio',new:'Nuevo presupuesto'};
const statuses = {draft:'Borrador',issued:'Emitido',accepted:'Aceptado',rejected:'Rechazado',cancelled:'Anulado'};
let data, view='home', editing, draft, calculated, filter='';
function notice(message='',error=false){$('notice').textContent=message;$('notice').className=error?'error':'success';}
async function api(action,payload){
  const options=payload===undefined?{}:{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({csrf:data.csrf,payload:JSON.stringify(payload)})};
  const response=await fetch('/?api='+action,options); let result;
  try{result=await response.json()}catch{throw Error('Respuesta inesperada. Comprueba la sesión y vuelve a intentarlo.');}
  if(!result.ok)throw Error(result.error||'No se pudo guardar.');return result.data;
}
async function load(){data=await api('load');notice();}
function find(kind,id){return data[kind].find(x=>x.id===id)}
function name(kind,id){return find(kind,id)?.name||'—'}
const button=(text,action,id='',extra='')=>`<button type="button" data-action="${action}" data-id="${esc(id)}" ${extra}>${esc(text)}</button>`;
const input=(key,label,value='',type='text',extra='')=>`<label>${esc(label)}<input name="${key}" type="${type}" value="${esc(value)}" ${extra}></label>`;
const area=(key,label,value='')=>`<label class="full">${esc(label)}<textarea name="${key}" rows="3">${esc(value)}</textarea></label>`;
function select(key,label,options,value='',blank=true){return `<label>${esc(label)}<select name="${key}">${blank?'<option value="">Seleccionar…</option>':''}${options.map(([id,text])=>`<option value="${esc(id)}" ${String(value)===String(id)?'selected':''}>${esc(text)}</option>`).join('')}</select></label>`}
const choices=(kind)=>data[kind].filter(x=>x.active).map(x=>[x.id,x.name]);
function chips(key,items,selected=[]){return `<div class="chips">${items.map(x=>`<label><input name="${key}" type="checkbox" value="${esc(x.id)}" ${selected.includes(x.id)?'checked':''}>${esc(x.code?x.code+' · '+x.name:x.name)}</label>`).join('')}</div>`}
function show(target){
  view=target;filter='';$('title').textContent=labels[target]||target;
  document.querySelectorAll('[data-view]').forEach(b=>b.classList.toggle('selected',b.dataset.view===target));
  if(target==='home')home();else if(target==='quotes')quotes();else if(target==='new'){draft={date:new Date().toLocaleDateString('en-CA'),valid_days:30,lines:[]};quoteEditor();}else catalog();
}
function home(){
  const pending=data.quote.filter(q=>['draft','issued'].includes(q.status));
  $('content').innerHTML=`<div class="grid">${[['Catálogo',data.product.filter(x=>x.active).length],['Clientes',data.customers.length],['Presupuestos pendientes',pending.length],['Aceptados',data.quote.filter(q=>q.status==='accepted').length]].map(([t,n])=>`<div class="card">${t}<b class="metric">${n}</b></div>`).join('')}</div><div class="card toolbar">${button('+ Nuevo presupuesto','new')}${button('Ver presupuestos','quotes')}<a class="button" href="/">Gestionar clientes</a></div><div class="card"><h2>Presupuestos recientes</h2>${quoteTable(data.quote.slice(0,8))}</div><p class="muted">Los datos se guardan en el servidor. Cada modificación conserva una versión anterior. Las tarifas de venta no se consideran costes de producción.</p>`;
}
function quoteTable(rows){return `<div class="scroll"><table><thead><tr><th>Número</th><th>Cliente</th><th>Fecha</th><th>Estado</th><th>Total</th><th></th></tr></thead><tbody>${rows.map(q=>`<tr><td>${esc(q.name)}</td><td>${esc(q.customer.name)}</td><td>${esc(q.date)}</td><td>${statuses[q.status]}</td><td class="money">${money(q.total)}</td><td>${button('Abrir','openquote',q.id)}</td></tr>`).join('')||'<tr><td colspan="6">Todavía no hay presupuestos.</td></tr>'}</tbody></table></div>`}
function quotes(){ $('content').innerHTML=`<div class="toolbar">${button('+ Nuevo presupuesto','new')}<input id="filter" placeholder="Buscar número o cliente" value="${esc(filter)}"></div><div class="card">${quoteTable(data.quote.filter(q=>(q.name+' '+q.customer.name).toLowerCase().includes(filter.toLowerCase())))}</div>`; }
function catalog(){
  const rows=data[view].filter(x=>(x.name+' '+(x.reference||'')+' '+(x.code||'')+' '+name('supplier',x.supplier_id)).toLowerCase().includes(filter.toLowerCase()));
  $('content').innerHTML=`<div class="toolbar">${button('+ Añadir','add')}${view==='product'?button('Importar CSV','import'):''}<input id="filter" placeholder="Buscar nombre, referencia o proveedor" value="${esc(filter)}"></div><div class="card scroll"><table><thead><tr><th>Nombre</th><th>Información</th><th>Estado</th><th></th></tr></thead><tbody>${rows.map(r=>`<tr><td><strong>${esc(r.name)}</strong><small>${esc(r.reference||r.code||'')}</small></td><td>${recordInfo(r)}</td><td>${r.active?'Activo':'Inactivo'}</td><td>${button('Editar','edit',r.id)} ${button('Histórico','history',r.id)} ${view==='product'?button('Duplicar','duplicate',r.id):''}</td></tr>`).join('')||'<tr><td colspan="4">No hay registros.</td></tr>'}</tbody></table></div>`;
}
function recordInfo(r){
  if(view==='product')return `${esc(r.category)} · ${esc(name('supplier',r.supplier_id))}<small>Coste: ${money(r.cost)} · PVP: ${r.pvp==null?'Automático':money(r.pvp)}</small>`;
  if(view==='rate')return `${esc(r.technique)} · ${esc(r.zone)} · ${esc(r.size)}<small>${r.basis==='cost'?'Coste':'Venta'}: ${money(r.amount)} / ${esc(r.unit)} · ${r.min_qty}–${r.max_qty||'∞'} uds.</small>`;
  if(view==='color')return `${esc(name('supplier',r.supplier_id))} · ${esc(r.hex)}`;
  return esc(r.email||r.notes||'');
}
function edit(kind,id='',duplicate=false){
  const r=id?structuredClone(find(kind,id)):{active:true,name:'',margin:35,min_qty:1,vat:21,mode:'margin',unit:'unit',basis:'cost',min_qty:1};
  if(duplicate){delete r.id;delete r.version;r.name+=' (copia)';r.reference='';}
  editing={kind,record:r};
  let fields=input('name','Nombre',r.name,'text','required maxlength="190"');
  if(['supplier','settings'].includes(kind))fields+=input('tax_id','NIF / CIF',r.tax_id)+input('email','Correo electrónico',r.email,'email')+input('phone','Teléfono',r.phone)+input('address','Dirección',r.address);
  if(kind==='product'){
    fields+=input('reference','Referencia',r.reference)+input('category','Categoría',r.category)+select('brand_id','Marca / fabricante',choices('brand'),r.brand_id)+select('supplier_id','Proveedor de compra',choices('supplier'),r.supplier_id)+select('color_supplier_id','Biblioteca de colores',choices('supplier'),r.color_supplier_id||r.supplier_id)+select('calculation','Cálculo',[['unit','Producto + personalización'],['area','Por m² (medidas en cm)'],['fixed','Precio cerrado']],r.calculation||'unit',false)+input('cost','Coste compra (vacío = desconocido)',r.cost,'number','min="0" step="0.0001"')+input('pvp','PVP base opcional (sin IVA)',r.pvp,'number','min="0" step="0.01"')+input('min_qty','Cantidad mínima',r.min_qty,'number','min="1" step="1" required')+input('vat','IVA %',r.vat,'number','min="0" max="100" step="0.01"')+select('mode','Cálculo del porcentaje',[['margin','Margen sobre venta'],['markup','Recargo sobre coste']],r.mode,false)+input('margin','Porcentaje predeterminado',r.margin,'number','min="0" step="0.01"')+area('margin_rules','Tramos: desde; hasta (vacío sin límite); porcentaje. Una línea por tramo.',(r.margin_rules||[]).map(x=>`${x.min_qty};${x.max_qty||''};${x.margin}`).join('\n'));
    fields+=`<fieldset class="full"><legend>Técnicas compatibles</legend>${chips('techniques',['DTF','Bordado','Serigrafía','Láser','Sublimación','UV'].map(x=>({id:x,name:x})),r.techniques||[])}</fieldset><fieldset class="full"><legend>Colores disponibles para este modelo</legend><div class="toolbar">${button('Seleccionar todos','checkcolors')}${button('Deseleccionar todos','uncheckcolors')}</div><div id="colorchoices"></div></fieldset><fieldset class="full"><legend>Tallas disponibles</legend><div class="toolbar">${button('Seleccionar todas','checksizes')}${button('Deseleccionar todas','unchecksizes')}</div>${chips('size_ids',data.size.filter(x=>x.active),(r.size_ids||[]))}</fieldset>`;
  }
  if(kind==='color')fields+=select('supplier_id','Proveedor de la biblioteca',choices('supplier'),r.supplier_id)+input('code','Código',r.code,'text','required')+input('hex','Color orientativo',r.hex||'#cccccc','color');
  if(kind==='rate')fields+=input('technique','Técnica (DTF, Bordado, Láser…)',r.technique,'text','required')+input('zone','Zona (Pecho, Espalda, Manga derecha…)',r.zone)+input('size','Tamaño / formato',r.size)+select('basis','Naturaleza del importe',[['cost','Coste interno'],['sale','Tarifa de venta']],r.basis,false)+select('unit','Unidad',[['unit','Unidad'],['m2','m² del producto'],['cm2','cm² de impresión'],['minute','Minuto']],r.unit,false)+input('amount','Importe sin IVA',r.amount,'number','required min="0" step="0.0001"')+input('min_qty','Desde unidades',r.min_qty,'number','min="1" required')+input('max_qty','Hasta unidades (vacío sin límite)',r.max_qty,'number','min="1"');
  fields+=area('notes','Observaciones',r.notes)+`<label class="full"><input name="active" type="checkbox" ${r.active?'checked':''}> Activo (desmarcar conserva el histórico)</label>`;
  $('fields').innerHTML=`<h2>${r.id?'Editar':'Añadir'} · ${labels[kind]}</h2><div class="fields">${fields}</div><p id="formerror" role="alert" class="error" hidden></p>`;
  if(kind==='product')refreshColors(r.color_ids||[]);
  $('editor').showModal();
}
function refreshColors(selected){const supplier=$('editform').elements.color_supplier_id.value;$('colorchoices').innerHTML=chips('color_ids',data.color.filter(x=>x.active&&x.supplier_id===supplier),selected);}
async function saveRecord(event){
  event.preventDefault();const f=new FormData(event.target);const r={...editing.record,...Object.fromEntries(f)};
  r.active=f.has('active');
  if(editing.kind==='product'){
    for(const k of ['techniques','color_ids','size_ids'])r[k]=f.getAll(k);
    r.margin_rules=String(f.get('margin_rules')||'').split('\n').filter(x=>x.trim()).map(x=>{const a=x.split(';');if(a.length!==3)throw Error('Usa desde;hasta;porcentaje en cada tramo.');return{min_qty:a[0].trim(),max_qty:a[1].trim()||null,margin:a[2].trim()}});
  }
  const saved=await api('save',{kind:editing.kind,id:r.id||'',version:r.version||0,data:r});
  const list=data[editing.kind];const index=list.findIndex(x=>x.id===saved.id);if(index<0)list.unshift(saved);else list[index]=saved;
  $('editor').close();notice('Guardado en el servidor.');catalog();
}
function captureDraft(){if(!$('quoteform'))return;Object.assign(draft,Object.fromEntries(new FormData($('quoteform'))));}
function quoteEditor(){
  $('title').textContent=draft.id?`Presupuesto ${draft.name}`:'Nuevo presupuesto';
  $('content').innerHTML=`<form id="quoteform" class="card"><div class="fields">${select('customer_id','Cliente',data.customers.map(c=>[c.id,c.name]),draft.customer_id)}${input('date','Fecha',draft.date,'date','required')}${input('valid_days','Validez (días)',draft.valid_days,'number','min="1"')}${input('payment','Forma de pago',draft.payment)}${area('notes','Observaciones para el cliente',draft.notes)}</div></form><div class="toolbar">${button('+ Configurar producto','configure')}${button('Guardar borrador','savequote')}</div><div class="card">${draft.lines.map((l,i)=>`<div class="toolbar"><strong>${esc(l.preview.description)}</strong><span>${l.preview.quantity} × ${money(l.preview.price)} = ${money(l.preview.total)}</span>${button('Quitar','removeline',i)}</div>`).join('')||'<p>Añade el primer producto o servicio.</p>'}</div>`;
}
function configure(){
  captureDraft();calculated=null;
  $('fields').innerHTML=`<h2>Configurar producto</h2><form></form><div class="fields">${select('product_id','Producto',data.product.filter(x=>x.active).map(x=>[x.id,(x.reference?x.reference+' · ':'')+x.name]))}</div><div id="configuration"></div><p id="formerror" class="error" hidden></p>`;
  editing={kind:'configuration'};$('editor').showModal();$('editform').querySelector('button[type=submit]').textContent='Añadir al presupuesto';
}
function productConfiguration(){
  const p=find('product',$('editform').elements.product_id.value);if(!p){$('configuration').innerHTML='';return;}
  let html=`<div class="fields">${input('quantity','Cantidad',p.min_qty||1,'number','min="1" step="1" required')}${p.calculation==='area'?input('width_cm','Ancho cm','', 'number','min="0.01" step="0.01" required')+input('height_cm','Alto cm','','number','min="0.01" step="0.01" required'):''}${input('unit_cost','Coste unitario manual (vacío = catálogo)','','number','min="0" step="0.0001"')}${input('handling','Manipulación €/ud',0,'number','min="0" step="0.01"')}${input('setup','Preparación total €',0,'number','min="0" step="0.01"')}${input('design','Diseño total €',0,'number','min="0" step="0.01"')}${input('supplements_total','Suplementos tallas / colores total €',0,'number','min="0" step="0.01"')}${select('mode','Tipo de cálculo',[['margin','Margen sobre venta'],['markup','Recargo sobre coste']],p.mode||'margin',false)}${input('margin','Porcentaje (vacío = reglas del catálogo)','','number','min="0" step="0.01"')}${input('price','PVP manual sin IVA (vacío = automático)','','number','min="0" step="0.01"')}${input('discount','Descuento %',0,'number','min="0" max="100" step="0.01"')}${input('vat','IVA %',p.vat??21,'number','min="0" max="100" step="0.01"')}${area('description','Descripción comercial opcional (sin costes ni medidas DTF)')}</div>`;
  const colors=data.color.filter(c=>c.active&&(p.color_ids||[]).includes(c.id));const sizes=data.size.filter(s=>s.active&&(p.size_ids||[]).includes(s.id));
  if(colors.length&&sizes.length)html+=`<fieldset><legend>Reparto por color y talla (sustituye la cantidad general)</legend><div class="scroll"><table class="distribution"><thead><tr><th>Color</th>${sizes.map(s=>`<th>${esc(s.name)}</th>`).join('')}</tr></thead><tbody>${colors.map(c=>`<tr><td>${esc(c.code+' · '+c.name)}</td>${sizes.map(s=>`<td><input aria-label="${esc(c.name+' '+s.name)}" class="qty" data-color="${c.id}" data-size="${s.id}" type="number" min="0" step="1" value="0"></td>`).join('')}</tr>`).join('')}</tbody></table></div></fieldset>`;
  const groups=new Map();data.rate.filter(r=>r.active&&(p.techniques||[]).includes(r.technique)).forEach(r=>groups.set([r.technique,r.zone,r.size].join('|'),r));
  html+=`<fieldset><legend>Personalización · selecciona zonas y tamaños</legend>${[...groups.values()].map((r,i)=>`<div class="card"><label><input class="personalization" type="checkbox" data-rate="${r.id}"> ${esc(r.technique+' · '+r.zone+' · '+r.size)} (${r.basis==='sale'?'tarifa de venta':'coste'})</label>${r.unit==='cm2'?input('pw_'+r.id,'Ancho impresión cm','','number','min="0.01" step="0.01"')+input('ph_'+r.id,'Alto impresión cm','','number','min="0.01" step="0.01"'):r.unit==='minute'?input('minutes_'+r.id,'Minutos por unidad','','number','min="0.01" step="0.01"'):''}</div>`).join('')||'<p class="muted">No hay tarifas de técnicas compatibles. Puedes crearlas en Tarifas y costes.</p>'}</fieldset><div class="toolbar">${button('Calcular precio','calculate')}</div><div id="calculation" aria-live="polite"></div>`;
  $('configuration').innerHTML=html;
}
function configurationInput(){
  const f=new FormData($('editform'));const x=Object.fromEntries(f);if(x.margin==='')delete x.margin;
  x.distribution=[...document.querySelectorAll('.qty')].filter(el=>Number(el.value)>0).map(el=>({color_id:el.dataset.color,size_id:el.dataset.size,quantity:el.value}));
  x.personalizations=[...document.querySelectorAll('.personalization:checked')].map(el=>{const r=find('rate',el.dataset.rate);return{technique:r.technique,zone:r.zone,size:r.size,width_cm:x['pw_'+r.id],height_cm:x['ph_'+r.id],minutes:x['minutes_'+r.id]}});
  return x;
}
async function calculate(){
  const input=configurationInput();const preview=await api('calculate',input);calculated={input,preview};
  $('calculation').innerHTML=`<div class="card"><h3>${esc(preview.description)}</h3><p>${preview.quantity} unidades · Recomendado: ${preview.recommended==null?'Pendiente de costes':money(preview.recommended)} / ud</p><p class="big">${money(preview.price)} / ud · Total ${money(preview.total)}</p><p>Coste: ${money(preview.cost)} · Beneficio: ${preview.profit==null?'No calculable':money(preview.profit)} · Margen: ${preview.real_margin==null?'No calculable':preview.real_margin+' %'}</p>${preview.real_margin!==null&&preview.real_margin<30?'<p class="error">Margen inferior al 30 %. Revisa el precio antes de añadirlo.</p>':''}${preview.cost==null?'<p class="muted">Faltan costes internos. Una tarifa de venta no permite calcular la rentabilidad real.</p>':''}</div>`;
  return calculated;
}
async function saveQuote(){captureDraft();const payload={...draft,lines:draft.lines.map(l=>l.saved_index!==undefined?{saved_index:l.saved_index}:l.input)};const q=await api('save_quote',payload);await load();notice('Presupuesto guardado con su desglose y precios históricos.');openQuote(q.id);}
function openQuote(id){
  view='quotes';const q=find('quote',id);$('title').textContent='Presupuesto '+q.name;
  $('content').innerHTML=`<div class="toolbar"><span class="tag">${statuses[q.status]} · versión ${q.version}</span>${q.status==='draft'?button('Editar borrador','editquote',q.id)+button('Marcar emitido','issued',q.id):''}${q.status==='issued'?button('Marcar aceptado','accepted',q.id)+button('Marcar rechazado','rejected',q.id):''}${button('Imprimir / PDF','print',q.id)}${button('Duplicar','duplicatequote',q.id)}${button('Versiones','quotehistory',q.id)}</div><div class="card"><h2>${esc(q.customer.name)}</h2><p>${esc(q.date)} · Validez ${q.valid_days} días</p>${quoteLines(q,true)}<p class="big">Total ${money(q.total)}</p><p>${esc(q.notes)}</p></div>`;
}
function quoteLines(q,internal=false){return `<div class="scroll"><table><thead><tr><th>Descripción</th><th>Cantidad</th><th>Precio</th><th>Dto.</th><th>Base</th>${internal?'<th>Rentabilidad interna</th>':''}</tr></thead><tbody>${q.lines.map(l=>`<tr><td>${esc(l.description)}${internal?`<small>${esc((l.input.distribution||[]).map(d=>`${d.color} / ${d.size}: ${d.quantity}`).join(' · '))}</small><small>${esc(l.technical.map(t=>[t.technique,t.zone,t.size].join(' ')).join(' · '))}</small>`:''}</td><td>${l.quantity}</td><td>${money(l.price)}</td><td>${l.discount}%</td><td>${money(l.base)}</td>${internal?`<td>Coste ${money(l.cost)}<small>Margen ${l.real_margin==null?'pendiente':l.real_margin+' %'}</small></td>`:''}</tr>`).join('')}</tbody></table></div>`}
function printQuote(q){
  $('print').innerHTML=`<header><div><h1>${esc(q.company.name)}</h1><small>${esc(q.company.tax_id||'')}</small><small>${esc(q.company.address||'')}</small><small>${esc(q.company.email||'')} · ${esc(q.company.phone||'')}</small></div><div><h2>PRESUPUESTO ${esc(q.name)}</h2><p>Fecha ${esc(q.date)} · Validez ${q.valid_days} días</p></div></header><h3>${esc(q.customer.name)}</h3><p>${esc(q.customer.tax_id)}<br>${esc(q.customer.address)}</p>${quoteLines(q)}<div class="print-total"><p>Base imponible ${money(q.base)}</p>${[...new Set(q.lines.map(l=>l.vat))].map(v=>`<p>IVA ${v}%: ${money(q.lines.filter(l=>l.vat===v).reduce((a,l)=>a+l.tax,0))}</p>`).join('')}<strong>TOTAL ${money(q.total)}</strong></div><p>Forma de pago: ${esc(q.payment)}</p><p class="print-notes">${esc(q.notes)}</p>`;window.print();
}
async function history(kind,id){
  const revisions=await api('history&kind='+encodeURIComponent(kind)+'&id='+encodeURIComponent(id));
  $('title').textContent='Histórico de versiones';
  $('content').innerHTML=`<div class="card">${revisions.map(r=>`<details><summary>Versión ${r.version} · ${esc(r.updated_at)}</summary>${kind==='quote'?quoteLines(r,true):`<pre>${esc(JSON.stringify(r,null,2))}</pre>`}</details>`).join('')}</div>`;
}
function importCsv(){
  editing={kind:'import'};$('fields').innerHTML=`<h2>Importar productos CSV</h2><p>Guarda el Excel como CSV UTF-8. Columnas: PRODUCTO;REFERENCIA;COSTE;PVP;CATEGORIA;COMENTARIOS. No se reemplazan productos existentes.</p><input id="csvfile" type="file" accept=".csv,text/csv"><div id="importpreview"></div><p id="formerror" class="error" hidden></p>`;$('editor').showModal();$('editform').querySelector('button[type=submit]').textContent='Confirmar importación';
}
function parseCsv(text){
  const delimiter=text.split('\n')[0].includes(';')?';':',';const rows=[];let row=[],value='',quoted=false;
  for(let i=0;i<text.length;i++){const c=text[i];if(c==='"'){if(quoted&&text[i+1]==='"'){value+='"';i++}else quoted=!quoted;}else if(c===delimiter&&!quoted){row.push(value);value=''}else if(c==='\n'&&!quoted){row.push(value.replace(/\r$/,''));rows.push(row);row=[];value=''}else value+=c;}
  if(quoted)throw Error('CSV con comillas sin cerrar.');if(value||row.length){row.push(value);rows.push(row)}return rows;
}
async function previewImport(file){
  if(!file||file.size>1000000)throw Error('Selecciona un CSV de hasta 1 MB.');const rows=parseCsv((await file.text()).replace(/^\uFEFF/,''));const headers=rows.shift().map(x=>x.trim().toUpperCase());
  if(!headers.includes('PRODUCTO'))throw Error('Falta la columna PRODUCTO.');
  const items=rows.filter(r=>r.some(x=>x.trim())).map(r=>Object.fromEntries(headers.map((k,i)=>[k,(r[i]||'').trim()])));
  if(items.length>500)throw Error('Importa como máximo 500 productos por archivo.');
  editing.items=items.map(r=>({name:r.PRODUCTO,reference:r.REFERENCIA||'',cost:r.COSTE?r.COSTE.replace(',','.'):null,pvp:r.PVP?r.PVP.replace(',','.'):null,category:r.CATEGORIA||'Otros',notes:r.COMENTARIOS||'',active:true,min_qty:1,vat:21,margin:35,mode:'margin',calculation:'unit',techniques:[],color_ids:[],size_ids:[]}));
  for(const r of editing.items){if(!r.name||[r.cost,r.pvp].some(v=>v!==null&&(!Number.isFinite(Number(v))||Number(v)<0)))throw Error('Hay nombres o importes no válidos. Corrige el CSV antes de importar.');}
  $('importpreview').innerHTML=`<p>${editing.items.length} productos nuevos. Revisa antes de confirmar.</p><div class="scroll"><table>${editing.items.map(r=>`<tr><td>${esc(r.name)}</td><td>${esc(r.reference)}</td><td>${r.cost==null?'Sin coste':money(r.cost)}</td><td>${r.pvp==null?'Automático':money(r.pvp)}</td></tr>`).join('')}</table></div>`;
}
async function act(action,id){
  if(action==='new'||action==='quotes'){show(action);return}
  if(action==='add'){edit(view);return}if(action==='edit'){edit(view,id);return}if(action==='duplicate'){edit('product',id,true);return}
  if(action==='history'||action==='quotehistory'){await history(action==='quotehistory'?'quote':view,id);return}
  if(action==='configure'){configure();return}if(action==='calculate'){await calculate();return}if(action==='savequote'){await saveQuote();return}
  if(action==='removeline'){captureDraft();draft.lines.splice(Number(id),1);quoteEditor();return}if(action==='openquote'){openQuote(id);return}
  if(action==='editquote'){const q=find('quote',id);draft={...structuredClone(q),lines:q.lines.map((l,i)=>({saved_index:i,preview:l}))};quoteEditor();return}
  if(action==='duplicatequote'){const q=find('quote',id);draft={customer_id:q.customer_id,date:new Date().toLocaleDateString('en-CA'),valid_days:q.valid_days,payment:q.payment,notes:q.notes,lines:q.lines.map(l=>({input:structuredClone(l.input),preview:structuredClone(l)}))};notice('Copia preparada. Los precios se recalcularán con el catálogo actual al guardar.');quoteEditor();return}
  if(action==='print'){printQuote(find('quote',id));return}
  if(['issued','accepted','rejected'].includes(action)){const q=find('quote',id);await api('status',{id,version:q.version,status:action});await load();openQuote(id);return}
  if(action==='import'){importCsv();return}
  if(['checkcolors','uncheckcolors','checksizes','unchecksizes'].includes(action)){document.querySelectorAll(`[name="${action.endsWith('colors')?'color_ids':'size_ids'}"]`).forEach(x=>x.checked=!action.startsWith('un'));}
}
document.addEventListener('click',async event=>{const b=event.target.closest('button');if(!b)return;try{if(b.dataset.view){show(b.dataset.view);return}if(b.dataset.action){b.disabled=true;await act(b.dataset.action,b.dataset.id)}}catch(e){notice(e.message,true);if($('editor').open&&$('formerror')){$('formerror').hidden=false;$('formerror').textContent=e.message}}finally{b.disabled=false}});
$('cancel').onclick=()=>{$('editor').close();$('editform').querySelector('button[type=submit]').textContent='Guardar'};
$('editform').addEventListener('change',async event=>{try{if(event.target.name==='color_supplier_id')refreshColors([]);if(event.target.name==='product_id')productConfiguration();if(event.target.id==='csvfile')await previewImport(event.target.files[0]);}catch(e){$('formerror').hidden=false;$('formerror').textContent=e.message}});
$('editform').addEventListener('submit',async event=>{event.preventDefault();const submit=event.target.querySelector('button[type=submit]');submit.disabled=true;try{
  if(editing.kind==='configuration'){const result=await calculate();draft.lines.push(result);$('editor').close();quoteEditor();}
  else if(editing.kind==='import'){if(!editing.items?.length)throw Error('Selecciona un CSV válido.');while(editing.items.length){await api('save',{kind:'product',data:editing.items[0]});editing.items.shift();}$('editor').close();await load();catalog();notice('Importación completada.');}
  else await saveRecord(event);
}catch(e){$('formerror').hidden=false;$('formerror').textContent=e.message}finally{submit.disabled=false;submit.textContent='Guardar'}});
document.addEventListener('input',event=>{if(event.target.id==='filter'){filter=event.target.value;const position=event.target.selectionStart;if(view==='quotes')quotes();else catalog();$('filter').focus();$('filter').setSelectionRange(position,position);}});
load().then(()=>show('home')).catch(e=>notice(e.message,true));
