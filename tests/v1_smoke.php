<?php
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) return;
    $file = dirname(__DIR__) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) require $file;
});

use App\Models\Rental;
use App\Services\Auth;

$cases = [
    [200, 10, 0, 0],
    [200, 10, 1, 20],
    [200, 10, 3600, 20],
    [200, 10, 3601, 40],
    [200, 100, 7200, 200],
    [0, 10, 3600, 0],
];
foreach ($cases as [$deposit, $percent, $seconds, $expected]) {
    if (Rental::due($deposit, $percent, $seconds) !== $expected) {
        throw new RuntimeException('Calcul de caution incorrect');
    }
}
if (Auth::can('users.manage', ['role' => 'manager']) || !Auth::can('users.manage', ['role' => 'owner'])) {
    throw new RuntimeException('Permission propriétaire incorrecte');
}
if (PHP_SAPI === 'cli') echo "Calculs de caution et permissions propriétaires : OK\n";
