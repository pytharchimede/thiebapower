<?php $publicSettings=\App\Services\PublicExperienceSettings::all();$publicH=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); ?>
<link rel="stylesheet" href="/public-support.css?v=1">
<style><?php foreach(\App\Services\PublicExperienceSettings::ELEMENTS as $key=>$element): if(empty($publicSettings['visible'][$key])): ?><?= $element[2] ?>{display:none!important;}<?php endif; endforeach; ?></style>
<?php if($publicSettings['support_enabled']&&array_filter(['whatsapp','phone','email','chat'],static fn($k)=>$publicSettings[$k.'_enabled'])): ?>
<details class="public-support"><summary><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 13v-1a8 8 0 0 1 16 0v1M4 12v6h4v-6zm12 0v6h4v-6M20 18v2h-6"/></svg><span>Besoin d’aide ?</span></summary><div class="public-support-panel"><strong>Nous contacter</strong><p>Choisissez votre canal. Pour une location bloquée, indiquez la station et la référence de location.</p>
<?php if($publicSettings['whatsapp_enabled']): ?><a href="https://wa.me/<?= rawurlencode(ltrim($publicSettings['whatsapp'],'+')) ?>?text=<?= rawurlencode($publicSettings['message']) ?>" target="_blank" rel="noopener noreferrer">WhatsApp <span>Écrire un message ↗</span></a><?php endif; ?>
<?php if($publicSettings['phone_enabled']): ?><a href="tel:<?= $publicH($publicSettings['phone']) ?>">Téléphone <span><?= $publicH($publicSettings['phone']) ?></span></a><?php endif; ?>
<?php if($publicSettings['email_enabled']): ?><a href="mailto:<?= $publicH($publicSettings['email']) ?>?subject=Assistance%20Thiebapower">Email <span><?= $publicH($publicSettings['email']) ?></span></a><?php endif; ?>
<?php if($publicSettings['chat_enabled']): ?><a href="<?= $publicH($publicSettings['chat']) ?>" target="_blank" rel="noopener noreferrer">Chat <span>Ouvrir la conversation ↗</span></a><?php endif; ?>
</div></details><?php endif; ?>
