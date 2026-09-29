<?php
namespace App\Services;
use App\Core\App;
final class PaymentVerification {
 public function ready():bool {return strlen(App::env('PAYMENT_CALLBACK_SECRET'))>=32;}
 public function token(string $reference):string {
  if(!$this->ready())throw new \RuntimeException('Secret de notification Paiement Pro manquant');
  return hash_hmac('sha256','paiementpro-online-v1:'.$reference,App::env('PAYMENT_CALLBACK_SECRET'));
 }
 public function verified(array $payload,array $rental,?string $token=null):bool {
  if(!$this->ready()||$token===null||!hash_equals($this->token((string)$rental['reference']),$token))return false;
  $merchant=$rental['payment_environment']==='sandbox'?App::env('PAIEMENTPRO_SANDBOX_MERCHANT_ID'):App::env('PAIEMENTPRO_MERCHANT_ID');
  return $merchant!==''
   && hash_equals($merchant,(string)($payload['merchantId']??''))
   && hash_equals((string)$rental['reference'],(string)($payload['referenceNumber']??''))
   && (string)($payload['countryCurrencyCode']??'')==='952'
   && preg_match('/^[0-9]+$/D',(string)($payload['amount']??''))===1
   && (int)$payload['amount']===(int)$rental['rental_fee']+(int)$rental['deposit']
   && (string)($payload['responsecode']??'')==='0';
 }
}
