<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Locations · Thiebapower</title><link rel="stylesheet" href="/style.css"></head>
<body class="admin-body">
<?php
$adminPageTitle = "Locations";
$adminPageOverline = "EXPLOITATION";
$adminPageSubtitle = "Suivi des paiements et du matériel";
require __DIR__ . "/partials/admin_shell_start.php";
$esc=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$stateLabels=['active'=>'En cours','release_failed'=>'Sortie à vérifier','payment_review'=>'Paiement à rapprocher','releasing'=>'Sortie en cours','pending_payment'=>'Paiement en attente','returned'=>'Terminée · batterie retournée','payment_failed'=>'Paiement échoué','payment_timeout'=>'Paiement expiré'];
$ranks=['active'=>0,'release_failed'=>1,'payment_review'=>1,'releasing'=>2,'pending_payment'=>2,'returned'=>3,'payment_failed'=>3,'payment_timeout'=>3];
usort($counts,fn($a,$b)=>($ranks[$a['status']]??4)<=>($ranks[$b['status']]??4));
?>
<main class="management-main"><p class="admin-overline">EXPLOITATION</p><h1>Locations et incidents</h1>
<p>Les locations en cours apparaissent en premier, avec les dépassements en tête. Suivent les incidents, les attentes puis l’historique, du plus récent au plus ancien.</p>
<section class="management-card"><h2>Afficher les locations</h2><nav class="tb-rental-filters" aria-label="Filtrer les locations"><a href="/admin/rentals" <?= $status===''?'aria-current="page"':'' ?>>Toutes</a><?php foreach($counts as $count): ?><a href="/admin/rentals?status=<?= rawurlencode($count['status']) ?>" <?= $status===$count['status']?'aria-current="page"':'' ?>><?= $esc($stateLabels[$count['status']]??$count['status']) ?> <span><?= (int)$count['quantity'] ?></span></a><?php endforeach; ?></nav><p class="tb-muted">100 cartes maximum par page. Les filtres ci-dessus interrogent l’ensemble des locations ; la recherche et les exports portent sur les cartes affichées.</p></section>
<section class="management-card"><h2><?= $status?$esc($stateLabels[$status]??$status):'Locations à suivre et historique' ?></h2><p class="tb-rental-legend"><span><i class="fa-solid fa-stopwatch" aria-hidden="true"></i> En cours</span><span><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> À vérifier</span><span><i class="fa-solid fa-clock" aria-hidden="true"></i> En attente</span><span><i class="fa-solid fa-check" aria-hidden="true"></i> Historique grisé</span></p><div class="management-table-wrap"><table class="tb-rental-table"><thead><tr><th>Référence</th><th>Utilisation</th><th>État</th><th>Station / batterie</th><th>Paiement</th><th>Dates</th><th>Action</th></tr></thead><tbody>
<?php foreach($rentals as $r): $rank=$ranks[$r['status']]??3;$finished=$rank===3;$overdue=$r['status']==='active'&&!empty($r['due_at'])&&strtotime($r['due_at'].' UTC')<=time(); ?>
<tr class="tb-rental-card <?= ['is-active','is-incident','is-waiting','is-finished'][$rank] ?> <?= $overdue?'is-overdue':'' ?>" data-rental-reference="<?= $esc($r['reference']) ?>" data-rental-status="<?= $esc($r['status']) ?>" data-sort-time="<?= strtotime(($r['returned_at']??$r['started_at']??$r['created_at']).' UTC') ?>">
<td><a href="/admin/rentals/detail?reference=<?= rawurlencode($r['reference']) ?>"><strong><?= $esc($r['reference']) ?></strong></a><br><small><?= $esc($r['customer_name']) ?></small></td>
<td class="tb-rental-usage"><?php if(!empty($r['started_at'])): ?><div class="tb-use-timer" data-reference="<?= $esc($r['reference']) ?>" data-start="<?= strtotime($r['started_at'].' UTC') ?>" data-due="<?= !empty($r['due_at'])?strtotime($r['due_at'].' UTC'):0 ?>" data-end="<?= !empty($r['returned_at'])?strtotime($r['returned_at'].' UTC'):0 ?>" data-running="<?= $r['status']==='active'?'1':'0' ?>"><?php $seconds=max(0,(!empty($r['returned_at'])?strtotime($r['returned_at'].' UTC'):time())-strtotime($r['started_at'].' UTC')); ?><strong><?= sprintf('%02d:%02d:%02d',intdiv($seconds,3600),intdiv($seconds%3600,60),$seconds%60) ?> d’utilisation</strong></div><?php else: ?><span class="tb-muted">Utilisation non démarrée</span><?php endif; ?></td>
<td><span class="tb-rental-state status-pill" data-state="<?= $esc($r['status']) ?>"><?= $esc($stateLabels[$r['status']]??$r['status']) ?></span><?php if($r['refund_status']): ?><br><small>Restitution <?= $esc($r['refund_status']) ?></small><?php endif; ?></td>
<td><a href="/admin/stations/detail?imei=<?= rawurlencode((string)$r['station_code']) ?>"><?= $esc($r['station_code']) ?></a><br><?= $esc($r['battery_serial']) ?></td>
<td><?= number_format((int)$r['rental_fee']+(int)$r['deposit'],0,',',' ') ?> FCFA<br><small>Session <?= $esc($r['payment_session_id']??'—') ?></small></td>
<td>Créée : <?= $esc($r['created_at']) ?><?php if(!empty($r['returned_at'])): ?><br><small>Retournée : <?= $esc($r['returned_at']) ?></small><?php endif; ?></td>
<td class="tb-rental-actions"><?php if($r['status']==='returned' && App\Services\Auth::can('reports.export')): ?><a class="tb-link-button" href="/admin/rentals/receipt?reference=<?= rawurlencode($r['reference']) ?>">Télécharger le reçu</a><?php endif; ?><?php if (
    App\Services\Auth::can("rentals.manage") && in_array($r["status"], ["releasing", "release_failed", "active"], true)
): ?><form method="post" action="/admin/stations/reconcile"><input type="hidden" name="csrf" value="<?= htmlspecialchars(
    $_SESSION["csrf"],
    ENT_QUOTES,
    "UTF-8",
) ?>"><input type="hidden" name="reference" value="<?= htmlspecialchars(
    $r["reference"],
    ENT_QUOTES,
    "UTF-8",
) ?>"><button class="admin-ghost">Vérifier la station</button></form><?php endif; ?>
<?php if (
    App\Services\Auth::can("rentals.cancel") && $r["status"] === "pending_payment" &&
    $r["reservation_expires_at"] &&
    strtotime($r["reservation_expires_at"] . " UTC") <= time()
): ?><details><summary>Libérer après vérification</summary><form method="post" action="/admin/rentals/cancel" class="management-stack"><input type="hidden" name="csrf" value="<?= htmlspecialchars(
    $_SESSION["csrf"],
    ENT_QUOTES,
    "UTF-8",
) ?>"><input type="hidden" name="reference" value="<?= htmlspecialchars(
    $r["reference"],
    ENT_QUOTES,
    "UTF-8",
) ?>"><p>Vérifiez l’absence d’encaissement chez Paiement Pro. Un succès tardif sera classé à traiter, sans nouvelle éjection.</p><label>Recopier la référence<input name="confirm_reference" required></label><label>Preuve de contrôle fournisseur<input name="provider_proof" required minlength="6" maxlength="120"></label><button class="admin-ghost">Libérer la réservation</button></form></details><?php endif; ?>
<?php if (
    $r["status"] === "payment_review"
): ?><strong>Paiement à rapprocher ou rembourser</strong><?php endif; ?>
</td></tr><?php endforeach; ?>
<?php if(!$rentals): ?><tr><td colspan="7">Aucune location pour ce filtre.</td></tr><?php endif; ?></tbody></table></div></section></main><?php require __DIR__.'/partials/admin_shell_end.php'; ?></body></html>
