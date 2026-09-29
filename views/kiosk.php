<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Scanner pour louer · Thiebapower</title><link rel="stylesheet" href="/style.css"></head>
<body class="kiosk kiosk-qr-page"><header class="kiosk-header"><a class="kiosk-logo" href="/">THIEBA<span>POWER</span></a><span>Votre énergie, partout</span></header>
<main class="kiosk-qr-shell"><section class="kiosk-qr-copy"><span class="kiosk-tag">LIBRE SERVICE · CÔTE D’IVOIRE</span><h1>Une batterie.<br><em>Votre liberté.</em></h1><p>Scannez le QR code avec votre téléphone. Choisissez une batterie, réglez votre location et récupérez-la dans la station.</p><?php require __DIR__.'/partials/payment_logos.php'; ?></section>
<section class="kiosk-qr-panel" aria-label="QR code de location">
<?php if($selected!==''&&$qr): ?>
 <span class="admin-overline">STATION THIEBAPOWER</span><h2>Scannez pour louer</h2><div class="kiosk-qr-art"><?= $qr ?></div><p class="station-identity"><?= htmlspecialchars($selected,ENT_QUOTES,'UTF-8') ?></p><p>Ouvrez l’appareil photo de votre téléphone et pointez-le sur ce QR code.</p>
 <?php if(count($stations)>1): ?><nav class="kiosk-station-switch" aria-label="Choisir une station"><?php foreach($stations as $item): ?><a <?= $item['imei']===$selected?'aria-current="page"':'' ?> href="/?kiosk=<?= rawurlencode($item['imei']) ?>"><?= htmlspecialchars($item['label']?:$item['imei'],ENT_QUOTES,'UTF-8') ?></a><?php endforeach; ?></nav><?php endif; ?>
<?php else: ?><h2>Service indisponible</h2><p>Aucune station active avec une étiquette QR valide.</p><?php endif; ?>
</section></main><footer class="kiosk-footer">THIEBAPOWER · Votre énergie, partout</footer></body></html>
