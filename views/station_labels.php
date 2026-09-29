<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Étiquettes des stations · Thiebapower</title><link rel="stylesheet" href="/style.css"><script src="/labels.js" defer></script></head><body class="admin-body">
<?php $adminPageTitle='Étiquettes QR';$adminPageOverline='PARC HEYCHARGE';$adminPageSubtitle='Marges et aperçu A4 paysage avant impression';require __DIR__.'/partials/admin_shell_start.php';
$escape=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');$imei=(string)($_GET['imei']??''); ?>
<section class="admin-card label-settings">
<?php if(isset($_GET['saved'])): ?><p role="status">Paramétrage enregistré.</p><?php endif; ?>
<h2>Marges de la feuille A4 paysage</h2><p>Valeurs en centimètres. L’étiquette est centrée dans la zone disponible ; son QR code conserve ses proportions. Diminuez les marges pour agrandir toute l’étiquette.</p>
<form id="label-settings" action="/admin/stations/labels/settings" method="post">
<input type="hidden" name="csrf" value="<?= $escape($_SESSION['csrf']) ?>"><input type="hidden" name="imei" value="<?= $escape($imei) ?>">
<div class="label-margin-fields"><?php foreach(['left'=>'Gauche','right'=>'Droite','top'=>'Haut','bottom'=>'Bas'] as $key=>$caption): ?><label><?= $caption ?> (cm)<input type="number" name="<?= $key ?>" min="0" max="29.7" step="0.1" value="<?= $escape($margins[$key]/10) ?>" required></label><?php endforeach; ?></div>
<p id="label-measurements" role="status"></p><p id="label-error" role="alert" hidden></p>
<div class="label-actions"><button class="admin-button" type="submit">Enregistrer le paramétrage</button><button class="admin-ghost" type="button" id="label-defaults">Rétablir 5 / 7 cm</button><?php if($labels): ?><a class="admin-button" id="label-download" href="/admin/stations/labels.pdf<?= $imei!==''?'?imei='.rawurlencode($imei):'' ?>">Télécharger le PDF</a><a class="admin-ghost" id="label-print" target="_blank" rel="noopener" href="/admin/stations/labels.pdf?preview=1<?= $imei!==''?'&imei='.rawurlencode($imei):'' ?>">Ouvrir pour imprimer</a><?php endif; ?></div>
</form></section>
<section class="admin-card"><h2>Aperçu avant impression</h2><p>L’aperçu affiche le PDF exact avec les marges saisies. Enregistrez pour retrouver ces réglages lors des prochains téléchargements. À l’impression, choisissez A4 paysage et taille réelle (100 %).</p>
<?php if($labels): ?><iframe id="label-preview" title="Aperçu PDF des étiquettes" src="/admin/stations/labels.pdf?preview=1<?= $imei!==''?'&imei='.rawurlencode($imei):'' ?>"></iframe><?php else: ?><p>Aucune station à imprimer.</p><?php endif; ?></section>
<?php require __DIR__.'/partials/admin_shell_end.php'; ?></body></html>
