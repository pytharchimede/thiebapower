<?php
namespace App\Services;
use App\Core\App;

final class HeyChargeAccountService {
 /** Import account stations without enabling them. An unrecognised response must never erase the local fleet. */
 public function discover():int {
  $response=(new HeyChargeOpenApi)->stations();
  $rows=$response;
  if(!array_is_list($rows)){
   $rows=$response['stations']??$response['data']??$response['items']??$response['list']??null;
   if(is_array($rows)&&!array_is_list($rows))$rows=$rows['stations']??$rows['items']??$rows['list']??null;
  }
  if(!is_array($rows)||!array_is_list($rows))throw new \RuntimeException('Liste HeyCharge non reconnue');
  $db=App::db();$count=0;
  $q=$db->prepare('INSERT IGNORE INTO stations(imei,iccid,enabled) VALUES(?,?,0)');
  foreach($rows as $row){
   if(!is_array($row))continue;
   $imei=(string)($row['imei']??$row['station_imei']??'');
   if(!preg_match('/^[A-Za-z0-9_-]{1,120}$/D',$imei))continue;
   $iccid=substr((string)($row['iccid']??''),0,32)?:null;
   $q->execute([$imei,$iccid]);$count+=$q->rowCount();
  }
  return $count;
 }
}
