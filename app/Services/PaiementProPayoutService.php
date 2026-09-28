<?php
namespace App\Services;
use App\Core\App;
final class PaiementProPayoutService {
 private function credentials():array {
  $mode=IntegrationSettings::all()['paiementpro'];
  if($mode==='sandbox'){
   $merchant=App::env('PAIEMENTPRO_SANDBOX_MERCHANT_ID');$secret=App::env('PAIEMENTPRO_SANDBOX_SECRET_KEY');
   $wsdl=App::env('PAIEMENTPRO_SANDBOX_PAYOUT_WSDL');
  }else{
   $merchant=App::env('PAIEMENTPRO_MERCHANT_ID');$secret=App::env('PAIEMENTPRO_SECRET_KEY');
   $wsdl='https://paiementpro.net/webservice/v2/payout/soap.php?wsdl';
  }
  if(!$merchant||!$secret||!filter_var($wsdl,FILTER_VALIDATE_URL)||parse_url($wsdl,PHP_URL_SCHEME)!=='https')throw new \RuntimeException('Reversement Paiement Pro non configuré');
  return compact('merchant','secret','wsdl');
 }
 private function token(array $config,int $timestamp):string {return hash_hmac('sha256',$timestamp.$config['merchant'],$config['secret']);}
 public function prepare(string $reference,int $amount,string $channel,string $phone,string $name):array {
  if($amount<=0||!in_array($channel,['WAVECI','MOMOCI','OMCIV','FLOOZ'],true)||!preg_match('/^\+?[0-9]{10,16}$/',$phone))throw new \InvalidArgumentException('Paramètres de restitution invalides');
  $config=$this->credentials();$timestamp=time();
  return ['wsdl'=>$config['wsdl'],'params'=>['merchantId'=>$config['merchant'],'currency'=>'XOF','amount'=>$amount,'referenceNo'=>$reference,'channel'=>$channel,'clientName'=>$name,'token'=>$this->token($config,$timestamp),'timestamp'=>$timestamp,'payeeNo'=>$phone,'paymentReason'=>'Restitution caution '.$reference,'returnURL'=>rtrim(App::env('APP_URL'),'/').'/payment/return','callbackURL'=>rtrim(App::env('APP_URL'),'/').'/api/paiementpro/payout-callback']];
 }
 public function status(string $sessionId):object {
  if($sessionId==='')throw new \InvalidArgumentException('Session absente');
  $config=$this->credentials();$timestamp=time();
  $client=new \SoapClient($config['wsdl'],['connection_timeout'=>10,'cache_wsdl'=>WSDL_CACHE_NONE]);
  return $client->getTransStatus(['merchantId'=>$config['merchant'],'token'=>$this->token($config,$timestamp),'timestamp'=>$timestamp,'sessionid'=>$sessionId]);
 }
}
