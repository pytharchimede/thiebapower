<?php
declare(strict_types=1);
namespace App\Services {final class Auth {public static array $denied=[];public static function can($p){return !in_array($p,self::$denied,true);}public static function requirePermission($p){if(!self::can($p))throw new \LogicException('Permission denied');return [];}}}
namespace {
 spl_autoload_register(static function($c){if(str_starts_with($c,'App\\'))require dirname(__DIR__).'/app/'.str_replace('\\','/',substr($c,4)).'.php';});
 $esc=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
 $r=['reference'=>'TBP-<script>','deposit'=>200,'deposit_payment_verified_at'=>gmdate('Y-m-d H:i:s'),'status'=>'active','due_at'=>gmdate('Y-m-d H:i:s',time()-301),'returned_at'=>null,'billing_rule'=>'prorata_grace5','rental_fee'=>600,'duration_minutes'=>60,'late_percent'=>20];
 ob_start();require dirname(__DIR__).'/views/partials/rental_deposit.php';$html=ob_get_clean();
 if(!str_contains($html,'199 / 200 FCFA')||str_contains($html,'TBP-<script>')||!str_contains($html,'data-billing='))throw new RuntimeException('Card amount or escaping invalid');
 \App\Services\Auth::$denied=['finance.view'];ob_start();require dirname(__DIR__).'/views/partials/rental_deposit.php';if(trim(ob_get_clean())!=='')throw new RuntimeException('Financial card leaked');
 foreach(['finance.view','rentals.view'] as $permission){\App\Services\Auth::$denied=[$permission];try{(new \App\Controllers\RentalOperationsController())->deposits();throw new RuntimeException('Snapshot permission bypass');}catch(LogicException $e){}}
 echo "Rental deposit cards OK: paid balance, escaped billing attributes, financial visibility and snapshot permissions; no DB or API calls\n";
}
