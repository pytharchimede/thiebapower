(()=>{'use strict';let busy=false,timer=null,offset=0,failures=0,lastSuccess=Date.now();const data=new Map();
const seen=new Set();const cells=()=>Array.from(document.querySelectorAll('.tb-rental-deposit[data-reference]'));
function paint(){if(document.hidden)return;const stale=Date.now()-lastSuccess>60000;const now=Math.floor(Date.now()/1000+offset);
 for(const el of cells()){let b=data.get(el.dataset.reference);if(!b&&el.dataset.billing){try{b=JSON.parse(el.dataset.billing);data.set(el.dataset.reference,b);}catch{}}if(!b)continue;
 const lines=[];if(!b.deposit)lines.push('Caution désactivée');else if(!b.paid)lines.push('Caution de '+b.deposit+' FCFA · paiement à confirmer');else{
  let late=b.due?Math.max(0,(b.end||now)-b.due):0;if(!b.active&&!b.end)late=0;
  let deduction=b.rule==='prorata_grace5'?Math.ceil(b.fee*Math.max(0,late-300)/(Math.max(1,b.duration)*60)):Math.ceil(b.deposit*b.latePercent*Math.ceil(late/3600)/100);
  deduction=Math.min(b.deposit,Math.max(0,deduction));if(stale&&el.dataset.lastRefund)deduction=b.deposit-Number(el.dataset.lastRefund);
  const refund=b.deposit-deduction;el.dataset.lastRefund=refund;
  lines.push((b.active?'À restituer : ':'Caution restante : ')+refund.toLocaleString('fr-FR')+' / '+b.deposit.toLocaleString('fr-FR')+' FCFA','Retenue : '+deduction.toLocaleString('fr-FR')+' FCFA · Frais à notre charge');
  el.replaceChildren();const title=document.createElement('strong');title.textContent=lines[0];const detail=document.createElement('small');detail.textContent=lines[1];const bar=document.createElement('progress');bar.max=b.deposit;bar.value=refund;bar.setAttribute('aria-label','Part de caution restante');el.append(title,detail,bar);
 }
 if(!b.deposit||!b.paid)el.textContent=lines[0];el.classList.toggle('is-stale',stale);if(stale){const note=document.createElement('small');note.textContent='Dernière estimation conservée · connexion à vérifier';el.append(note);}
 }}
function schedule(){clearTimeout(timer);if(!document.hidden&&cells().length)timer=setTimeout(refresh,Math.min(60000,15000*2**failures));}
async function refresh(){if(busy||document.hidden||!cells().length)return;busy=true;const controller=new AbortController();const timeout=setTimeout(()=>controller.abort(),10000);
 try{const refs=[...new Set(cells().map(el=>el.dataset.reference))].slice(0,100);const r=await fetch('/admin/rentals/deposits?'+new URLSearchParams({references:refs.join(',')}),{cache:'no-store',signal:controller.signal});if(!r.ok)throw Error('unavailable');const d=await r.json();offset=d.at-Date.now()/1000;for(const row of d.rows)data.set(row.reference,row.billing);lastSuccess=Date.now();failures=0;paint();}catch{failures=Math.min(failures+1,2);}finally{clearTimeout(timeout);busy=false;schedule();}}
for(const el of cells())seen.add(el.dataset.reference);paint();refresh();setInterval(paint,1000);new MutationObserver(()=>{let changed=false;for(const el of cells()){if(!seen.has(el.dataset.reference)){seen.add(el.dataset.reference);changed=true;}}if(changed)refresh();}).observe(document.body,{childList:true,subtree:true});document.addEventListener('visibilitychange',()=>{clearTimeout(timer);if(!document.hidden){paint();refresh();}});
})();
