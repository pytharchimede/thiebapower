<?php
declare(strict_types=1);
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, "App\\")) {
        require dirname(__DIR__) . "/app/" . str_replace("\\", "/", substr($class, 4)) . ".php";
    }
});
use App\Services\PaiementProPayoutService;
use App\Services\PayoutApiAudit;
use App\Services\PayoutTestReport;
use App\Services\PayoutResult;
function check(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}
putenv("PAIEMENTPRO_MERCHANT_ID=PP-TEST");
putenv("PAIEMENTPRO_SECRET_KEY=secret-test");
putenv("APP_URL=https://thiebapower.com");
$client = new class {
    public array $calls = [];
    public bool $fail = false;
    public ?object $reply = null;
    public string $responseXml = "<Envelope><status>SUCCESS</status></Envelope>";
    public function initTransact(array $params): object
    {
        $this->calls[] = $params;
        if ($this->fail) {
            throw new SoapFault("Server", "timeout");
        }
        return $this->reply ??
            (object) ["status" => "SUCCEEDED", "code" => "0", "sessionid" => "S1"];
    }
    public function getTransStatus(array $params): object
    {
        $this->calls[] = $params;
        return (object) ["status" => "SUCCESS", "sessionid" => "S1"];
    }
    public function __getLastRequestHeaders(): string
    {
        return "POST /webservice/v2/payout/soap.php HTTP/1.1\r\nHost: paiementpro.net\r\n";
    }
    public function __getLastResponseHeaders(): string
    {
        return "HTTP/1.1 100 Continue\r\n\r\nHTTP/1.1 200 OK\r\n";
    }
    public function __getLastRequest(): string
    {
        return "<Envelope><token>" .
            end($this->calls)["token"] .
            "</token><amount>200</amount></Envelope>";
    }
    public function __getLastResponse(): string
    {
        return $this->responseXml;
    }
};
$traces = [];
$service = new PaiementProPayoutService(fn() => $client, fn() => 1700000000, function (
    $ref,
    $trace,
) use (&$traces) {
    $traces[] = ["reference" => $ref] + $trace;
});
$request = $service->prepare(
    "TBP-TEST-PAYOUT-ABC",
    200,
    "WAVECI",
    "0748367710",
    "Test Thiebapower",
    "production",
);
check(
    $request["params"]["token"] === hash_hmac("sha256", "1700000000PP-TEST", "secret-test"),
    "HMAC",
);
check($request["params"]["payeeNo"] === "+2250748367710", "phone");
$reply = $service->initiate($request);
check($reply->status === "SUCCEEDED" && count($client->calls) === 1, "one initiation");
check($traces[0]["httpStatus"] === "200", "final HTTP code");
check(
    $traces[0]["endpoint"] === "https://paiementpro.net/webservice/v2/payout/soap.php",
    "actual endpoint",
);
check(!str_contains(json_encode($traces), $request["params"]["token"]), "token masked");
$service->status("S1", "production", "TBP-TEST-PAYOUT-ABC");
check(
    $traces[1]["reference"] === "TBP-TEST-PAYOUT-ABC" && $traces[1]["method"] === "getTransStatus",
    "status correlation",
);
check(json_decode($traces[1]["requestParameters"], true)["sessionid"] === "S1", "status payload");
$client->fail = true;
$thrown = false;
try {
    $service->initiate($request);
} catch (SoapFault) {
    $thrown = true;
}
check(
    $thrown && count($traces) === 3 && count($client->calls) === 3,
    "fault captured without retry",
);
$isolated = new PaiementProPayoutService(
    fn() => $client,
    fn() => 1,
    fn() => throw new RuntimeException("audit down"),
);
$client->fail = false;
check($isolated->initiate($request)->status === "SUCCEEDED", "audit failure preserves reply");
$safe = PayoutApiAudit::safeSoap(
    "<root><secretKey>TOPSECRET</secretKey><token>abc</token><status>SUCCESS</status></root>",
    "abc",
);
check(
    !str_contains($safe, "TOPSECRET") &&
        !str_contains($safe, "abc") &&
        str_contains($safe, "SUCCESS"),
    "XML secrets",
);
check(
    PayoutApiAudit::safeSoap(
        '<!DOCTYPE r [<!ENTITY x SYSTEM "file:///etc/passwd">]><r>&x;</r>',
        "abc",
    ) === "[XML non archivé]",
    "DTD rejected",
);
$op = [
    "id" => 1,
    "reference" => "TBP-TEST-PAYOUT-ABC",
    "environment" => "production",
    "amount" => 200,
    "status" => "processing",
    "provider_session_id" => "S1",
];
$events = [
    [
        "id" => 2,
        "reference" => $op["reference"],
        "source" => "init",
        "created_at" => "2026-10-02",
        "response" => '{"status":"SUCCEEDED"}',
    ],
    [
        "id" => 1,
        "reference" => $op["reference"],
        "source" => "init",
        "created_at" => "2026-10-02",
        "response" => json_encode($traces[0]),
    ],
    [
        "id" => 3,
        "reference" => "OTHER",
        "source" => "error",
        "created_at" => "2026-10-02",
        "response" => '{"description":"UNRELATED"}',
    ],
];
$report = PayoutTestReport::build(
    $op,
    [
        [
            "reference" => $op["reference"],
            "created_at" => "2026-10-02",
            "endpoint" => $request["wsdl"],
            "parameters" => $traces[0]["requestParameters"],
        ],
    ],
    $events,
);
check(
    str_contains($report, "initTransact") &&
        str_contains($report, "getTransStatus") &&
        str_contains($report, "httpStatus") &&
        str_contains($report, "SUCCEEDED"),
    "complete report",
);
check(
    !str_contains($report, "UNRELATED") && !str_contains($report, $request["params"]["token"]),
    "isolated safe report",
);
$xml =
    '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:p="https://paiementpro.net/webservice/v2/payout/soap.php"><SOAP-ENV:Body><p:initTransactResponse><return><status>INITIATED</status><code>0</code><Sessionid>S1</Sessionid><Url>webservice/v2/payout/auth/?sessionid=S1</Url></return></p:initTransactResponse></SOAP-ENV:Body></SOAP-ENV:Envelope>';
