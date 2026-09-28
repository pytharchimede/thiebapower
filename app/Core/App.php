<?php
namespace App\Core;
use App\Controllers\RentalController;
use App\Controllers\AdminController;
use App\Controllers\PaymentController;
use App\Controllers\PaymentLabController;
use App\Controllers\SimulationController;
final class App {
 public static function env(string $key, string $default=''): string { static $vars=null; if($vars===null){$vars=[]; foreach(@file(dirname(__DIR__,2).'/.env',FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[] as $line){ if(str_contains($line,'=') && !str_starts_with(trim($line),'#')){[$k,$v]=explode('=',$line,2);$vars[trim($k)]=trim($v," \"'");}}} return getenv($key)?:($vars[$key]??$default); }
 public static function db(): \PDO { static $db; return $db??=new \PDO(self::env('DB_DSN'),self::env('DB_USER'),self::env('DB_PASSWORD'),[\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,\PDO::ATTR_DEFAULT_FETCH_MODE=>\PDO::FETCH_ASSOC]); }
 public static function run(): void { try { $path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);$method=$_SERVER['REQUEST_METHOD']??'GET';$routes=[
 'GET /'=>[RentalController::class,'index'], 'POST /rentals'=>[RentalController::class,'create'], 'GET /rentals/status'=>[RentalController::class,'status'],
 'POST /api/heycharge/callback'=>[PaymentController::class,'callback'], 'GET /payment/return'=>[PaymentController::class,'returnPage'],
 'POST /admin/payment-lab/payin'=>[PaymentLabController::class,'payin'], 'POST /admin/payment-lab/payout'=>[PaymentLabController::class,'payout'], 'POST /admin/payment-lab/reconcile'=>[PaymentLabController::class,'reconcile'],
 'POST /api/paiementpro/payout-callback'=>[PaymentLabController::class,'payoutNotification'], 'POST /api/paiementpro/test-callback'=>[PaymentLabController::class,'notification'], 'GET /payment/test-return'=>[PaymentLabController::class,'returnPage'],
 'POST /admin/simulation/paid'=>[SimulationController::class,'paid'], 'POST /admin/simulation/returned'=>[SimulationController::class,'returned'], 'POST /admin/simulation/reconcile'=>[SimulationController::class,'reconcile'],
 'GET /admin'=>[AdminController::class,'index'], 'POST /admin/prices'=>[AdminController::class,'prices'], 'POST /admin/batteries'=>[AdminController::class,'battery'], 'POST /admin/modes'=>[AdminController::class,'modes']
 ];$handler=$routes[$method.' '.$path]??null;if(!$handler){http_response_code(404);echo 'Page introuvable';return;} (new $handler[0])->{$handler[1]}(); }catch(\Throwable $e){error_log($e);http_response_code(500);echo 'Erreur serveur';} }
 public static function view(string $name,array $data=[]):void {extract($data,EXTR_SKIP);require dirname(__DIR__,2).'/views/'.$name.'.php';}
 public static function redirect(string $path):void {header('Location: '.$path, true,303);}
}
