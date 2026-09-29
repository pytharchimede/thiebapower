<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#103d46">
    <title>Louer une batterie · Thiebapower</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body class="kiosk">
<div class="kiosk-shell">
    <header class="kiosk-header">
        <a class="kiosk-logo" href="/">THIEBA<span>POWER</span></a>
        <div class="kiosk-header-right"><span class="live-dot"></span> Votre énergie, partout</div>
    </header>
    <main class="kiosk-main">
        <aside class="kiosk-side">
            <span class="kiosk-tag">LIBRE SERVICE · CÔTE D’IVOIRE</span>
            <h1>Une batterie.<br><em>Votre liberté.</em></h1>
            <p>Empruntez une batterie externe en quelques étapes et continuez votre journée l’esprit tranquille.</p>
            <div class="bank-illustration" aria-hidden="true"><div class="bank-cap"></div><span>ϟ</span><small>THIEBAPOWER</small></div>
            <div class="side-footer"><span>À partir de</span><strong><?= number_format((int) $prices['rental_fee'], 0, ',', ' ') ?> FCFA</strong><small>pour <?= (int) $prices['duration_minutes'] ?> minutes</small></div>
        </aside>
        <section class="kiosk-workflow" aria-label="Parcours de location">
            <div class="workflow-top"><span class="workflow-label">LOCATION DE POWERBANK</span><span class="workflow-step" id="step-counter">Étape 1 sur 3</span></div>
            <div class="progress" aria-hidden="true"><span id="progress-bar"></span></div>
            <div class="kiosk-screen" data-step="1">
                <div class="screen-icon" aria-hidden="true">▦</div>
                <h2>Scannez la station</h2>
                <p>Scannez le QR code de la station ou saisissez son code.</p>
                <button type="button" id="scan-button" class="touch-button outline">Scanner le QR code</button>
                <video id="scan-video" playsinline hidden></video>
                <label class="input-label" for="station-code">Code de la station</label>
                <input id="station-code" maxlength="120" autocomplete="off" placeholder="Saisir le code affiché sur la station">
                <p id="scan-result" class="screen-hint" role="status">La caméra ne s’active qu’à votre demande.</p>
                <button type="button" class="touch-button primary" id="station-next">Continuer <span aria-hidden="true">→</span></button>
            </div>
            <div class="kiosk-screen" data-step="2" hidden>
                <button type="button" class="back-button" data-back="1">← Retour</button>
                <div class="screen-icon" aria-hidden="true">ϟ</div>
                <h2>Choisissez une batterie</h2>
                <p><?= $depositEnabled ? 'La caution peut varier selon la batterie.' : 'Aucune caution demandée.' ?></p>
                <div class="battery-options" id="battery-options">
                    <?php foreach ($batteries as $battery): ?>
                        <button type="button" class="battery-option"
                            data-id="<?= (int) $battery['id'] ?>"
                            data-serial="<?= htmlspecialchars($battery['serial'], ENT_QUOTES, 'UTF-8') ?>"
                            data-deposit="<?= $depositEnabled ? (int) ($battery['deposit_override'] ?? $prices['default_deposit']) : 0 ?>" data-station="<?= htmlspecialchars((string)$battery['station_imei'],ENT_QUOTES,'UTF-8') ?>">
                            <span class="option-symbol" aria-hidden="true">ϟ</span>
                            <span><strong><?= htmlspecialchars($battery['serial'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= $depositEnabled ? 'Caution '.number_format((int) ($battery['deposit_override'] ?? $prices['default_deposit']), 0, ',', ' ').' FCFA' : 'Sans caution' ?></small></span>
                            <span class="option-arrow" aria-hidden="true">→</span>
                        </button>
                    <?php endforeach; ?>
                </div>
                <?php if (!$batteries): ?><div class="kiosk-notice">Aucune batterie disponible actuellement.</div><?php endif; ?>
            </div>
            <div class="kiosk-screen" data-step="3" hidden>
                <button type="button" class="back-button" data-back="2">← Retour</button>
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
                <p class="deposit-info" <?= $depositEnabled ? '' : 'hidden' ?>>Retour dans le délai : caution intégralement restituable. Après le délai : <?= (int) $prices['late_percent'] ?> % de la caution retenus par heure supplémentaire entamée, dans la limite de la caution.</p>
                <?php if ($checkoutEnabled): ?>
                    <form method="post" action="/rentals" id="rental-checkout">
                        <input type="hidden" name="station_code" id="checkout-station">
                        <input type="hidden" name="battery_id" id="checkout-battery">
                        <label class="input-label">Votre nom<input name="name" required maxlength="160"></label>
                        <label class="input-label">Votre email<input name="email" type="email" required></label>
                        <label class="input-label">Votre téléphone<input name="phone" type="tel" required placeholder="+225..."></label>
                        <?php if ($depositEnabled): ?><label class="input-label">Canal de restitution
                            <select name="payout_channel" required><option value="">Choisir</option><option value="WAVECI">Wave CI</option><option value="MOMOCI">MTN MoMo CI</option><option value="OMCIV">Orange Money CI</option><option value="FLOOZ">Flooz</option></select>
                        </label><?php endif; ?>
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
<script>window.TB_PRICE = <?= (int) $prices['rental_fee'] ?>;</script>
<script src="/app.js" defer></script>
</body>
</html>
