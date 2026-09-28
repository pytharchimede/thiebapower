<?php
namespace App\Services;
use App\Core\App;
final class PaiementProPayoutService {
 /** Prepares a payout according to the supplied SOAP documentation; no public endpoint invokes it. */
 public function prepare(string $reference,int $amount,string $channel,string $phone,string $name):array {
  if($amount<=0||!in_array($channel,['WAVECI','MOMOCI','OMCIV','FLOOZ'],true)||!preg_match('/^\+?[0-9]{10,16}$/',$phone))throw new \InvalidArgumentException('Paramètres de restitution invalides');
  $mode=IntegrationSettings::all()['paiementpro'];
  if($mode==='sandbox'){
   $merchant=App::env('PAIEMENTPRO_SANDBOX_MERCHANT_ID');$secret=App::env('PAIEMENTPRO_SANDBOX_SECRET_KEY');
   $wsdl=App::env('PAIEMENTPRO_SANDBOX_PAYOUT_WSDL');
  }else{
   $merchant=App::env('PAIEMENTPRO_MERCHANT_ID');$secret=App::env('PAIEMENTPRO_SECRET_KEY');
   $wsdl='https://paiementpro.net/webservice/v2/payout/soap.php?wsdl';
  }
  if(!$merchant||!$secret||!$wsdl)throw new \RuntimeException('Reversement Paiement Pro non configuré');
  $timestamp=time();
  return ['wsdl'=>$wsdl,'params'=>['merchantId'=>$merchant,'currency'=>'XOF','amount'=>$amount,'referenceNo'=>$reference,'channel'=>$channel,'clientName'=>$name,'token'=>hash_hmac('sha256',$timestamp.$merchant,$secret),'timestamp'=>$timestamp,'payeeNo'=>$phone,'paymentReason'=>'Restitution caution '.$reference,'returnURL'=>rtrim(App::env('APP_URL'),'/').'/payment/return','callbackURL'=>rtrim(App::env('APP_URL'),'/').'/api/paiementpro/payout-callback']];
 }
}
