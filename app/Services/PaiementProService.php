<?php
namespace App\Services;
use App\Core\App;
final class PaiementProService {
 public function initiate(array $r):string {
  if(!extension_loaded('soap'))throw new \RuntimeException('SOAP manquant');
  $merchant=App::env('PAIEMENTPRO_MERCHANT_ID'); if(!$merchant)throw new \RuntimeException('Marchand non configuré');
  $base=rtrim(App::env('APP_URL'),' /');
  $client=new \SoapClient('https://www.paiementpro.net/webservice/OnlineServicePayment_v2.php?wsdl',['connection_timeout'=>10,'cache_wsdl'=>WSDL_CACHE_NONE]);
  $name=preg_split('/\s+/',trim($r['customer_name']),2);
  $reply=$client->initTransact(['merchantId'=>$merchant,'countryCurrencyCode'=>'952','referenceNumber'=>$r['reference'],'amount'=>(int)$r['rental_fee']+(int)$r['deposit'],'customerEmail'=>$r['customer_email'],'customerFirstName'=>$name[0]??'Client','customerLastName'=>$name[1]??'Client','customerPhoneNumber'=>$r['customer_phone'],'description'=>'Location powerbank '.$r['reference'],'notificationURL'=>$base.'/api/heycharge/callback','returnURL'=>$base.'/payment/return?reference='.rawurlencode($r['reference'])]);
  if((string)($reply->Code??'')!=='0'||empty($reply->Sessionid))throw new \RuntimeException('Initialisation du paiement refusée');
  return 'https://www.paiementpro.net/webservice/onlinepayment/processing_v2.php?sessionid='.rawurlencode((string)$reply->Sessionid);
 }
}
