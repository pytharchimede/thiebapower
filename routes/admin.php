<?php
// Route map loaded by App\Core\App.
return [
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
