(()=>{'use strict';const toggle=document.getElementById('wallet-live');if(!toggle)return;
let timer=null,busy=false,failures=0;const status=document.getElementById('wallet-live-status');
const schedule=()=>{clearTimeout(timer);if(toggle.checked&&!document.hidden)timer=setTimeout(refresh,Math.min(60000,5000*2**failures));};
async function refresh(){if(busy||!toggle.checked||document.hidden)return;busy=true;try{
 const response=await fetch('/admin/deposit-wallet/snapshot',{cache:'no-store',headers:{Accept:'application/json'}});if(!response.ok)throw new Error('unavailable');const data=await response.json();
 for(const row of data.rows){const cell=document.querySelector('[data-wallet-rental="'+Number(row.id)+'"] [data-wallet-remaining]');if(!cell)continue;cell.replaceChildren();if(row.caution.paid){for(const text of ['Payée : '+row.caution.deposit+' FCFA','Retenue : '+row.caution.deduction+' FCFA','À restituer : '+row.caution.refund+' FCFA']){const line=document.createElement('div');line.textContent=text;cell.append(line);}}else cell.textContent='—';}
 failures=0;status.textContent='Cautions actualisées à '+new Date().toLocaleTimeString('fr-FR')+'. Rechargez la page pour les nouveaux transferts et confirmations.';
 }catch(e){failures=Math.min(failures+1,4);status.textContent='Actualisation indisponible. Derniers montants conservés ; nouvelle tentative différée.';}finally{busy=false;schedule();}}
 toggle.addEventListener('change',()=>{clearTimeout(timer);if(toggle.checked)refresh();else status.textContent='Suivi en pause.';});document.addEventListener('visibilitychange',()=>{clearTimeout(timer);if(!document.hidden&&toggle.checked)refresh();});
})();
