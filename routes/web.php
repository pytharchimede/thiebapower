<?php
// Route map loaded by App\Core\App.
return [
    'GET /' => [\App\Controllers\RentalController::class, 'index'],
    'GET /rent' => [\App\Controllers\RentalController::class, 'index'],
    'POST /rentals' => [\App\Controllers\RentalController::class, 'create'],
    'GET /rentals/status' => [\App\Controllers\RentalController::class, 'status'],
    'GET /payment/return' => [\App\Controllers\PaymentController::class, 'returnPage'],
    'GET /payment/test-return' => [\App\Controllers\PaymentLabController::class, 'returnPage'],
];
