<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Tarification · Thiebapower</title><link rel="stylesheet" href="/style.css"><link rel="stylesheet" href="/station-pricing.css?v=1"><script src="/station-pricing.js?v=1" defer></script></head><body class="admin-body">
<?php
$stations=$stations??[];$stationPrices=$stationPrices??[];$selectedStation=$selectedStation??'';$h=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');
$adminPageTitle = "Tarification";
$adminPageSubtitle = "Vos tarifs et règles de caution, au même endroit.";
require __DIR__ . "/partials/admin_shell_start.php";
?>
<main class="management-main">
<?php if(($_GET['saved']??'')==='1'): ?><p class="tb-success" role="status"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Modifications enregistrées.</p><?php endif; ?>
<section class="tb-hero tb-hero-compact"><span class="tb-eyebrow"><?= $selectedStation!==''?'TARIF DE LA STATION':'TARIF GÉNÉRAL' ?></span><h2><?= number_format(
    (int) $prices["rental_fee"],
    0,
    ",",
    " ",
) ?> <small>FCFA / <?= (int) $prices[
     "duration_minutes"
 ] ?> min</small></h2><p>Caution <?= (int) $prices["deposit_enabled"] === 1
     ? "activée"
     : "désactivée" ?> · Les locations existantes conservent leur tarif.</p></section>            <?php if (
     App\Services\Auth::can("pricing.manage")
 ): ?>
            <section id="pricing" class="admin-card">
                <div class="admin-section-heading"><div><span class="admin-overline">PARAMÈTRES</span><h2>Tarification</h2><p>Le tarif est figé à la création de chaque location.</p></div></div>
                <form action="/admin/prices" method="post" class="admin-form" id="pricing-form">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(
                        $csrf,
                        ENT_QUOTES,
                        "UTF-8",
                    ) ?>">
                    <fieldset class="pricing-scope"><legend>Où appliquer ce tarif ?</legend>
                        <label>Portée<select name="pricing_scope" id="pricing-scope"><option value="default" <?= $selectedStation===''?'selected':'' ?>>Tarif général · stations sans tarif spécifique</option><option value="all">Toutes les stations · remplacer tous les tarifs</option><option value="selected" <?= $selectedStation!==''?'selected':'' ?>>Une station ou une sélection de stations</option></select></label>
                        <p id="pricing-scope-help">Le tarif général s’applique aux stations sans personnalisation, y compris les nouvelles stations.</p>
                        <div id="pricing-station-selection"><p>Cochez une ou plusieurs stations. Les montants ci-dessous seront appliqués à votre sélection.</p><div class="pricing-station-checks">
                        <?php foreach($stations as $station): ?><label><input type="checkbox" name="stations[]" value="<?= $h($station['imei']) ?>" <?= $selectedStation===$station['imei']?'checked':'' ?>><span><?= $h($station['label']?:$station['imei']) ?><small><?= $h($station['imei']) ?></small></span></label><?php endforeach; ?>
                        </div><p id="pricing-selection-status" role="status"></p></div>
                    </fieldset>
                    <label>Location (FCFA)<input id="rental-fee" type="number" min="1" name="rental_fee" value="<?= (int) $prices[
                        "rental_fee"
                    ] ?>" required></label>
                    <label class="lab-confirm"><input type="checkbox" name="deposit_enabled" value="1" <?= (int) $prices[
                        "deposit_enabled"
                    ] === 1
                        ? "checked"
                        : "" ?>> Activer la caution sur les nouvelles locations</label>
                    <label>Caution par défaut (FCFA)<input id="default-deposit" type="number" min="0" name="default_deposit" value="<?= (int) $prices[
                        "default_deposit"
                    ] ?>" required></label>
                    <label>Durée incluse (minutes)<input type="number" min="1" name="duration_minutes" value="<?= (int) $prices[
                        "duration_minutes"
                    ] ?>" required></label>
                    <label>Délai de grâce (minutes)<input type="number" name="grace_minutes" min="0" max="1440" required value="<?= (int)($prices['grace_minutes']??5) ?>"><small>Minutes gratuites après la durée incluse. 0 désactive la grâce. Nouvelles locations uniquement.</small></label>
                    <input type="hidden" name="late_percent" value="<?= (int)$prices['late_percent'] ?>">
                    <div class="kiosk-notice"><strong>Comment se calcule le dépassement ?</strong><p>La durée incluse commence à la sortie physique confirmée. Le délai de grâce configuré est gratuit. Ensuite : tarif de location × secondes de retard au-delà du délai de grâce ÷ durée incluse en secondes. Le montant est arrondi une seule fois au FCFA supérieur et plafonné à la caution.</p><p id="billing-example"></p><p>Sans caution : aucune retenue automatique. Les anciennes locations conservent la règle de retenue par heure entamée enregistrée à leur création.</p></div>
                    <div class="form-action"><span>Le changement ne modifie pas les locations déjà créées.</span><button class="admin-button">Enregistrer les tarifs</button></div>
                </form>
            </section>
            <?php endif; ?>

