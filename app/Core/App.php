<?php
namespace App\Core;

use App\Services\Audit;

final class App
{
    public static function env(string $key, string $default = ""): string
    {
        static $vars = null;
        if ($vars === null) {
            $vars = [];
            foreach (
                @file(
                    dirname(__DIR__, 2) . "/.env",
                    FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES,
                ) ?:
                []
                as $line
            ) {
                if (
                    str_contains($line, "=") &&
                    !str_starts_with(trim($line), "#")
                ) {
                    [$name, $value] = explode("=", $line, 2);
                    $vars[trim($name)] = self::envValue($value);
                }
            }
        }
        $external = getenv($key);
        return $external !== false ? $external : $vars[$key] ?? $default;
    }

    /** Remove file-line whitespace and one matching pair of dotenv quotes. */
    public static function envValue(string $value): string
    {
        $value = trim($value, " \t\r\n");
        $length = strlen($value);
        if (
            $length >= 2 &&
            ($value[0] === '"' || $value[0] === "'") &&
            $value[$length - 1] === $value[0]
        ) {
            return substr($value, 1, -1);
        }
        return $value;
    }

    public static function db(): \PDO
    {
        static $db;
        return $db ??= new \PDO(
            self::env("DB_DSN"),
            self::env("DB_USER"),
            self::env("DB_PASSWORD"),
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ],
        );
    }

    public static function run(): void
    {
        $started = microtime(true);
        $path = parse_url($_SERVER["REQUEST_URI"] ?? "/", PHP_URL_PATH) ?: "/";
        $method = $_SERVER["REQUEST_METHOD"] ?? "GET";
        register_shutdown_function(static function () use (
            $method,
            $path,
            $started,
        ): void {
            Audit::visit($method, $path, (int) http_response_code(), $started);
        });
        $routes = array_merge(
            require dirname(__DIR__, 2) . "/routes/web.php",
            require dirname(__DIR__, 2) . "/routes/api.php",
            require dirname(__DIR__, 2) . "/routes/admin.php",
        );
        try {
            $handler = $routes[$method . " " . $path] ?? null;
            if ($handler === null) {
                http_response_code(404);
                echo "Page introuvable";
                return;
            }
            (new ($handler[0])())->{$handler[1]}();
        } catch (\Throwable $e) {
            error_log($e);
            Audit::event("application.error", "route", $path, [
                "exception" => get_class($e),
            ]);
            http_response_code(500);
            echo "Erreur serveur";
        }
    }

    public static function view(string $name, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        require dirname(__DIR__, 2) . "/views/" . $name . ".php";
    }

    public static function redirect(string $path): void
    {
        header("Location: " . $path, true, 303);
    }
}
