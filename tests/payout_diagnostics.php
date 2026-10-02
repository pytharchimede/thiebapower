<?php
declare(strict_types=1);
spl_autoload_register(static function(string $class):void {
 if(str_starts_with($class,'App\\'))require dirname(__DIR__).'/app/'.str_replace('\\','/',substr($class,4)).'.php';
});
use App\Services\PaiementProPayoutService;
use App\Services\PayoutApiAudit;
use App\Services\PayoutTestReport;
function check(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);}
putenv('PAIEMENTPRO_MERCHANT_ID=PP-TEST');putenv('PAIEMENTPRO_SECRET_KEY=secret-test');putenv('APP_URL=https://thiebapower.com');
$client=new class {
 public array $calls=[];
 public bool $fail=false;
 public function initTransact(array $params):object {$this->calls[]=$params;if($this->fail)throw new SoapFault('Server','timeout');return (object)['status'=>'SUCCEEDED','code'=>'0','sessionid'=>'S1'];}
 public function getTransStatus(array $params):object {$this->calls[]=$params;return (object)['status'=>'SUCCESS','sessionid'=>'S1'];}
 public function __getLastRequestHeaders():string {return "POST /webservice/v2/payout/soap.php HTTP/1.1\r\nHost: paiementpro.net\r\n";}
 public function __getLastResponseHeaders():string {return "HTTP/1.1 100 Continue\r\n\r\nHTTP/1.1 200 OK\r\n";}
 public function __getLastRequest():string {return '<Envelope><token>'.end($this->calls)['token'].'</token><amount>200</amount></Envelope>';}
 public function __getLastResponse():string {return '<Envelope><status>SUCCESS</status></Envelope>';}
};
$traces=[];
$service=new PaiementProPayoutService(fn()=>$client,fn()=>1700000000,function($ref,$trace)use(&$traces){$traces[]=['reference'=>$ref]+$trace;});
$request=$service->prepare('TBP-TEST-PAYOUT-ABC',200,'WAVECI','0748367710','Test Thiebapower','production');
check($request['params']['token']===hash_hmac('sha256','1700000000PP-TEST','secret-test'),'HMAC');
check($request['params']['payeeNo']==='+2250748367710','phone');
$reply=$service->initiate($request);
check($reply->status==='SUCCEEDED'&&count($client->calls)===1,'one initiation');
check($traces[0]['httpStatus']==='200','final HTTP code');
check($traces[0]['endpoint']==='https://paiementpro.net/webservice/v2/payout/soap.php','actual endpoint');
check(!str_contains(json_encode($traces),$request['params']['token']),'token masked');
$service->status('S1','production','TBP-TEST-PAYOUT-ABC');
check($traces[1]['reference']==='TBP-TEST-PAYOUT-ABC'&&$traces[1]['method']==='getTransStatus','status correlation');
check(json_decode($traces[1]['requestParameters'],true)['sessionid']==='S1','status payload');
$client->fail=true;$thrown=false;try{$service->initiate($request);}catch(SoapFault){$thrown=true;}
check($thrown&&count($traces)===3&&count($client->calls)===3,'fault captured without retry');
$isolated=new PaiementProPayoutService(fn()=>$client,fn()=>1,fn()=>throw new RuntimeException('audit down'));
$client->fail=false;check($isolated->initiate($request)->status==='SUCCEEDED','audit failure preserves reply');
$safe=PayoutApiAudit::safeSoap('<root><secretKey>TOPSECRET</secretKey><token>abc</token><status>SUCCESS</status></root>','abc');
check(!str_contains($safe,'TOPSECRET')&&!str_contains($safe,'abc')&&str_contains($safe,'SUCCESS'),'XML secrets');
check(PayoutApiAudit::safeSoap('<!DOCTYPE r [<!ENTITY x SYSTEM "file:///etc/passwd">]><r>&x;</r>','abc')==='[XML non archivé]','DTD rejected');
$op=['id'=>1,'reference'=>'TBP-TEST-PAYOUT-ABC','environment'=>'production','amount'=>200,'status'=>'processing','provider_session_id'=>'S1'];
$events=[['id'=>2,'reference'=>$op['reference'],'source'=>'init','created_at'=>'2026-10-02','response'=>'{"status":"SUCCEEDED"}'],['id'=>1,'reference'=>$op['reference'],'source'=>'init','created_at'=>'2026-10-02','response'=>json_encode($traces[0])],['id'=>3,'reference'=>'OTHER','source'=>'error','created_at'=>'2026-10-02','response'=>'{"description":"UNRELATED"}']];
$report=PayoutTestReport::build($op,[['reference'=>$op['reference'],'created_at'=>'2026-10-02','endpoint'=>$request['wsdl'],'parameters'=>$traces[0]['requestParameters']]],$events);
check(str_contains($report,'initTransact')&&str_contains($report,'getTransStatus')&&str_contains($report,'httpStatus')&&str_contains($report,'SUCCEEDED'),'complete report');
check(!str_contains($report,'UNRELATED')&&!str_contains($report,$request['params']['token']),'isolated safe report');
fwrite(STDOUT,"Payout diagnostics: 14 checks OK (mock SOAP, no real payment)\n");
