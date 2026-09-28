<?php
namespace App\Services;
use App\Core\App;
final class PaiementProService {
 private function config():array {
  $mode=IntegrationSettings::all()['paiementpro'];
  if($mode==='sandbox'){
   $merchant=App::env('PAIEMENTPRO_SANDBOX_MERCHANT_ID');
   $wsdl=App::env('PAIEMENTPRO_SANDBOX_WSDL');
   $processing=App::env('PAIEMENTPRO_SANDBOX_PROCESSING_URL');
   if(!$merchant||!$wsdl||!$processing)throw new \RuntimeException('Sandbox Paiement Pro non configurée');
  }else{
   $merchant=App::env('PAIEMENTPRO_MERCHANT_ID');
   $wsdl='https://www.paiementpro.net/webservice/OnlineServicePayment_v2.php?wsdl';
   $processing='https://www.paiementpro.net/webservice/onlinepayment/processing_v2.php';
   if(!$merchant)throw new \RuntimeException('Compte de production Paiement Pro non configuré');
  }
  foreach([$wsdl,$processing] as $url)if(!filter_var($url,FILTER_VALIDATE_URL)||parse_url($url,PHP_URL_SCHEME)!=='https')throw new \RuntimeException('URL Paiement Pro HTTPS requise');
  return compact('merchant','wsdl','processing');
 }
 public function initiateTest(array $r):string {
  return $this->initiateWithUrls($r,'/api/paiementpro/test-callback','/payment/test-return?reference='.rawurlencode($r['reference']));
 }
 public function initiate(array $r):string {
  return $this->initiateWithUrls($r,'/api/heycharge/callback','/payment/return?reference='.rawurlencode($r['reference']));
 }
 private function initiateWithUrls(array $r,string $notificationPath,string $returnPath):string {
  if(!extension_loaded('soap'))throw new \RuntimeException('SOAP manquant');
  $config=$this->config();$base=rtrim(App::env('APP_URL'),' /');
  $client=new \SoapClient($config['wsdl'],['connection_timeout'=>10,'cache_wsdl'=>WSDL_CACHE_NONE]);
  $name=preg_split('/\s+/',trim($r['customer_name']),2);
  $reply=$client->initTransact(['merchantId'=>$config['merchant'],'countryCurrencyCode'=>'952','referenceNumber'=>$r['reference'],'amount'=>(int)$r['rental_fee']+(int)$r['deposit'],'customerEmail'=>$r['customer_email'],'customerFirstName'=>$name[0]??'Client','customerLastName'=>$name[1]??'Client','customerPhoneNumber'=>$r['customer_phone'],'description'=>'Location powerbank '.$r['reference'],'notificationURL'=>$base.$notificationPath,'returnURL'=>$base.$returnPath]);
  if((string)($reply->Code??'')!=='0'||empty($reply->Sessionid))throw new \RuntimeException('Initialisation du paiement refusée');
  return $config['processing'].'?sessionid='.rawurlencode((string)$reply->Sessionid);
 }
}
