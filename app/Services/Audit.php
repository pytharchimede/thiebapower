<?php
namespace App\Services;

use App\Core\App;

final class Audit
{
    private static ?string $requestId = null;

    public static function requestId(): string
    {
        return self::$requestId ??= bin2hex(random_bytes(16));
    }

    public static function event(string $action, ?string $subjectType = null, ?string $subjectId = null, array $details = []): void
    {
        $safe = [];
        foreach ($details as $key => $value) {
            if (preg_match('/password|secret|token|authorization|cookie|hash|credential/i', (string) $key)) {
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $safe[(string) $key] = substr((string) $value, 0, 300);
            }
        }
        try {
            App::db()->prepare('INSERT INTO audit_events(request_id,actor_id,action,subject_type,subject_id,details,ip_address) VALUES(?,?,?,?,?,?,?)')
                ->execute([self::requestId(), Auth::id(), substr($action, 0, 100), $subjectType, $subjectId,
                    json_encode($safe, JSON_INVALID_UTF8_SUBSTITUTE), self::ip()]);
        } catch (\Throwable $e) {
            error_log('Audit write failed: ' . $e->getMessage());
        }
    }

    public static function visit(string $method, string $path, int $status, float $started): void
    {
        try {
            $fingerprint = session_status() === PHP_SESSION_ACTIVE ? hash('sha256', session_id()) : null;
            App::db()->prepare('INSERT INTO request_events(request_id,actor_id,session_fingerprint,method,path,status_code,ip_address,user_agent,referer,duration_ms) VALUES(?,?,?,?,?,?,?,?,?,?)')
                ->execute([self::requestId(), Auth::id(), $fingerprint, substr($method, 0, 12), substr($path, 0, 250),
                    $status, self::ip(), substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                    substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 500),
                    max(0, (int) round((microtime(true) - $started) * 1000))]);
        } catch (\Throwable $e) {
            error_log('Visit write failed: ' . $e->getMessage());
        }
    }

    public static function ip(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    }
}
