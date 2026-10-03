<?php
namespace App\Services;

final class PayoutResult {
 /** Recover fields omitted by an incomplete WSDL from the same SOAP exchange. */
 public static function response(object|array $reply,string $xml=''):array {
  $data=is_object($reply)?get_object_vars($reply):$reply;
  if($xml!==''&&strlen($xml)<=65536&&stripos($xml,'<!DOCTYPE')===false&&class_exists(\DOMDocument::class)){
   $doc=new \DOMDocument;
   if(@$doc->loadXML($xml,LIBXML_NONET)){
    $xpath=new \DOMXPath($doc);
    foreach($xpath->query('/*[local-name()="Envelope"]/*[local-name()="Body"]/*[local-name()="initTransactResponse" or local-name()="getTransStatusResponse"]/*[local-name()="return"]/*') as $node){
     if(!$node->hasChildNodes()||$node->childNodes->length===1)$data[$node->localName]??=$node->textContent;
    }
   }
  }
  foreach($data as $key=>$value){
   if(strtolower($key)==='sessionid'&&!isset($data['sessionid']))$data['sessionid']=$value;
   if(strtolower($key)==='url'&&!isset($data['url']))$data['url']=$value;
  }
  return $data;
 }
 public static function authorizationUrl(object|array $reply):string {
  $data=self::response($reply);$url=trim((string)($data['url']??''));
  if($url==='')return '';
  if(str_starts_with($url,'webservice/'))$url='https://paiementpro.net/'.$url;
  elseif(str_starts_with($url,'/webservice/'))$url='https://paiementpro.net'.$url;
  $parts=parse_url($url);
  if(!$parts||($parts['scheme']??'')!=='https'||($parts['host']??'')!=='paiementpro.net'||isset($parts['user'])||isset($parts['pass'])||isset($parts['port'])||($parts['path']??'')!=='/webservice/v2/payout/auth/')return '';
  parse_str($parts['query']??'',$query);
  $session=(string)($data['sessionid']??'');
  return $session!==''&&is_string($query['sessionid']??null)&&hash_equals($session,$query['sessionid'])?$url:'';
 }
 /** Only trusted initiation journals for this reference may restore a session. */
 public static function recordedInitiation(string $reference,array $events):array {
  foreach($events as $event){
   if(($event['reference']??'')!==$reference||($event['source']??'')!=='init')continue;
   $data=json_decode((string)$event['response'],true);
   if(!is_array($data))continue;
   if(isset($data['method'])&&$data['method']!=='initTransact')continue;
   $reply=self::response($data,(string)($data['responseSoap']??''));
   if(self::initiation($reply)['state']==='processing')return $reply;
  }
  return [];
 }
 public static function dispatchable(string $state):bool {return $state==='pending';}
 /** initTransact never proves final delivery, even when the provider says SUCCEEDED. */
 public static function initiation(object|array $reply):array {
  $data=self::response($reply);
  $status=strtoupper(trim((string)($data['status']??'')));
  $code=(string)($data['code']??'');
  $session=trim((string)($data['sessionid']??$data['sessionId']??''));
  if($status==='FAILED')return ['state'=>'failed','session'=>''];
  if($session!=='')return ['state'=>'processing','session'=>$session];
  if(in_array($status,['INITIATED','SUCCEEDED','SUCCESS'],true)&&$code==='0')return ['state'=>'initiated','session'=>''];
  return ['state'=>'unknown','session'=>''];
 }

 public static function finalStatus(object|array $reply,array $expected):string {
  $data=self::response($reply);
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
