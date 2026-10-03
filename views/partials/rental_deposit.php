<?php if(\App\Services\Auth::can('finance.view')): $depositData=\App\Services\RentalDepositPresenter::snapshot($r);$depositRemaining=\App\Services\DepositWallet::remaining($r); ?>
<div class="tb-rental-deposit" data-reference="<?= $esc($r['reference']) ?>" data-billing="<?= $esc(json_encode($depositData)) ?>">
<?php if($depositData['deposit']===0): ?>Caution désactivée<?php elseif(!$depositRemaining['paid']): ?>Caution de <?= $depositData['deposit'] ?> FCFA · paiement à confirmer<?php else: ?>
<strong>À restituer : <?= $depositRemaining['refund'] ?> / <?= $depositData['deposit'] ?> FCFA</strong><br><small>Retenue : <?= $depositRemaining['deduction'] ?> FCFA · Frais à notre charge</small>
<?php endif; ?></div>
<?php endif; ?>
