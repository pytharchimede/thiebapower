<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="referrer" content="no-referrer"><title>Mes locations · Thiebapower</title><link rel="stylesheet" href="/style.css"><link rel="stylesheet" href="/station-experience.css?v=20261004-3"><link rel="stylesheet" href="/my-rentals.css?v=20261004-1"><script src="/my-rentals.js?v=20261004-2" defer></script></head>
<body class="kiosk customer-rentals-page" data-promotions="<?= \App\Services\PromotionService::enabled()?'1':'0' ?>">
<header class="kiosk-header"><a class="kiosk-logo" href="/">THIEBA<span>POWER</span></a><a class="customer-station-link" href="/stations/map">Trouver une station <span aria-hidden="true">↗</span></a></header>
<main class="customer-rentals-shell">
<section class="customer-rentals-intro"><span class="customer-eyebrow">VOTRE ESPACE THIEBA POWER</span><h1>Chaque location,<br><span>en toute simplicité.</span></h1><p>Suivez votre batterie, retrouvez vos reçus et contactez notre équipe en cas de problème.</p></section>
<div class="customer-summary" aria-label="Résumé de vos locations"><article><span>Mes locations</span><strong id="customer-total">—</strong></article><article><span>En cours</span><strong id="customer-active">—</strong></article><article><span>Retours confirmés</span><strong id="customer-returned">—</strong></article></div>
<div class="customer-toolbar"><h2>Mes locations</h2><button id="refresh-rentals" class="customer-button customer-button-secondary" type="button">Actualiser le suivi</button><p id="my-rentals-status" role="status" aria-live="polite">Chargement de votre suivi…</p></div>
<div id="my-rentals-list" class="customer-rentals-list"></div>
<footer class="customer-device-note"><div><strong>Votre historique reste sur cet appareil.</strong><p>Seules les locations commencées depuis ce navigateur sont retrouvées ici. Sur un téléphone partagé, effacez votre historique après utilisation.</p></div><button id="forget-rentals" type="button">Effacer sur cet appareil</button></footer>
</main><?php require __DIR__.'/partials/public_support.php'; ?></body></html>
