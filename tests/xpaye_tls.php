<?php
declare(strict_types=1);
namespace App\Services {
 function curl_init($url){$GLOBALS['tls_calls']++;return (object)[];}
 function curl_setopt($ch,$key,$value){$GLOBALS['tls_options'][$key]=$value;return true;}
 function curl_setopt_array($ch,$options){$GLOBALS['tls_options']+= $options;return true;}
 function curl_exec($ch){return '{"token":"test-token"}';}
 function curl_getinfo($ch){return ['http_code'=>200];}
 function curl_errno($ch){return 0;}function curl_error($ch){return '';}function curl_close($ch):void{}
}
namespace {
 spl_autoload_register(static function($c){if(str_starts_with($c,'App\\'))require dirname(__DIR__).'/app/'.str_replace('\\','/',substr($c,4)).'.php';});
 putenv('XPAYE_LOGIN=test-login');putenv('XPAYE_PASSWORD=test-pass');$GLOBALS['tls_calls']=0;$GLOBALS['tls_options']=[];
 $path=tempnam(sys_get_temp_dir(),'xpaye-ca-');file_put_contents($path,'fixture bundle');putenv('XPAYE_CA_BUNDLE='.$path);
 try{
  $c=new \App\Services\XPayeWalletClient();$c->authenticate();$o=$GLOBALS['tls_options'];
  if($o[CURLOPT_CAINFO]!==$path||$o[CURLOPT_SSL_VERIFYPEER]!==true||$o[CURLOPT_SSL_VERIFYHOST]!==2||$o[CURLOPT_FOLLOWLOCATION]!==false)throw new RuntimeException('TLS validation changed');
  if($c->diagnostics()['events'][0]['ca_bundle']!==$path)throw new RuntimeException('CA not diagnosed');
  putenv('XPAYE_CA_BUNDLE='.$path.'-absent');try{(new \App\Services\XPayeWalletClient())->authenticate();throw new LogicException('Missing CA accepted');}catch(RuntimeException $e){}
  if($GLOBALS['tls_calls']!==1)throw new LogicException('Invalid bundle reached network');
 }finally{unlink($path);putenv('XPAYE_CA_BUNDLE');}
 echo "XPaye CA configuration OK: explicit bundle, TLS checks preserved, unreadable bundle blocked; mocked cURL, no API call\n";
}
