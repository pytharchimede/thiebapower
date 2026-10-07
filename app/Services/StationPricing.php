<?php
namespace App\Services;
use App\Core\App;

/** A station inherits the general price until an explicit complete override is saved. */
final class StationPricing
{
 public const FIELDS=['rental_fee','default_deposit','duration_minutes','late_percent','deposit_enabled','grace_minutes'];
 public static function resolve(string $station,bool $lock=false):array {
  $db=App::db();$global=$db->query('SELECT * FROM pricing WHERE id=1'.($lock?' FOR UPDATE':''))->fetch();
  $q=$db->prepare('SELECT * FROM station_pricing WHERE station_imei=?'.($lock?' FOR UPDATE':''));$q->execute([$station]);$custom=$q->fetch();
  if($custom)foreach(self::FIELDS as $field)$global[$field]=$custom[$field];
  $global['is_station_price']=(bool)$custom;return $global;
 }
 public static function validate(array $input):array {
  $price=[];
  foreach(self::FIELDS as $field){
   $value=$field==='deposit_enabled'?((string)($input[$field]??'')==='1'?1:0):filter_var($input[$field]??null,FILTER_VALIDATE_INT);
   $min=in_array($field,['rental_fee','duration_minutes'],true)?1:0;
   $max=$field==='late_percent'?100:($field==='grace_minutes'?1440:2147483647);
   if(!is_int($value)||$value<$min||$value>$max)throw new \InvalidArgumentException('Tarifs invalides.');$price[$field]=$value;
  }return $price;
 }
 public static function apply(array $price,string $scope,array $stations):void {
  if(!in_array($scope,['default','all','selected','inherit'],true))throw new \InvalidArgumentException('Choisissez la portée du tarif.');
  if(in_array($scope,['selected','inherit'],true)&&!$stations)throw new \InvalidArgumentException('Sélectionnez au moins une station.');
  foreach($stations as $station)if(!is_string($station)||!preg_match('/^[A-Za-z0-9_-]{1,120}$/D',$station))throw new \InvalidArgumentException('Station invalide.');
  $stations=array_values(array_unique($stations));sort($stations);$db=App::db();$db->beginTransaction();
  try{
   // All pricing writers and checkouts take this lock before resolving overrides.
   $db->query('SELECT id FROM pricing WHERE id=1 FOR UPDATE')->fetch();
   foreach($stations as $station){$q=$db->prepare('SELECT 1 FROM stations WHERE imei=?');$q->execute([$station]);if(!$q->fetchColumn())throw new \InvalidArgumentException('Station inconnue.');}
   if(in_array($scope,['default','all'],true))$db->prepare('UPDATE pricing SET rental_fee=?,default_deposit=?,duration_minutes=?,late_percent=?,deposit_enabled=?,grace_minutes=? WHERE id=1')->execute(array_values($price));
   if($scope==='all')$db->exec('DELETE FROM station_pricing');
   if($scope==='selected')foreach($stations as $station)$db->prepare('INSERT INTO station_pricing(station_imei,rental_fee,default_deposit,duration_minutes,late_percent,deposit_enabled,grace_minutes) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE rental_fee=VALUES(rental_fee),default_deposit=VALUES(default_deposit),duration_minutes=VALUES(duration_minutes),late_percent=VALUES(late_percent),deposit_enabled=VALUES(deposit_enabled),grace_minutes=VALUES(grace_minutes)')->execute([$station,...array_values($price)]);
   if($scope==='inherit')foreach($stations as $station)$db->prepare('DELETE FROM station_pricing WHERE station_imei=?')->execute([$station]);
   $db->commit();
  }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
 }
}
