<?php
namespace App\Core {final class App {public static function env(string $key):string{return '';}}}
namespace App\Services {
 final class Audit {public static function requestId():string{return 'test';}}
 final class SystemReports {public static int $count=0;public static function record(...$args):void{self::$count++;}}
}
namespace {
 require dirname(__DIR__).'/app/Services/CheckoutConflict.php';
 require dirname(__DIR__).'/app/Services/CheckoutDiagnostics.php';
 try {\App\Services\CheckoutDiagnostics::measure('checkout_request',static fn()=>throw new \App\Services\CheckoutConflict('Batterie réservée'));}catch(\App\Services\CheckoutConflict $e){}
 if(\App\Services\SystemReports::$count!==0)throw new \RuntimeException('Business conflict created a technical incident');
 try {\App\Services\CheckoutDiagnostics::measure('checkout_request',static fn()=>throw new \RuntimeException('API indisponible'));}catch(\RuntimeException $e){}
 if(\App\Services\SystemReports::$count!==1)throw new \RuntimeException('Technical incident was suppressed');
 echo "Checkout conflicts are separated from technical incidents OK\n";
}
