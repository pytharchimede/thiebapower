<?php
$paymentCatalog=[
    'WAVECI'=>['Wave','logo_wave.png'],
    'OMCIV'=>['Orange Money','logo_om.png'],
    'MOMOCI'=>['MTN MoMo','logo_momo.png'],
    'FLOOZ'=>['Moov Money','logo_flooz.png'],
];

$paymentChannelMode=$paymentChannelMode??'payment';

$enabledPaymentChannels=$paymentChannelMode==='payout'
    ? \App\Services\RentalPaymentChannel::payoutChannels()
    : \App\Services\RentalPaymentChannel::paymentChannels();

$paymentChannels=array_intersect_key(
    $paymentCatalog,
    array_flip($enabledPaymentChannels)
);

$paymentChannelCount=count($paymentChannels);
?>
<fieldset class="payment-methods">
    <legend><?= htmlspecialchars($paymentLegend??'Canal',ENT_QUOTES,'UTF-8') ?></legend>

    <?php if($paymentChannels): ?>
        <div
            class="payment-method-options payment-method-options--<?= $paymentChannelCount ?>"
            data-channel-count="<?= $paymentChannelCount ?>"
        >
            <?php foreach($paymentChannels as $paymentValue=>[$paymentCaption,$paymentImage]): ?>
                <label class="payment-method">
                    <input
                        type="radio"
                        name="<?= htmlspecialchars($paymentField,ENT_QUOTES,'UTF-8') ?>"
                        value="<?= htmlspecialchars($paymentValue,ENT_QUOTES,'UTF-8') ?>"
                        required
                    >
                    <span>
                        <img
                            src="/images/payments/<?= htmlspecialchars($paymentImage,ENT_QUOTES,'UTF-8') ?>"
                            alt="<?= htmlspecialchars($paymentCaption,ENT_QUOTES,'UTF-8') ?>"
                        >
                        <small><?= htmlspecialchars($paymentCaption,ENT_QUOTES,'UTF-8') ?></small>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="tb-muted">
            Aucun moyen de paiement n’est actuellement activé. Contactez l’assistance.
        </p>
    <?php endif; ?>
</fieldset>
