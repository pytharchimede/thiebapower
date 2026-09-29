<?php
$paymentChannels=['WAVECI'=>['Wave','logo_wave.png'],'OMCIV'=>['Orange Money','logo_om.png'],'MOMOCI'=>['MTN MoMo','logo_momo.png'],'FLOOZ'=>['Moov Money','logo_flooz.png']];
?>
<fieldset class="payment-methods"><legend><?= htmlspecialchars($paymentLegend??'Canal',ENT_QUOTES,'UTF-8') ?></legend><div class="payment-method-options">
<?php foreach($paymentChannels as $paymentValue=>[$paymentCaption,$paymentImage]): ?>
<label class="payment-method"><input type="radio" name="<?= htmlspecialchars($paymentField,ENT_QUOTES,'UTF-8') ?>" value="<?= $paymentValue ?>" required><span><img src="/images/payments/<?= $paymentImage ?>" alt="" width="32" height="32"><small><?= $paymentCaption ?></small></span></label>
<?php endforeach; ?></div></fieldset>
