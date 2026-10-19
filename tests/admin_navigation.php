<?php
namespace App\Services {
 final class Auth {
  public static array $allowed=[];
  public static function can(string $permission,?array $user=null):bool{return in_array($permission,self::$allowed,true);}
  public static function user():array{return ['display_name'=>'Test'];}
 }
}
namespace {
 require dirname(__DIR__).'/app/Services/AdminNavigation.php';
 require dirname(__DIR__).'/app/Services/ReleaseInfo.php';
 use App\Services\AdminNavigation;
 use App\Services\Auth;
 function check(bool $ok,string $message):void{if(!$ok)throw new \RuntimeException($message);}
 Auth::$allowed=['payout.view'];
 $groups=AdminNavigation::visible();
 check(array_keys($groups)===['finances'],'Empty or forbidden modules leaked');
 check($groups['finances']['href']==='/admin/modules/finances','Payout-only account linked to inaccessible Finance');
 check(count($groups['finances']['items'])===1,'Finance links leaked to payout-only role');
 Auth::$allowed=['finance.view'];
 check(!in_array('profitability',array_column(AdminNavigation::visible()['finances']['items'],3),true),'Profitability requires station permission');
 Auth::$allowed[]='stations.view';
 check(in_array('profitability',array_column(AdminNavigation::visible()['finances']['items'],3),true),'Authorized profitability link missing');
 $routes=require dirname(__DIR__).'/routes/admin.php';
 foreach(AdminNavigation::groups() as $key=>$group){
  check(isset($routes['GET /admin/modules/'.$key]),'Module dashboard route missing');
  foreach($group['items'] as $item)check(isset($routes['GET '.$item[1]]),'Navigation route missing: '.$item[1]);
 }
 check(AdminNavigation::active('/admin/stations','/admin/stations/detail'),'Station detail parent missing');
 check(!AdminNavigation::active('/admin/stations','/admin/stations/profitability'),'Two groups selected at once');
 check(!AdminNavigation::active('/admin/finance','/admin/finance/cash'),'Finance dashboard selected on cash page');
 Auth::$allowed=['finance.view','finance.withdraw','finance.manage','reports.export'];
 $_SESSION=['csrf'=>'fixture'];
 $report=['withdrawn'=>0,'rentalRevenue'=>0,'payments'=>[],'depositsCollected'=>0,'refunded'=>0,'providerNet'=>0,'daily'=>[],'refunds'=>[],'cash'=>[],'openingCash'=>0,'cashIn'=>0,'cashOut'=>0,'cashBalance'=>0];
 $balances=['error'=>null,'rows'=>[['Payout','Solde du wallet BE-CASH',5008,'API Paiement Pro · fixture UTC']]];
 $start='2026-10-01';$end='2026-10-19';$withdrawals=[];$withdrawalUrls=[];
 foreach(['overview','withdrawals','summary','daily','receipts','refunds','cash'] as $financeSection){
  $_SERVER['REQUEST_URI']=$financeSection==='overview'?'/admin/finance':'/admin/finance/'.$financeSection;
  ob_start();require dirname(__DIR__).'/views/finance.php';$html=ob_get_clean();
  check(str_contains($html,'tb-nav-group'),'Grouped navigation missing');
  check(str_contains($html,'action="/admin/finance/withdraw"')===($financeSection==='withdrawals'),'Withdrawal form leaked to another Finance page');
  check(str_contains($html,'action="/admin/finance/cash"')===($financeSection==='cash'),'Cash form leaked to another Finance page');
  check(str_contains($html,'tb-balance-recap')===($financeSection==='overview'),'Balance recap on wrong page');
  check(str_contains($html,'name="from"')===($financeSection!=='withdrawals'),'Period filter lost');
 }
 Auth::$allowed=['finance.view'];$financeSection='withdrawals';
 ob_start();require dirname(__DIR__).'/views/finance.php';$html=ob_get_clean();
 check(!str_contains($html,'action="/admin/finance/withdraw"'),'Read-only Finance role gained withdrawal form');
 echo "Admin navigation: routes, module permissions, active links, split Finance views and unchanged action endpoints OK\n";
}
