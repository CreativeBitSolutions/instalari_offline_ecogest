(function(){
 'use strict';
 const token=document.querySelector('meta[name="casa-csrf"]')?.content||'';
 function forms(){document.querySelectorAll('form').forEach(function(form){if((form.method||'').toLowerCase()==='post'&&!form.querySelector('[name="casa_csrf"]')){const input=document.createElement('input');input.type='hidden';input.name='casa_csrf';input.value=token;form.appendChild(input);}});}
 document.addEventListener('DOMContentLoaded',forms);document.addEventListener('submit',forms,true);
 const xhrOpen=XMLHttpRequest.prototype.open,xhrSend=XMLHttpRequest.prototype.send;
 XMLHttpRequest.prototype.open=function(method,url){const target=new URL(url,location.href);this.casaSameOrigin=target.origin===location.origin;this.casaWrite=this.casaSameOrigin&&String(method).toUpperCase()==='POST'&&/\/(update_factura|add_product|delete_item|set_zero_item|modifica_cantitate_factura|modifica_pret_factura|modifica_um_factura)\.php$/.test(target.pathname);return xhrOpen.apply(this,arguments);};
 XMLHttpRequest.prototype.send=function(){if(this.casaSameOrigin)this.setRequestHeader('X-Casa-CSRF',token);if(this.casaWrite)this.addEventListener('loadend',function(){setTimeout(tick,0);},{once:true});return xhrSend.apply(this,arguments);};
 const originalFetch=window.fetch;
 window.fetch=function(resource,options){options=options||{};const url=new URL(typeof resource==='string'?resource:resource.url,location.href);if(url.origin===location.origin){options.headers=new Headers(options.headers||{});options.headers.set('X-Casa-CSRF',token);}return originalFetch.call(this,resource,options);};
 let busy=false,lastPull=0,listVersion='',pendingRows=null,verified=false,detailsPending=false,detailsBusy=false;
 const page=location.pathname.split('/').pop();
 async function request(url,options){
  const response=await fetch(url,options||{});
  let data;try{data=await response.json();}catch(e){throw new Error('Răspuns local incomplet, HTTP '+response.status+'. Verificați jurnalul PHP.');}
  if(!response.ok||data.success===false)throw new Error(data.error||data.message||'Operație neconfirmată.');return data;
 }
 function status(message,warning){const node=document.getElementById('casa-pull-status');if(node){node.textContent=message;node.className='alert '+(warning?'alert-warning':'alert-info')+' m-3';}}
 function applyRows(){
  const table=window.facturiDataTable;
  if(!table||!pendingRows||document.querySelector('.modal.show,.dropdown-menu.show')||document.activeElement?.matches('input,select,textarea'))return;
  const holder=document.createElement('tbody');holder.innerHTML=pendingRows.rows;
  const position=window.scrollY,oldPage=table.page();
  table.clear();table.rows.add(Array.from(holder.children));table.draw(false);
  table.page(Math.min(oldPage,Math.max(0,table.page.info().pages-1))).draw('page');
  window.scrollTo(0,position);listVersion=pendingRows.version;pendingRows=null;
 }
 async function refreshList(){
  if(!window.facturiDataTable)return;
  const data=await request('facturi.php?casa_fragment=1&version='+encodeURIComponent(listVersion));
  if(data.rows!==undefined&&data.version!==listVersion){pendingRows=data;applyRows();}
 }
 async function refreshDetails(){
  if(!detailsPending||detailsBusy||page!=='detalii_factura.php'||document.querySelector('.modal.show'))return;
  detailsBusy=true;
  try{
  const response=await fetch(location.href,{cache:'no-store'});if(!response.ok)return;
  const doc=new DOMParser().parseFromString(await response.text(),'text/html');
  const fresh=doc.querySelector('.container-fluid'),current=document.querySelector('.container-fluid');
  if(!fresh||!current)return;
  fresh.querySelectorAll('script').forEach(function(script){script.remove();});
  const position=window.scrollY;
  if(window.jQuery&&jQuery.fn.dataTable&&jQuery.fn.dataTable.isDataTable('#example'))jQuery('#example').DataTable().destroy();
  current.replaceWith(fresh);forms();
  if(window.jQuery&&jQuery.fn.dataTable&&document.querySelector('#example'))jQuery('#example').DataTable({pageLength:50});
  window.scrollTo(0,position);
  detailsPending=false;
  }catch(e){status('Detaliile actualizate nu au putut fi afișate. Se va reîncerca.',true);}finally{detailsBusy=false;}
 }
 async function pull(){
  const onlineId=document.querySelector('nav.casa')?.dataset.onlineInvoice||'0';
  let gate='',done=false,changed=0,readonly=false;
  if(page==='facturi.php')await request('pull_online.php',{method:'POST',body:new URLSearchParams({action:'company'})});
  while(!done){
   const data=await request('pull_online.php',{method:'POST',body:new URLSearchParams({action:'invoices',gate:gate,invoice_id:page==='facturi.php'?'0':onlineId})});
   gate=data.gate;done=data.done;changed+=data.count;readonly=data.readonly;
   if(data.count)await refreshList();
  }
  verified=page==='facturi.php'||onlineId==='0';lastPull=Date.now();
  status('Verificare online încheiată la '+new Date().toLocaleTimeString('ro-RO')+'.',false);
  if(page==='factura.php'&&readonly){
   status('Factura este administrată online. Formularul nu mai poate fi salvat offline. Deschideți Detalii pentru versiunea actualizată.',true);
   document.querySelectorAll('form button[type="submit"],form input[type="submit"]').forEach(function(button){button.disabled=true;});
   const node=document.getElementById('casa-pull-status');
   if(node){const link=document.createElement('a');link.className='btn btn-sm btn-primary ml-2';link.textContent='Detalii actualizate';link.href='detalii_factura.php?id_factura='+encodeURIComponent(new URL(location.href).searchParams.get('id_factura')||'');node.appendChild(link);}
  }
  if(changed&&page==='detalii_factura.php'){detailsPending=true;await refreshDetails();}
 }
 async function tick(){
  if(busy||document.hidden)return;busy=true;
  try{
   try{const data=await request('sync_tick.php',{method:'POST'});const node=document.getElementById('casa-sync');if(node)node.textContent=data.message||'Sincronizare în așteptare';}
   catch(e){const node=document.getElementById('casa-sync');if(node)node.textContent=e.message;}
   if(Date.now()-lastPull>60000){
    try{await pull();}catch(e){verified=false;lastPull=Date.now()-45000;status(e.message+' Se afișează copia locală. Verificați seria și ultimul număr facturat online înainte de emitere. Reîncercare automată.',true);}
   }
   await refreshList();
  }catch(e){status(e.message,true);}finally{busy=false;}
 }
 document.addEventListener('click',function(event){
  const link=event.target.closest('a[href="factura.php"]');
  if(link&&!verified&&!confirm('Ultimul număr online nu este confirmat. Continuați offline și verificați manual seria și ultimul număr înainte de emitere?'))event.preventDefault();
 });
 document.addEventListener('DOMContentLoaded',tick);setInterval(tick,15000);setInterval(function(){applyRows();refreshDetails();},3000);
 document.addEventListener('visibilitychange',function(){if(!document.hidden){lastPull=0;tick();}});
})();
