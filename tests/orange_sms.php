<?php
declare(strict_types=1);
// Test fixtures override every Orange setting; never use the production .env.
foreach (['ORANGE_SMS_ENABLED'=>'1','ORANGE_SMS_CREDENTIALS_SOURCE'=>'auto','ORANGE_SMS_AUTHORIZATION_HEADER'=>'','ORANGE_SMS_CLIENT_ID'=>'','ORANGE_SMS_CLIENT_SECRET'=>'','ORANGE_SMS_ENCRYPTION_KEY'=>base64_encode(random_bytes(32)),'TECHNICAL_ADMIN_USERNAME'=>'test-admin'] as $key=>$value) putenv($key.'='.$value);
spl_autoload_register(static function($c){ if(str_starts_with($c,'App\\'))require dirname(__DIR__).'/app/'.str_replace('\\','/',substr($c,4)).'.php'; });
use App\Services\{OrangeSmsClient as Client,OrangeSmsSettings as Settings};
function same($want,$got): void {if($want!==$got)throw new RuntimeException('Assertion failed: '.var_export($got,true));}
function rejects(callable $fn): void {try{$fn();}catch(InvalidArgumentException|RuntimeException $e){return;}throw new Exception('Expected rejection');}
$s=Settings::defaults(); same(false,$s['enabled']); same('simulation',$s['mode']);
foreach(['07 00 00 00 00','2250700000000','002250700000000','+2250700000000'] as $p)same('+2250700000000',Client::phone($p));
foreach(['+33700000000','070000000','07000000000',"+2250700000000\n<script>"] as $p)rejects(fn()=>Client::phone($p));
rejects(fn()=>Client::payload($s,'0700000000',''));rejects(fn()=>Client::payload($s,'0700000000',str_repeat('é',481)));
$body=Client::payload($s,'0700000000','Bonjour'); same('tel:+2250700000000',$body['outboundSMSMessageRequest']['address']);same('tel:+2250000',$body['outboundSMSMessageRequest']['senderAddress']);
rejects(fn()=>(new Client($s))->send('0700000000','Bonjour'));
$s['enabled']=true;$calls=[];
$t=function($method,$url,$headers,$body)use(&$calls){$calls[]=[$method,$url,$headers,$body];throw new RuntimeException('Simulation called network');};
same('simulated',(new Client($s,$t))->send('0700000000','Bonjour')['state']);same(0,count($calls));
putenv('ORANGE_SMS_ENCRYPTION_KEY='.base64_encode(random_bytes(32)));$cipher=Settings::encrypt('demo-secret');same('demo-secret',Settings::decrypt($cipher));
$raw=base64_decode($cipher);$raw[30]=chr(ord($raw[30])^1);rejects(fn()=>Settings::decrypt(base64_encode($raw)));
putenv('ORANGE_SMS_CLIENT_ID=test-id');putenv('ORANGE_SMS_CLIENT_SECRET=test-secret');
$s['mode']='production';$s['sender_mode']='custom';rejects(fn()=>Client::payload($s,'0700000000','Bonjour'));$s['sender_name']='THIEBAPOWER';rejects(fn()=>Client::payload($s,'0700000000','Bonjour'));$s['sender_approved']=true;
$calls=[];$transport=function($method,$url,$headers,$body)use(&$calls){
 $calls[]=[$method,$url,$headers,$body];
 if(str_ends_with($url,'/token'))return ['http_status'=>200,'data'=>['access_token'=>'TOKEN','expires_in'=>3600]];
 if($method==='GET')return ['http_status'=>200,'data'=>['partnerContracts'=>['contracts'=>[]]]];
 return ['http_status'=>201,'data'=>['outboundSMSMessageRequest'=>['resourceURL'=>'https://api.orange.com/resource/ID123']]];
};
$c=new Client($s,$transport);$result=$c->send('0700000000','Bonjour');same('accepted',$result['state']);same('ID123',$result['resource_id']);same(2,count($calls));
same('https://api.orange.com/smsmessaging/v1/outbound/tel%3A%2B2250000/requests',$calls[1][1]);same('grant_type=client_credentials',$calls[0][3]);same('Authorization: Basic '.base64_encode('test-id:test-secret'),$calls[0][2][0]);same('Authorization: Bearer TOKEN',$calls[1][2][0]);same('THIEBAPOWER',json_decode($calls[1][3],true)['outboundSMSMessageRequest']['senderName']);same(false,str_contains(json_encode($result),'TOKEN'));
foreach(['balance'=>'contracts','usage'=>'statistics','purchases'=>'purchaseorders'] as $op=>$path){$c->inspect($op);same('https://api.orange.com/sms/admin/v1/'.$path,end($calls)[1]);same(null,end($calls)[3]);}same(5,count($calls));
foreach([400=>'rejected',401=>'rejected',429=>'rejected',500=>'unknown'] as $http=>$state){$n=0;$mock=function($method)use($http,&$n){$n++;return $method==='POST'&&$n===1?['http_status'=>200,'data'=>['access_token'=>'TOKEN']]:['http_status'=>$http,'data'=>[]];};same($state,(new Client($s,$mock))->send('0700000000','Test')['state']);same(2,$n);}
$n=0;$mock=function()use(&$n){$n++;return ['http_status'=>401,'data'=>[]];};rejects(fn()=>(new Client($s,$mock))->send('0700000000','Test'));same(1,$n);
rejects(fn()=>Settings::validate(['mode'=>'invalid'],Settings::defaults()));rejects(fn()=>Settings::validate(['sender_address'=>'tel:+2250700000000'],Settings::defaults()));
rejects(fn()=>Settings::validate(['mode'=>'production','sender_mode'=>'custom','sender_address'=>'tel:+2250000'],Settings::defaults()));
$v=Settings::validate(['mode'=>'production','enabled'=>'on','sender_address'=>'tel:+2250000','client_id'=>'app','client_secret'=>'secret','sender_mode'=>'custom','sender_name'=>'THIEBAPOWER','sender_approved'=>'on'],Settings::defaults());same('secret',Settings::decrypt($v['secret_cipher']));
$v2=Settings::validate(['mode'=>'simulation','sender_address'=>'tel:+2250000','client_id'=>'app','client_secret'=>''],$v);same($v['secret_cipher'],$v2['secret_cipher']);
rejects(fn()=>Settings::validate(['sender_address'=>'tel:+2250000','client_id'=>'other'],$v));
$default=$s;$default['sender_mode']='default';$default['sender_approved']=false;
$payload=Client::payload($default,'0700000000','Test');same(false,array_key_exists('senderName',$payload['outboundSMSMessageRequest']));
$calls=[];same('accepted',(new Client($default,$transport))->send('0700000000','Test')['state']);same(false,array_key_exists('senderName',json_decode($calls[1][3],true)['outboundSMSMessageRequest']));
rejects(fn()=>Settings::validate(['sender_mode'=>'invalid','sender_address'=>'tel:+2250000'],Settings::defaults()));
putenv('ORANGE_SMS_ENABLED=0');$calls=[];rejects(fn()=>(new Client($default,$transport))->send('0700000000','Test'));same(0,count($calls));
putenv('ORANGE_SMS_ENABLED=1');same(true,Settings::serverEnabled());putenv('ORANGE_SMS_ENABLED=1');
putenv('TECHNICAL_ADMIN_USERNAME=ulrich');
same(true,\App\Services\Auth::can('sms.test',['role'=>'owner','username'=>'ulrich']));
same(false,\App\Services\Auth::can('sms.test',['role'=>'owner','username'=>'client']));
same(false,\App\Services\Auth::can('payout.send',['role'=>'owner','username'=>'client']));
same(true,\App\Services\Auth::can('finance.withdraw',['role'=>'owner','username'=>'client']));
putenv('TECHNICAL_ADMIN_USERNAME=test-admin');
$partial=Settings::defaults();$partial['client_id']='stored-id';
putenv('ORANGE_SMS_CLIENT_ID=env-id');putenv('ORANGE_SMS_CLIENT_SECRET=env-secret');
same(['env-id','env-secret'],Settings::credentials($partial));
$partial['secret_cipher']=Settings::encrypt('stored-secret');
putenv('ORANGE_SMS_CREDENTIALS_SOURCE=admin');same(['stored-id','stored-secret'],Settings::credentials($partial));
putenv('ORANGE_SMS_CREDENTIALS_SOURCE=env');same(['env-id','env-secret'],Settings::credentials($partial));
putenv('ORANGE_SMS_AUTHORIZATION_HEADER=Basic '.base64_encode('header-id:header-secret'));
same(['header-id','header-secret'],Settings::credentials($partial));same('basic_header',Settings::authenticationInfo($partial)['authentication_method']);
$calls=[];(new Client($default,$transport))->authenticate();same('Authorization: Basic '.base64_encode('header-id:header-secret'),$calls[0][2][0]);
putenv('ORANGE_SMS_AUTHORIZATION_HEADER=');putenv('ORANGE_SMS_CLIENT_SECRET=');
rejects(fn()=>Settings::credentials($partial));
putenv('ORANGE_SMS_CREDENTIALS_SOURCE=auto');
same(['stored-id','stored-secret'],Settings::credentials($partial));
$partial['secret_cipher']='';rejects(fn()=>Settings::credentials($partial));
foreach(['Bearer token','Basic invalid','Basic '.base64_encode('id:'),'Basic '.base64_encode('id:bad secret')] as $bad)rejects(fn()=>Settings::basicCredentials($bad));
putenv('ORANGE_SMS_CLIENT_ID=');
echo "Orange SMS: validation, encryption, simulation, OAuth, endpoints, accepted vs delivered, no retry and monitoring OK\n";
