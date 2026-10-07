<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reversements Paiement Pro · Thiebapower</title>
<link rel="stylesheet" href="/style.css">
<link rel="stylesheet" href="/payout.css">
<script src="/payout-report.js" defer></script>
</head>
<body class="admin-body payout-page">
<?php $adminPageTitle='Reversements API'; $adminPageOverline='PAIEMENT PRO'; $adminPageSubtitle='Suivi des appels et réponses du fournisseur'; require __DIR__.'/partials/admin_shell_start.php'; ?>
<main class="payout-main">
<div class="grid">
<section class="card">
<div class="eyebrow">Connexion active</div>
<h2>Endpoint et méthode</h2>
<p>Environnement : <strong>
<?=htmlspecialchars($mode,ENT_QUOTES,'UTF-8')?>
</strong>
<br>WSDL : <span class="mono">
<?=htmlspecialchars($endpoint?:'non configuré',ENT_QUOTES,'UTF-8')?>
</span>
<br>Méthode : <code>initTransact</code>
<br>Vérification : <code>getTransStatus(sessionid)</code>
</p>
<p class="small">Le token est calculé avec HMAC SHA-256 à partir du timestamp et de l’ID marchand. Sa valeur et la clé secrète restent masquées.</p>
</section>
<section class="card">
<div class="eyebrow">État des essais payout</div>
<h2>
<?=$labOpen?> essai(s) en cours</h2>
<p>Chaque essai crée une nouvelle référence. Une réponse INITIATED confirme seulement la prise en charge de la demande. La réception des fonds doit être vérifiée auprès de Paiement Pro.</p>
<p class="notice good">Cette page ne lance aucun remboursement de caution.</p>
</section>
</div>
<section class="card">
<div class="eyebrow">Dernière réponse reçue</div>
<h2>
<?php if($events):?>
<?=htmlspecialchars($events[0]['source'],ENT_QUOTES,'UTF-8')?> · <?=htmlspecialchars($events[0]['reference'],ENT_QUOTES,'UTF-8')?>
<?php else:?>Aucun appel de test enregistré<?php endif?>
</h2>
<?php if($events):?>
<pre>
<?=htmlspecialchars(json_encode(json_decode($events[0]['response'],true),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),ENT_QUOTES,'UTF-8')?>
</pre>
<p class="small muted">
<?=htmlspecialchars($events[0]['created_at'],ENT_QUOTES,'UTF-8')?>
</p>
<?php else:?>
<p>Le statut, le code, la description et la session renvoyés par l’API apparaîtront ici après l’essai.</p>
<?php endif?>
</section>
<section class="card">
<div class="eyebrow">Envoi contrôlé</div>
<h2>Tester un payout · 200 FCFA</h2>
<p>Une référence neuve sera créée. Le formulaire envoie un vrai paiement de 200 FCFA au numéro choisi.</p>
<?php if(!$enabled):?>
<p class="notice">Fonction désactivée : activer <code>PAYMENT_LAB_PAYOUT_ENABLED=1</code> dans <code>.env</code>.</p>
<?php elseif($labOpen):?>
<p class="notice">Un essai est en attente. Vérifiez son résultat chez Paiement Pro puis clôturez-le localement dans l’historique pour faire un nouveau test.</p>
<?php endif?>
<div class="grid">
<form method="post" action="/admin/payment-lab/payout">
<input type="hidden" name="csrf" value="<?=htmlspecialchars($csrf,ENT_QUOTES,'UTF-8')?>">
<label>Numéro du bénéficiaire<input name="phone" type="tel" required placeholder="+225...">
</label>
<?php $paymentField='channel';$paymentLegend='Canal';require __DIR__.'/partials/payment_channels.php'; ?>
<label class="check">
<input type="checkbox" name="confirm_amount" value="200" required>Je confirme le versement réel de 200 FCFA.</label>
<button class="button" <?= $enabled && $labOpen === 0 && $canSend ? '' : 'disabled' ?>>Envoyer 200 FCFA à Paiement Pro</button>
</form>
<div>
<strong>Paramètres de l’appel</strong>
<pre>merchantId: ID configuré dans .env
currency: XOF
amount: 200
referenceNo: générée lors de l’envoi
channel: choix du formulaire
payeeNo: numéro du formulaire
clientName: Test Thiebapower
timestamp: horodatage de l’envoi
token: [HMAC masqué]
returnURL: https://thiebapower.com/payment/return
callbackURL: https://thiebapower.com/api/paiementpro/payout-callback</pre>
<p class="small muted">L’appel effectivement envoyé est archivé plus bas avec sa référence et ses paramètres filtrés.</p>
</div>
</div>
</section>
<section class="card">
<div class="eyebrow">Historique des essais</div>
<h2>Réponses et sessions</h2>
<?php if($canSend&&$enabled):?>
<form method="post" action="/admin/payment-lab/clear-payout-history">
<input type="hidden" name="csrf" value="<?=htmlspecialchars($csrf,ENT_QUOTES,'UTF-8')?>">
<button class="button">Vider l’historique local des essais</button>
<p class="small">Clôture les essais locaux et permet un nouvel essai. Les journaux sont conservés. Une transaction déjà initiée peut encore être payée par Paiement Pro ; cette action ne l’annule pas.</p>
</form>
<?php endif;?>
<div class="tablewrap">
<table>
<thead>
<tr>
<th>Référence</th>
<th>Montant</th>
<th>Session</th>
<th>Statut</th>
<th>Réponse</th>
<th>Suivi</th>
</tr>
</thead>
<tbody>
<?php foreach($operations as $op):?>
<tr>
<td class="mono">
<?=htmlspecialchars($op['reference'],ENT_QUOTES,'UTF-8')?>
</td>
<td>
<?=(int)$op['amount']?> FCFA</td>
<td class="mono">
<?=htmlspecialchars((string)($op['provider_session_id']??'—'),ENT_QUOTES,'UTF-8')?>
</td>
<td>
<span class="pill">
<?=htmlspecialchars($op['status'],ENT_QUOTES,'UTF-8')?>
</span>
</td>
<td>
<?=htmlspecialchars((string)($op['provider_message']??'—'),ENT_QUOTES,'UTF-8')?>
</td>
<td>
<button type="button" class="button" data-copy-report="payout-report-<?=(int)$op['id']?>">Copier le rapport fournisseur</button>
<details><summary>Voir le rapport</summary>
<textarea id="payout-report-<?=(int)$op['id']?>" readonly rows="12" style="width:100%;min-width:280px"><?=htmlspecialchars($reports[$op['id']],ENT_QUOTES,'UTF-8')?></textarea>
</details>
<span data-copy-feedback="payout-report-<?=(int)$op['id']?>" role="status" aria-live="polite"></span>
<?php if($canSend&&in_array($op['status'],['initiated','processing'],true)&&($authorizationUrls[$op['id']]??'')!==''):?>
<a class="button" href="<?=htmlspecialchars($authorizationUrls[$op['id']],ENT_QUOTES,'UTF-8')?>" target="_blank" rel="noopener noreferrer">Ouvrir l’authentification Paiement Pro</a>
<?php endif;?>
<?php if(($op['status']==='processing'&&$op['provider_session_id'])||$op['status']==='initiated'):?>
<form method="post" action="/admin/payment-lab/reconcile">
<input type="hidden" name="csrf" value="<?=htmlspecialchars($csrf,ENT_QUOTES,'UTF-8')?>">
<input type="hidden" name="id" value="<?=(int)$op['id']?>">
<button class="button" <?= $canSend ? '' : 'disabled' ?>><?=$op['status']==='initiated'?'Récupérer la session et vérifier':'Vérifier'?></button>
</form>
<?php endif;?>
<?php if(in_array($op['status'],['unknown','initiated'],true)):?>
<form method="post" action="/admin/payment-lab/archive">
<input type="hidden" name="csrf" value="<?=htmlspecialchars($csrf,ENT_QUOTES,'UTF-8')?>">
<input type="hidden" name="id" value="<?=(int)$op['id']?>">
<label class="small">Recopier la référence<input name="confirm_reference" required autocomplete="off">
</label>
<label class="small">Résultat vérifié / motif<input name="archive_note" required minlength="6" maxlength="250" placeholder="Ex. Versement reçu confirmé dans Paiement Pro">
</label>
<label class="check small">
<input type="checkbox" name="confirm_archive" value="1" required>Je comprends que ceci clôt seulement l’essai local, sans annuler la transaction fournisseur.</label>
<button class="button" <?= $canSend ? '' : 'disabled' ?>>Clore cet essai</button>
</form>
<?php elseif($op['status']==='archived'):?>Clôturé localement<br>
<small>
<?=htmlspecialchars((string)($op['archive_note']??''),ENT_QUOTES,'UTF-8')?>
</small>
<?php else:?>—<?php endif?>
</td>
</tr>
<?php endforeach?>
</tbody>
</table>
</div>
</section>
<section class="card">
<div class="eyebrow">Restitutions de cautions</div>
<h2>État final des reversements automatiques</h2>
<p class="small">En attente = non envoyé ; vérification = session reçue ; initié = accepté sans session ; issue inconnue = ne pas réémettre ; réussi = confirmé par vérification fournisseur.</p>
<div class="tablewrap"><table>
<thead><tr><th>Location</th><th>Reversement</th><th>Montant</th><th>Bénéficiaire</th><th>Session</th><th>Statut final</th></tr></thead>
<tbody>
<?php foreach ($settlements as $settlement): ?>
<tr>
<td class="mono"><?=htmlspecialchars($settlement['rental_reference'], ENT_QUOTES, 'UTF-8')?></td>
<td class="mono"><?=htmlspecialchars((string) ($settlement['provider_reference'] ?? '—'), ENT_QUOTES, 'UTF-8')?></td>
<td><?=(int) $settlement['refund_amount']?> FCFA</td>
<td><?=htmlspecialchars((string) $settlement['payout_channel'], ENT_QUOTES, 'UTF-8')?> · <?=htmlspecialchars((string) $settlement['customer_phone'], ENT_QUOTES, 'UTF-8')?></td>
<td class="mono"><?=htmlspecialchars((string) ($settlement['provider_session_id'] ?? '—'), ENT_QUOTES, 'UTF-8')?></td>
<td><span class="pill"><?=htmlspecialchars(['pending' => 'en attente', 'processing' => 'vérification', 'initiated' => 'initié', 'unknown' => 'issue inconnue', 'refunded' => 'réussi', 'failed' => 'échoué'][$settlement['status']] ?? $settlement['status'], ENT_QUOTES, 'UTF-8')?></span></td>
</tr>
<?php endforeach; ?>
<?php if (!$settlements): ?><tr><td colspan="6">Aucune restitution.</td></tr><?php endif; ?>
</tbody></table></div>
</section>
<div class="grid">
<section class="card">
<div class="eyebrow">Requêtes</div>
<h2>Paramètres réellement envoyés</h2>
<?php foreach($requests as $req):?>
<details class="event">
<summary>
<?=htmlspecialchars($req['reference'],ENT_QUOTES,'UTF-8')?> · <?=htmlspecialchars($req['created_at'],ENT_QUOTES,'UTF-8')?>
</summary>
<p class="small mono">
<?=htmlspecialchars($req['endpoint'],ENT_QUOTES,'UTF-8')?> · initTransact</p>
<pre>
<?=htmlspecialchars(json_encode(json_decode($req['parameters'],true),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),ENT_QUOTES,'UTF-8')?>
</pre>
</details>
<?php endforeach?>
<?php if(!$requests):?>
<p class="muted">Aucun appel archivé depuis l’installation de ce journal.</p>
<?php endif?>
</section>
<section class="card">
<div class="eyebrow">Retours API</div>
<h2>Réponses et erreurs</h2>
<p class="small">INITIATED avec code 0 ne prouve pas le versement. Les callbacks non vérifiés ne l’attestent pas non plus.</p>
<?php foreach($events as $event):?>
<details class="event">
<summary>
<?=htmlspecialchars($event['reference'],ENT_QUOTES,'UTF-8')?> · <?=htmlspecialchars($event['source'],ENT_QUOTES,'UTF-8')?> · <?=htmlspecialchars($event['created_at'],ENT_QUOTES,'UTF-8')?>
</summary>
<pre>
<?=htmlspecialchars(json_encode(json_decode($event['response'],true),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),ENT_QUOTES,'UTF-8')?>
</pre>
</details>
<?php endforeach?>
<?php if(!$events):?>
<p class="muted">Aucune réponse archivée depuis l’installation de ce journal.</p>
<?php endif?>
</section>
</div>
<p class="footer">Le token HMAC et la clé secrète ne sont jamais affichés. Aucun bouton ne réémet une référence inconnue.</p>
</main>
<?php require __DIR__.'/partials/admin_shell_end.php'; ?>
</body>
</html>