<section class="admin-card"><div class="admin-section-heading"><div><span class="admin-overline">PAR STATION</span><h2>Tarifs appliqués</h2><p>Un tarif spécifique remplace le tarif général pour cette station. La caution propre à une batterie reste prioritaire si la caution est activée.</p></div><a class="admin-ghost" href="/admin/pricing">Voir le tarif général</a></div><div class="station-price-grid">
<?php foreach($stations as $station): $effective=$stationPrices[$station['imei']]; ?><article class="station-price-card"><div><h3><?= $h($station['label']?:$station['imei']) ?></h3><small><?= $h($station['imei']) ?></small><span class="station-price-source"><?= $effective['is_station_price']?'Tarif spécifique':'Tarif général' ?></span></div><p><strong><?= number_format((int)$effective['rental_fee'],0,',',' ') ?> FCFA</strong> / <?= (int)$effective['duration_minutes'] ?> min</p><p>Caution : <?= (int)$effective['deposit_enabled']===1?number_format((int)$effective['default_deposit'],0,',',' ').' FCFA':'désactivée' ?> · Grâce : <?= (int)$effective['grace_minutes'] ?> min</p>
<?php if(App\Services\Auth::can('pricing.manage')): ?><div class="station-price-actions"><a class="admin-ghost" href="/admin/pricing?station=<?= rawurlencode($station['imei']) ?>#pricing">Modifier</a><?php if($effective['is_station_price']): ?><form method="post" action="/admin/prices"><input type="hidden" name="csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="pricing_scope" value="inherit"><input type="hidden" name="stations[]" value="<?= $h($station['imei']) ?>"><button class="admin-ghost">Revenir au tarif général</button></form><?php endif; ?></div><?php endif; ?></article><?php endforeach; ?>
<?php if(!$stations): ?><p>Aucune station enregistrée. Le tarif général sera appliqué aux nouvelles stations.</p><?php endif; ?></div></section>

<script>(()=>{const fee=document.querySelector('[name="rental_fee"]'),duration=document.querySelector('[name="duration_minutes"]'),deposit=document.querySelector('[name="default_deposit"]'),enabled=document.querySelector('[name="deposit_enabled"]'),target=document.getElementById('billing-example');if(!target)return;const grace=document.querySelector('[name="grace_minutes"]');const update=()=>{const g=Number(grace.value),f=Number(fee.value),m=Number(duration.value),d=enabled.checked?Number(deposit.value):0;if(f>0&&m>0){const c=Math.ceil(f*10/m);target.textContent=`Exemple : ${f} FCFA pour ${m} minutes. Un retour après ${m+g+10} minutes représente ${g+10} minutes de retard, dont ${g} gratuites : 10 minutes facturables, soit ${c} FCFA calculés. Retenue : ${Math.min(d,c)} FCFA ; caution remboursable : ${Math.max(0,d-c)} FCFA.`;}};[fee,duration,deposit,enabled,grace].forEach(el=>el.addEventListener('input',update));update();})();</script>
</main><?php require __DIR__ . "/partials/admin_shell_end.php"; ?></body></html>
