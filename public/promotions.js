(() => {
 'use strict';
 const button=document.getElementById('preview-promotion'),form=document.getElementById('rental-checkout');
 if(!button||!form)return;
 const code=document.getElementById('promotion-code'),status=document.getElementById('promotion-status'),previous=document.getElementById('previous-rental-token');
 const field=name=>form.elements.namedItem(name);
 try{previous.value=localStorage.getItem('tbp_last_receipt_token')||'';}catch{}
 let revision=0,deposit=0;
 const money=value=>new Intl.NumberFormat('fr-FR').format(value)+' FCFA';
 const reset=()=>{
  document.getElementById('summary-fee').textContent=money(Number(window.TB_PRICE));
  document.getElementById('summary-deposit').textContent=money(deposit);
  document.getElementById('summary-total').textContent=money(Number(window.TB_PRICE)+deposit);
 };
 const invalidate=message=>{++revision;reset();status.textContent=message;};
 window.addEventListener('tbp-battery-selected',e=>{deposit=Number(e.detail.deposit);invalidate('Vérifiez votre code pour cette batterie.');});
 field('phone').addEventListener('input',()=>invalidate('Vérifiez le code avec ce numéro de téléphone.'));
 code.addEventListener('input',()=>invalidate('Vérifiez ce code pour connaître la remise sur la caution.'));
 button.addEventListener('click',async()=>{
  if(!code.value.trim()){status.textContent='Saisissez votre code promotionnel.';code.focus();return;}
  if(!field('phone').value.trim()){status.textContent='Renseignez votre téléphone avant de vérifier le code.';field('phone').focus();return;}
  if(!field('battery_id').value){status.textContent='Sélectionnez une batterie disponible.';return;}
  button.disabled=true;button.textContent='Vérification…';status.textContent='Vérification du code en cours…';
  const current=++revision,controller=new AbortController(),timeout=setTimeout(()=>controller.abort(),15000);
  try{
   const response=await fetch('/promotions/preview',{method:'POST',signal:controller.signal,headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify({code:code.value.trim(),phone:field('phone').value,previous_token:previous.value,battery_id:field('battery_id').value,station_code:field('station_code').value})});
   let result;try{result=await response.json();}catch{throw new Error('La vérification est momentanément indisponible. Réessayez.');}
   if(current!==revision)return;
   if(!response.ok)throw new Error(result.error||'Code indisponible.');
   if(![result.fee,result.deposit,result.discount].every(v=>typeof v==='number'&&Number.isFinite(v)&&v>=0))throw new Error('Réponse invalide. Réessayez.');
   document.getElementById('summary-fee').textContent=money(result.fee);
   document.getElementById('summary-deposit').textContent=money(result.deposit);
   document.getElementById('summary-total').textContent=money(result.fee+result.deposit);
   status.textContent='Code appliqué : −'+money(result.discount)+' sur la caution. Location inchangée. Le code sera revérifié au paiement.';
  }catch(error){if(current===revision){reset();status.textContent=error.name==='AbortError'?'La vérification prend trop de temps. Réessayez.':error.message;}}
  finally{clearTimeout(timeout);button.disabled=false;button.textContent='Vérifier le code';}
 });
})();
