<?php
// Route map loaded by App\Core\App.
return [
    'POST /api/orange/sms/delivery' => [\App\Controllers\OrangeSmsCallbackController::class, 'receive'],
    'POST /api/paiementpro/rental-callback' => [\App\Controllers\PaymentController::class, 'callback'],
    'GET /api/paiementpro/rental-callback' => [\App\Controllers\PaymentController::class, 'callback'],
    'GET /api/heycharge/callback' => [\App\Controllers\StationController::class, 'callbackStatus'],
    'POST /api/heycharge/callback' => [\App\Controllers\StationController::class, 'callbackStatus'],
    'POST /api/paiementpro/payout-callback' => [\App\Controllers\PaymentLabController::class, 'payoutNotification'],
    'GET /api/paiementpro/payout-callback' => [\App\Controllers\PaymentLabController::class, 'payoutNotification'],
    'POST /api/paiementpro/test-callback' => [\App\Controllers\PaymentLabController::class, 'notification'],
    'POST /api/heycharge/callback/register' => [\App\Controllers\StationController::class, 'register'],
    'POST /api/heycharge/callback/return' => [\App\Controllers\StationController::class, 'returned'],
    'POST /api/heycharge/callback/status' => [\App\Controllers\StationController::class, 'status'],
];
