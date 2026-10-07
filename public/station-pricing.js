(()=>{
 'use strict';
 const form=document.getElementById('pricing-form');if(!form)return;
 const scope=document.getElementById('pricing-scope'),selection=document.getElementById('pricing-station-selection'),help=document.getElementById('pricing-scope-help'),status=document.getElementById('pricing-selection-status');
 const checks=[...selection.querySelectorAll('input[type=checkbox]')];
 const update=()=>{
  const selected=scope.value==='selected';selection.hidden=!selected;checks.forEach(input=>input.disabled=!selected);
  help.textContent=scope.value==='all'?'Ce tarif remplacera les tarifs de toutes les stations, y compris les tarifs spécifiques. Les nouvelles stations utiliseront aussi ce tarif.':selected?'Seules les stations cochées recevront ce tarif. Les autres conservent leur tarification.':'Le tarif général s’applique aux stations sans personnalisation, y compris les nouvelles stations. Les tarifs spécifiques sont conservés.';
  status.textContent=checks.filter(input=>input.checked).length+' station(s) sélectionnée(s).';
 };
 scope.addEventListener('change',update);checks.forEach(input=>input.addEventListener('change',update));update();
 form.addEventListener('submit',event=>{if(scope.value==='selected'&&!checks.some(input=>input.checked)){event.preventDefault();status.textContent='Sélectionnez au moins une station.';checks[0]?.focus();}});
})();
