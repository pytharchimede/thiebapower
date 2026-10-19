(()=>{
 'use strict';
 const form=document.getElementById('withdrawal-form'),dataNode=document.getElementById('tb-withdraw-data');
 if(!form||!dataNode)return;
 const data=JSON.parse(dataNode.textContent),amount=form.elements.amount;
 const el=id=>document.getElementById('tb-withdraw-'+id);
 const money=value=>new Intl.NumberFormat('fr-FR',{maximumFractionDigits:0}).format(value)+' FCFA';
 let current=null,fee=0,total=0;
 const update=()=>{
  current=data.channels[form.elements.channel.value]||null;
  const raw=amount.value,n=Number(raw),valid=/^[1-9][0-9]*$/.test(raw)&&Number.isSafeInteger(n)&&n<=999999999;
  el('all').disabled=!current||current.maximum<1;
  el('max').textContent=current?money(current.maximum):'Indisponible';
  amount.max=String(current?.maximum??0);
  fee=current&&valid?current.fixed+Math.ceil(n*current.basis_points/10000):0;
  total=valid?n+fee:0;
  el('net').textContent=valid?money(n):'0 FCFA';
  el('fee').textContent=current&&valid?money(fee):'—';
  el('total').textContent=current&&valid?money(total):'—';
  el('left').textContent=current&&valid?money(current.balance-total):'—';
  el('rate').textContent=current?'Barème payout configuré : '+(current.basis_points/100).toLocaleString('fr-FR')+' %'+(current.fixed?' + '+money(current.fixed):'')+'. Frais arrondis au FCFA supérieur.':'';
  let error='';
  if(!current)error='Solde ou frais indisponibles pour ce canal. Actualisez la page ou faites vérifier le barème payout.';
  else if(current.maximum<1)error='Solde insuffisant pour effectuer un retrait, frais inclus.';
  else if(raw&&!valid)error='Saisissez un montant entier supérieur à zéro.';
  else if(valid&&n>current.maximum)error='Maximum disponible, frais inclus : '+money(current.maximum)+'.';
  amount.setCustomValidity(error);
  el('error').textContent=error;
  el('submit').disabled=!!error||!valid;
 };
 amount.addEventListener('input',update);
 form.querySelectorAll('[name="channel"]').forEach(input=>input.addEventListener('change',update));
 el('all').addEventListener('click',()=>{if(current){amount.value=String(current.maximum);update();}});
 form.addEventListener('submit',event=>{
  update();
  if(el('submit').disabled||!form.reportValidity()){event.preventDefault();return;}
  const label=form.querySelector('[name="channel"]:checked').parentElement.textContent.trim();
  const message='Envoyer '+money(Number(amount.value))+' à '+form.elements.recipient_name.value+' au '+form.elements.phone.value+' via '+label+' ?\nFrais estimés : '+money(fee)+'\nTotal débité estimé : '+money(total);
  if(!window.confirm(message)){event.preventDefault();return;}
  el('submit').disabled=true;el('submit').textContent='Envoi en cours…';
 });
 update();
})();
