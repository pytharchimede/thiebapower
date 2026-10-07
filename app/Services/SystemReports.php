<?php
namespace App\Services;
use App\Core\App;
final class SystemReports
{
    private static array $recorded = [];
    public static function redact(string $value): string
    {
        foreach (
            [
                "DB_PASSWORD",
                "XPAYE_LOGIN",
                "XPAYE_PASSWORD",
                "PAIEMENTPRO_SECRET_KEY",
                "PAIEMENTPRO_SANDBOX_SECRET_KEY",
                "HEYCHARGE_API_KEY",
                "PAYMENT_CALLBACK_SECRET",
            ]
            as $key
        ) {
            $secret = App::env($key);
            if ($secret !== "") {
                $value = str_replace($secret, "[masqué]", $value);
            }
        }
        $value = preg_replace(
            "/([?&](?:token|secret|key|password)=)[^&\s]+/i",
            '$1[masqué]',
            $value,
        );
        $value = preg_replace(
            "/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i",
            "[email masqué]",
            $value,
        );
        $value = preg_replace(
            "/(?<![a-z0-9])\+?\d[\d .-]{8,}\d(?![a-z0-9])/i",
            "[numéro masqué]",
            $value,
        );
        return substr($value, 0, 1000);
    }
    public static function record(
        string $stage,
        \Throwable $error,
        array $context = [],
        ?string $eventKey = null,
    ): void {
        try {
            $diagnostic = Audit::requestId();
            $id = substr(hash("sha256", $eventKey ?? $diagnostic), 0, 32);
            if (isset(self::$recorded[$id])) {
                return;
            }
            self::$recorded[$id] = true;
            $report = [
                "id" => $id,
                "diagnostic" => $diagnostic,
                "created_at" => gmdate("c"),
                "stage" => substr($stage, 0, 80),
                "exception" => get_class($error),
                "message" => self::redact($error->getMessage()),
                "reference" => preg_match(
                    '/^TBP-[A-Za-z0-9_-]{1,100}$/D',
                    (string) ($context["reference"] ?? ""),
                )
                    ? $context["reference"]
                    : null,
                "steps" => array_slice($context["steps"] ?? [], 0, 12),
                "acknowledged" => false,
            ];
            error_log(
                "TBP_INCIDENT " .
                    json_encode(
                        $report,
                        JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE,
                    ),
            );
            $dir = SystemStorage::ensure();
            if (SystemStorage::read("incident-" . $id . ".json")) {
                return;
            }
            SystemStorage::write("incident-" . $id . ".json", $report, true);
            $lock = @fopen($dir . "/reports.lock", "c");
            if (!$lock) {
                return;
            }
            try {
                if (!flock($lock, LOCK_EX | LOCK_NB)) {
                    return;
                }
                $files = glob($dir . "/incident-*.json") ?: [];
                if (count($files) > 200) {
                    usort(
                        $files,
                        fn($a, $b) => filemtime($a) <=> filemtime($b),
                    );
                    foreach (
                        array_slice($files, 0, count($files) - 200)
                        as $file
                    ) {
                        @unlink($file);
                    }
                }
            } finally {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        } catch (\Throwable $loggingError) {
            error_log(
                "TBP_INCIDENT stockage indisponible; consulter le journal PHP.",
            );
        }
    }
    public static function listing(): array
    {
        $rows = [];
        foreach (
            glob(SystemStorage::directory() . "/incident-*.json") ?: []
            as $file
        ) {
            $row = SystemStorage::read(basename($file));
            if ($row) {
                $rows[] = $row;
            }
        }
        usort($rows, fn($a, $b) => strcmp($b["created_at"], $a["created_at"]));
        return $rows;
    }
    public static function acknowledge(string $id): void
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $id)) {
            throw new \InvalidArgumentException("Rapport invalide");
        }
        $row = SystemStorage::read("incident-" . $id . ".json");
        if (!$row) {
            throw new \InvalidArgumentException("Rapport absent");
        }
        $row["acknowledged"] = true;
        SystemStorage::write("incident-" . $id . ".json", $row);
    }
    public static function pendingFeed(): array
    {
        $rows = array_values(
            array_filter(self::listing(), fn($r) => !$r["acknowledged"]),
        );
        return [
            "unread" => count($rows),
            "items" => array_map(
                fn($r) => [
                    "id" => "incident-" . $r["id"],
                    "title" => "Incident système · " . $r["stage"],
                    "message" => "Diagnostic " . $r["diagnostic"],
                    "link" => "/admin/system#report-" . $r["id"],
                    "unread" => 1,
                ],
                array_slice($rows, 0, 20),
            ),
        ];
    }
}
