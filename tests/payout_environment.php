<?php
require dirname(__DIR__) . "/app/Core/App.php";
use App\Core\App;
foreach (
    [
        ['  abc\r\n', "abc"],
        ['"abc"', "abc"],
        ["'abc'", "abc"],
        ['" abc "', " abc "],
        ["a=b#c", "a=b#c"],
    ]
    as [$input, $expected]
) {
    $input = str_replace(['\\r', '\\n'], ["\r", "\n"], $input);
    if (App::envValue($input) !== $expected) {
        throw new RuntimeException("Lecture dotenv incorrecte");
    }
}
echo "Lecture dotenv payout OK\n";
require dirname(__DIR__) . "/app/Services/PaiementProPayoutService.php";
use App\Services\PaiementProPayoutService;
putenv("PAIEMENTPRO_MERCHANT_ID=PP-TEST");
putenv("PAIEMENTPRO_SECRET_KEY=secret-test");
putenv("APP_URL=https://thiebapower.com");
$service = new PaiementProPayoutService(static function (): object {
    throw new RuntimeException("SOAP interdit dans ce test");
}, static fn(): int => 1791044082);
$request = $service->prepare(
    "TEST",
    200,
    "WAVECI",
    "+2250748367710",
    "Test",
    "production",
);
if (
    $request["params"]["token"] !==
    hash_hmac("sha256", "1791044082PP-TEST", "secret-test")
) {
    throw new RuntimeException("HMAC incorrect");
}
foreach (["invalid-mode", "bad-secret"] as $case) {
    if ($case === "bad-secret") {
        putenv("PAIEMENTPRO_SECRET_KEY=secret-test\r");
    }
    try {
        $service->prepare(
            "TEST",
            200,
            "WAVECI",
            "+2250748367710",
            "Test",
            $case === "invalid-mode" ? "invalid" : "production",
        );
    } catch (RuntimeException $e) {
        continue;
    }
    throw new RuntimeException("Configuration invalide acceptée");
}
echo "HMAC et contrôles configuration OK (aucun paiement)\n";
