<?php
$esc=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$labels=['pending'=>'Prêt à transférer','needs_fees'=>'Frais à renseigner','submitted'=>'Transfert accepté par XPaye · crédit à rapprocher','unknown'=>'Résultat incertain · à vérifier','confirmed'=>'Crédit payout rapproché'];
$canWrite=\App\Services\Auth::can('finance.withdraw');$csrf=$esc($_SESSION['csrf']??'');
?>
<?php if(!$totals): ?><p>Aucun transfert enregistré.</p><?php endif; ?>
<?php foreach($totals as $total): ?><article class="management-card"><small><?= $total['purpose']==='deposit'?'Cautions':'Essais' ?> · <?= $esc($labels[$total['status']]??$total['status']) ?></small><h2><?= number_format((int)$total['amount'],0,',',' ') ?> FCFA</h2><p><?= (int)$total['count'] ?> transfert(s)</p></article><?php endforeach; ?>
