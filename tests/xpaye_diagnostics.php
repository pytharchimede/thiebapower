<?php
declare(strict_types=1);
spl_autoload_register(static function($class){if(str_starts_with($class,'App\\'))require dirname(__DIR__).'/app/'.str_replace('\\','/',substr($class,4)).'.php';});
use App\Services\XPayeWalletClient;
putenv('XPAYE_LOGIN=private-login@example.test');putenv('XPAYE_PASSWORD=private-pass');
$client=new XPayeWalletClient(static function($path,$payload,$token){
 if($path==='/auth/token')return ['http'=>200,'body'=>'{"data":{"token":"private-token"},"message":"private-token private-pass private-login@example.test"}'];
 return ['http'=>422,'body'=>'{"code":"INSUFFICIENT_BALANCE","message":"Solde insuffisant","details":{"Authorization":"Bearer private-token","password":"private-pass","accessToken":"different-secret"}}'];
});
$token=$client->authenticate();$result=$client->request(100,$token);$events=$client->diagnostics()['events'];
if(count($events)!==2||$events[1]['endpoint']!=='https://api.xpaye.africa/wallet/request'||$events[1]['payload']!==['montant'=>100]||$events[1]['http_status']!==422||$events[1]['response']['code']!=='INSUFFICIENT_BALANCE')throw new RuntimeException('Incomplete API diagnostic');
$json=json_encode($events);foreach(['private-login','private-pass','private-token','different-secret'] as $secret)if(str_contains($json,$secret))throw new RuntimeException('Diagnostic leaked a credential');
$timeout=new XPayeWalletClient(static function(){throw new RuntimeException('cURL 28: request timed out private-pass');});
try{$timeout->authenticate();throw new LogicException('Timeout accepted');}catch(RuntimeException $e){if(str_contains($e->getMessage(),'private-pass'))throw new LogicException('Exception leaked password');}
$events=$timeout->diagnostics()['events'];if(count($events)!==1||!str_contains($events[0]['error'],'cURL 28')||!isset($events[0]['elapsed_ms']))throw new LogicException('Network failure not traced');
$nonJson=new XPayeWalletClient(static fn()=>['http'=>502,'body'=>'unstructured-auth-secret']);
try{$nonJson->authenticate();}catch(RuntimeException $e){}
if(str_contains(json_encode($nonJson->diagnostics()),'unstructured-auth-secret'))throw new LogicException('Non-JSON auth response leaked');
echo "XPaye diagnostics OK: endpoint, payload, HTTP response, nested credential redaction and network failures; mocked API, no payment\n";
