<?php
namespace App\Services;
use App\Core\App;
final class PayoutApiAudit {
 public static function record(string $reference,string $source,mixed $response):void {
  if(!in_array($source,['init','status','callback','error'],true))throw new \InvalidArgumentException('Source invalide');
  $fields=['status','code','description','sessionid','sessionId','referenceNo','amount','currency','transactionId','transactionid','faultcode','faultstring','exception'];
  $data=is_object($response)?get_object_vars($response):(is_array($response)?$response:['description'=>(string)$response]);
  $safe=[];
  foreach($fields as $key)if(array_key_exists($key,$data))$safe[$key]=substr((string)(is_scalar($data[$key])?$data[$key]:'[complexe]'),0,500);
  $json=json_encode($safe,JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
  try {App::db()->prepare('INSERT INTO payout_api_events(reference,source,response) VALUES(?,?,?)')->execute([substr($reference,0,120),$source,$json]);}
  catch(\Throwable $e){error_log('Payout audit unavailable '.$reference.': '.$e->getMessage());}
 }
}
