<?php
$shareUrl=rtrim(\App\Core\App::env('APP_URL','https://thiebapower.com'),'/').'/stations/map';
$shareText='Thiebapower : '.$p['discount_amount'].' FCFA de remise sur votre caution avec le code '.$p['code'].'. Valable jusqu’au '.$end.'. Saisissez le code avant le paiement. Tarif de location inchangé, caution réellement payée remboursable, une utilisation par numéro, dans la limite des utilisations disponibles.';
if($p['kind']==='loyalty')$shareText.=' Offre fidélité : '.$p['minimum_completed'].' locations terminées requises sur le même navigateur.';
if($p['kind']==='referral')$shareText.=' Offre réservée à un proche du parrain.';
?>
<div class="promo-sharing" data-share-text="<?= $h($shareText) ?>" data-share-url="<?= $h($shareUrl) ?>" data-code="<?= $h($p['code']) ?>">
<button type="button" class="promo-copy">Copier le code</button><a href="https://wa.me/?text=<?= rawurlencode($shareText.' '.$shareUrl) ?>" target="_blank" rel="noopener noreferrer">WhatsApp ↗</a><button type="button" class="promo-native">Partager…</button><details><summary>Autres réseaux</summary><a href="https://t.me/share/url?url=<?= rawurlencode($shareUrl) ?>&amp;text=<?= rawurlencode($shareText) ?>" target="_blank" rel="noopener noreferrer">Telegram</a><a href="https://twitter.com/intent/tweet?text=<?= rawurlencode($shareText.' '.$shareUrl) ?>" target="_blank" rel="noopener noreferrer">X</a><a href="https://www.facebook.com/sharer/sharer.php?u=<?= rawurlencode($shareUrl) ?>" target="_blank" rel="noopener noreferrer">Facebook (copiez le code dans votre publication)</a></details><p class="promo-share-status" role="status" aria-live="polite"></p>
</div>
