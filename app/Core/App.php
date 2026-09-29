<?php
namespace App\Core;

use App\Controllers\AdminController;
use App\Controllers\AuditController;
use App\Controllers\AuthController;
use App\Controllers\PaymentController;
use App\Controllers\PaymentLabController;
use App\Controllers\PayoutConsoleController;
use App\Controllers\RentalController;
use App\Controllers\SimulationController;
use App\Controllers\StationController;
use App\Controllers\UsersController;
use App\Services\Audit;

final class App
{
    public static function env(string $key, string $default = ''): string
    {
        static $vars = null;
        if ($vars === null) {
            $vars = [];
            foreach (@file(dirname(__DIR__, 2) . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                if (str_contains($line, '=') && !str_starts_with(trim($line), '#')) {
                    [$name, $value] = explode('=', $line, 2);
                    $vars[trim($name)] = trim($value, " \"'");
                }
            }
        }
        $external = getenv($key);
        return $external !== false ? $external : ($vars[$key] ?? $default);
    }

    public static function db(): \PDO
    {
        static $db;
        return $db ??= new \PDO(self::env('DB_DSN'), self::env('DB_USER'), self::env('DB_PASSWORD'), [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
    }

    public static function run(): void
    {
        $started = microtime(true);
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        register_shutdown_function(static function () use ($method, $path, $started): void {
            Audit::visit($method, $path, (int) http_response_code(), $started);
        });
        $routes = [
            'GET /' => [RentalController::class, 'index'],
            'POST /rentals' => [RentalController::class, 'create'],
            'GET /rentals/status' => [RentalController::class, 'status'],
            'POST /api/heycharge/callback' => [PaymentController::class, 'callback'],
            'GET /payment/return' => [PaymentController::class, 'returnPage'],
            'POST /api/paiementpro/payout-callback' => [PaymentLabController::class, 'payoutNotification'],
            'GET /api/paiementpro/payout-callback' => [PaymentLabController::class, 'payoutNotification'],
            'POST /api/paiementpro/test-callback' => [PaymentLabController::class, 'notification'],
            'GET /payment/test-return' => [PaymentLabController::class, 'returnPage'],
            'GET /admin/login' => [AuthController::class, 'loginPage'],
            'POST /admin/login' => [AuthController::class, 'login'],
            'POST /admin/logout' => [AuthController::class, 'logout'],
            'POST /api/heycharge/callback/register' => [StationController::class, 'register'],
            'POST /api/heycharge/callback/return' => [StationController::class, 'returned'],
            'POST /api/heycharge/callback/status' => [StationController::class, 'status'],
            'POST /admin/stations' => [StationController::class, 'save'],
            'POST /admin/stations/toggle' => [StationController::class, 'toggle'],
            'POST /admin/stations/sync' => [StationController::class, 'sync'],
            'POST /admin/stations/confirm-payment' => [StationController::class, 'confirmPayment'],
            'POST /admin/stations/reconcile' => [StationController::class, 'reconcile'],
            'GET /admin' => [AdminController::class, 'index'],
            'POST /admin/prices' => [AdminController::class, 'prices'],
            'POST /admin/batteries' => [AdminController::class, 'battery'],
            'POST /admin/modes' => [AdminController::class, 'modes'],
            'GET /admin/users' => [UsersController::class, 'index'],
            'POST /admin/users' => [UsersController::class, 'create'],
            'POST /admin/users/update' => [UsersController::class, 'update'],
            'POST /admin/roles/permissions' => [UsersController::class, 'permissions'],
            'GET /admin/audit' => [AuditController::class, 'index'],
            'GET /admin/payout' => [PayoutConsoleController::class, 'index'],
            'POST /admin/payment-lab/payin' => [PaymentLabController::class, 'payin'],
            'POST /admin/payment-lab/payout' => [PaymentLabController::class, 'payout'],
            'POST /admin/payment-lab/reconcile' => [PaymentLabController::class, 'reconcile'],
            'POST /admin/payment-lab/archive' => [PaymentLabController::class, 'archive'],
            'POST /admin/simulation/paid' => [SimulationController::class, 'paid'],
            'POST /admin/simulation/returned' => [SimulationController::class, 'returned'],
            'POST /admin/simulation/reconcile' => [SimulationController::class, 'reconcile'],
        ];
        try {
            $handler = $routes[$method . ' ' . $path] ?? null;
            if ($handler === null) {
                http_response_code(404);
                echo 'Page introuvable';
                return;
            }
            (new $handler[0])->{$handler[1]}();
        } catch (\Throwable $e) {
            error_log($e);
            Audit::event('application.error', 'route', $path, ['exception' => get_class($e)]);
            http_response_code(500);
            echo 'Erreur serveur';
        }
    }

    public static function view(string $name, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        require dirname(__DIR__, 2) . '/views/' . $name . '.php';
    }

    public static function redirect(string $path): void
    {
        header('Location: ' . $path, true, 303);
    }
}
