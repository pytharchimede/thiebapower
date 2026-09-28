<?php
declare(strict_types=1);
spl_autoload_register(static function(string $class):void {
 if(!str_starts_with($class,'App\\'))return;
 require dirname(__DIR__).'/app/'.str_replace('\\','/',substr($class,4)).'.php';
});
use App\Models\Rental;
use App\Services\PaiementProPayoutService;
use App\Services\PayoutCallbackAssessment;
use App\Services\PayoutResult;

$tests=[];
$test=function(string $name,callable $fn)use(&$tests):void{$fn();$tests[]=$name;};
$same=static function(mixed $expected,mixed $actual,string $message=''):void{if($expected!==$actual)throw new RuntimeException(($message?$message.': ':'').'expected '.var_export($expected,true).', got '.var_export($actual,true));};

$test('initiation with session stays processing',fn()=>$same('processing',PayoutResult::initiation((object)['status'=>'INITIATED','code'=>0,'sessionid'=>'S1'])['state']));
$test('initiation without session is initiated, not paid',fn()=>$same('initiated',PayoutResult::initiation((object)['status'=>'INITIATED','code'=>0])['state']));
$test('ambiguous response is unknown',fn()=>$same('unknown',PayoutResult::initiation((object)['status'=>'OK'])['state']));
$test('explicit rejection is failed',fn()=>$same('failed',PayoutResult::initiation((object)['status'=>'FAILED','code'=>13])['state']));
$test('only pending settlements may be dispatched',function()use($same){$same(true,PayoutResult::dispatchable('pending'));$same(false,PayoutResult::dispatchable('initiated'));$same(false,PayoutResult::dispatchable('unknown'));$same(false,PayoutResult::dispatchable('failed'));});
$expected=['sessionid'=>'S1','referenceNo'=>'TBP-REFUND-2','amount'=>200,'currency'=>'XOF','channel'=>'WAVECI','payeeNo'=>'+2250748367710'];
$success=(object)($expected+['status'=>'SUCCESS']);
$test('fully matched status succeeds',fn()=>$same('succeeded',PayoutResult::finalStatus($success,$expected)));
$test('wrong beneficiary never succeeds',function()use($same,$success,$expected){$bad=clone $success;$bad->payeeNo='+2250102030405';$same('mismatch',PayoutResult::finalStatus($bad,$expected));});
$test('pending provider status remains processing',function()use($same,$expected){$same('processing',PayoutResult::finalStatus((object)['status'=>'INITIATED'],$expected));});
$test('ivorian local phone is normalized',fn()=>$same('+2250748367710',PaiementProPayoutService::normalizePhone('07 48 36 77 10')));
$test('return on time refunds all deposit',fn()=>$same(0,Rental::due(200,10,0)));
$test('one started late hour deducts ten percent',fn()=>$same(20,Rental::due(200,10,1)));
$test('late deduction is capped at deposit',fn()=>$same(200,Rental::due(200,10,3600*20)));
$payload=['referenceNo'=>'TBP-REFUND-2','amount'=>'200','merchantId'=>'PP-X','channel'=>'WAVECI','payeeNo'=>'0748367710','status'=>'SUCCESS'];
$callbackExpected=['reference'=>'TBP-REFUND-2','amount'=>200,'merchantId'=>'PP-X','channel'=>'WAVECI','payeeNo'=>'+2250748367710'];
$test('matching callback is still unauthenticated',fn()=>$same('false',PayoutCallbackAssessment::assess($payload,$callbackExpected)['authenticated']));
$test('repeated callbacks are deterministic and cannot transition state',fn()=>$same(PayoutCallbackAssessment::assess($payload,$callbackExpected),PayoutCallbackAssessment::assess($payload,$callbackExpected)));
$test('database enforces one settlement per rental',function()use($same){$schema=file_get_contents(dirname(__DIR__).'/database/schema.sql');$same(true,str_contains($schema,'rental_id BIGINT UNSIGNED NOT NULL UNIQUE'));});
fwrite(STDOUT,count($tests)." tests OK\n");
