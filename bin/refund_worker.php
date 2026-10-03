<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
spl_autoload_register(static function(string $class):void {
 if(!str_starts_with($class,'App\\'))return;
 $file=dirname(__DIR__).'/app/'.str_replace('\\','/',substr($class,4)).'.php';
 if(is_file($file))require $file;
});
use App\Core\App;
use App\Services\AutomaticDepositRefundService;
\App\Services\DepositWallet::recoverVerifiedPayments();
\App\Services\DepositWallet::prepareMissingFees();
if((int)\App\Services\DepositWallet::settings()['enabled']===1){
 foreach(App::db()->query("SELECT id FROM deposit_wallet_transfers WHERE purpose='deposit' AND status='pending' ORDER BY id LIMIT 25")->fetchAll() as $transfer){
  try{\App\Services\DepositWallet::send((int)$transfer['id']);}catch(Throwable $e){error_log('Wallet worker transfer '.$transfer['id'].': '.$e->getMessage());}
 }
}
if(App::env('AUTOMATIC_REFUNDS_ENABLED')!=='1'){fwrite(STDERR,"Reversements automatiques désactivés\n");exit(0);}
$db=App::db();$service=new AutomaticDepositRefundService();
// Unfunded pending refunds must not starve already sent payouts or funded returns.
$processing=$db->query("SELECT id,status FROM deposit_settlements WHERE status='processing' ORDER BY id LIMIT 25")->fetchAll();
$pending=$db->query("SELECT s.id,s.status FROM deposit_settlements s JOIN rentals r ON r.id=s.rental_id JOIN deposit_wallet_transfers w ON w.rental_id=r.id JOIN deposit_wallet_settings cfg ON cfg.id=1 WHERE s.status='pending' AND r.payment_environment='production' AND w.status='confirmed' AND w.deposit_amount>=s.refund_amount AND w.fee_reserve>=CAST(JSON_UNQUOTE(JSON_EXTRACT(cfg.fee_rules,CONCAT('$.',r.payout_channel,'.fixed'))) AS UNSIGNED)+CEIL(s.refund_amount*CAST(JSON_UNQUOTE(JSON_EXTRACT(cfg.fee_rules,CONCAT('$.',r.payout_channel,'.basis_points'))) AS UNSIGNED)/10000) ORDER BY s.id LIMIT 25")->fetchAll();
$rows=array_merge($processing,$pending);
foreach($rows as $row){
 try {
  if($row['status']==='pending')$service->dispatch((int)$row['id']);
  else $service->reconcile((int)$row['id']);
 }catch(Throwable $e){error_log('Refund worker settlement '.$row['id'].': '.$e->getMessage());}
}
