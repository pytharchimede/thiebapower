<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Étiquettes des stations · Thiebapower</title><link rel="stylesheet" href="/style.css"></head><body class="admin-body label-page">
<?php $adminPageTitle='Étiquettes QR'; $adminPageOverline='PARC HEYCHARGE'; $adminPageSubtitle='Une étiquette par station, prête à imprimer'; require __DIR__.'/partials/admin_shell_start.php'; ?>
<div class="label-toolbar"><a href="/admin/stations">← Retour au parc</a><strong>A4 paysage · marges latérales 5 cm · marges haute et basse 7 cm</strong><a class="admin-button" href="/admin/stations/labels.pdf<?= isset($_GET['imei'])?'?imei='.rawurlencode((string)$_GET['imei']):'' ?>">Télécharger le PDF A4</a><button type="button" onclick="window.print()">Imprimer</button></div>
<main class="label-sheet">
<?php foreach($labels as $station): ?><article class="station-label">
 <div class="label-copy"><div class="label-top"><span class="label-brand">THIEBA<b>POWER</b></span></div><p class="label-kicker">BATTERIES EXTERNES EN LIBRE SERVICE</p><h1>Louez une batterie externe.</h1><p class="label-lead">Scannez le QR code, payez et récupérez votre batterie.</p><div class="label-process"><span><b>01</b> SCANNEZ</span><span><b>02</b> CHOISISSEZ</span><span><b>03</b> PAYEZ</span><span><b>04</b> RÉCUPÉREZ</span></div><div class="label-payment">Wave · Orange Money · MTN MoMo · Moov Money</div></div>
 <div class="label-scan"><div class="label-qr"><?= $station['qr'] ?></div><strong>SCANNEZ POUR LOUER</strong><small><?= htmlspecialchars($station['label']?:'Station Thiebapower',ENT_QUOTES,'UTF-8') ?></small><small><?= htmlspecialchars($station['imei'],ENT_QUOTES,'UTF-8') ?></small></div>
 </article><?php endforeach; ?>
<?php if(!$labels): ?><p>Aucune étiquette disponible. Vérifiez l’URL du site et les stations synchronisées.</p><?php endif; ?>
</main><?php require __DIR__.'/partials/admin_shell_end.php'; ?></body></html>
