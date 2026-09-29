<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Parc de terminaux · Thiebapower</title><link rel="stylesheet" href="/style.css"></head>
<body class="management-page"><header class="management-header"><a class="kiosk-logo" href="/admin">THIEBA<span>POWER</span></a><nav><a href="/admin">Tableau de bord</a><a href="/admin#pricing">Tarification</a></nav></header>
<main class="management-main">
<p class="admin-overline">PARC HEYCHARGE</p><h1>Terminaux et batteries</h1>
<p>Les terminaux enregistrés apparaissent ici. Une station doit être synchronisée, active et disposer de batteries chargées pour proposer une location.</p>
<section class="management-card"><h2>État du parc</h2><p><?= count($stations) ?> terminaux · <?php foreach ($totals as $row): ?><?= htmlspecialchars($row['status'],ENT_QUOTES,'UTF-8') ?> : <?= (int)$row['quantity'] ?> · <?php endforeach; ?></p></section>
<section class="management-card"><h2>Terminaux</h2><div class="management-table-wrap"><table><thead><tr><th>Station</th><th>Connexion</th><th>Batteries</th><th>Disponibles</th><th>Charge min.</th><th>Dernière synchronisation</th><th>Actions</th></tr></thead><tbody>
<?php foreach($stations as $station): ?>
<?php $fresh=$station['last_seen_at'] && strtotime($station['last_seen_at'].' UTC') >= time()-600; ?>
<tr><td><a href="/admin/stations/detail?imei=<?= rawurlencode($station['imei']) ?>"><strong><?= htmlspecialchars($station['label']?:$station['imei'],ENT_QUOTES,'UTF-8') ?></strong></a><br><small><?= htmlspecialchars($station['imei'],ENT_QUOTES,'UTF-8') ?></small></td>
<td><?= $station['enabled'] ? 'Activé' : 'Désactivé' ?> · <?= $fresh ? 'Joignable récemment' : 'À vérifier' ?></td><td><?= (int)$station['batteries_count'] ?></td><td><?= (int)$station['available_count'] ?></td><td><?= $station['minimum_capacity']===null?'—':(int)$station['minimum_capacity'].' %' ?></td><td><?= htmlspecialchars((string)($station['last_seen_at']??'Jamais'),ENT_QUOTES,'UTF-8') ?></td>
<td><form method="post" action="/admin/stations/sync"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'],ENT_QUOTES,'UTF-8') ?>"><input type="hidden" name="imei" value="<?= htmlspecialchars($station['imei'],ENT_QUOTES,'UTF-8') ?>"><button class="admin-ghost">Synchroniser</button></form><a href="/admin/stations/detail?imei=<?= rawurlencode($station['imei']) ?>">Détails</a></td></tr>
<?php endforeach; ?>
</tbody></table></div><?php if(!$stations): ?><p>Aucun terminal enregistré. Ajoutez un IMEI depuis le tableau de bord ou attendez son événement d’enregistrement.</p><?php endif; ?></section>
</main></body></html>
