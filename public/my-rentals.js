(() => {
 'use strict';
 const list=document.getElementById('my-rentals-list');if(!list)return;
 const status=document.getElementById('my-rentals-status'),cards=new Map();let busy=false,timer,generation=0,formIndex=0;
 const states={pending_payment:'Paiement en attente',payment_failed:'Paiement échoué',payment_timeout:'Réservation expirée',payment_review:'Paiement à vérifier',releasing:'Sortie en cours',release_failed:'Sortie à vérifier',active:'Location en cours',returned:'Retour confirmé'};
 const issues={release:['Batterie non sortie','J’ai payé, mais je n’ai pas récupéré la batterie.'],return:['Retour non reconnu','J’ai rendu la batterie, mais la location continue.'],payment:['Problème de paiement','Mon paiement ou ma restitution est à vérifier.'],other:['Autre incident','Un problème avec la batterie ou le service.']};
 const el=(tag,className,text)=>{const n=document.createElement(tag);if(className)n.className=className;if(text!==undefined)n.textContent=text;return n;};
 const money=n=>new Intl.NumberFormat('fr-FR').format(n)+' FCFA';
 const date=raw=>{if(!raw)return 'Non confirmé';const d=new Date(raw.replace(' ','T')+'Z');return Number.isNaN(d.getTime())?raw:new Intl.DateTimeFormat('fr-FR',{day:'numeric',month:'short',hour:'2-digit',minute:'2-digit',timeZone:'Africa/Abidjan'}).format(d);};
 const post=async(url,data)=>{const response=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data),cache:'no-store'});const result=await response.json();if(!response.ok)throw new Error(result.error||'Service temporairement indisponible');return result;};
 const tokens=()=>{try{const values=JSON.parse(localStorage.getItem('tbp_rental_tokens')||'[]');const last=localStorage.getItem('tbp_last_receipt_token');return [...new Set([...(Array.isArray(values)?values:[]),last].filter(v=>typeof v==='string'&&/^[a-f0-9]{32}$/.test(v)))].slice(-30);}catch{return [];}};
 const link=(label,href,className)=>{const n=el('a',className,label);n.href=href;return n;};
 function incidentForm(r){
  const details=el('details','customer-incident'),summary=el('summary','');summary.append(el('span','','Signaler un incident'),el('span','incident-summary-arrow','＋'));details.append(summary);
  const form=el('form','customer-incident-form'),intro=el('div','incident-intro');intro.append(el('h3','','Comment pouvons-nous vous aider ?'),el('p','','Votre demande sera liée à cette location. Notre équipe retrouvera sa référence et vos coordonnées.'));form.append(intro);
  const fieldset=el('fieldset','incident-types');fieldset.append(el('legend','','Que s’est-il passé ?'));const grid=el('div','incident-type-grid');const group='incident-type-'+(++formIndex);
  for(const [value,[title,help]] of Object.entries(issues)){
   const label=el('label','incident-type'),radio=el('input','');radio.type='radio';radio.name=group;radio.value=value;radio.required=true;radio.checked=value===(r.status==='returned'?'return':['pending_payment','payment_failed','payment_review','payment_timeout'].includes(r.status)?'payment':'release');
   const text=el('span','incident-type-copy');text.append(el('strong','',title),el('small','',help));label.append(radio,text);grid.append(label);
  }
  fieldset.append(grid);form.append(fieldset);
  const label=el('label','incident-description','Décrivez le problème'),text=el('textarea','');text.name='message';text.id='incident-message-'+formIndex;text.required=true;text.minLength=5;text.maxLength=2000;text.rows=4;text.placeholder='Exemple : j’ai rendu la batterie vers 14 h à la station de Koumassi, mais elle apparaît toujours en location.';label.htmlFor=text.id;
  const hint=el('div','incident-message-hint');hint.append(el('span','','Ajoutez le lieu, l’heure et les détails utiles.'),el('span','incident-counter','0 / 2 000'));const counter=hint.lastChild;
  text.oninput=()=>{counter.textContent=text.value.length.toLocaleString('fr-FR')+' / 2 000';};form.append(label,text,hint);
  const result=el('p','incident-feedback');result.setAttribute('role','status');result.hidden=true;
  const actions=el('div','incident-actions'),cancel=el('button','customer-button customer-button-secondary','Fermer'),send=el('button','customer-button customer-button-primary','Envoyer le signalement');cancel.type='button';send.type='submit';cancel.onclick=()=>{details.open=false;summary.focus();};actions.append(cancel,send);form.append(result,actions);
  form.onsubmit=async event=>{
   event.preventDefault();if(send.disabled||!form.reportValidity())return;const message=text.value.trim();if(message.length<5){text.setCustomValidity('Décrivez le problème avec au moins cinq caractères.');text.reportValidity();text.oninput=()=>{text.setCustomValidity('');counter.textContent=text.value.length.toLocaleString('fr-FR')+' / 2 000';};return;}
   const radio=form.querySelector('input[type=radio]:checked');const revision=generation;details.dataset.sending='1';send.disabled=true;send.textContent='Envoi en cours…';result.hidden=true;
   try{const data=await post('/my-rentals/support',{token:r.token,issue:radio.value,message});if(revision!==generation)return;result.textContent=data.message;result.className='incident-feedback is-success';result.hidden=false;send.textContent='Signalement envoyé';text.disabled=true;fieldset.disabled=true;details.dataset.sent='1';}
   catch(e){if(revision!==generation)return;result.textContent=e.message;result.className='incident-feedback is-error';result.hidden=false;send.disabled=false;send.textContent='Réessayer l’envoi';}
   finally{delete details.dataset.sending;}
  };
  details.append(form);return details;
 }
 function overview(r){
  const box=el('div','customer-rental-overview'),head=el('div','customer-card-heading');const tone=r.status==='returned'?'returned':r.status==='active'?'active':['release_failed','payment_review','payment_failed','payment_timeout'].includes(r.status)?'warning':'pending';head.append(el('span','customer-rental-badge is-'+tone,states[r.status]||r.status),el('span','customer-reference',r.reference));box.append(head);
  box.append(el('h2','customer-rental-station',r.station),el('p','customer-battery','Batterie '+r.battery));
  const amounts=el('div','customer-rental-amounts'),fee=el('div','');fee.append(el('span','','Tarif de location'),el('strong','',money(r.rental_fee)),el('small','',r.duration_minutes+' minutes incluses'));amounts.append(fee);
  if(r.deposit>0){const deposit=el('div','');deposit.append(el('span','','Caution'),el('strong','',money(r.deposit)));amounts.append(deposit);}box.append(amounts);
  const timeline=el('ol','customer-rental-timeline');for(const [label,value] of [['Retrait',r.started_at],['À rendre avant',r.due_at],['Retour',r.returned_at]]){const item=el('li',value?'is-confirmed':'');item.append(el('span','',label),el('strong','',date(value)));timeline.append(item);}box.append(timeline);
  if(r.deposit>0){const bill=el('div','customer-refund');bill.append(el('p','', 'Retenue '+(r.status==='returned'?'finale':'estimée')+' : '+money(r.billing.deduction)));if(r.status==='returned')bill.append(el('p','','À restituer : '+money(r.refund_amount??r.billing.refund)+' · '+(r.refund_status==='refunded'?'Restitution clôturée':'En attente de confirmation')));box.append(bill);}
  const actions=el('div','customer-rental-actions');if(r.status==='returned')actions.append(link('Télécharger mon reçu','/rentals/receipt?reference='+encodeURIComponent(r.reference)+'&token='+encodeURIComponent(r.token),'customer-button customer-button-primary'));if(r.status==='active')actions.append(link('Trouver une station de retour','/stations/map','customer-button customer-button-secondary'));box.append(actions);
  if(r.support_status){const support=el('div','customer-support-update');support.append(el('strong','',r.support_status==='resolved'?'Votre incident est résolu':'Votre demande est en cours de traitement'));if(r.support_resolution)support.append(el('p','',r.support_resolution));box.append(support);}
  return box;
 }
 function draw(rows){
  const seen=new Set();list.querySelector('.customer-empty')?.remove();
  rows.forEach((r,index)=>{
   seen.add(r.token);let item=cards.get(r.token);
   if(!item){const card=el('article','customer-rental-card');const main=el('div','customer-rental-content');card.append(main);const incident=incidentForm(r);
    if(r.status==='returned'&&document.body.dataset.promotions==='1'){const invite=el('button','customer-button customer-button-secondary','Inviter un proche'),result=el('p','customer-referral-result');invite.type='button';invite.onclick=async()=>{invite.disabled=true;try{const offer=await post('/my-rentals/referral',{token:r.token});result.textContent='Partagez '+offer.code+' : '+money(offer.discount)+' de remise pour votre proche.';}catch(e){result.textContent=e.message;invite.disabled=false;}};card.append(invite,result);}
    card.append(incident);item={card,main,incident};cards.set(r.token,item);list.append(card);
   }
   item.card.style.order=index;item.main.replaceChildren(overview(r));
   // Keep the form node: automatic refresh must never discard a draft, focus or send outcome.
   if(!item.incident.dataset.sent&&!item.incident.dataset.sending){const submit=item.incident.querySelector('button[type=submit]');if(r.support_status==='open'){submit.disabled=true;submit.textContent='Une demande est déjà ouverte';item.incident.dataset.blocked='1';}else if(item.incident.dataset.blocked){submit.disabled=false;submit.textContent='Envoyer le signalement';delete item.incident.dataset.blocked;}}
  });
  for(const [key,item] of cards){if(!seen.has(key)){item.card.remove();cards.delete(key);}}
  if(!rows.length){const empty=el('section','customer-empty');empty.append(el('span','customer-empty-icon','↗'),el('h2','','Votre prochaine location commence ici'),el('p','','Les locations commencées sur ce navigateur apparaîtront ici avec leur suivi et leur reçu.'),link('Trouver une station','/stations/map','customer-button customer-button-primary'));list.append(empty);}
  for(const [id,value] of [['customer-total',rows.length],['customer-active',rows.filter(r=>r.status==='active').length],['customer-returned',rows.filter(r=>r.status==='returned').length]]){const node=document.getElementById(id);if(node)node.textContent=String(value);}
 }
 async function refresh(){clearTimeout(timer);if(busy||document.hidden)return;busy=true;const revision=generation,button=document.getElementById('refresh-rentals');button.disabled=true;
  try{const data=await post('/my-rentals/snapshot',{tokens:tokens()});if(revision!==generation)return;draw(data.rentals);status.textContent=data.rentals.length?'Suivi actualisé à '+new Date().toLocaleTimeString('fr-FR',{hour:'2-digit',minute:'2-digit',timeZone:'Africa/Abidjan'}):'Aucune location retrouvée sur cet appareil.';if(data.rentals.some(r=>['active','releasing','pending_payment'].includes(r.status)))timer=setTimeout(refresh,15000);}
  catch(e){if(revision===generation)status.textContent=e.message;}finally{busy=false;button.disabled=false;}
 }
 document.getElementById('refresh-rentals').onclick=refresh;document.getElementById('forget-rentals').onclick=()=>{if(!confirm('Effacer les accès aux locations et reçus sur cet appareil ?'))return;++generation;try{localStorage.removeItem('tbp_rental_tokens');localStorage.removeItem('tbp_last_receipt_token');}catch{}clearTimeout(timer);draw([]);status.textContent='Historique effacé de cet appareil.';};document.addEventListener('visibilitychange',()=>{if(document.hidden)clearTimeout(timer);else refresh();});refresh();
})();
