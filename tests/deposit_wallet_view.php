<?php
declare(strict_types=1);
namespace App\Services {
 final class Auth {public static array $denied=[];public static function can($permission){return !in_array($permission,self::$denied,true);}}
 final class SystemStorage {public static function read($name){return ['events'=>[['endpoint'=>'https://api.xpaye.africa/wallet/request','payload'=>['montant'=>100]]]];}}
}
namespace {
 spl_autoload_register(static function($c){if(str_starts_with($c,'App\\'))require dirname(__DIR__).'/app/'.str_replace('\\','/',substr($c,4)).'.php';});
 $_SESSION=['csrf'=>'fixture-csrf'];$rows=[['id'=>2,'rental_id'=>null,'reference'=>null,'serial'=>null,'created_at'=>'2026-10-03 23:22:41','customer_name'=>null,'customer_phone'=>null,'payout_channel'=>null,'amount'=>100,'fee_reserve'=>0,'status'=>'submitted','http_status'=>201,'response_summary'=>'{"status":"success"}','refund_status'=>null,'refund_amount'=>null,'confirmation_proof'=>null]];
 ob_start();require dirname(__DIR__).'/views/partials/deposit_wallet_rows.php';$html=ob_get_clean();
 if(str_contains($html,'name="action" value="send"')||!str_contains($html,'value="confirm"')||str_contains($html,'<details open'))throw new RuntimeException('Incorrect actions or expanded JSON');
 $doc=new DOMDocument();@$doc->loadHTML($html);$xpath=new DOMXPath($doc);if($xpath->query('//pre[not(ancestor::details)]')->length)throw new RuntimeException('JSON outside accordion');
 \App\Services\Auth::$denied=['finance.withdraw','payout.view'];ob_start();require dirname(__DIR__).'/views/partials/deposit_wallet_rows.php';$html=ob_get_clean();if(str_contains($html,'<form')||str_contains($html,'<pre'))throw new RuntimeException('Restricted action or diagnostic leaked');
 $totals=[['purpose'=>'deposit','status'=>'unknown','amount'=>204,'count'=>1],['purpose'=>'test','status'=>'submitted','amount'=>100,'count'=>1]];ob_start();require dirname(__DIR__).'/views/partials/deposit_wallet_totals.php';$html=ob_get_clean();if(!str_contains($html,'Cautions')||!str_contains($html,'Essais')||str_contains($html,'304'))throw new RuntimeException('Test funds confused with deposits');
 echo "Wallet view OK: hidden JSON, status-sensitive actions, permissions and separate test/deposit totals\n";
}
