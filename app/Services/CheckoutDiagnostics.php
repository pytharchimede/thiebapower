<?php
namespace App\Services;
use App\Core\App;
final class CheckoutDiagnostics
{
    private static array $steps = [];
    public static function measure(string $stage, callable $action): mixed
    {
        $start = microtime(true);
        $outcome = "ok";
        $error = null;
        try {
            return $action();
        } catch (\Throwable $e) {
            $outcome = "error";
            $error = $e;
            throw $e;
        } finally {
            $message = $error ? substr($error->getMessage(), 0, 500) : "";
            foreach (
                [
                    "PAIEMENTPRO_SECRET_KEY",
                    "PAIEMENTPRO_SANDBOX_SECRET_KEY",
                    "HEYCHARGE_API_KEY",
                    "PAYMENT_CALLBACK_SECRET",
                ]
                as $key
            ) {
                $secret = App::env($key);
                if ($secret !== "") {
                    $message = str_replace($secret, "[masqué]", $message);
                }
            }
            $message = preg_replace(
                "/([?&](?:token|secret|key|password)=)[^&\s]+/i",
                '$1[masqué]',
                $message,
            );
            self::$steps[] = [
                "stage" => $stage,
                "duration_ms" => (int) ((microtime(true) - $start) * 1000),
                "outcome" => $outcome,
            ];
            if ($error && $stage === "checkout_request") {
                SystemReports::record($stage, $error, [
                    "steps" => self::$steps,
                ]);
            }
            error_log(
                "TBP_CHECKOUT " .
                    json_encode(
                        [
                            "diagnostic" => Audit::requestId(),
                            "stage" => $stage,
                            "duration_ms" =>
                                (int) ((microtime(true) - $start) * 1000),
                            "outcome" => $outcome,
                            "exception" => $error ? get_class($error) : null,
                            "message" => $message,
                        ],
                        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
                    ),
            );
        }
    }
}
