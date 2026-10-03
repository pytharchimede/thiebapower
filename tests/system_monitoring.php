<?php
declare(strict_types=1);
namespace App\Core {
    final class App
    {
        public static $database;
        public static function env(string $key, string $default = ""): string
        {
            $value = getenv($key);
            return $value === false ? $default : $value;
        }
        public static function db()
        {
            return self::$database;
        }
    }
}
namespace App\Services {
    final class Auth
    {
        public static function requirePermission(
            string $permission,
            bool $mutation = false,
        ): array {
            return ["id" => 1, "role" => "owner"];
        }
        public static function can(
            string $permission,
            ?array $user = null,
        ): bool {
            return true;
        }
    }
    final class SystemNotifications
    {
        public static function refresh(): void {}
        public static function feed(array $user): array
        {
            return ["items" => [], "unread" => 0];
        }
    }
}
namespace {
    foreach (
        ["SystemStorage", "SystemReports", "Audit", "CheckoutDiagnostics"]
        as $file
    ) {
        require dirname(__DIR__) . "/app/Services/" . $file . ".php";
    }
    require dirname(__DIR__) . "/app/Controllers/MonitoringController.php";
    use App\Services\SystemStorage;
    use App\Services\SystemReports;
    function check(bool $ok, string $message): void
    {
        if (!$ok) {
            throw new \RuntimeException($message);
        }
    }
    $dir = sys_get_temp_dir() . "/tbp-system-" . bin2hex(random_bytes(8));
    putenv("SYSTEM_STORAGE_PATH=" . $dir);
    ini_set("error_log", $dir . "-php.log");
    final class Statement
    {
        public function execute(array $params = []): void {}
        public function fetchAll(): array
        {
            return [
                [
                    "reference" => "TBP-TEST",
                    "status" => "active",
                    "customer_name" => "Client",
                    "serial" => "BAT",
                    "started_at" => gmdate("Y-m-d H:i:s", time() - 60),
                    "due_at" => gmdate("Y-m-d H:i:s", time() + 60),
                    "returned_at" => null,
                ],
            ];
        }
    }
    final class DB
    {
        public int $queries = 0;
        public function prepare(string $sql): Statement
        {
            $this->queries++;
            return new Statement();
        }
    }
    try {
        check(SystemStorage::countersEnabled(), "Default counters on");
        SystemStorage::setCounters(false);
        check(!SystemStorage::countersEnabled(), "Persist global off");
        $db = new DB();
        \App\Core\App::$database = $db;
        $controller = new \App\Controllers\MonitoringController();
        $snapshot = function () use ($controller) {
            ob_start();
            $controller->snapshot();
            return json_decode(ob_get_clean(), true);
        };
        $_GET = [];
        $data = $snapshot();
        check(
            $data["countersEnabled"] === false && $db->queries === 0,
            "Disabled counters do not query rentals",
        );
        SystemStorage::setCounters(true);
        $data = $snapshot();
        check(
            count($data["activeRentals"]) === 1 && $db->queries === 1,
            "One grouped query",
        );
        $snapshot();
        check($db->queries === 1, "Shared snapshot reused");
        $_GET = ["counters" => "0"];
        $data = $snapshot();
        check(
            $data["countersEnabled"] === true &&
                $db->queries === 1 &&
                $data["activeRentals"] === [],
            "Local pause skips queries without changing global setting",
        );
        putenv("PAIEMENTPRO_SECRET_KEY=private-example");
        try {
            \App\Services\CheckoutDiagnostics::measure(
                "checkout_request",
                function () {
                    throw new \RuntimeException(
                        "private-example +2250748367710 client@example.com",
                    );
                },
            );
            throw new \LogicException("Missing failure");
        } catch (\RuntimeException $e) {
            check(
                str_contains($e->getMessage(), "private-example"),
                "Original exception preserved",
            );
        }
        $reports = SystemReports::listing();
        check(count($reports) === 1, "One incident persisted");
        $report = $reports[0];
        check(
            !str_contains($report["message"], "private-example") &&
                !str_contains($report["message"], "0748367710") &&
                !str_contains($report["message"], "client@example.com"),
            "Sensitive values redacted",
        );
        check($db->queries === 1, "Reports do not use DB");
        check(SystemReports::pendingFeed()["unread"] === 1, "Incident in bell");
        SystemReports::acknowledge($report["id"]);
        check(
            SystemReports::pendingFeed()["unread"] === 0,
            "Acknowledgement removes bell incident",
        );
        putenv("SYSTEM_STORAGE_PATH=/dev/null/unwritable");
        SystemReports::record(
            "test.storage.failure",
            new \RuntimeException("Expected"),
            [],
            "unwritable",
        );
        putenv("SYSTEM_STORAGE_PATH=" . $dir);
        $loads = 0;
        $lock = fopen($dir . "/snapshot.lock", "c");
        flock($lock, LOCK_EX);
        $result = SystemStorage::rentalSnapshot(["TBP-OTHER"], function () use (
            &$loads,
        ) {
            $loads++;
            return [];
        });
        check(
            $loads === 0 && !empty($result["unavailable"]),
            "Contention never falls back to concurrent SQL",
        );
        flock($lock, LOCK_UN);
        fclose($lock);
        echo "System monitoring OK: global/local off, grouped/shared query, incident without DB, redaction, acknowledgement, storage failure, contention\n";
    } finally {
        foreach (glob($dir . "/*") ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($dir);
        @unlink($dir . "-php.log");
        putenv("SYSTEM_STORAGE_PATH");
        putenv("PAIEMENTPRO_SECRET_KEY");
    }
}
