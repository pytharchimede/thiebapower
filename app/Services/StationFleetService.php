<?php
namespace App\Services;
use App\Core\App;
final class StationFleetService {
 public function sync(string $imei):array {
  $remote=(new HeyChargeOpenApi)->station($imei);
  if(($remote['imei']??null)!==$imei||!isset($remote['batteries'])||!is_array($remote['batteries']))throw new \RuntimeException('Réponse station incohérente');
  $db=App::db();$db->beginTransaction();
  try {
   $db->prepare("INSERT INTO stations(imei,iccid,status,last_seen_at) VALUES(?,?,'online',UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE iccid=VALUES(iccid),status='online',last_seen_at=UTC_TIMESTAMP()")
    ->execute([$imei,substr((string)($remote['iccid']??''),0,32)?:null]);
   $seen=[];
   foreach($remote['batteries'] as $item){
    $serial=(string)($item['battery_id']??'');$slot=(string)($item['slot_id']??'');
    if(!preg_match('/^[A-Za-z0-9_-]{1,100}$/D',$serial)||!preg_match('/^[A-Za-z0-9_-]{1,32}$/D',$slot))continue;
    $seen[]=$serial;
    $q=$db->prepare('SELECT * FROM batteries WHERE serial=? FOR UPDATE');$q->execute([$serial]);$old=$q->fetch();
    if($old && $old['station_imei']!==null && $old['station_imei']!==$imei && in_array($old['status'],['reserved','rented'],true))continue;
    $pending=false;
    if($old){$q=$db->prepare("SELECT id FROM manual_release_commands WHERE battery_id=? AND status IN ('requested','unknown') LIMIT 1");$q->execute([$old['id']]);$pending=(bool)$q->fetchColumn();}
    if($old && in_array($old['status'],['reserved','rented'],true))$status=$old['status'];
    else $status=$pending?'maintenance':(self::rentable($item)?'available':'charging');
    $db->prepare('INSERT INTO batteries(serial,status,station_imei,slot_id,battery_capacity,battery_abnormal,cable_abnormal) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status),station_imei=VALUES(station_imei),slot_id=VALUES(slot_id),battery_capacity=VALUES(battery_capacity),battery_abnormal=VALUES(battery_abnormal),cable_abnormal=VALUES(cable_abnormal)')
     ->execute([$serial,$status,$imei,$slot,min(100,max(0,(int)($item['battery_capacity']??0))),(int)($item['battery_abnormal']??0)?1:0,(int)($item['cable_abnormal']??0)?1:0]);
   }
   $q=$db->prepare("SELECT id,serial,status FROM batteries WHERE station_imei=?");$q->execute([$imei]);
   foreach($q->fetchAll() as $row)if(!in_array($row['serial'],$seen,true)&&in_array($row['status'],['available','charging'],true))$db->prepare("UPDATE batteries SET status='maintenance' WHERE id=?")->execute([$row['id']]);
   $q=$db->prepare("SELECT id,battery_id,battery_serial FROM manual_release_commands WHERE station_imei=? AND status IN ('requested','unknown') FOR UPDATE");$q->execute([$imei]);
   foreach($q->fetchAll() as $command)if(!in_array($command['battery_serial'],$seen,true)){
    $db->prepare("UPDATE manual_release_commands SET status='confirmed',confirmed_at=UTC_TIMESTAMP() WHERE id=?")->execute([$command['id']]);
    $db->prepare("UPDATE batteries SET status='maintenance',station_imei=NULL,slot_id=NULL WHERE id=? AND status='maintenance' AND station_imei=?")->execute([$command['battery_id'],$imei]);
   }
   $db->commit();return $remote;
  }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
 }
 public static function rentable(array $battery):bool {
  return preg_match('/^[A-Za-z0-9_-]{1,100}$/D',(string)($battery['battery_id']??''))===1
   && preg_match('/^[A-Za-z0-9_-]{1,32}$/D',(string)($battery['slot_id']??''))===1
   && (int)($battery['battery_capacity']??0)>=70;
 }
 public static function availableAt(array $station,string $serial,string $slot):bool {
  foreach($station['batteries']??[] as $battery)
   if((string)($battery['battery_id']??'')===$serial && (string)($battery['slot_id']??'')===$slot)return self::rentable($battery);
  return false;
 }
 public static function contains(array $station,string $batteryId):bool {
  foreach($station['batteries']??[] as $b)if((string)($b['battery_id']??'')===$batteryId)return true;
  return false;
 }
 public static function battery(array $station,string $batteryId):?array {
  foreach($station['batteries']??[] as $b)if((string)($b['battery_id']??'')===$batteryId)return $b;
  return null;
 }
}
