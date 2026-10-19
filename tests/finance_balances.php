<?php
namespace App\Core {
 final class App {
  public static function env(string $key,string $default=''):string{return ['PAIEMENTPRO_MERCHANT_ID'=>'fixture','PAIEMENTPRO_SECRET_KEY'=>'fixture-secret'][$key]??$default;}
 }
}
namespace {
 require dirname(__DIR__).'/app/Services/PaiementProPayoutService.php';
 $soap=new class {
  public int $calls=0;
  public mixed $reply;
  public function getBalance(array $params):mixed {
   $this->calls++;
   if(array_keys($params)!==['merchantId','token','timestamp'])throw new \RuntimeException('Unexpected wallet request');
   if($params['token']!==hash_hmac('sha256',$params['timestamp'].'fixture','fixture-secret'))throw new \RuntimeException('Invalid wallet authentication');
   return $this->reply;
  }
 };
 $provider=new \App\Services\PaiementProPayoutService(static fn()=>$soap,static fn()=>1792417703);
 $before=ini_get('default_socket_timeout');
 foreach([5008,0] as $amount){
  $soap->reply=(object)['status'=>'success','balance'=>$amount,'currency'=>'XOF'];
  $actual=$provider->balance();
  if($actual['balance']!==$amount||$actual['currency']!=='XOF')throw new \RuntimeException('Incorrect live wallet value');
 }
 foreach([
  ['status'=>'failed','balance'=>5008,'currency'=>'XOF'],
  ['status'=>'success','balance'=>5008,'currency'=>'USD'],
  ['status'=>'success','currency'=>'XOF'],
  ['status'=>'success','balance'=>'invalid','currency'=>'XOF'],
 ] as $bad){
  $soap->reply=(object)$bad;$rejected=false;
  try{$provider->balance();}catch(\RuntimeException){$rejected=true;}
  if(!$rejected)throw new \RuntimeException('Invalid balance must not become zero');
 }
 if(ini_get('default_socket_timeout')!==$before)throw new \RuntimeException('Socket timeout not restored');
 echo "Wallet lookup: verified balance, zero, provider failures, currency, malformed amounts and timeout restoration OK (no payment)\n";
}
