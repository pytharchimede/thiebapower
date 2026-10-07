(()=>{'use strict';const toggle=document.getElementById('wallet-live');if(!toggle)return;
let timer=null,busy=false,failures=0;const status=document.getElementById('wallet-live-status'),rows=document.getElementById('wallet-rows'),totals=document.getElementById('wallet-totals');
const schedule=()=>{clearTimeout(timer);if(toggle.checked&&!document.hidden)timer=setTimeout(refresh,Math.min(60000,5000*2**failures));};
async function refresh(){if(busy||!toggle.checked||document.hidden)return;busy=true;const controller=new AbortController();const timeout=setTimeout(()=>controller.abort(),10000);try{
 const response=await fetch('/admin/deposit-wallet/snapshot',{cache:'no-store',signal:controller.signal,headers:{Accept:'application/json'}});if(!response.ok)throw new Error('unavailable');const data=await response.json();
 if(typeof data.totals_html!=='string'||typeof data.rows_html!=='string'||typeof data.signature!=='string'||!Array.isArray(data.rows))throw Error('invalid response');
 // HTML is rendered and escaped by the authenticated same-origin endpoint, with the same permissions as this view.
 totals.innerHTML=data.totals_html;
 const editing=rows.contains(document.activeElement)&&document.activeElement.matches('input,textarea,select');
 if(rows.dataset.signature!==data.signature&&!editing){
  const open=new Set(Array.from(rows.querySelectorAll('details[open][data-wallet-details]'),el=>el.dataset.walletDetails));
  rows.innerHTML=data.rows_html;rows.dataset.signature=data.signature;
  for(const el of rows.querySelectorAll('details[data-wallet-details]'))el.open=open.has(el.dataset.walletDetails);
 }
 for(const row of data.rows){const cell=rows.querySelector('[data-wallet-id="'+Number(row.id)+'"] [data-wallet-remaining]');if(!cell)continue;cell.replaceChildren();if(row.caution.paid){for(const text of ['Payée : '+row.caution.deposit+' FCFA','Retenue : '+row.caution.deduction+' FCFA','À restituer : '+row.caution.refund+' FCFA']){const line=document.createElement('div');line.textContent=text;cell.append(line);}}else cell.textContent='—';}
 failures=0;status.textContent='Cautions, transferts et rapprochements actualisés à '+new Date().toLocaleTimeString('fr-FR')+(editing&&rows.dataset.signature!==data.signature?' · Mise à jour des actions après votre saisie.':'.');
 }catch(e){failures=Math.min(failures+1,4);status.textContent='Actualisation indisponible. Dernières données conservées ; nouvelle tentative différée.';}finally{clearTimeout(timeout);busy=false;schedule();}}
 toggle.addEventListener('change',()=>{clearTimeout(timer);if(toggle.checked)refresh();else status.textContent='Suivi en pause.';});document.addEventListener('visibilitychange',()=>{clearTimeout(timer);if(!document.hidden&&toggle.checked)refresh();});refresh();
})();
