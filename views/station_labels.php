<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Étiquettes des stations · Thiebapower</title><link rel="stylesheet" href="/style.css"></head><body class="admin-body label-page">
<?php $adminPageTitle='Étiquettes QR'; $adminPageOverline='PARC HEYCHARGE'; $adminPageSubtitle='Une étiquette par station, prête à imprimer'; require __DIR__.'/partials/admin_shell_start.php'; ?>
<div class="label-toolbar"><a href="/admin/stations">← Retour au parc</a><strong>Format 100 × 150 mm</strong><button type="button" onclick="window.print()">Imprimer les étiquettes</button></div>
<main class="label-sheet">
<?php foreach($labels as $station): ?><article class="station-label">
 <div class="label-top"><span class="label-brand">THIEBA<b>POWER</b></span><span class="label-bolt">ϟ</span></div>
 <p class="label-kicker">BATTERIES EXTERNES EN LIBRE SERVICE</p><h1>De l’énergie<br>à emporter.</h1>
 <div class="label-qr"><?= $station['qr'] ?></div><h2>Scannez pour louer</h2><p>Choisissez une batterie sur votre téléphone, payez, puis récupérez-la dans ce terminal.</p>
 <div class="label-steps"><span>01 · Scanner</span><span>02 · Choisir</span><span>03 · Récupérer</span></div>
 <div class="label-bottom"><strong><?= htmlspecialchars($station['label']?:'Station Thiebapower',ENT_QUOTES,'UTF-8') ?></strong><small>Station <?= htmlspecialchars($station['imei'],ENT_QUOTES,'UTF-8') ?></small><small><?= htmlspecialchars($station['url'],ENT_QUOTES,'UTF-8') ?></small><?php if(!$station['enabled']): ?><small>À activer avant installation</small><?php endif; ?></div>
 </article><?php endforeach; ?>
<?php if(!$labels): ?><p>Aucune étiquette disponible. Vérifiez l’URL du site et les stations synchronisées.</p><?php endif; ?>
</main><?php require __DIR__.'/partials/admin_shell_end.php'; ?></body></html>
