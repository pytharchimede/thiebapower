<?php
namespace App\Services;
use App\Core\App;
final class PayoutApiAudit {
 public static function request(string $reference,array $request):void {
  $allowed=['merchantId','currency','amount','referenceNo','channel','clientName','timestamp','payeeNo','clientId','returnContext','paymentReason','returnURL','callbackURL'];
  $safe=array_intersect_key($request['params'],array_flip($allowed));
  $safe['token']='[HMAC SHA-256 masqué]';
  $json=json_encode($safe,JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
  try {App::db()->prepare('INSERT INTO payout_api_requests(reference,endpoint,parameters) VALUES(?,?,?)')->execute([$reference,$request['wsdl'],$json]);}
  catch(\Throwable $e){error_log('Payout request audit unavailable '.$reference.': '.$e->getMessage());}
 }
 public static function record(string $reference,string $source,mixed $response):void {
  if(!in_array($source,['init','status','callback','error'],true))throw new \InvalidArgumentException('Source invalide');
  $fields=['status','code','description','sessionid','sessionId','referenceNo','referenceNumber','reference','merchantId','amount','currency','channel','payeeNo','tran_id','transactionId','transactionid','responsecode','message','faultcode','faultstring','exception','method','contentType','bodyHash','fieldNames','authenticated','matchedReference','matchedAmount','matchedMerchant','matchedBeneficiary'];
  $data=is_object($response)?get_object_vars($response):(is_array($response)?$response:['description'=>(string)$response]);
  $safe=[];
  foreach($fields as $key)if(array_key_exists($key,$data))$safe[$key]=substr((string)(is_scalar($data[$key])?$data[$key]:'[complexe]'),0,500);
  $json=json_encode($safe,JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
  try {App::db()->prepare('INSERT INTO payout_api_events(reference,source,response) VALUES(?,?,?)')->execute([substr($reference,0,120),$source,$json]);
   Audit::event('payout.api_'.$source,'payment',$reference,['status'=>$safe['status']??'','code'=>$safe['code']??'']);}
  catch(\Throwable $e){error_log('Payout audit unavailable '.$reference.': '.$e->getMessage());}
 }
}
