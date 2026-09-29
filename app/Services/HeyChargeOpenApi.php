<?php
namespace App\Services;
use App\Core\App;
final class HeyChargeOpenApi {
 public function mode():string {return IntegrationSettings::all()['heycharge'];}
 public function configured():bool {return App::env('HEYCHARGE_API_BASE')!=='' && App::env('HEYCHARGE_API_KEY')!=='';}
 public function station(string $imei):array {return $this->request('GET','/v1/station/'.rawurlencode($this->identifier($imei)));}
 public function release(string $imei,string $batteryId,string $slotId):void {
  if($this->mode()!=='normal')throw new \LogicException('Mode matériel désactivé');
  $this->request('POST','/v1/station/'.rawurlencode($this->identifier($imei)),['battery_id'=>$this->identifier($batteryId),'slot_id'=>$this->identifier($slotId)]);
 }
 public function forceUnlock(string $imei,string $slotId):void {$this->request('POST','/v1/station/'.rawurlencode($this->identifier($imei)).'/forceUnlock',['slot_id'=>$this->identifier($slotId)]);}
 public function reboot(string $imei):void {$this->request('POST','/v1/station/'.rawurlencode($this->identifier($imei)).'/reboot',[]);}
 private function identifier(string $value):string {
  if($value===''||strlen($value)>120||!preg_match('/^[A-Za-z0-9_-]+$/D',$value))throw new \InvalidArgumentException('Identifiant matériel invalide');
  return $value;
 }
 private function request(string $method,string $path,?array $form=null):array {
  $base=rtrim(App::env('HEYCHARGE_API_BASE'),'/');$key=App::env('HEYCHARGE_API_KEY');
  if(!$key||!filter_var($base,FILTER_VALIDATE_URL)||parse_url($base,PHP_URL_SCHEME)!=='https'||parse_url($base,PHP_URL_HOST)!=='openapi.heycharge.global')throw new \RuntimeException('Configuration HeyCharge invalide');
  $ch=curl_init($base.$path);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_HTTPAUTH=>CURLAUTH_BASIC,CURLOPT_USERPWD=>$key.':',CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_HTTPHEADER=>['Accept: application/json']]);
  if($method==='POST')curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($form??[])]);
  try {$body=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);if($body===false)throw new \RuntimeException('HeyCharge indisponible: '.curl_error($ch));}
  finally {curl_close($ch);}
  if($code<200||$code>=300)throw new \RuntimeException('HeyCharge HTTP '.$code);
  $json=json_decode((string)$body,true);
  if($body!==''&&$json===null&&json_last_error()!==JSON_ERROR_NONE)throw new \RuntimeException('Réponse HeyCharge invalide');
  return is_array($json)?$json:[];
 }
}
