/* Read-only interface enhancements. No request, payment or business-state mutation. */
(() => {
  'use strict';
  const states = {available:'Disponible',reserved:'Réservée',rented:'En location',maintenance:'Maintenance',charging:'En charge',active:'En cours',pending_payment:'Paiement en attente',releasing:'Sortie en cours',returned:'Retournée',payment_failed:'Paiement échoué',payment_timeout:'Paiement expiré',release_failed:'Sortie à vérifier',payment_review:'Paiement à rapprocher',initiated:'Initié',processing:'En traitement',succeeded:'Réussi',refunded:'Remboursée',failed:'Échoué',unknown:'À vérifier',pending:'En attente',archived:'Clôturé'};
  const normalize = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('fr');
  const escapeXml = value => value.replace(/[\x00-\x08\x0b\x0c\x0e-\x1f]/g,'').replace(/[<>&"']/g,c=>({'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;',"'":'&apos;'}[c]));
  const download = (bytes, type, name) => { const url=URL.createObjectURL(new Blob([bytes],{type})); const a=document.createElement('a'); a.href=url;a.download=name;a.click();setTimeout(()=>URL.revokeObjectURL(url),1000); };
  // OOXML workbook in a standards-compliant ZIP; inline strings never execute formulas.
  const zip = files => {
    const enc=new TextEncoder(); let offset=0; const chunks=[],central=[];
    const crc32 = data => {let c=0xffffffff;for(const byte of data){c^=byte;for(let j=0;j<8;j++)c=(c>>>1)^((c&1)?0xedb88320:0);}return(c^0xffffffff)>>>0;};
    const bytes = (size, values) => {const b=new Uint8Array(size),v=new DataView(b.buffer);for(const [at,num,length] of values){length===2?v.setUint16(at,num,true):v.setUint32(at,num,true);}return b;};
    for(const [name,content] of Object.entries(files)){
      const n=enc.encode(name),data=enc.encode(content),crc=crc32(data);
      const local=bytes(30,[[0,0x04034b50,4],[4,20,2],[6,0x800,2],[14,crc,4],[18,data.length,4],[22,data.length,4],[26,n.length,2]]);
      chunks.push(local,n,data);
      central.push(bytes(46,[[0,0x02014b50,4],[4,20,2],[6,20,2],[8,0x800,2],[16,crc,4],[20,data.length,4],[24,data.length,4],[28,n.length,2],[42,offset,4]]),n);
      offset+=local.length+n.length+data.length;
    }
    const centralSize=central.reduce((sum,c)=>sum+c.length,0),count=Object.keys(files).length;
    const end=bytes(22,[[0,0x06054b50,4],[8,count,2],[10,count,2],[12,centralSize,4],[16,offset,4]]);
    const result=new Uint8Array(offset+centralSize+22);let pos=0;for(const c of [...chunks,...central,end]){result.set(c,pos);pos+=c.length;}return result;
  };
  const workbook = rows => {
    const col = index => {let s='';for(let n=index+1;n;n=Math.floor((n-1)/26))s=String.fromCharCode(65+(n-1)%26)+s;return s;};
    const sheet='<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" state="frozen"/></sheetView></sheetViews><sheetData>'+rows.map((r,i)=>'<row r="'+(i+1)+'">'+r.map((v,j)=>'<c r="'+col(j)+(i+1)+'" t="inlineStr"><is><t xml:space="preserve">'+escapeXml(v)+'</t></is></c>').join('')+'</row>').join('')+'</sheetData></worksheet>';
    return zip({'[Content_Types].xml':'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>', '_rels/.rels':'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>', 'xl/workbook.xml':'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Listing" sheetId="1" r:id="rId1"/></sheets></workbook>', 'xl/_rels/workbook.xml.rels':'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>', 'xl/worksheets/sheet1.xml':sheet});
  };
  // Downloadable multipage PDF using built-in Helvetica. No external runtime required.
  const pdf = (title, headers, rows) => {
    const safe = value => value.replace(/[–—]/g,'-').replace(/œ/g,'oe').replace(/Œ/g,'OE').replace(/[’‘]/g,"'").replace(/…/g,'...').replace(/[^\x20-\xff]/g,'?').replace(/[\\()]/g,'\\$&');
    const pages=[];let lines=[];
    const push = (text, size=10, gap=16) => { if(lines.length>=42){pages.push(lines);lines=[];} lines.push({text,size,gap}); };
    rows.forEach((r,i)=>{if(lines.length && lines.length+headers.length+2>42){pages.push(lines);lines=[];} push('FICHE '+(i+1),11,20);r.forEach((value,j)=>{const text=headers[j]+' : '+value;for(let p=0;p<text.length;p+=91)push(text.slice(p,p+91));});push('',10,13);});
    if(lines.length)pages.push(lines);if(!pages.length)pages.push([{text:'Aucun résultat',size:10,gap:16}]);
    const objects=['','<< /Type /Catalog /Pages 2 0 R >>','', '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>'];
    const ids=[];
    pages.forEach((page,index)=>{const pageId=objects.length;ids.push(pageId);const streamId=pageId+1;let y=724;
      let content='0.08 0.25 0.21 rg 0 780 595 62 re f\nBT /F1 19 Tf 1 1 1 rg 38 806 Td (THIEBAPOWER) Tj ET\nBT /F1 12 Tf 0.1 0.24 0.2 rg 38 757 Td ('+safe(title).slice(0,110)+') Tj ET\n';
      page.forEach(line=>{content+='BT /F1 '+line.size+' Tf 0.15 0.25 0.2 rg 38 '+y+' Td ('+safe(line.text)+') Tj ET\n';y-=line.gap;});
      content+='BT /F1 9 Tf 0.4 0.45 0.4 rg 38 28 Td ('+safe(new Date().toLocaleString('fr-FR'))+' - '+rows.length+' fiches - Page '+(index+1)+'/'+pages.length+') Tj ET\n';
      objects.push('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents '+streamId+' 0 R >>','<< /Length '+content.length+' >>\nstream\n'+content+'endstream');
    });
    objects[2]='<< /Type /Pages /Count '+ids.length+' /Kids ['+ids.map(id=>id+' 0 R').join(' ')+'] >>';
    let output='%PDF-1.4\n',offsets=[0];for(let i=1;i<objects.length;i++){offsets.push(output.length);output+=i+' 0 obj\n'+objects[i]+'\nendobj\n';}
    const xref=output.length;output+='xref\n0 '+objects.length+'\n0000000000 65535 f \n'+offsets.slice(1).map(o=>String(o).padStart(10,'0')+' 00000 n \n').join('')+'trailer\n<< /Size '+objects.length+' /Root 1 0 R >>\nstartxref\n'+xref+'\n%%EOF';
    return Uint8Array.from(output,c=>c.charCodeAt(0));
  };
  const makeButton = (text,icon) => {const b=document.createElement('button');b.type='button';b.className='tb-export-button';const i=document.createElement('i');i.className='fa-solid '+icon;i.setAttribute('aria-hidden','true');b.append(i,document.createTextNode(text));return b;};
  document.querySelectorAll('.status-pill').forEach(el=>{const state=el.dataset.state||el.textContent.trim();el.dataset.state=state;if(states[state]&&!el.classList.contains('tb-rental-state'))el.textContent=states[state];});
  let listingId=0;
  const detailViews=new Map();
  const showRecord = () => {
    const record=detailViews.get(location.hash);
    document.querySelectorAll('.tb-inline-detail').forEach(el=>el.remove());
    document.querySelectorAll('main[data-tb-detail-hidden]').forEach(el=>{el.hidden=false;delete el.dataset.tbDetailHidden;});
    if(!record)return;
    const {main,title,head,values}=record;
    const detail=document.createElement('main');detail.className='management-main tb-inline-detail';
    const back=document.createElement('a');back.href='#';back.className='tb-link-button';back.textContent='← Retour au listing';
    back.addEventListener('click',e=>{e.preventDefault();history.replaceState(null,'',location.pathname+location.search);showRecord();});
    const section=document.createElement('section');section.className='management-card';
    const heading=document.createElement('h2');heading.textContent=title+' · Détails';
    const dl=document.createElement('dl');dl.className='tb-detail-grid';
    head.forEach((label,i)=>{if(!values[i])return;const field=document.createElement('div'),dt=document.createElement('dt'),dd=document.createElement('dd');dt.textContent=label;dd.textContent=values[i];field.append(dt,dd);dl.append(field);});
    section.append(heading,dl);detail.append(back,section);main.after(detail);main.hidden=true;main.dataset.tbDetailHidden='1';heading.tabIndex=-1;heading.focus();window.scrollTo({top:0});
  };
  window.addEventListener('hashchange',showRecord);
  document.querySelectorAll('.admin-table-wrap > table, .management-table-wrap > table').forEach(table=>{
    if(!table.tHead || !table.tBodies.length || table.dataset.tbEnhanced)return;
    table.dataset.tbEnhanced='true';table.setAttribute('role','table');const head=Array.from(table.tHead.rows[0].cells).map(c=>c.textContent.trim());
    const body=table.tBodies[0];const rows=Array.from(body.rows).filter(r=>r.cells.length===head.length&&!r.querySelector('td[colspan]'));
    const wrap=table.parentElement;wrap.classList.add('tb-card-list');
    const section=table.closest('section');const title=(section?.querySelector('h2')?.textContent||document.querySelector('.admin-global-top h1')?.textContent||'Listing').trim();
    rows.forEach(row=>{row.setAttribute('role','row');Array.from(row.cells).forEach((cell,j)=>{cell.setAttribute('role','cell');if(states[cell.textContent.trim()]&&!cell.querySelector('form,a,button,input,details')){const state=cell.textContent.trim();cell.textContent=states[state];cell.dataset.state=state;}const label=document.createElement('span');label.className='tb-cell-label';label.textContent=head[j];label.setAttribute('aria-hidden','true');cell.prepend(label);});});
    const clean = cell => {const clone=cell.cloneNode(true);clone.querySelectorAll('.tb-cell-label,form,button,input,select,textarea,details').forEach(el=>el.remove());return clone.textContent.replace(/\s+/g,' ').trim();};
    const data=rows.map(row=>Array.from(row.cells).map(clean));
    const toolbar=document.createElement('div');toolbar.className='tb-list-toolbar';const form=document.createElement('form');form.className='tb-search-form';form.setAttribute('role','search');form.setAttribute('aria-label','Rechercher dans '+title);
    const label=document.createElement('label');label.textContent='Rechercher dans ce listing';const input=document.createElement('input');input.type='search';input.placeholder='Référence, nom, station…';input.id='tb-search-'+(++listingId);label.htmlFor=input.id;label.append(input);form.append(label);form.addEventListener('submit',e=>e.preventDefault());
    const statusIndex=head.findIndex(h=>/état|statut/i.test(h));let select=null;
    if(statusIndex>=0){select=document.createElement('select');select.setAttribute('aria-label','Filtrer par état');const all=document.createElement('option');all.value='';all.textContent='Tous les états';select.append(all);[...new Set(data.map(r=>r[statusIndex]))].sort().forEach(s=>{const op=document.createElement('option');op.value=s;op.textContent=s;select.append(op);});const sl=document.createElement('label');sl.textContent='État';sl.append(select);form.append(sl);}
    const actions=document.createElement('div');actions.className='tb-export-actions';const excelButton=makeButton('Excel','fa-file-excel'),pdfButton=makeButton('PDF','fa-file-pdf');actions.append(excelButton,pdfButton);toolbar.append(form,actions);
    const meta=document.createElement('span');meta.className='tb-list-meta';meta.setAttribute('role','status');meta.setAttribute('aria-live','polite');const empty=document.createElement('p');empty.className='tb-no-match';empty.textContent='Aucun résultat. Essayez un autre terme ou un autre état.';empty.hidden=true;
    wrap.before(toolbar,meta);wrap.after(empty);
    const filter = ()=>{const query=normalize(input.value.trim());let count=0;rows.forEach((row,i)=>{row.hidden=!(normalize(data[i].join(' ')).includes(query)&&(!select||!select.value||data[i][statusIndex]===select.value));if(!row.hidden)count++;});meta.textContent=count+' résultat'+(count>1?'s':'')+' sur '+rows.length+' chargé'+(rows.length>1?'s':'')+' · Recherche et exports limités aux données de cette page.';empty.hidden=count>0;excelButton.disabled=pdfButton.disabled=count===0;};
    rows.forEach((row,index)=>{
      const primary=Array.from(row.querySelectorAll('a[href]')).find(a=>/\/detail\?/.test(a.getAttribute('href')));
      const cell=document.createElement('td');cell.className='tb-row-action';cell.setAttribute('role','cell');
      const link=document.createElement('a');link.className='tb-link-button';link.textContent='Voir la fiche →';
      if(primary){link.href=primary.getAttribute('href');}else{
        const hash='#fiche-'+listingId+'-'+index;link.href=hash;
        detailViews.set(hash,{main:table.closest('main'),title,head,values:data[index]});
      }
      cell.append(link);row.append(cell);
    });
    if(table.classList.contains('tb-rental-table'))document.addEventListener('tb-list-update',()=>{
      const order=Array.from(body.rows);rows.sort((a,b)=>order.indexOf(a)-order.indexOf(b));
      data.splice(0,data.length,...rows.map(row=>Array.from(row.cells).slice(0,head.length).map(clean)));
      if(select){const current=select.value;select.replaceChildren();const all=document.createElement('option');all.value='';all.textContent='Tous les états';select.append(all);[...new Set(data.map(r=>r[statusIndex]))].sort().forEach(state=>{const option=document.createElement('option');option.value=state;option.textContent=state;select.append(option);});select.value=current;if(select.selectedIndex<0)select.value='';}
      filter();
    });
    input.addEventListener('input',filter);select?.addEventListener('change',filter);filter();
    const selected=()=>rows.filter(r=>!r.hidden).map(r=>Array.from(r.cells).slice(0,head.length).map(clean));const filename=normalize(title).replace(/[^a-z0-9]+/g,'-').slice(0,65)+'-'+new Date().toISOString().slice(0,10);
    excelButton.addEventListener('click',()=>download(workbook([head,...selected()]),'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',filename+'.xlsx'));
    pdfButton.addEventListener('click',()=>download(pdf(title,head,selected()),'application/pdf',filename+'.pdf'));
  });
  showRecord();
})();
