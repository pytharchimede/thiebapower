<?php
// Route map loaded by App\Core\App.
return [
    "POST /promotions/preview" => [\App\Controllers\PromotionController::class, "preview"],
    "POST /my-rentals/referral" => [\App\Controllers\PromotionController::class, "referral"],
    "GET /my-rentals" => [\App\Controllers\CustomerExperienceController::class, "index"],
    "POST /my-rentals/snapshot" => [\App\Controllers\CustomerExperienceController::class, "snapshot"],
    "POST /my-rentals/support" => [\App\Controllers\CustomerExperienceController::class, "support"],
    "GET /stations/map" => [\App\Controllers\StationExperienceController::class, "map"],
    "GET /stations/snapshot" => [\App\Controllers\StationExperienceController::class, "publicStations"],
    'GET /rentals/receipt' => [\App\Controllers\RentalReceiptController::class, 'customer'],
    'GET /' => [\App\Controllers\RentalController::class, 'index'],
    'GET /rent' => [\App\Controllers\RentalController::class, 'index'],
    'POST /rentals' => [\App\Controllers\RentalController::class, 'create'],
    'GET /rentals/status' => [\App\Controllers\RentalController::class, 'status'],
    'GET /payment/return' => [\App\Controllers\PaymentController::class, 'returnPage'],
    'GET /payment/test-return' => [\App\Controllers\PaymentLabController::class, 'returnPage'],
];
