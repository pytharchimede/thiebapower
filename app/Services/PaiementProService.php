<?php
namespace App\Services;
use App\Core\App;
final class PaiementProService {
 public static function customerEmail(array $r):string {
  $email=trim((string)($r['customer_email']??''));
  if($email==='')$email=trim(App::env('PAIEMENTPRO_CUSTOMER_EMAIL_FALLBACK'));
  if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>190)throw new \RuntimeException('Configurer PAIEMENTPRO_CUSTOMER_EMAIL_FALLBACK avec une adresse de réception Thiebapower valide');
  return $email;
 }
 public function initiateTest(array $r):string {
  return $this->createSession($r,'/api/paiementpro/test-callback','/payment/test-return?reference='.rawurlencode($r['reference']))['url'];
 }
 public function initiate(array $r):string {
  return $this->initiateSession($r)['url'];
 }
 public function initiateSession(array $r):array {
  return $this->createSession($r,'/api/paiementpro/rental-callback?token='.(new PaymentVerification)->token($r['reference']),'/payment/return?reference='.rawurlencode($r['reference']));
 }
 private function createSession(array $r,string $notificationPath,string $returnPath):array {
  $mode=$r['payment_environment']??IntegrationSettings::all()['paiementpro'];
  $merchant=$mode==='sandbox'?App::env('PAIEMENTPRO_SANDBOX_MERCHANT_ID'):App::env('PAIEMENTPRO_MERCHANT_ID');
  if(!$merchant)throw new \RuntimeException('Marchand Paiement Pro non configuré');
  $base=rtrim(App::env('APP_URL'),' /');
  if(!filter_var($base,FILTER_VALIDATE_URL)||parse_url($base,PHP_URL_SCHEME)!=='https')throw new \RuntimeException('APP_URL HTTPS requise');
  $name=preg_split('/\s+/',trim($r['customer_name']),2);
  $payload=['merchantId'=>$merchant,'countryCurrencyCode'=>'952','referenceNumber'=>$r['reference'],
   'amount'=>(int)$r['rental_fee']+(int)$r['deposit'],'customerEmail'=>self::customerEmail($r),
   'customerFirstName'=>$name[0]??'Client','customerLastname'=>$name[1]??'Client',
   'customerPhoneNumber'=>$r['customer_phone'],'description'=>'Location powerbank '.$r['reference'],
   'notificationURL'=>$base.$notificationPath,'returnURL'=>$base.$returnPath];
  $channel=RentalPaymentChannel::normalize($r['payment_channel']??null);
  if($channel===null||!RentalPaymentChannel::paymentEnabled($channel))throw new \LogicException('Choisissez un moyen de paiement Côte d’Ivoire actuellement actif.');
  $payload['channel']=$channel;
  $endpoint=$mode==='sandbox'?'https://sandbox.paiementpro.net/webservice/onlinepayment/init/curl-init.php':'https://www.paiementpro.net/webservice/onlinepayment/init/curl-init.php';
  $ch=curl_init($endpoint);
  curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload,JSON_THROW_ON_ERROR),CURLOPT_HTTPHEADER=>['Content-Type: application/json; charset=utf-8','Accept: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>20,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_FOLLOWLOCATION=>false]);
  try {$body=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);if($body===false)throw new \RuntimeException('Paiement Pro indisponible: '.curl_error($ch));}
  finally {curl_close($ch);}
  $reply=json_decode((string)$body,true);
  if($http!==200||!is_array($reply)||($reply['success']??false)!==true||!is_string($reply['url']??null))throw new \RuntimeException('Initialisation Paiement Pro refusée (HTTP '.$http.')');
  $url=$reply['url'];$host=parse_url($url,PHP_URL_HOST);
  if(parse_url($url,PHP_URL_SCHEME)!=='https'||!is_string($host)||!($host==='paiementpro.net'||str_ends_with($host,'.paiementpro.net')))throw new \RuntimeException('URL de paiement inattendue');
  parse_str((string)parse_url($url,PHP_URL_QUERY),$query);$session=(string)($query['sessionid']??'');
  if($session===''||strlen($session)>160||!preg_match('/^[A-Za-z0-9_-]+$/D',$session))throw new \RuntimeException('Session de paiement manquante');
  return ['id'=>$session,'url'=>$url];
 }
}
