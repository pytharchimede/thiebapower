<?php

$partial = file_get_contents(__DIR__.'/../views/partials/payment_channels.php');
$css = file_get_contents(__DIR__.'/../public/style.css');
$payout = file_get_contents(__DIR__.'/../views/payout.php');

$checks = [
    'paymentChannels utilisé' =>
        str_contains($partial, 'RentalPaymentChannel::paymentChannels()'),

    'payoutChannels utilisé' =>
        str_contains($partial, 'RentalPaymentChannel::payoutChannels()'),

    'compteur dynamique présent' =>
        str_contains($partial, 'count($paymentChannels)'),

    'classe dynamique présente' =>
        str_contains($partial, 'payment-method-options--<?= $paymentChannelCount ?>'),

    'configuration 1 opérateur' =>
        str_contains($css, '.payment-method-options--1'),

    'configuration 2 opérateurs' =>
        str_contains($css, '.payment-method-options--2'),

    'configuration 3 opérateurs' =>
        str_contains($css, '.payment-method-options--3'),

    'configuration 4 opérateurs' =>
        str_contains($css, '.payment-method-options--4'),

    'responsive mobile présent' =>
        str_contains($css, '@media (max-width:600px)'),

    'payout utilise son propre mode' =>
        str_contains($payout, "\$paymentChannelMode='payout'"),
];

foreach ($checks as $label => $ok) {
    if (!$ok) {
        fwrite(STDERR, "ECHEC: {$label}\n");
        exit(1);
    }
}

echo "Payment channel responsive UI OK: payin/payout separated, layouts 1/2/3/4 present.\n";
