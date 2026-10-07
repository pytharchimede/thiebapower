'use strict';
(() => {
 const form=document.getElementById('label-settings');if(!form)return;
 const preview=document.getElementById('label-preview'),error=document.getElementById('label-error'),size=document.getElementById('label-measurements');
 let timer;
 function update(){
  const data=new FormData(form),values={};
  for(const key of ['left','right','top','bottom','logo_size','logo_gap'])values[key]=Number(data.get(key));
  const w=29.7-values.left-values.right,h=21-values.top-values.bottom;
  const valid=String(data.get('logo_size')).trim()!==''&&String(data.get('logo_gap')).trim()!==''&&values.logo_size>=2&&values.logo_size<=5&&values.logo_gap>=0&&values.logo_gap<=4&&5*values.logo_size+4*values.logo_gap<=34+1e-8&&['left','right','top','bottom'].every(k=>String(data.get(k)).trim()!==''&&Number.isFinite(values[k])&&values[k]>=0)&&w>=15-1e-8&&h>=6-1e-8;
  error.hidden=valid;error.textContent=valid?'':'Vérifiez les marges et les icônes : taille 2–5 mm, espacement 0–4 mm, largeur totale maximale 34 mm.';
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
 document.getElementById('label-defaults').addEventListener('click',()=>{for(const key of ['left','right','top','bottom'])form.elements[key].value=['left','right'].includes(key)?5:7;form.elements.logo_size.value=4;form.elements.logo_gap.value=2;update();});
 update();
})();
