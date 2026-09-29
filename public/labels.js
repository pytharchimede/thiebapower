'use strict';
(() => {
 const form=document.getElementById('label-settings');if(!form)return;
 const preview=document.getElementById('label-preview'),error=document.getElementById('label-error'),size=document.getElementById('label-measurements');
 let timer;
 function update(){
  const data=new FormData(form),values={};
  for(const key of ['left','right','top','bottom'])values[key]=Number(data.get(key));
  const w=29.7-values.left-values.right,h=21-values.top-values.bottom;
  const valid=['left','right','top','bottom'].every(k=>String(data.get(k)).trim()!==''&&Number.isFinite(values[k])&&values[k]>=0)&&w>=15-1e-8&&h>=6-1e-8;
  error.hidden=valid;error.textContent=valid?'':'Conservez une zone de contenu d’au moins 15 × 6 cm.';
  form.querySelector('button[type="submit"]').disabled=!valid;
  for(const id of ['label-download','label-print']){const link=document.getElementById(id);if(link){link.hidden=!valid;}}
  if(!valid){size.textContent='';if(preview)preview.hidden=true;return;}
  const scale=Math.min(w/19.7,h/7);
  size.textContent=`Zone disponible : ${w.toFixed(1)} × ${h.toFixed(1)} cm · Étiquette : ${(19.7*scale).toFixed(1)} × ${(7*scale).toFixed(1)} cm`;
  const query=new URLSearchParams();for(const key of Object.keys(values))query.set(key,String(values[key]));
  if(data.get('imei'))query.set('imei',data.get('imei'));
  const url='/admin/stations/labels.pdf?'+query.toString();
  const download=document.getElementById('label-download'),print=document.getElementById('label-print');
  if(download)download.href=url;if(print)print.href=url+'&preview=1';
  if(preview){preview.hidden=false;preview.src=url+'&preview=1#view=Fit';}
 }
 form.addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(update,450);});
 form.addEventListener('submit',event=>{update();if(form.querySelector('button[type="submit"]').disabled)event.preventDefault();});
 document.getElementById('label-defaults').addEventListener('click',()=>{for(const key of ['left','right','top','bottom'])form.elements[key].value=['left','right'].includes(key)?5:7;update();});
 update();
})();
