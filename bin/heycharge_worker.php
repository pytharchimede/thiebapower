<?php
declare(strict_types=1);
// Exécuter toutes les minutes avec PHP CLI depuis le dépôt privé.
if(PHP_SAPI!=='cli')exit(1);
spl_autoload_register(static function(string $class):void {
 if(!str_starts_with($class,'App\\'))return;
 $file=dirname(__DIR__).'/app/'.str_replace('\\','/',substr($class,4)).'.php';
 if(is_file($file))require $file;
});
use App\Core\App;
use App\Services\HeyChargeOpenApi;
use App\Services\IntegrationSettings;
use App\Services\RentalLifecycleService;
use App\Services\StationFleetService;
if(IntegrationSettings::all()['heycharge']!=='normal'||!(new HeyChargeOpenApi)->configured())exit(0);
$db=App::db();
if((int)$db->query("SELECT GET_LOCK('thiebapower_heycharge_worker',0)")->fetchColumn()!==1)exit(0);
try {
 $rows=$db->query("SELECT reference FROM rentals WHERE status IN ('releasing','release_failed','active') AND station_code IS NOT NULL ORDER BY id DESC LIMIT 100")->fetchAll();
 foreach($rows as $row){
  try {(new RentalLifecycleService)->reconcileStation($row['reference']);}
  catch(\Throwable $e){error_log('HeyCharge reconciliation '.$row['reference'].': '.$e->getMessage());}
 }
 $stations=$db->query("SELECT imei FROM stations WHERE enabled=1 AND (last_seen_at IS NULL OR last_seen_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 5 MINUTE)) ORDER BY last_seen_at LIMIT 20")->fetchAll();
 foreach($stations as $row){
  try {(new StationFleetService)->sync($row['imei']);}
  catch(\Throwable $e){error_log('HeyCharge station sync '.$row['imei'].': '.$e->getMessage());}
 }
}finally {$db->query("SELECT RELEASE_LOCK('thiebapower_heycharge_worker')");}
