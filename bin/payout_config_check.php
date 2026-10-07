<?php
declare(strict_types=1);
// Read-only diagnostics: no SOAP request and no payment.
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, "App\\")) {
        require dirname(__DIR__) .
            "/app/" .
            str_replace("\\", "/", substr($class, 4)) .
            ".php";
    }
});
use App\Core\App;
use App\Services\IntegrationSettings;
$mode = IntegrationSettings::all()["paiementpro"];
echo "Mode BDD : " . $mode . PHP_EOL;
$prefix = $mode === "sandbox" ? "PAIEMENTPRO_SANDBOX_" : "PAIEMENTPRO_";
$ok = in_array($mode, ["sandbox", "production"], true);
$lines = file(dirname(__DIR__) . "/.env", FILE_IGNORE_NEW_LINES) ?: [];
foreach (["MERCHANT_ID", "SECRET_KEY"] as $suffix) {
    $key = $prefix . $suffix;
    $value = App::env($key);
    $count = 0;
    foreach ($lines as $line) {
        if (
            str_contains($line, "=") &&
            trim(explode("=", $line, 2)[0]) === $key
        ) {
            $count++;
        }
    }
    $valid =
        $value !== "" &&
        $value === trim($value) &&
        !preg_match('/[\x00-\x1F\x7F]/', $value);
    $ok = $ok && $valid && $count <= 1;
    echo $key .
        " : " .
        ($valid
            ? "présent, sans caractères de contrôle ni espaces aux extrémités"
            : "ABSENT OU MAL FORMÉ") .
        PHP_EOL;
    echo "  Source : " .
        (getenv($key) !== false
            ? "environnement du processus (prioritaire)"
            : ".env") .
        "; occurrences .env : " .
        $count .
        PHP_EOL;
    if ($suffix === "MERCHANT_ID") {
        echo "  Marchand : " . $value . PHP_EOL;
    }
}
echo "SOAP : " .
    (class_exists("SoapClient") ? "OK" : "ABSENT") .
    "; DOM : " .
    (class_exists("DOMDocument") ? "OK" : "ABSENT") .
    PHP_EOL;
echo "Aucun secret ni token affiché. La validité de la clé doit être confirmée avec PaiementPro. Le processus web peut avoir un environnement différent du CLI.\n";
exit($ok ? 0 : 1);
