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
use App\Services\HeyChargeAccountService;
if(IntegrationSettings::all()['heycharge']!=='normal'||!(new HeyChargeOpenApi)->configured())exit(0);
$db=App::db();
if((int)$db->query("SELECT GET_LOCK('thiebapower_heycharge_worker',0)")->fetchColumn()!==1)exit(0);
try {
 $lastDiscovery=$db->query("SELECT last_run_at FROM service_heartbeats WHERE name='heycharge_discovery'")->fetchColumn();
 if(!$lastDiscovery||strtotime($lastDiscovery.' UTC')<time()-600){
  try {
   (new HeyChargeAccountService)->discover();
  }catch(\Throwable $e){error_log('HeyCharge account discovery: '.$e->getMessage());}
  $db->exec("INSERT INTO service_heartbeats(name,last_run_at) VALUES('heycharge_discovery',UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE last_run_at=VALUES(last_run_at)");
 }
 $expired=$db->query("SELECT reference FROM rentals WHERE status IN ('pending_payment','payment_failed') AND reservation_expires_at<=UTC_TIMESTAMP() AND EXISTS (SELECT 1 FROM batteries b WHERE b.id=rentals.battery_id AND b.status='reserved') ORDER BY reservation_expires_at LIMIT 20")->fetchAll();
 foreach($expired as $row){
  try {(new RentalLifecycleService)->expirePendingPayment($row['reference']);}
  catch(\Throwable $e){error_log('Payment reservation timeout '.$row['reference'].': '.$e->getMessage());}
 }
 $rows=array_merge(
  $db->query("SELECT id,reference FROM rentals WHERE status IN ('releasing','release_failed') AND release_command_at IS NOT NULL ORDER BY COALESCE(last_station_check_at,'1970-01-01'),id LIMIT 2")->fetchAll(),
  $db->query("SELECT r.id,r.reference FROM rentals r JOIN batteries b ON b.id=r.battery_id WHERE r.status='active' ORDER BY EXISTS(SELECT 1 FROM heycharge_events e WHERE e.event_type='return' AND e.battery_serial=b.serial AND e.received_at>COALESCE(r.last_station_check_at,'1970-01-01')) DESC,COALESCE(r.last_station_check_at,'1970-01-01'),r.id LIMIT 3")->fetchAll()
 );
 foreach($rows as $row){
  $db->prepare('UPDATE rentals SET last_station_check_at=UTC_TIMESTAMP() WHERE id=?')->execute([$row['id']]);
  try {(new RentalLifecycleService)->reconcileStation($row['reference']);}
  catch(\Throwable $e){error_log('HeyCharge reconciliation '.$row['reference'].': '.$e->getMessage());}
 }
 // A registration callback creates a disabled station. Fetch its actual inventory
 // automatically, but leave activation to an operator after verification.
 $stations=$db->query("SELECT imei FROM stations WHERE (enabled=1 AND (last_seen_at IS NULL OR last_seen_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 5 MINUTE))) OR (enabled=0 AND last_seen_at IS NULL AND EXISTS (SELECT 1 FROM heycharge_events e WHERE e.imei=stations.imei AND e.event_type='register' AND e.received_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY))) ORDER BY last_seen_at LIMIT 5")->fetchAll();
 foreach($stations as $row){
  try {(new StationFleetService)->sync($row['imei']);}
  catch(\Throwable $e){
   error_log('HeyCharge station sync '.$row['imei'].': '.$e->getMessage());
   $q=$db->prepare("UPDATE stations SET status='offline' WHERE imei=? AND (last_seen_at IS NULL OR last_seen_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 MINUTE))");
   $q->execute([$row['imei']]);
  }
 }
 $db->exec("INSERT INTO service_heartbeats(name,last_run_at) VALUES('heycharge',UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE last_run_at=VALUES(last_run_at)");
}finally {$db->query("SELECT RELEASE_LOCK('thiebapower_heycharge_worker')");}
