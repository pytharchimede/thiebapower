<?php
// Route map loaded by App\Core\App.
return [
 "GET /admin/sms/templates" => [\App\Controllers\SmsTemplatesController::class,"index"],
 "POST /admin/sms/templates" => [\App\Controllers\SmsTemplatesController::class,"save"],
 "GET /admin/sms" => [\App\Controllers\OrangeSmsController::class,"index"],
 "POST /admin/sms/settings" => [\App\Controllers\OrangeSmsController::class,"save"],
 "POST /admin/sms/test" => [\App\Controllers\OrangeSmsController::class,"test"],
 "GET /admin/training" => [\App\Controllers\ClientTrainingController::class,"index"],
 "POST /admin/training/proposals" => [\App\Controllers\ClientTrainingController::class,"save"],
 "GET /admin/training/pdf" => [\App\Controllers\ClientTrainingController::class,"export"],
 "GET /admin/public-settings" => [\App\Controllers\PublicExperienceSettingsController::class,"index"],
 "POST /admin/public-settings" => [\App\Controllers\PublicExperienceSettingsController::class,"save"],
    "POST /admin/promotions/limits" => [\App\Controllers\PromotionController::class, "limits"],
    "POST /admin/promotions/remove" => [\App\Controllers\PromotionController::class, "remove"],
    "POST /admin/promotions/visibility" => [\App\Controllers\PromotionController::class, "visibility"],
    "GET /admin/promotions" => [\App\Controllers\PromotionController::class, "index"],
    "POST /admin/promotions" => [\App\Controllers\PromotionController::class, "create"],
    "POST /admin/promotions/toggle" => [\App\Controllers\PromotionController::class, "toggle"],
    "GET /admin/stations/profitability" => [\App\Controllers\StationProfitabilityController::class, "index"],
    "POST /admin/stations/investment" => [\App\Controllers\StationProfitabilityController::class, "investment"],
    "POST /admin/stations/cost" => [\App\Controllers\StationProfitabilityController::class, "cost"],
    "GET /admin/rentals/watch" => [\App\Controllers\ExperienceOperationsController::class, "watch"],
    "GET /admin/support" => [\App\Controllers\ExperienceOperationsController::class, "support"],
    "POST /admin/support/resolve" => [\App\Controllers\ExperienceOperationsController::class, "resolve"],
    "GET /admin/stations/profile" => [\App\Controllers\StationExperienceController::class, "profile"],
    "POST /admin/stations/profile" => [\App\Controllers\StationExperienceController::class, "save"],
    "GET /admin/stations/places" => [\App\Controllers\StationExperienceController::class, "places"],
    "GET /admin/rentals/deposits" => [\App\Controllers\RentalOperationsController::class, "deposits"],
    "GET /admin/deposit-wallet/snapshot" => [\App\Controllers\DepositWalletController::class, "snapshot"],
    "GET /admin/deposit-wallet" => [\App\Controllers\DepositWalletController::class, "index"],
    "POST /admin/deposit-wallet" => [\App\Controllers\DepositWalletController::class, "action"],
    "GET /admin/statistics" => [\App\Controllers\StatisticsController::class, "index"],
    "GET /admin/statistics/snapshot" => [\App\Controllers\StatisticsController::class, "snapshot"],
    "GET /admin/reports/qr" => [\App\Controllers\ReportController::class, "qr"],
    "GET /admin/rentals/receipt" => [\App\Controllers\RentalReceiptController::class, "admin"],
    "POST /admin/reports/pdf" => [\App\Controllers\ReportController::class, "pdf"],
    "POST /admin/finance/withdraw" => [\App\Controllers\FinanceWithdrawalController::class, "send"],
    "POST /admin/finance/withdraw/verify" => [\App\Controllers\FinanceWithdrawalController::class, "verify"],
    "GET /admin/system" => [\App\Controllers\SystemController::class, "index"],
    "POST /admin/system/settings" => [
        \App\Controllers\SystemController::class,
        "settings",
    ],
    "POST /admin/system/acknowledge" => [
        \App\Controllers\SystemController::class,
        "acknowledge",
    ],
    "GET /admin/monitoring" => [
        \App\Controllers\MonitoringController::class,
        "snapshot",
    ],
    "POST /admin/notifications/read" => [
        \App\Controllers\MonitoringController::class,
        "markRead",
    ],
    "GET /admin/pricing" => [
        \App\Controllers\AdminPresentationController::class,
        "pricing",
    ],
    "GET /admin/batteries" => [
        \App\Controllers\AdminPresentationController::class,
        "batteries",
    ],
    "GET /admin/batteries/detail" => [
        \App\Controllers\AdminPresentationController::class,
        "batteryDetail",
    ],
    "GET /admin/rentals/detail" => [
        \App\Controllers\AdminPresentationController::class,
        "rentalDetail",
    ],
    "GET /admin/finance" => [
        \App\Controllers\FinanceController::class,
        "index",
    ],
    "POST /admin/finance/cash" => [
        \App\Controllers\FinanceController::class,
        "addCash",
    ],
    "GET /admin/login" => [\App\Controllers\AuthController::class, "loginPage"],
    "POST /admin/login" => [\App\Controllers\AuthController::class, "login"],
    "POST /admin/logout" => [\App\Controllers\AuthController::class, "logout"],
    "POST /admin/stations" => [
        \App\Controllers\StationController::class,
        "save",
    ],
    "GET /admin/rentals" => [
        \App\Controllers\RentalOperationsController::class,
        "index",
    ],
    "POST /admin/rentals/cancel" => [
        \App\Controllers\RentalOperationsController::class,
        "cancelPending",
    ],
    "GET /admin/stations" => [
        \App\Controllers\StationController::class,
        "index",
    ],
    "POST /admin/stations/discover" => [
        \App\Controllers\StationController::class,
        "discover",
    ],
    "POST /admin/stations/labels/settings" => [
        \App\Controllers\StationController::class,
        "saveLabelSettings",
    ],
    "GET /admin/stations/labels" => [
        \App\Controllers\StationController::class,
        "labels",
    ],
    "GET /admin/stations/labels.pdf" => [
        \App\Controllers\StationController::class,
        "labelsPdf",
    ],
    "GET /admin/stations/detail" => [
        \App\Controllers\StationController::class,
        "detail",
    ],
    "POST /admin/stations/toggle" => [
        \App\Controllers\StationController::class,
        "toggle",
    ],
    "POST /admin/stations/sync" => [
        \App\Controllers\StationController::class,
        "sync",
    ],
    "POST /admin/stations/release-battery" => [
        \App\Controllers\StationController::class,
        "releaseBattery",
    ],
    "POST /admin/stations/confirm-reinsertion" => [
        \App\Controllers\StationController::class,
        "confirmReinsertion",
    ],
    "POST /admin/stations/reconcile" => [
        \App\Controllers\StationController::class,
        "reconcile",
    ],
    "GET /admin" => [\App\Controllers\AdminController::class, "index"],
    "POST /admin/prices" => [\App\Controllers\AdminController::class, "prices"],
    "POST /admin/batteries" => [
        \App\Controllers\AdminController::class,
        "battery",
    ],
    "POST /admin/modes" => [\App\Controllers\AdminController::class, "modes"],
    "GET /admin/users" => [\App\Controllers\UsersController::class, "index"],
    "POST /admin/users" => [\App\Controllers\UsersController::class, "create"],
    "POST /admin/users/update" => [
        \App\Controllers\UsersController::class,
        "update",
    ],
    "POST /admin/roles/permissions" => [
        \App\Controllers\UsersController::class,
        "permissions",
    ],
    "GET /admin/audit" => [\App\Controllers\AuditController::class, "index"],
    "GET /admin/payout" => [
        \App\Controllers\PayoutConsoleController::class,
        "index",
    ],
    "POST /admin/payment-lab/payin" => [
        \App\Controllers\PaymentLabController::class,
        "payin",
    ],
    "POST /admin/payment-lab/payout" => [
        \App\Controllers\PaymentLabController::class,
        "payout",
    ],
    "POST /admin/payment-lab/reconcile" => [
        \App\Controllers\PaymentLabController::class,
        "reconcile",
    ],
    "POST /admin/payment-lab/archive" => [
        \App\Controllers\PaymentLabController::class,
        "archive",
    ],
    "POST /admin/payment-lab/clear-payout-history" => [
        \App\Controllers\PaymentLabController::class,
        "clearPayoutHistory",
    ],
    "POST /admin/simulation/paid" => [
        \App\Controllers\SimulationController::class,
        "paid",
    ],
    "POST /admin/simulation/returned" => [
        \App\Controllers\SimulationController::class,
        "returned",
    ],
    "POST /admin/simulation/reconcile" => [
        \App\Controllers\SimulationController::class,
        "reconcile",
    ],
];
