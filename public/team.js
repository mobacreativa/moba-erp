'use strict';
const $=id=>document.getElementById(id);
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const roles={admin:'Administrador',sales:'Comercial',production:'Producción'};
const states={pending:'Pendiente',in_progress:'En curso',blocked:'Bloqueada',completed:'Terminada'};
let data,view='orders',editing;
const input=(k,label,v='',type='text',extra='')=>`<label>${esc(label)}<input name="${k}" type="${type}" value="${esc(v)}" ${extra}></label>`;
const area=(k,label,v='')=>`<label class="full">${esc(label)}<textarea name="${k}" rows="6" maxlength="20000">${esc(v)}</textarea></label>`;
const select=(k,label,options,v='')=>`<label>${esc(label)}<select name="${k}" aria-label="${esc(label)}">${options.map(([id,t])=>`<option value="${esc(id)}" ${String(id)===String(v)?'selected':''}>${esc(t)}</option>`).join('')}</select></label>`;
const button=(t,a,id='')=>`<button type="button" data-action="${a}" data-id="${esc(id)}">${esc(t)}</button>`;
function notice(t,error=false){$('notice').textContent=t;$('notice').className=error?'error':'success';}
async function api(action,payload){const r=await fetch('/?api='+action,payload===undefined?{}:{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({csrf:data.csrf,payload:JSON.stringify(payload)})});const j=await r.json();if(!j.ok)throw Error(j.error);return j.data;}
async function load(){data=await api('team_load');}
function render(){
  $('title').textContent=view==='users'?'Usuarios':'Órdenes de trabajo';
  if(view==='users'){
    $('content').innerHTML=`<p>El administrador principal conserva su acceso actual. Desactivar un usuario o cambiar sus datos cierra sus sesiones anteriores.</p>${button('+ Crear usuario','user')}<div class="card scroll"><table><thead><tr><th>Nombre</th><th>Usuario</th><th>Rol</th><th>Estado</th><th></th></tr></thead><tbody>${data.users.map(u=>`<tr><td>${esc(u.name)}</td><td>${esc(u.username)}</td><td>${roles[u.role]}</td><td>${Number(u.active)?'Activo':'Inactivo'}</td><td>${button('Editar','user',u.id)}</td></tr>`).join('')}</tbody></table></div>`;
  }else{
    $('content').innerHTML=`<p>${esc(data.actor.name)} · ${roles[data.actor.role]}</p>${data.actor.role!=='production'?button('+ Crear orden','order'):''}<div class="card scroll"><table><thead><tr><th>Trabajo</th><th>Responsable</th><th>Fecha prevista</th><th>Estado</th><th></th></tr></thead><tbody>${data.orders.map(o=>`<tr><td>${esc(o.name)}<small>${esc(o.quote_name)}</small></td><td>${esc(o.assignee_name)}</td><td>${esc(o.due_date||'Sin fecha')}</td><td>${states[o.status]}</td><td>${button('Abrir','open',o.id)}</td></tr>`).join('')||'<tr><td colspan="5">No hay órdenes asignadas.</td></tr>'}</tbody></table></div>`;
  }
}
function dialog(action,r,fields){editing={action,record:r};$('fields').innerHTML=fields;$('editor').showModal();}
function user(id){const u=data.users.find(x=>String(x.id)===id)||{active:1,role:'production'};dialog('team_user',u,`<h2 class="full">${u.id?'Editar usuario':'Crear usuario'}</h2>`+input('name','Nombre',u.name,'text','required maxlength="190"')+input('username','Usuario',u.username,'text','required minlength="3" maxlength="80" autocomplete="off"')+select('role','Rol',Object.entries(roles),u.role)+input('password',u.id?'Nueva contraseña (vacía para conservar)':'Contraseña','','password',`${u.id?'':'required'} minlength="12" maxlength="72" autocomplete="new-password"`)+`<label><input name="active" type="checkbox" ${Number(u.active)?'checked':''}>Usuario activo</label><p class="full">Usa una contraseña única de al menos 12 caracteres. La contraseña no se mostrará de nuevo.</p>`);}
function order(id){const o=data.orders.find(x=>x.id===id)||{};dialog('team_order',o,'<h2 class="full">Orden de trabajo</h2>'+input('name','Título',o.name,'text','required maxlength="190"')+select('assignee_id','Responsable',[['','Seleccionar…'],...data.users.filter(u=>Number(u.active)&&['admin','production'].includes(u.role)).map(u=>[u.id,u.name])],o.assignee_id)+input('due_date','Fecha prevista',o.due_date,'date')+select('quote_id','Presupuesto de origen',[['','Sin presupuesto'],...data.quotes.map(q=>[q.id,q.name])],o.quote_id)+area('instructions','Instrucciones de producción (sin precios)',o.instructions));}
function open(id){const o=data.orders.find(x=>x.id===id);$('content').innerHTML=`<div class="toolbar">${button('Volver','back')}${data.actor.role!=='production'?button('Editar asignación','order',id):''}${data.actor.role!=='sales'?button('Actualizar trabajo','progress',id):''}${button('Histórico','history',id)}</div><article class="card"><h2>${esc(o.name)}</h2><p>${esc(o.assignee_name)} · ${esc(o.due_date||'Sin fecha')} · ${states[o.status]}</p><p>${esc(o.quote_name)}</p><h3>Instrucciones</h3><p class="preserve-lines">${esc(o.instructions)}</p><h3>Última nota de avance</h3><p class="preserve-lines">${esc(o.progress||'Sin notas')}</p></article>`;}
document.addEventListener('click',async event=>{
  const b=event.target.closest('[data-action]');if(!b)return;const id=b.dataset.id;
  try{switch(b.dataset.action){case'user':user(id);break;case'order':order(id);break;case'open':open(id);break;case'back':render();break;case'progress':{const o=data.orders.find(x=>x.id===id);dialog('team_progress',o,'<h2 class="full">Actualizar trabajo</h2>'+select('status','Estado',Object.entries(states),o.status)+area('progress','Nota de avance',o.progress));break;}case'history':{const rows=await api('team_history&id='+encodeURIComponent(id));$('content').innerHTML=`${button('Volver a la orden','open',id)}<h2>Histórico de cambios</h2>${rows.map(o=>`<article class="card"><h3>Versión ${o.version} · ${states[o.status]}</h3><p>${esc(o.updated_at)} · ${esc(o.actor)} · ${esc(o.assignee_name)}</p><p class="preserve-lines">${esc(o.instructions)}</p><p class="preserve-lines">${esc(o.progress)}</p></article>`).join('')}`;break;}}}catch(e){notice(e.message,true);}
});
$('orders').addEventListener('click',()=>{view='orders';render();});
$('users')?.addEventListener('click',()=>{view='users';render();});
$('cancel').addEventListener('click',()=>{$('editor').close();$('editform').reset();$('fields').innerHTML='';});
$('editor').addEventListener('cancel',()=>{$('editform').reset();$('fields').innerHTML='';});
$('editform').addEventListener('submit',async e=>{e.preventDefault();const submit=e.submitter;submit.disabled=true;const f=new FormData(e.target);const p=Object.fromEntries(f);p.id=editing.record.id||'';p.version=editing.record.version||0;if(editing.action==='team_user'){p.id=editing.record.id||0;p.active=f.has('active');}
  try{const result=await api(editing.action,p);$('editor').close();$('fields').innerHTML='';await load();render();notice('Guardado.');if(editing.action!=='team_user')open(result.id);}catch(error){notice(error.message,true);$('fields').querySelector('.error')?.remove();const msg=document.createElement('p');msg.className='error full';msg.setAttribute('role','alert');msg.textContent=error.message;$('fields').prepend(msg);}finally{submit.disabled=false;}
});
load().then(()=>{render();notice('');}).catch(e=>notice(e.message,true));
