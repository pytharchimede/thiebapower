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
  const workbook = (title, headers, rows, qrSvg) => {
    const col = index => {let s='';for(let n=index+1;n;n=Math.floor((n-1)/26))s=String.fromCharCode(65+(n-1)%26)+s;return s;};
    const summary=['Lignes exportées : '+rows.length,'Sélection affichée · '+new Date().toLocaleDateString('fr-FR')];
    headers.forEach((header,i)=>{const values=rows.map(r=>r[i]);if(values.length&&values.every(v=>/^\s*[0-9][0-9 \u00a0\u202f]* FCFA(?:\s|$)/u.test(v))){const total=values.reduce((n,v)=>n+Number(v.match(/^[\s0-9\u00a0\u202f]+/u)[0].replace(/[^0-9]/g,'')),0);summary.push(header+' : '+new Intl.NumberFormat('fr-FR').format(total)+' FCFA');}});
    const data=[['THIEBAPOWER'],[title],['Édité le '+new Date().toLocaleString('fr-FR')+' · Données de la sélection affichée'],[],...summary.slice(0,4).map(v=>[v]),[],headers,...rows];const headerAt=data.length-rows.length;
    const cols=headers.map((_,i)=>'<col min="'+(i+1)+'" max="'+(i+1)+'" width="'+Math.min(48,Math.max(18,...rows.map(r=>r[i].length*.7)))+'" customWidth="1"/>').join('');
    const sheet='<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="'+headerAt+'" topLeftCell="A'+(headerAt+1)+'" state="frozen"/></sheetView></sheetViews><cols>'+cols+'</cols><sheetData>'+data.map((r,i)=>'<row r="'+(i+1)+'" ht="'+(i===0?32:(i+1===headerAt?32:Math.max(26,...r.map(v=>v.split("\n").length*15))))+'" customHeight="1">'+r.map((v,j)=>'<c r="'+col(j)+(i+1)+'" s="'+(i===0||i+1===headerAt?1:(i<headerAt?3:(i%2?2:0)))+'" t="inlineStr"><is><t xml:space="preserve">'+escapeXml(v)+'</t></is></c>').join('')+'</row>').join('')+'</sheetData><autoFilter ref="A'+headerAt+':'+col(headers.length-1)+data.length+'"/><mergeCells count="3"><mergeCell ref="A1:'+col(headers.length-1)+'1"/><mergeCell ref="A2:'+col(headers.length-1)+'2"/><mergeCell ref="A3:'+col(headers.length-1)+'3"/></mergeCells><pageMargins left="0.3" right="0.3" top="0.6" bottom="0.6" header="0.2" footer="0.2"/><pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/><headerFooter><oddHeader>&amp;LTHIEBAPOWER&amp;R'+escapeXml(title)+'</oddHeader><oddFooter>&amp;LDocument confidentiel&amp;RPage &amp;P / &amp;N</oddFooter></headerFooter></worksheet>';
    const dark=new Set([...qrSvg.matchAll(/M(\d+) (\d+)h1v1h-1z/g)].map(m=>m[1]+','+m[2]));
    const qrSheet='<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cols><col min="1" max="45" width="1.4" customWidth="1"/></cols><sheetData><row r="1" ht="26" customHeight="1"><c r="A1" t="inlineStr" s="3"><is><t>THIEBAPOWER · QR vers l’administration</t></is></c></row>'+Array.from({length:45},(_,y)=>'<row r="'+(y+4)+'" ht="7" customHeight="1">'+Array.from({length:45},(_,x)=>dark.has(x+','+y)?'<c r="'+col(x)+(y+4)+'" s="4"/>':'').join('')+'</row>').join('')+'</sheetData><mergeCells count="1"><mergeCell ref="A1:AS1"/></mergeCells></worksheet>';
    const styles='<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><color rgb="FF103D46"/><name val="Calibri"/></font><font><b/><sz val="12"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts><fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF103D46"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFEDF5F3"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="5"><xf fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf><xf fontId="1" fillId="2" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf><xf fontId="0" fillId="3" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf><xf fontId="0" fillId="3" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf><xf fontId="0" fillId="2" borderId="0" xfId="0" applyFill="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    return zip({'[Content_Types].xml':'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>', '_rels/.rels':'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>', 'xl/workbook.xml':'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Listing" sheetId="1" r:id="rId1"/><sheet name="QR" sheetId="2" r:id="rId3"/></sheets><definedNames><definedName name="_xlnm.Print_Titles" localSheetId="0">Listing!$'+headerAt+':$'+headerAt+'</definedName></definedNames></workbook>', 'xl/_rels/workbook.xml.rels':'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/></Relationships>', 'xl/worksheets/sheet1.xml':sheet,'xl/styles.xml':styles,'xl/worksheets/sheet2.xml':qrSheet});
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
    const clean = cell => {const clone=cell.cloneNode(true);clone.querySelectorAll('.tb-cell-label,form,button,input,select,textarea,details').forEach(el=>el.remove());clone.querySelectorAll('br').forEach(el=>el.replaceWith('\n'));clone.querySelectorAll('small,strong,time,p').forEach(el=>el.append(document.createTextNode('\n')));return clone.textContent.replace(/[ \t]+/g,' ').replace(/\n\s*\n/g,'\n').trim();};
    const data=rows.map(row=>Array.from(row.cells).map(clean));
    const toolbar=document.createElement('div');toolbar.className='tb-list-toolbar';const form=document.createElement('form');form.className='tb-search-form';form.setAttribute('role','search');form.setAttribute('aria-label','Rechercher dans '+title);
    const label=document.createElement('label');label.textContent='Rechercher dans ce listing';const input=document.createElement('input');input.type='search';input.placeholder='Référence, nom, station…';input.id='tb-search-'+(++listingId);label.htmlFor=input.id;label.append(input);form.append(label);form.addEventListener('submit',e=>e.preventDefault());
    const statusIndex=head.findIndex(h=>/état|statut/i.test(h));let select=null;
    if(statusIndex>=0){select=document.createElement('select');select.setAttribute('aria-label','Filtrer par état');const all=document.createElement('option');all.value='';all.textContent='Tous les états';select.append(all);[...new Set(data.map(r=>r[statusIndex]))].sort().forEach(s=>{const op=document.createElement('option');op.value=s;op.textContent=s;select.append(op);});const sl=document.createElement('label');sl.textContent='État';sl.append(select);form.append(sl);}
    const actions=document.createElement('div');actions.className='tb-export-actions';const excelButton=makeButton('Excel','fa-file-excel'),pdfButton=makeButton('PDF','fa-file-pdf');if(window.TB_CAN_EXPORT)actions.append(excelButton,pdfButton);toolbar.append(form,actions);
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
    const exportIndexes=head.map((h,i)=>i).filter(i=>!/action/i.test(head[i]));const exportHeaders=exportIndexes.map(i=>head[i]);const selected=()=>rows.filter(r=>!r.hidden).map(r=>{const cells=Array.from(r.cells).slice(0,head.length).map(clean);return exportIndexes.map(i=>cells[i]);});const filename=normalize(title).replace(/[^a-z0-9]+/g,'-').slice(0,65)+'-'+new Date().toISOString().slice(0,10);
    excelButton.addEventListener('click',async()=>{excelButton.disabled=true;try{const response=await fetch('/admin/reports/qr',{credentials:'same-origin'});if(!response.ok)throw new Error('Permission ou connexion expirée');const qrSvg=await response.text();if(!qrSvg.includes('<svg'))throw new Error('Connexion requise');download(workbook(title,exportHeaders,selected(),qrSvg),'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',filename+'.xlsx');}catch(e){window.alert('Export indisponible : '+e.message);}finally{excelButton.disabled=false;}});
    pdfButton.addEventListener('click',()=>{const f=document.createElement('form');f.method='post';f.action='/admin/reports/pdf';const token=document.querySelector('meta[name="tb-csrf"]')?.content;for(const [name,value] of Object.entries({csrf:token||'',report:JSON.stringify({title,headers:exportHeaders,rows:selected()})})){const input=document.createElement('input');input.type='hidden';input.name=name;input.value=value;f.append(input);}document.body.append(f);f.submit();f.remove();});
  });
  showRecord();
})();
