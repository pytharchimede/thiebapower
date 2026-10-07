'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),{JSDOM}=require('jsdom');
(async()=>{
 const dom=new JSDOM('<form id="rental-checkout"><input name="phone"><input name="battery_id" value="1"><input name="station_code" value="TEST01"><input id="promotion-code"><input id="previous-rental-token"><button type="button" id="preview-promotion">Vérifier</button></form><p id="promotion-status"></p><strong id="summary-fee"></strong><strong id="summary-deposit"></strong><strong id="summary-total"></strong>',{url:'https://example.test',runScripts:'outside-only'});
 const w=dom.window,d=w.document;w.TB_PRICE=1000;w.eval(fs.readFileSync('public/promotions.js','utf8'));
 const button=d.getElementById('preview-promotion'),status=d.getElementById('promotion-status');
 assert.equal(button.disabled,true);assert.equal(d.getElementById('promotion-code').disabled,true);assert.match(status.textContent,/téléphone/);
 d.getElementById('promotion-code').value='TEST200';button.click();assert.match(status.textContent,/téléphone/);
 d.querySelector('[name=phone]').value='0700000002';w.dispatchEvent(new w.CustomEvent('tbp-battery-selected',{detail:{deposit:5000}}));assert.equal(button.disabled,false);assert.equal(d.getElementById('promotion-code').disabled,false);
 let resolve;w.fetch=async()=>new Promise(r=>resolve=r);button.click();assert.equal(button.disabled,true);assert.match(status.textContent,/en cours/);
 resolve({ok:true,json:async()=>({fee:1000,deposit:4800,discount:200})});await new Promise(r=>setTimeout(r,0));assert.equal(button.disabled,false);assert.match(status.textContent,/Code appliqué/);assert.match(d.getElementById('summary-fee').textContent,/1\s000/);assert.match(d.getElementById('summary-total').textContent,/5\s800/);
 w.fetch=async()=>({ok:false,json:async()=>({error:'Code expiré'})});button.click();await new Promise(r=>setTimeout(r,0));assert.equal(status.textContent,'Code expiré');assert.match(d.getElementById('summary-total').textContent,/6\s000/);assert.equal(button.disabled,false);
 w.fetch=async()=>({ok:false,json:async()=>{throw new Error('HTML')}});button.click();await new Promise(r=>setTimeout(r,0));assert.match(status.textContent,/momentanément indisponible/);assert.equal(button.disabled,false);
 w.fetch=async()=>new Promise(r=>resolve=r);button.click();d.querySelector('[name=phone]').dispatchEvent(new w.Event('input'));resolve({ok:true,json:async()=>({fee:1000,deposit:4800,discount:200})});await new Promise(r=>setTimeout(r,0));assert.doesNotMatch(status.textContent,/Code appliqué/);d.querySelector('[name=phone]').value='07';d.querySelector('[name=phone]').dispatchEvent(new w.Event('input'));assert.equal(button.disabled,true);assert.equal(d.getElementById('promotion-code').disabled,true);dom.window.close();
 console.log('Promotion DOM: required input, loading, deposit-only discount, retry, non-JSON failure and stale response OK');
})().catch(e=>{console.error(e);process.exit(1)});
