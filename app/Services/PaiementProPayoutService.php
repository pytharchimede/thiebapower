<?php

namespace App\Services;

use App\Core\App;

final class PaiementProPayoutService
{
    private $soapFactory;
    private $clock;
    private $traceSink;
    public function __construct(
        ?callable $soapFactory = null,
        ?callable $clock = null,
        ?callable $traceSink = null,
    ) {
        $this->soapFactory =
            $soapFactory ??
            static fn(string $wsdl): object => new \SoapClient($wsdl, [
                "connection_timeout" => 10,
                "cache_wsdl" => WSDL_CACHE_NONE,
                "trace" => true,
            ]);
        $this->clock = $clock ?? static fn(): int => time();
        $this->traceSink =
            $traceSink ??
            static fn(string $reference, array $trace) => PayoutApiAudit::record(
                $reference,
                $trace["method"] === "initTransact" ? "init" : "status",
                $trace,
            );
    }
    private function credentials(?string $mode = null): array
    {
        $mode ??= IntegrationSettings::all()["paiementpro"];
        if ($mode === "sandbox") {
            $merchant = App::env("PAIEMENTPRO_SANDBOX_MERCHANT_ID");
            $secret = App::env("PAIEMENTPRO_SANDBOX_SECRET_KEY");
            $wsdl = App::env("PAIEMENTPRO_SANDBOX_PAYOUT_WSDL");
        } else {
            $merchant = App::env("PAIEMENTPRO_MERCHANT_ID");
            $secret = App::env("PAIEMENTPRO_SECRET_KEY");
            $wsdl = "https://paiementpro.net/webservice/v2/payout/soap.php?wsdl";
        }
        if (
            !$merchant ||
            !$secret ||
            !filter_var($wsdl, FILTER_VALIDATE_URL) ||
            parse_url($wsdl, PHP_URL_SCHEME) !== "https"
        ) {
            throw new \RuntimeException("Reversement Paiement Pro non configuré");
        }
        return compact("merchant", "secret", "wsdl");
    }
    private function token(array $config, int $timestamp): string
    {
        return hash_hmac("sha256", $timestamp . $config["merchant"], $config["secret"]);
    }
    public static function normalizePhone(string $phone): string
    {
        $phone = preg_replace("/[\s.()-]+/", "", $phone);
        if (preg_match('/^0[0-9]{9}$/D', $phone)) {
            $phone = "+225" . $phone;
        } elseif (preg_match('/^225[0-9]{10}$/D', $phone)) {
            $phone = "+" . $phone;
        }
        if (!preg_match('/^\+225[0-9]{10}$/D', $phone)) {
            throw new \InvalidArgumentException("Numéro ivoirien invalide");
        }
        return $phone;
    }
    public function prepare(
        string $reference,
        int $amount,
        string $channel,
        string $phone,
        string $name,
        ?string $mode = null,
    ): array {
        if ($amount <= 0 || !in_array($channel, ["WAVECI", "MOMOCI", "OMCIV", "FLOOZ"], true)) {
            throw new \InvalidArgumentException("Paramètres de restitution invalides");
        }
        $phone = self::normalizePhone($phone);
        $config = $this->credentials($mode);
        $timestamp = ($this->clock)();
        return [
            "wsdl" => $config["wsdl"],
            "params" => [
                "merchantId" => $config["merchant"],
                "currency" => "XOF",
                "amount" => $amount,
                "referenceNo" => $reference,
                "channel" => $channel,
                "clientName" => $name,
                "token" => $this->token($config, $timestamp),
                "timestamp" => $timestamp,
                "payeeNo" => $phone,
                "clientId" => $reference,
                "returnContext" => "reference=" . $reference,
                "paymentReason" =>
                    (str_starts_with($reference, "TBP-TEST-")
                        ? "Essai API payout "
                        : "Restitution caution ") . $reference,
                "returnURL" => rtrim(App::env("APP_URL"), "/") . "/payment/return",
                "callbackURL" =>
                    rtrim(App::env("APP_URL"), "/") . "/api/paiementpro/payout-callback",
            ],
        ];
    }
    public function initiate(array $request): object
    {
        $client = ($this->soapFactory)($request["wsdl"]);
        try {
            $reply = $client->initTransact($request["params"]);
            return (object) PayoutResult::response(
                $reply,
                method_exists($client, "__getLastResponse")
                    ? (string) $client->__getLastResponse()
                    : "",
            );
        } finally {
            $this->capture(
                $client,
                $request["wsdl"],
                "initTransact",
                $request["params"],
                $request["params"]["referenceNo"],
            );
        }
    }
    public function status(
        string $sessionId,
        ?string $mode = null,
        ?string $reference = null,
    ): object {
        if ($sessionId === "") {
            throw new \InvalidArgumentException("Session absente");
        }
        $config = $this->credentials($mode);
        $timestamp = ($this->clock)();
        $client = ($this->soapFactory)($config["wsdl"]);
        $params = [
            "merchantId" => $config["merchant"],
            "token" => $this->token($config, $timestamp),
            "timestamp" => $timestamp,
            "sessionid" => $sessionId,
        ];
        try {
            $reply = $client->getTransStatus($params);
            return (object) PayoutResult::response(
                $reply,
                method_exists($client, "__getLastResponse")
                    ? (string) $client->__getLastResponse()
                    : "",
            );
        } finally {
            $this->capture(
                $client,
                $config["wsdl"],
                "getTransStatus",
                $params,
                $reference ?? $sessionId,
            );
        }
    }
    private function capture(
        object $client,
        string $wsdl,
        string $method,
        array $params,
        string $reference,
    ): void {
        // Diagnostics must never alter the financial outcome or trigger another call.
        try {
            $requestHeaders = method_exists($client, "__getLastRequestHeaders")
                ? (string) $client->__getLastRequestHeaders()
                : "";
            $responseHeaders = method_exists($client, "__getLastResponseHeaders")
                ? (string) $client->__getLastResponseHeaders()
                : "";
            $endpoint = "";
            if (preg_match("~^POST\s+(\S+)\s+HTTP/~", $requestHeaders, $match)) {
                if (str_starts_with($match[1], "https://")) {
                    $endpoint = $match[1];
                } elseif (preg_match('/^Host:\s*([^\r\n]+)/mi', $requestHeaders, $host)) {
                    $endpoint = "https://" . trim($host[1]) . $match[1];
                }
            }
            preg_match_all("~HTTP/\S+\s+(\d{3})~", $responseHeaders, $codes);
            $http = $codes[1] ? end($codes[1]) : "unavailable";
            $trace = [
                "method" => $method,
                "wsdl" => $wsdl,
                "endpoint" => $endpoint ?: "unavailable",
                "httpStatus" => $http,
                "requestParameters" => json_encode(
                    array_replace($params, ["token" => "[HMAC SHA-256 masqué]"]),
                    JSON_INVALID_UTF8_SUBSTITUTE,
                ),
            ];
            foreach (
                ["requestSoap" => "__getLastRequest", "responseSoap" => "__getLastResponse"]
                as $key => $getter
            ) {
                $xml = method_exists($client, $getter) ? (string) $client->$getter() : "";
                $trace[$key] = PayoutApiAudit::safeSoap($xml, (string) $params["token"]);
            }
            ($this->traceSink)($reference, $trace);
        } catch (\Throwable) {
            error_log("Payout transport audit unavailable");
        }
    }
}
