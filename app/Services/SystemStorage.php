<?php
namespace App\Services;
use App\Core\App;
/** Private bounded files; no database dependency for settings or incident reporting. */
final class SystemStorage
{
    public static function directory(): string
    {
        return App::env(
            "SYSTEM_STORAGE_PATH",
            dirname(__DIR__, 2) . "/storage/system",
        );
    }
    public static function ensure(): string
    {
        $dir = self::directory();
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException("Stockage système indisponible");
        }
        return $dir;
    }
    public static function read(string $name): ?array
    {
        $raw = @file_get_contents(self::directory() . "/" . $name);
        if ($raw === false) {
            return null;
        }
        return json_decode($raw, true) ?: null;
    }
    public static function write(
        string $name,
        array $data,
        bool $ifAbsent = false,
    ): void {
        $dir = self::ensure();
        $tmp = tempnam($dir, "write-");
        if ($tmp === false) {
            throw new \RuntimeException("Stockage indisponible");
        }
        try {
            chmod($tmp, 0600);
            if (
                file_put_contents(
                    $tmp,
                    json_encode(
                        $data,
                        JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE,
                    ),
                ) === false ||
                !($ifAbsent
                    ? @link($tmp, $dir . "/" . $name) ||
                        is_file($dir . "/" . $name)
                    : rename($tmp, $dir . "/" . $name))
            ) {
                throw new \RuntimeException("Écriture système impossible");
            }
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }
    public static function countersEnabled(): bool
    {
        return (self::read("settings.json")["counters_enabled"] ?? true) ===
            true;
    }
    public static function setCounters(bool $enabled): void
    {
        self::write("settings.json", ["counters_enabled" => $enabled]);
    }
    /** One shared snapshot per bounded reference set, refreshed once per 30 seconds. */
    public static function rentalSnapshot(
        array $references,
        callable $load,
    ): array {
        sort($references);
        $name =
            "snapshot-" . hash("sha256", implode(",", $references)) . ".json";
        $cached = self::read($name);
        $now = time();
        if ($cached && ($cached["at"] ?? 0) > $now - 30) {
            return $cached;
        }
        $dir = self::ensure();
        $lock = fopen($dir . "/snapshot.lock", "c");
        if (!$lock) {
            throw new \RuntimeException("Verrou indisponible");
        }
        try {
            if (!flock($lock, LOCK_EX | LOCK_NB)) {
                if ($cached && $cached["at"] > $now - 120) {
                    return $cached;
                }
                return ["at" => 0, "rows" => [], "unavailable" => true];
            }
            $cached = self::read($name);
            if ($cached && $cached["at"] > $now - 30) {
                return $cached;
            }
            $rows = $load();
            $snapshot = ["at" => $now, "rows" => $rows];
            self::write($name, $snapshot);
            $files = glob($dir . "/snapshot-*.json") ?: [];
            if (count($files) > 32) {
                usort($files, fn($a, $b) => filemtime($a) <=> filemtime($b));
                foreach (array_slice($files, 0, count($files) - 32) as $file) {
                    @unlink($file);
                }
            }
            return $snapshot;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
