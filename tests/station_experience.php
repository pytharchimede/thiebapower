<?php
declare(strict_types=1);
spl_autoload_register(static function(string $class):void{if(str_starts_with($class,'App\\'))require dirname(__DIR__).'/app/'.str_replace('\\','/',substr($class,4)).'.php';});
use App\Services\StationProfile;
use App\Services\PlaceSearch;
use App\Services\RentalWatch;
use App\Services\StationProfitability;
use App\Services\PromotionService;
$n=0;$assert=static function(bool $condition,string $label)use(&$n):void{if(!$condition)throw new RuntimeException($label);$n++;};
$profile=StationProfile::validate(['label'=>'Thieba Power · Koumassi','latitude'=>'0','longitude'=>'0','manager_email'=>'gerant@example.ci','manager_name'=>'Amani']);
$assert($profile['latitude']===0.0&&$profile['longitude']===0.0,'Zero coordinates are valid');
foreach([['label'=>''],['label'=>'Station','latitude'=>5],['label'=>'Station','latitude'=>91,'longitude'=>0],['label'=>'Station','latitude'=>0,'longitude'=>181],['label'=>'Station','latitude'=>[], 'longitude'=>0],['label'=>'Station','manager_email'=>'bad'],['label'=>str_repeat('x',161)]] as $bad){$rejected=false;try{StationProfile::validate($bad);}catch(InvalidArgumentException){$rejected=true;}$assert($rejected,'Invalid station profile rejected');}
$public=StationProfile::publicFields(['imei'=>'A','label'=>'Lieu','status'=>'online','last_seen_at'=>gmdate('Y-m-d H:i:s'),'available_count'=>3,'manager_name'=>'Secret','manager_phone'=>'0700000000','manager_email'=>'secret@example.com','manager_notes'=>'PRIVATE NOTE','investment'=>100000]);
$assert($public['manager_name']==='Secret'&&$public['manager_phone']==='0700000000','Public manager contact explicitly requested');
$assert(!isset($public['manager_email'])&&!isset($public['manager_notes'])&&!isset($public['investment']),'Private email, notes and financial data excluded');
$assert($public['available']===3,'Fresh stock published');
$public=StationProfile::publicFields(['imei'=>'A','label'=>'Lieu','status'=>'offline','last_seen_at'=>gmdate('Y-m-d H:i:s'),'available_count'=>3]);$assert($public['available']===0,'Offline stock is not advertised');
$disabled=StationProfile::publicFields(['imei'=>'OFF','label'=>'Suspendue','enabled'=>0,'status'=>'online','last_seen_at'=>gmdate('Y-m-d H:i:s'),'available_count'=>9]);$assert(!$disabled['enabled']&&$disabled['available']===0,'Suspended station never advertises rentable stock');
$places=PlaceSearch::normalize(['features'=>[['properties'=>['name'=>'Cap Sud','city'=>'Abidjan','country'=>"Côte d’Ivoire"],'geometry'=>['coordinates'=>[-4.0,5.3]]],['geometry'=>['coordinates'=>[400,5]]]]]);$assert(count($places)===1&&$places[0]['latitude']===5.3,'Photon GeoJSON coordinates and filtering');
$now=strtotime('2026-10-04 12:00:00 UTC');
$assert(count(RentalWatch::reasons(['status'=>'returned'], $now))===0,'Normal return is not an incident');
$assert(count(RentalWatch::reasons(['status'=>'pending_payment','reservation_expires_at'=>'2026-10-04 12:00:01'],$now))===0,'Unexpired reservations not flagged');
$assert(count(RentalWatch::reasons(['status'=>'pending_payment','reservation_expires_at'=>'2026-10-04 12:00:00'],$now))===1,'Expired reservation flagged');
$assert(count(RentalWatch::reasons(['status'=>'releasing','release_command_at'=>'2026-10-04 11:58:00'],$now))===1,'Unconfirmed release flagged');
$assert(count(RentalWatch::reasons(['status'=>'returned','refund_status'=>'unknown','open_requests'=>1],$now))===2,'Support and uncertain refund visible');
$metrics=StationProfitability::metrics(50000,1000,10000,100000);$assert($metrics['net']===41000&&$metrics['remaining']===59000,'Profitability excludes investment from operating costs');
$assert(StationProfitability::metrics(100,0,200,1000)['remaining']===1100,'Losses increase amount to recover');
$assert(StationProfitability::metrics(100,0,0,0)['recovered_percent']===null,'Unknown investment never divides by zero');
$assert(PromotionService::discountedFee(1000,200)===800,'Only fee is discounted');
foreach([0,1000,1200] as $discount){$rejected=false;try{PromotionService::discountedFee(1000,$discount);}catch(InvalidArgumentException){$rejected=true;}$assert($rejected,'Invalid discounts rejected');}
$assert(PromotionService::phoneKey('+2250700000000')===PromotionService::phoneKey('07 00 00 00 00'),'Equivalent phones share coupon limit');
// Explicit process-local values override a production .env without changing it.
$previousPromotions=getenv('PROMOTIONS_ENABLED');
try {
 putenv('PROMOTIONS_ENABLED=0');$assert(!PromotionService::enabled(),'Promotions explicitly disabled');
 putenv('PROMOTIONS_ENABLED=1');$assert(PromotionService::enabled(),'Promotions explicitly enabled');
} finally {putenv($previousPromotions===false?'PROMOTIONS_ENABLED':'PROMOTIONS_ENABLED='.$previousPromotions);}
$routes=require dirname(__DIR__).'/routes/web.php';$assert(isset($routes['POST /my-rentals/snapshot'])&&!isset($routes['GET /my-rentals/snapshot']),'Customer token API only accepts POST');
echo "$n station experience tests OK\n";

foreach([[2000,500,1500],[2000,2000,0],[2000,3000,0]] as [$deposit,$discount,$expected]){if(\App\Services\PromotionService::discountedDeposit($deposit,$discount)!==$expected)throw new RuntimeException('Deposit discount failed');}

$assert(PromotionService::limitMessage([],1,true)===null,'Available phone quota has no error');
$assert(str_contains(PromotionService::limitMessage([['paid'=>0,'remaining_seconds'=>90]],1,true),'Réessayez dans 1 min 30 s'),'Pending phone shows remaining delay');
$assert(str_contains(PromotionService::limitMessage([['paid'=>1,'remaining_seconds'=>null]],1,true),'épuisé ses 1'),'Paid phone explicitly exhausted');
$assert(str_contains(PromotionService::limitMessage([['paid'=>1,'remaining_seconds'=>null]],1,false),'limite globale'),'Paid global explicitly exhausted');
$assert(str_contains(PromotionService::limitMessage([['paid'=>0,'remaining_seconds'=>20],['paid'=>0,'remaining_seconds'=>80]],1,true),'1 min 20 s'),'Reduced limit waits for enough reservations to expire');
echo "Promotion messages tests OK\n";
