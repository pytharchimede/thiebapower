<section class="admin-card tb-finance-balances" aria-labelledby="tb-balances-title">
 <div class="admin-section-heading"><div><span class="admin-overline">PAIEMENT PRO · SOLDES</span><h2 id="tb-balances-title">Réception et envoi d’argent</h2><p>Soldes actuels indépendants du filtre de période. Cliquez sur une carte pour consulter et exporter le récapitulatif.</p></div></div>
 <?php if($balances['error']): ?><p role="status"><?= $escape($balances['error']) ?></p><?php endif; ?>
 <div class="tb-balance-grid">
 <?php foreach($balances['rows'] as $row): ?>
 <button type="button" class="tb-balance-card" data-balance-open aria-haspopup="dialog" aria-controls="tb-balance-recap">
 <small><?= $escape($row[0]) ?></small><span><?= $escape($row[1]) ?></span>
 <strong><?= $row[2]===null?'Indisponible':$money($row[2]) ?></strong>
 <small><?= $escape($row[3]) ?></small>
 </button>
 <?php endforeach; ?>
 </div>
 <p>Les encaissements locaux incluent les cautions et ne constituent pas le solde disponible chez Paiement Pro.</p>
 <button class="admin-button" type="button" data-balance-open>Voir le récapitulatif et exporter</button>
 <dialog id="tb-balance-recap" aria-labelledby="tb-balance-recap-title">
 <section class="admin-card"><div class="admin-section-heading"><h2 id="tb-balance-recap-title">Récapitulatif des soldes Paiement Pro</h2><button type="button" class="admin-button" id="tb-balance-close">Fermer</button></div>
 <p>Devise : XOF / FCFA. Les données non fournies par Paiement Pro sont indiquées comme indisponibles. Les sources et dates sont incluses dans les exports.</p>
 <div class="admin-table-wrap"><table data-tb-summary="true"><thead><tr><th>Compte</th><th>Indicateur</th><th>Montant</th><th>Source et date UTC</th></tr></thead><tbody>
 <?php foreach($balances['rows'] as $row): ?><tr><td><?= $escape($row[0]) ?></td><td><?= $escape($row[1]) ?></td><td><?= $row[2]===null?'Indisponible':$money($row[2]) ?></td><td><?= $escape($row[3]) ?></td></tr><?php endforeach; ?>
 </tbody></table></div>
 </section>
 </dialog>
</section>
<style>
.tb-balance-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(215px,1fr));gap:14px;margin:18px 0}.tb-balance-card{display:flex;flex-direction:column;gap:8px;text-align:left;background:var(--card-bg,#fff);color:inherit;border:1px solid #dce5e2;border-radius:14px;padding:20px;cursor:pointer;font:inherit;min-width:0}.tb-balance-card:hover,.tb-balance-card:focus-visible{border-color:#16866a;box-shadow:0 3px 14px #16866a20}.tb-balance-card strong{font-size:1.5rem;line-height:1.3;color:#14674f}.tb-balance-card small{font-size:.75rem;color:#5a6c67;overflow-wrap:anywhere}.tb-balance-card>small:first-child{text-transform:uppercase;letter-spacing:.06em;font-weight:700}#tb-balance-recap{border:0;border-radius:18px;padding:0;width:min(1050px,94vw);max-height:90vh;overflow:auto;color:#19352c;background:#f4f8f6}#tb-balance-recap::backdrop{background:#112b24a8}#tb-balance-recap .admin-card{margin:0}
</style>
<script>
(()=>{const dialog=document.getElementById('tb-balance-recap');let opener=null;document.querySelectorAll('[data-balance-open]').forEach(button=>button.addEventListener('click',()=>{opener=button;dialog.showModal();}));document.getElementById('tb-balance-close').addEventListener('click',()=>dialog.close());dialog.addEventListener('close',()=>opener?.focus());})();
</script>
