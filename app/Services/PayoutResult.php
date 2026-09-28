<?php
namespace App\Services;

final class PayoutResult {
 public static function dispatchable(string $state):bool {return $state==='pending';}
 /** initTransact never proves final delivery, even when the provider says SUCCEEDED. */
 public static function initiation(object|array $reply):array {
  $data=is_object($reply)?get_object_vars($reply):$reply;
  $status=strtoupper(trim((string)($data['status']??'')));
  $code=(string)($data['code']??'');
  $session=trim((string)($data['sessionid']??$data['sessionId']??''));
  if($status==='FAILED')return ['state'=>'failed','session'=>''];
  if($session!=='')return ['state'=>'processing','session'=>$session];
  if(in_array($status,['INITIATED','SUCCEEDED','SUCCESS'],true)&&$code==='0')return ['state'=>'initiated','session'=>''];
  return ['state'=>'unknown','session'=>''];
 }

 public static function finalStatus(object|array $reply,array $expected):string {
  $data=is_object($reply)?get_object_vars($reply):$reply;
  $status=strtoupper(trim((string)($data['status']??'')));
  if($status==='FAILED')return 'failed';
  if($status!=='SUCCESS')return 'processing';
  foreach(['sessionid','referenceNo','currency','channel','payeeNo'] as $field){
   if(!array_key_exists($field,$data)||!hash_equals((string)$expected[$field],(string)$data[$field]))return 'mismatch';
  }
  if((int)($data['amount']??-1)!==(int)$expected['amount'])return 'mismatch';
  return 'succeeded';
 }
}
