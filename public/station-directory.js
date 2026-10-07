(() => {
 'use strict';
 const list=document.getElementById('station-directory-list');if(!list)return;
 const status=document.getElementById('directory-status'),filter=document.getElementById('station-filter'),clear=document.getElementById('clear-station-filter'),refresh=document.getElementById('refresh-stations');
 let rows=[],markers=[],position=null,map=null,busy=false;
 if(window.L){map=L.map('station-directory-map').setView([5.35,-4.02],12);L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'}).addTo(map);}else document.getElementById('station-directory-map').hidden=true;
 const normalize=text=>String(text||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('fr');
 const located=r=>Number.isFinite(r.latitude)&&Number.isFinite(r.longitude);
 const state=r=>!r.enabled?'inactive':r.fresh?'active':'uncertain';
 const stateText=r=>!r.enabled?'Locations suspendues':r.fresh?'Station activée':'Connexion à vérifier';
 const distance=r=>{if(!position||!located(r))return Infinity;const rad=d=>d*Math.PI/180;const a=Math.sin(rad(r.latitude-position.latitude)/2)**2+Math.cos(rad(r.latitude))*Math.cos(rad(position.latitude))*Math.sin(rad(r.longitude-position.longitude)/2)**2;return 6371*2*Math.atan2(Math.sqrt(a),Math.sqrt(Math.max(0,1-a)));};
 const el=(tag,className,text)=>{const node=document.createElement(tag);if(className)node.className=className;if(text!==undefined)node.textContent=text;return node;};
 const link=(label,href,className)=>{const a=el('a',className,label);a.href=href;return a;};
 function details(r,popup=false){
  const card=el(popup?'div':'article',popup?'finder-popup':'station-directory-card finder-station-card');
  const top=el('div','finder-card-top');top.append(el('span','finder-state is-'+state(r),stateText(r)));if(position&&Number.isFinite(distance(r)))top.append(el('span','finder-distance',distance(r).toFixed(1)+' km'));card.append(top);
  card.append(el(popup?'h3':'h2','finder-station-name',r.label));
  card.append(el('p','finder-address',r.address||'Adresse à renseigner'));
  const stock=el('div','finder-stock');stock.append(el('strong','',r.enabled&&r.fresh?String(r.available):'—'));stock.append(el('span','',!r.enabled?'Location indisponible':r.fresh?(r.available===1?'batterie disponible':'batteries disponibles'):'Disponibilité à vérifier'));card.append(stock);
  const info=el('div','finder-contact');info.append(el('p','finder-hours',r.opening_hours||'Horaires à confirmer'));
  if(r.manager_name){const name=el('p','finder-manager');name.append(el('span','','Gérant · '),el('strong','',r.manager_name));info.append(name);}
  if(r.manager_phone){const phone=String(r.manager_phone);const digits=phone.replace(/[^0-9]/g,'');if(digits.length>=8&&digits.length<=16){const dial=digits.length===10?'+225'+digits:(phone.trim().startsWith('+')?'+'+digits:digits);info.append(link(phone,'tel:'+dial,'finder-phone'));}else info.append(el('p','',phone));}
  card.append(info);
  const actions=el('div','finder-card-actions');
  if(r.enabled)actions.append(link('Louer ici','/rent?station='+encodeURIComponent(r.imei),'finder-rent-link'));
  if(located(r)){const itinerary=link('Itinéraire','https://www.google.com/maps/dir/?api=1&destination='+encodeURIComponent(r.latitude+','+r.longitude),'finder-route-link');itinerary.target='_blank';itinerary.rel='noopener noreferrer';actions.append(itinerary);}
  card.append(actions);return card;
 }
 function draw(fit=true){
  list.replaceChildren();markers.forEach(m=>map.removeLayer(m));markers=[];const term=normalize(filter.value.trim());clear.hidden=!term;
  const filtered=rows.filter(r=>normalize(r.label+' '+r.address).includes(term)).sort((a,b)=>distance(a)-distance(b));
  for(const r of filtered){const card=details(r);list.append(card);if(map&&located(r)){const icon=L.divIcon({className:'finder-map-marker is-'+state(r),html:'<span>'+ (r.enabled&&r.fresh?Math.max(0,Number(r.available)||0):!r.enabled?'×':'?')+'</span>',iconSize:[44,44],iconAnchor:[22,44],popupAnchor:[0,-42]});const marker=L.marker([r.latitude,r.longitude],{icon,title:r.label}).bindPopup(details(r,true),{maxWidth:320,minWidth:240}).addTo(map);markers.push(marker);const show=el('button','finder-show-map','Voir sur la carte');show.type='button';show.onclick=()=>{map.setView(marker.getLatLng(),16);marker.openPopup();if(window.innerWidth<900)document.getElementById('station-directory-map').scrollIntoView({behavior:'smooth',block:'center'});};card.querySelector('.finder-card-actions').append(show);}}
  document.getElementById('station-result-count').textContent=String(filtered.length);
  if(!filtered.length)list.append(el('p','finder-empty',term?'Aucune station trouvée. Essayez un autre quartier ou effacez la recherche.':'Aucune station enregistrée pour le moment.'));
  const missing=filtered.filter(r=>!located(r)).length;status.textContent=filtered.length+' station(s) trouvée(s).'+(missing?' '+missing+' emplacement(s) à renseigner.':'');
  if(map&&markers.length&&fit)map.fitBounds(L.featureGroup(markers).getBounds().pad(.15),{maxZoom:16});
 }
 filter.oninput=()=>draw();clear.onclick=()=>{filter.value='';draw();filter.focus();};
 document.getElementById('near-me').onclick=()=>{if(!navigator.geolocation){status.textContent='Géolocalisation indisponible.';return;}status.textContent='Recherche de votre position…';navigator.geolocation.getCurrentPosition(p=>{position=p.coords;draw();},()=>{status.textContent='Position non accessible. Recherchez votre quartier.';},{timeout:10000});};
 async function load(fit=true){if(busy)return;busy=true;refresh.disabled=true;try{const response=await fetch('/stations/snapshot',{cache:'no-store'});if(!response.ok)throw new Error();const data=await response.json();if(!Array.isArray(data.stations))throw new Error();rows=data.stations;draw(fit);}catch{status.textContent='Actualisation indisponible. Réessayez dans un instant.';}finally{busy=false;refresh.disabled=false;}}
 refresh.onclick=()=>load(false);load();
})();
