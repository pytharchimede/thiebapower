<?php
declare(strict_types=1);
namespace App\Core {
    final class App
    {
        public static $fake;
        public static function db()
        {
            return self::$fake;
        }
    }
}
namespace App\Services {
    final class CheckoutDiagnostics
    {
        public static function measure(string $stage, callable $action): mixed
        {
            return $action();
        }
    }
}
namespace {
    require dirname(__DIR__) . "/app/Services/StationFleetService.php";
    require dirname(__DIR__) . "/app/Services/CheckoutConflict.php";
    require dirname(__DIR__) . "/app/Services/RentalCheckoutService.php";
    require dirname(__DIR__) . "/app/Services/SystemNotifications.php";
    final class FakeStatement
    {
        public function __construct(private $result) {}
        public function execute(array $params = []): void {}
        public function fetch()
        {
            return $this->result;
        }
        public function fetchColumn()
        {
            return $this->result;
        }
    }
    final class FakeDB
    {
        public int $lock = 0;
        public array $snapshots = [];
        public $existing = null;
        public int $releases = 0;
        public function prepare(string $sql): FakeStatement
        {
            if (str_contains($sql, "GET_LOCK")) {
                return new FakeStatement($this->lock);
            }
            if (str_contains($sql, "RELEASE_LOCK")) {
                $this->releases++;
                return new FakeStatement(1);
            }
            if (str_contains($sql, "FROM stations")) {
                return new FakeStatement(array_shift($this->snapshots));
            }
            if (str_contains($sql, "checkout_token")) {
                return new FakeStatement($this->existing);
            }
            throw new \RuntimeException("Unexpected query: " . $sql);
        }
    }
    function check(bool $value, string $message): void
    {
        if (!$value) {
            throw new \RuntimeException($message);
        }
    }
    function station(int $age): array
    {
        return [
            "status" => "online",
            "last_seen_at" => gmdate("Y-m-d H:i:s", time() - $age),
        ];
    }
    $db = new FakeDB();
    \App\Core\App::$fake = $db;
    $fleet = new \App\Services\StationFleetService();
    $db->snapshots = [station(5)];
    check($fleet->syncPublic("S"), "Fresh snapshot");
    $db->snapshots = [station(40), station(40)];
    check(
        $fleet->syncPublic("S"),
        "Contended request can reuse recent snapshot",
    );
    $db->snapshots = [station(500), station(500)];
    check(!$fleet->syncPublic("S"), "Old snapshot cannot be served");
    $db->lock = 1;
    $db->snapshots = [station(40), station(2)];
    check(
        $fleet->syncPublic("S") && $db->releases === 1,
        "Refresh rechecked after lock and lock released",
    );
    $input = [
        "checkout_token" => str_repeat("a", 32),
        "battery_id" => 1,
        "station_code" => "S",
        "name" => "Client",
        "phone" => "0748367710",
    ];
    $db->existing = [
        "reference" => "TBP-TEST",
        "status" => "pending_payment",
        "battery_id" => 1,
        "station_code" => "S",
        "customer_name" => "Client",
        "customer_phone" => "0748367710",
        "checkout_payment_url" => "https://paiementpro.net/existing-session",
        "reservation_expires_at" => gmdate("Y-m-d H:i:s", time() + 60),
    ];
    $service = new \App\Services\RentalCheckoutService();
    check(
        $service->begin($input) === "https://paiementpro.net/existing-session",
        "Duplicate uses original session without new SOAP/payin",
    );
    $db->existing["status"] = "active";
    check($service->begin($input)==='/payment/return?reference=TBP-TEST', 'Existing active rental resumes status without reissuing payment');
    $db->existing['status']='payment_timeout';
    check($service->begin($input)==='/payment/return?reference=TBP-TEST', 'Expired duplicate resumes status without reissuing payment');
    $db->lock = 0;
    try {
        $service->begin($input);
        throw new \LogicException("Concurrent call accepted");
    } catch (\App\Services\CheckoutConflict $e) {
    }
    check(
        \App\Services\SystemNotifications::rentalAlert(
            [
                "status" => "active",
                "due_at" => gmdate("Y-m-d H:i:s", time() - 1),
            ],
            time(),
        )[0] === "rental.overdue",
        "Overdue alert",
    );
    check(
        \App\Services\SystemNotifications::rentalAlert(
            [
                "status" => "returned",
                "due_at" => gmdate("Y-m-d H:i:s", time() - 1),
            ],
            time(),
        ) === null,
        "Returned rental does not alert",
    );
    echo "Checkout/monitoring OK (snapshot reuse, locks, duplicate protection, alerts; no real API calls)\n";
}