$client->responseXml = $xml;
$client->reply = (object) ["status" => "INITIATED", "code" => "0"];
$before = count($client->calls);
$recovered = $service->initiate($request);
check(
    $recovered->sessionid === "S1" && count($client->calls) === $before + 1,
    "incomplete WSDL response recovered without retry",
);
check(
    PayoutResult::initiation((object) ["status" => "INITIATED", "code" => 0, "Sessionid" => "S1"])[
        "session"
    ] === "S1",
    "provider Sessionid case",
);
check(
    PayoutResult::authorizationUrl($recovered) ===
        "https://paiementpro.net/webservice/v2/payout/auth/?sessionid=S1",
    "relative authorization URL",
);
foreach (
    [
        "https://evil.test/webservice/v2/payout/auth/?sessionid=S1",
        "https://paiementpro.net/webservice/v2/payout/auth/?sessionid=OTHER",
        "javascript:alert(1)",
        "//evil.test",
    ]
    as $url
) {
    check(
        PayoutResult::authorizationUrl(["Sessionid" => "S1", "Url" => $url]) === "",
        "unsafe or mismatched URL rejected",
    );
}
$journal = [
    [
        "reference" => "TBP-TEST-PAYOUT-ABC",
        "source" => "init",
        "response" => json_encode(["method" => "initTransact", "responseSoap" => $xml]),
    ],
];
check(
    PayoutResult::recordedInitiation("TBP-TEST-PAYOUT-ABC", $journal)["sessionid"] === "S1",
    "existing session recovered from trusted initiation journal",
);
$journal[0] += ["id" => 1, "created_at" => "2026-10-03"];
$legacyReport = PayoutTestReport::build(
    array_replace($op, ["status" => "initiated", "provider_session_id" => null]),
    [],
    $journal,
);
check(
    str_contains($legacyReport, "Session retrouvée dans le journal : S1") &&
        str_contains($legacyReport, "aucun appel getTransStatus") &&
        str_contains(
            $legacyReport,
            "https://paiementpro.net/webservice/v2/payout/auth/?sessionid=S1",
        ),
    "legacy report separates recovered session and unverified payout",
);
check(PayoutResult::recordedInitiation("OTHER", $journal) === [], "other reference excluded");
$journal[0]["source"] = "callback";
check(
    PayoutResult::recordedInitiation("TBP-TEST-PAYOUT-ABC", $journal) === [],
    "callback cannot restore session",
);
check(
    !isset(
        PayoutResult::response([], str_replace("<code>0</code>", "<!DOCTYPE r>", $xml))[
            "sessionid"
        ],
    ),
    "DTD rejected during recovery",
);
check(
    PayoutResult::finalStatus(
        [
            "status" => "SUCCESS",
            "Sessionid" => "S1",
            "referenceNo" => "R",
            "amount" => 200,
            "currency" => "XOF",
            "channel" => "WAVECI",
            "payeeNo" => "+2250748367710",
        ],
        [
            "sessionid" => "S1",
            "referenceNo" => "R",
            "amount" => 200,
            "currency" => "XOF",
            "channel" => "WAVECI",
            "payeeNo" => "+2250748367710",
        ],
    ) === "succeeded",
    "final session case normalized while matching financial fields",
);
fwrite(STDOUT, "Payout diagnostics OK (mock SOAP, no real payment)\n");
