<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#103d46">
    <title>Louer une batterie · Thiebapower</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/public-entry.css?v=20261005-6">
</head>
<body class="kiosk public-entry public-rent">
<div class="kiosk-shell">
    <?php require __DIR__.'/partials/public_header.php'; ?>
    <main class="kiosk-main">
        <aside class="kiosk-side">
            <span class="kiosk-tag">LIBRE SERVICE · CÔTE D’IVOIRE</span>
            <h1>Une batterie.<br><em>La journée continue.</em></h1>
            <p>Empruntez une batterie externe en quelques étapes et continuez votre journée l’esprit tranquille.</p>
            <div class="bank-illustration" aria-hidden="true"><div class="bank-cap"></div><span>ϟ</span><small>THIEBAPOWER</small></div>
            <div class="side-footer"><span>À partir de</span><strong><?= number_format((int) $prices['rental_fee'], 0, ',', ' ') ?> FCFA</strong><small>pour <?= (int) $prices['duration_minutes'] ?> minutes</small></div>
        </aside>
        <section class="kiosk-workflow" aria-label="Parcours de location">
            <div class="workflow-top"><span class="workflow-label">STATION <?= htmlspecialchars($station['imei'],ENT_QUOTES,'UTF-8') ?></span><span class="workflow-step" id="step-counter">Étape 1 sur 2</span></div>
            <div class="progress" aria-hidden="true"><span id="progress-bar"></span></div>
            <div class="kiosk-screen" data-step="1">
                <div class="screen-icon" aria-hidden="true">ϟ</div>
                <h2>Choisissez une batterie</h2>
                <p class="station-identity">Station <?= htmlspecialchars($station['label']?:$station['imei'],ENT_QUOTES,'UTF-8') ?></p>
                <p><?= $depositEnabled ? 'La caution peut varier selon la batterie.' : 'Aucune caution demandée.' ?></p>
                <div class="battery-options" id="battery-options">
                    <?php foreach ($batteries as $battery): ?>
                        <button type="button" class="battery-option"
                            data-id="<?= (int) $battery['id'] ?>"
                            data-serial="<?= htmlspecialchars($battery['serial'], ENT_QUOTES, 'UTF-8') ?>"
                            data-slot="<?= htmlspecialchars((string)$battery['slot_id'], ENT_QUOTES, 'UTF-8') ?>"
                            data-deposit="<?= $depositEnabled ? (int) ($battery['deposit_override'] ?? $prices['default_deposit']) : 0 ?>" data-station="<?= htmlspecialchars((string)$battery['station_imei'],ENT_QUOTES,'UTF-8') ?>">
                            <span class="option-powerbank" aria-hidden="true"><span class="powerbank-ports"></span><span class="powerbank-light"></span><span class="powerbank-bolt">ϟ</span><span class="powerbank-brand">THIEBA POWER</span></span>
                            <span class="option-details">
                                <span class="option-topline"><span class="option-slot">Emplacement <?= htmlspecialchars((string)$battery['slot_id'],ENT_QUOTES,'UTF-8') ?></span><span class="option-charge"><svg viewBox="0 0 24 16" aria-hidden="true"><rect x="1" y="2" width="19" height="12" rx="2"/><path d="M22 6v4"/><rect x="4" y="5" width="<?= 13*max(0,min(100,(int)$battery['battery_capacity']))/100 ?>" height="6" class="charge-fill"/></svg><?= (int)$battery['battery_capacity'] ?> %</span></span>
                                <span class="option-rental-price"><strong><?= number_format((int)$prices['rental_fee'],0,',',' ') ?></strong><span>FCFA</span></span>
                                <span class="option-price-caption">Location · <?= (int)$prices['duration_minutes'] ?> minutes incluses</span>
                                <span class="option-deposit-note"><?= $depositEnabled ? 'Caution restituable : '.number_format((int)($battery['deposit_override']??$prices['default_deposit']),0,',',' ').' FCFA' : 'Sans caution' ?></span>
                                <span class="option-serial"><?= htmlspecialchars($battery['serial'],ENT_QUOTES,'UTF-8') ?></span>
                            </span>
                            <span class="option-arrow" aria-hidden="true">→</span>
                        </button>
                    <?php endforeach; ?>
                </div>
                <?php if (!$batteries): ?><div class="kiosk-notice"><?= $inventoryError ? 'Impossible de lire le terminal pour le moment. Réessayez dans quelques instants.' : 'Aucune batterie chargée et disponible actuellement.' ?></div><?php endif; ?>
            </div>
            <div class="kiosk-screen" data-step="2" hidden>
                <button type="button" class="back-button" data-back="1">← Retour</button>
                <div class="screen-icon" aria-hidden="true">✓</div>
                <h2>Votre récapitulatif</h2>
                <p>Vérifiez le tarif et la caution avant le paiement.</p>
                <div class="receipt">
                    <div><span>Station</span><strong id="summary-station">—</strong></div>
                    <div><span>Batterie</span><strong id="summary-battery">—</strong></div>
                    <div><span>Location · <?= (int) $prices['duration_minutes'] ?> min</span><strong id="summary-fee"><?= number_format((int) $prices['rental_fee'], 0, ',', ' ') ?> FCFA</strong></div>
                    <div <?= $depositEnabled ? '' : 'hidden' ?>><span>Caution restituable</span><strong id="summary-deposit">—</strong></div>
                    <div class="total"><span>Total à payer</span><strong id="summary-total">—</strong></div>
                </div>
                <p class="deposit-info"><?= (int)$prices['duration_minutes'] ?> minutes incluses, puis <?= (int)($prices['grace_minutes']??5) ?> minutes gratuites. Ensuite, le dépassement est calculé au prorata du tarif payé, arrondi au FCFA supérieur. <?= $depositEnabled ? 'Il est retenu sur la caution, sans jamais la dépasser. Le reste est remboursable.' : 'Sans caution, aucune retenue automatique ; merci de respecter le délai de retour.' ?></p>
                <?php if ($checkoutEnabled): ?>
                    <form method="post" action="/rentals" id="rental-checkout">
                        <input type="hidden" name="checkout_token" value="<?= bin2hex(random_bytes(16)) ?>"><input type="hidden" name="station_code" id="checkout-station" value="<?= htmlspecialchars($station['imei'],ENT_QUOTES,'UTF-8') ?>">
                        <input type="hidden" name="battery_id" id="checkout-battery">
                        <label class="input-label">Votre nom<input name="name" required autocomplete="name" maxlength="160"></label>
                        <label class="input-label">Votre téléphone<input name="phone" type="tel" required autocomplete="tel" inputmode="tel" placeholder="07 00 00 00 00 ou +225..."></label>
                        <?php if(\App\Services\PromotionService::enabled()&&$depositEnabled): ?>
                        <section class="checkout-promotion" aria-label="Code promotionnel facultatif">
                            <label for="promotion-code">Vous avez un code promo ? <small>Facultatif</small></label>
                            <p id="promotion-help">Renseignez votre téléphone ci-dessus, puis saisissez le code et appuyez sur « Appliquer ».</p>
                            <div class="promotion-input-row"><input name="promotion_code" id="promotion-code" maxlength="40" pattern="[A-Za-z0-9_-]{3,40}" placeholder="Votre code" aria-describedby="promotion-help promotion-status" disabled><button type="button" id="preview-promotion" class="touch-button outline" disabled>Appliquer</button></div>
                            <input type="hidden" name="previous_token" id="previous-rental-token">
                            <p id="promotion-status" role="status" aria-live="polite">Renseignez un téléphone valide pour activer le champ. La remise porte uniquement sur la caution.</p>
                        </section><script src="/promotions.js?v=20261005-3" defer></script><?php endif; ?>
                        <?php if ($depositEnabled): $paymentField='payment_channel';$paymentLegend='Moyen de paiement';require __DIR__.'/partials/payment_channels.php'; ?><p class="tb-muted">La caution restante sera remboursée automatiquement par le même moyen de paiement, au numéro renseigné, après le retour confirmé de la batterie.</p><?php endif; ?>
                        <?php require __DIR__.'/partials/payment_logos.php'; ?>
                        <button class="touch-button primary">Procéder au paiement <span aria-hidden="true">→</span></button>
                    </form>
                <?php else: ?>
                    <div class="kiosk-notice"><strong>Service en préparation</strong><br>Le paiement et la sortie physique des batteries ne sont pas encore disponibles sur cette station.</div>
                    <button type="button" class="touch-button primary" disabled>Procéder au paiement</button>
                <?php endif; ?>
            </div>
        </section>
    </main>
    <footer class="kiosk-footer"><span>THIEBAPOWER · Votre énergie, partout</span><span>Besoin d’aide ? Adressez-vous au personnel de la station.</span></footer>
</div>
<script>window.TB_PRICE = <?= (int) $prices['rental_fee'] ?>; window.TB_STATION = <?= json_encode($station['imei'],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;</script>
<script src="/app.js?v=20261004-1" defer></script>
<?php require __DIR__.'/partials/public_support.php'; ?></body>
</html>
