<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Tarification · Thiebapower</title><link rel="stylesheet" href="/style.css"></head><body class="admin-body">
<?php
$adminPageTitle = "Tarification";
$adminPageSubtitle = "Vos tarifs et règles de caution, au même endroit.";
require __DIR__ . "/partials/admin_shell_start.php";
?>
<main class="management-main">
<?php if(($_GET['saved']??'')==='1'): ?><p class="tb-success" role="status"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Modifications enregistrées.</p><?php endif; ?>
<section class="tb-hero tb-hero-compact"><span class="tb-eyebrow">VOTRE OFFRE</span><h2><?= number_format(
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
                <form action="/admin/prices" method="post" class="admin-form">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(
                        $csrf,
                        ENT_QUOTES,
                        "UTF-8",
                    ) ?>">
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
                    <input type="hidden" name="late_percent" value="<?= (int)$prices['late_percent'] ?>">
                    <div class="kiosk-notice"><strong>Comment se calcule le dépassement ?</strong><p>La durée incluse commence à la sortie physique confirmée. Les cinq premières minutes de retard sont gratuites. Ensuite : tarif de location × secondes de retard au-delà de 5 minutes ÷ durée incluse en secondes. Le montant est arrondi une seule fois au FCFA supérieur et plafonné à la caution.</p><p id="billing-example"></p><p>Sans caution : aucune retenue automatique. Les anciennes locations conservent la règle de retenue par heure entamée enregistrée à leur création.</p></div>
                    <div class="form-action"><span>Le changement ne modifie pas les locations déjà créées.</span><button class="admin-button">Enregistrer les tarifs</button></div>
                </form>
            </section>
            <?php endif; ?>

<script>(()=>{const fee=document.querySelector('[name="rental_fee"]'),duration=document.querySelector('[name="duration_minutes"]'),deposit=document.querySelector('[name="default_deposit"]'),enabled=document.querySelector('[name="deposit_enabled"]'),target=document.getElementById('billing-example');if(!target)return;const update=()=>{const f=Number(fee.value),m=Number(duration.value),d=enabled.checked?Number(deposit.value):0;if(f>0&&m>0){const c=Math.ceil(f*10/m);target.textContent=`Exemple : ${f} FCFA pour ${m} minutes. Un retour après ${m+15} minutes représente 15 minutes de retard, dont 5 gratuites : 10 minutes facturables, soit ${c} FCFA calculés. Retenue : ${Math.min(d,c)} FCFA ; caution remboursable : ${Math.max(0,d-c)} FCFA.`;}};[fee,duration,deposit,enabled].forEach(el=>el.addEventListener('input',update));update();})();</script>
</main><?php require __DIR__ . "/partials/admin_shell_end.php"; ?></body></html>
